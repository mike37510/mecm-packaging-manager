<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Build a deployable BIOS ZIP: config + vendor scripts + vendor tools + README.
 */
final class BiosPackageBuilder
{
    public function __construct(
        private readonly string $resourcesBiosRoot,
        private readonly string $tempDir,
        private readonly ?BiosSettingsService $biosSettings = null,
    ) {
    }

    /**
     * @param 'hp'|'dell'|'lenovo' $vendor
     * @param list<string> $scriptBasenames
     * @return array{ok: true, path: string, filename: string}|array{ok: false, error: string}
     */
    public function build(string $vendor, string $configPath, string $configFilename, array $scriptBasenames): array
    {
        if (!is_file($configPath)) {
            return ['ok' => false, 'error' => 'file_missing'];
        }
        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'error' => 'zip_unavailable'];
        }

        $folder = pathinfo($configFilename, PATHINFO_FILENAME);
        if ($folder === '' || !preg_match('/^Bios(HP|Dell|Lenovo)-\d+$/', $folder)) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        $zipName = $folder . '.zip';
        $this->ensureTemp();
        $zipPath = rtrim($this->tempDir, '/') . '/' . $zipName . '.' . bin2hex(random_bytes(4)) . '.tmp.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'error' => 'write_failed'];
        }

        $toolsEmbedded = false;

        try {
            $zip->addFile($configPath, $folder . '/' . $configFilename);

            $scriptsDir = rtrim($this->resourcesBiosRoot, '/') . '/' . $vendor;
            foreach ($scriptBasenames as $script) {
                $src = $scriptsDir . '/' . $script;
                if (!is_file($src)) {
                    continue;
                }
                $body = (string) file_get_contents($src);
                $body = $this->rewriteConfigPath($vendor, $body, $configFilename);
                $body = $this->rewriteLogPaths($body);
                $zip->addFromString($folder . '/' . $script, $body);
            }

            $toolsEmbedded = $this->addVendorTools($zip, $vendor, $folder, $scriptsDir);
            if (!$toolsEmbedded) {
                foreach ($this->toolPlaceholders($vendor) as $relative => $content) {
                    $zip->addFromString($folder . '/' . $relative, $content);
                }
            }

            $scriptsDoc = $scriptsDir . '/SCRIPTS.txt';
            if (is_file($scriptsDoc)) {
                $zip->addFile($scriptsDoc, $folder . '/SCRIPTS.txt');
            }

            $zip->addFromString($folder . '/README.txt', $this->readme($vendor, $folder, $configFilename, $scriptBasenames, $toolsEmbedded));
        } finally {
            $zip->close();
        }

        if (!is_file($zipPath) || filesize($zipPath) < 1) {
            @unlink($zipPath);

            return ['ok' => false, 'error' => 'write_failed'];
        }

        return ['ok' => true, 'path' => $zipPath, 'filename' => $zipName];
    }

    private function rewriteConfigPath(string $vendor, string $scriptBody, string $configFilename): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $configFilename);
        $patterns = match ($vendor) {
            'hp' => [
                '/^(\s*\$ConfigPath\s*=\s*")[^"]*(")/m',
            ],
            'dell', 'lenovo' => [
                '/^(\s*\$ConfigPath\s*=\s*")[^"]*(")/m',
            ],
            default => [],
        };
        foreach ($patterns as $pattern) {
            $scriptBody = preg_replace($pattern, '${1}' . $escaped . '${2}', $scriptBody, 1) ?? $scriptBody;
        }

        return $scriptBody;
    }

    /**
     * Inject admin-configured log paths into Apply scripts ($LogLocalPath / $LogSharePath).
     */
    private function rewriteLogPaths(string $scriptBody): string
    {
        $cfg = $this->biosSettings?->get() ?? BiosSettingsService::DEFAULTS + ['updated_at' => null];
        $local = (string) ($cfg['log_local_path'] ?? '');
        $share = (string) ($cfg['log_share_path'] ?? '');

        $scriptBody = $this->rewritePsQuotedAssignment($scriptBody, 'LogLocalPath', $local);
        $scriptBody = $this->rewritePsQuotedAssignment($scriptBody, 'LogSharePath', $share);

        return $scriptBody;
    }

    private function rewritePsQuotedAssignment(string $scriptBody, string $varName, string $value): string
    {
        $literal = $this->psSingleQuoted($value);
        $pattern = '/^(\s*\$' . preg_quote($varName, '/') . '\s*=\s*)(?:\'[^\']*\'|"[^"]*")(.*)$/m';

        // Callback: avoid preg_replace eating backslashes in UNC paths (\\server\share).
        $out = preg_replace_callback(
            $pattern,
            static fn (array $m): string => $m[1] . $literal . $m[2],
            $scriptBody,
            1
        );

        return is_string($out) ? $out : $scriptBody;
    }

    private function psSingleQuoted(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Embed real vendor tools from resources/bios/{vendor}/ when present.
     *
     * @return bool true if at least one tool file was added
     */
    private function addVendorTools(\ZipArchive $zip, string $vendor, string $folder, string $scriptsDir): bool
    {
        $trees = match ($vendor) {
            'hp' => ['Modules'],
            'dell' => ['tools'],
            'lenovo' => ['Lenovo.BIOS.Config'],
            default => [],
        };

        $added = false;
        foreach ($trees as $tree) {
            $srcRoot = $scriptsDir . '/' . $tree;
            if (!is_dir($srcRoot)) {
                continue;
            }
            if ($this->addDirectoryToZip($zip, $srcRoot, $folder . '/' . $tree)) {
                $added = true;
            }
        }

        return $added;
    }

    /**
     * Recursively add a directory into the ZIP. Skips PLACE_* placeholders and empty dirs.
     */
    private function addDirectoryToZip(\ZipArchive $zip, string $absDir, string $zipPrefix): bool
    {
        $added = false;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $name = $fileInfo->getFilename();
            if (str_starts_with($name, 'PLACE_')) {
                continue;
            }
            $relative = substr($fileInfo->getPathname(), strlen(rtrim($absDir, '/')) + 1);
            $relative = str_replace('\\', '/', $relative);
            $entry = $zipPrefix . '/' . $relative;

            if ($fileInfo->isDir()) {
                continue;
            }
            if (!$fileInfo->isFile()) {
                continue;
            }
            if ($zip->addFile($fileInfo->getPathname(), $entry)) {
                $added = true;
            }
        }

        return $added;
    }

    /**
     * @return array<string, string> relative path => content
     */
    private function toolPlaceholders(string $vendor): array
    {
        return match ($vendor) {
            'hp' => [
                'Modules/PLACE_CMSL_HERE.txt' => implode("\r\n", [
                    'Placez ici le module HP CMSL (dossier HPCMSL ou HP.ClientManagementScriptLibrary).',
                    'Le script Apply ajoute ce dossier Modules au PSModulePath.',
                    'Téléchargement : https://www.hp.com/us-en/solutions/client-management-solutions/download.html',
                    'Ou : Install-Module -Name HPCMSL -Force -AcceptLicense',
                    '',
                ]),
            ],
            'dell' => [
                'tools/PLACE_CCTK_HERE.txt' => implode("\r\n", [
                    'Placez cctk.exe ici (Dell Command | Configure).',
                    'Chemins usuels après install Dell :',
                    '  C:\\Program Files (x86)\\Dell\\Command Configure\\X86_64\\cctk.exe',
                    'Le script Apply cherche aussi tools\\cctk.exe à côté de lui.',
                    'Support Dell Systems Management / Command | Configure.',
                    '',
                ]),
            ],
            'lenovo' => [
                'Lenovo.BIOS.Config/PLACE_MODULE_HERE.txt' => implode("\r\n", [
                    'Placez ici le module PowerShell Lenovo.BIOS.Config (Think BIOS Config Tool v2).',
                    'Le script Apply fait Import-Module sur ce dossier s’il est présent.',
                    'Docs : https://docs.lenovocdrt.com/guides/tbct_v2/',
                    '',
                ]),
            ],
            default => [],
        };
    }

    /**
     * @param list<string> $scripts
     */
    private function readme(string $vendor, string $folder, string $configFilename, array $scripts, bool $toolsEmbedded): string
    {
        $toolHint = match ($vendor) {
            'hp' => $toolsEmbedded
                ? "1. Le module HP CMSL est déjà dans Modules\\ (inclus dans ce ZIP).\r\n"
                    . "2. Ajustez éventuellement CurrentBiosPassword / UseRandomPassword dans Apply-HPBiosConfig.ps1.\r\n"
                    . "3. Exécutez en admin : .\\Apply-HPBiosConfig.ps1"
                : "1. Copiez le module CMSL dans Modules\\ (voir Modules\\PLACE_CMSL_HERE.txt).\r\n"
                    . "2. Ajustez éventuellement CurrentBiosPassword / UseRandomPassword dans Apply-HPBiosConfig.ps1.\r\n"
                    . "3. Exécutez en admin : .\\Apply-HPBiosConfig.ps1",
            'dell' => $toolsEmbedded
                ? "1. cctk.exe (Dell Command | Configure) est déjà dans tools\\ (inclus dans ce ZIP).\r\n"
                    . "2. Ajustez éventuellement CurrentBiosPassword / UseRandomPassword dans Apply-DellBiosConfig.ps1.\r\n"
                    . "3. Exécutez en admin : .\\Apply-DellBiosConfig.ps1"
                : "1. Copiez cctk.exe dans tools\\ (voir tools\\PLACE_CCTK_HERE.txt), ou installez Dell Command | Configure.\r\n"
                    . "2. Ajustez éventuellement CurrentBiosPassword / UseRandomPassword dans Apply-DellBiosConfig.ps1.\r\n"
                    . "3. Exécutez en admin : .\\Apply-DellBiosConfig.ps1",
            'lenovo' => $toolsEmbedded
                ? "1. Le module Lenovo.BIOS.Config est déjà inclus dans ce ZIP.\r\n"
                    . "2. Ajustez éventuellement CurrentBiosPassword / UseRandomPassword dans Apply-LenovoBiosConfig.ps1.\r\n"
                    . "3. Exécutez en admin : .\\Apply-LenovoBiosConfig.ps1"
                : "1. Copiez le module Lenovo.BIOS.Config dans Lenovo.BIOS.Config\\ (voir PLACE_MODULE_HERE.txt).\r\n"
                    . "2. Ajustez éventuellement CurrentBiosPassword / UseRandomPassword dans Apply-LenovoBiosConfig.ps1.\r\n"
                    . "3. Exécutez en admin : .\\Apply-LenovoBiosConfig.ps1",
            default => '',
        };

        $scriptList = implode("\r\n", array_map(static fn (string $s): string => '  - ' . $s, $scripts));

        $toolsLine = match ($vendor) {
            'hp' => $toolsEmbedded ? "  - Modules\\  (HP CMSL inclus)\r\n" : "  - Modules\\  (à compléter — voir PLACE_CMSL_HERE.txt)\r\n",
            'dell' => $toolsEmbedded ? "  - tools\\  (cctk.exe + DLLs inclus)\r\n" : "  - tools\\  (à compléter — voir PLACE_CCTK_HERE.txt)\r\n",
            'lenovo' => $toolsEmbedded ? "  - Lenovo.BIOS.Config\\  (module inclus)\r\n" : "  - Lenovo.BIOS.Config\\  (à compléter — voir PLACE_MODULE_HERE.txt)\r\n",
            default => '',
        };

        $footer = $toolsEmbedded
            ? "Les outils constructeur sont inclus dans ce paquet pour un déploiement portable.\r\n"
                . "Respectez les licences HP / Dell / Lenovo lors de la redistribution.\r\n"
            : "Les binaires constructeur (CMSL / cctk / Lenovo.BIOS.Config) ne sont pas inclus :\r\n"
                . "téléchargez-les depuis le site officiel du constructeur, puis placez-les dans l'arborescence indiquée.\r\n";

        return "MECM Packaging Manager — paquet BIOS {$folder}\r\n"
            . "================================================\r\n\r\n"
            . "Contenu :\r\n"
            . "  - {$configFilename}  (configuration générée)\r\n"
            . "  - Scripts PowerShell :\r\n{$scriptList}\r\n"
            . "  - SCRIPTS.txt  (description détaillée de chaque script)\r\n"
            . $toolsLine
            . "\r\n"
            . "Documentation des scripts :\r\n"
            . "  Ouvrez SCRIPTS.txt dans ce dossier — rôle de Apply / Clear / Get, prérequis.\r\n\r\n"
            . "Déploiement MECM / séquence de tâches :\r\n"
            . "{$toolHint}\r\n\r\n"
            . $footer;
    }

    private function ensureTemp(): void
    {
        if (!is_dir($this->tempDir) && !@mkdir($this->tempDir, 0775, true) && !is_dir($this->tempDir)) {
            throw new \RuntimeException('Unable to create BIOS package temp directory.');
        }
    }
}
