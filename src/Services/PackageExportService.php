<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Procedure\StepType;
use App\Models\PackagingProcedure;
use App\Models\ProcedureStep;
use App\Models\Software;
use App\Repositories\AuditLogRepository;
use App\Repositories\PackageExportRepository;
use App\Repositories\ProcedureRepository;
use App\Repositories\SoftwareRepository;
use App\Repositories\SyncRepository;
use App\Support\InstallerBinaryValidator;
use ZipArchive;

/**
 * Builds a deployable ZIP package for manual MECM / lab testing.
 *
 * - EXE / Inno / NSIS : technique type Brave (scripts + témoin MECM)
 * - MSI / MSIX : binaire dans sources/ + msiexec
 *
 * Chaque version conserve un seul ZIP (re-génération = remplacement des archives
 * de la même version) ; les versions différentes restent dans l’historique.
 */
final class PackageExportService
{
    public function __construct(
        private readonly SoftwareRepository $softwares,
        private readonly ProcedureRepository $procedures,
        private readonly AuditLogRepository $auditLogs,
        private readonly SyncRepository $syncs,
        private readonly PackageExportRepository $exports,
        private readonly DeployService $deploy,
        private readonly string $exportPath,
        private readonly string $iconPath,
        private readonly string $sourcesPath,
    ) {
    }

    /**
     * @return array{ok: bool, error?: string, path?: string, filename?: string, export_id?: int, purged_same_version?: int}
     */
    public function export(int $softwareId, ?int $userId, ?string $ip): array
    {
        $software = $this->softwares->findById($softwareId);
        if ($software === null) {
            return ['ok' => false, 'error' => 'Logiciel introuvable.'];
        }

        $installerAbs = $this->resolveInstaller($software);
        if ($installerAbs === null) {
            $pending = $this->pendingInstallerPathIfAny($software);
            if ($pending !== null) {
                $check = InstallerBinaryValidator::validate($pending, $software->installerType);
                return [
                    'ok' => false,
                    'error' => $check['error']
                        ?? 'Installateur local invalide (HTML ou fichier corrompu). Resynchronisez avec une URL directe .exe/.msi — ZIP refusé.',
                ];
            }

            return [
                'ok' => false,
                'error' => 'Aucun installateur local valide. Lancez d\'abord « Vérifier la mise à jour » avec une URL directe (.exe/.msi).',
            ];
        }

        $procedure = $this->procedures->getOrCreateActive($softwareId);

        $softwareDir = rtrim($this->exportPath, '/') . '/' . $softwareId;
        if (!\App\Support\WebFs::ensureWritableDirectory($softwareDir)) {
            return [
                'ok' => false,
                'error' => 'Dossier d\'export non accessible en écriture (permissions). '
                    . 'Lancez : sudo php bin/fix-permissions.php',
            ];
        }

        $slug = $this->slugify($software->name);
        $versionSlug = $this->slugify($software->currentVersion);
        $folderName = $slug . '_' . $versionSlug;
        $filename = $folderName . '_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.zip';
        $relativePath = $softwareId . '/' . $filename;
        $zipPath = rtrim($this->exportPath, '/') . '/' . $relativePath;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'error' => 'Impossible de créer le fichier ZIP.'];
        }

        $prefix = $folderName . '/';
        $installerName = basename($installerAbs);
        $strategy = $software->isMsiFamily() ? 'msi' : 'exe_brave';

        if ($software->isMsiFamily()) {
            $this->buildMsiPackage($zip, $prefix, $software, $procedure, $installerAbs, $installerName);
        } else {
            $this->buildExeBravePackage($zip, $prefix, $software, $procedure, $installerAbs, $installerName);
        }

        $this->addIcon($zip, $prefix, $software);
        $zip->addFromString($prefix . 'DOCUMENTATION.md', $this->buildDocumentation($software, $procedure));
        $zip->addFromString($prefix . 'CHANGELOG.md', $this->buildChangelog($software));
        $zip->addFromString($prefix . 'procedure.json', $this->buildProcedureJson($software, $procedure));
        $zip->addFromString($prefix . 'deploy.json', $this->deploy->buildDeployJson($software, $procedure));
        $zip->addFromString($prefix . 'deploy.ps1', $this->deploy->buildDeployPs1($software, $procedure));
        $zip->addFromString(
            $prefix . 'SYNC.txt',
            "SHA256 : {$software->lastSyncSha256}\n"
            . "Dernière synchro : {$software->lastSyncAt}\n"
            . "Fichier : {$installerName}\n"
            . 'Type : ' . $software->installerType . "\n"
            . "MECM : deploy.json + deploy.ps1 inclus\n"
        );

        // ZipArchive may buffer until close(); permission/disk errors surface here.
        if ($zip->close() !== true) {
            @unlink($zipPath);
            return [
                'ok' => false,
                'error' => 'Échec de l\'écriture du ZIP (permissions ou disque plein).',
            ];
        }
        clearstatcache(true, $zipPath);
        $fileSize = is_file($zipPath) ? (int) filesize($zipPath) : 0;
        if ($fileSize <= 0) {
            @unlink($zipPath);
            return ['ok' => false, 'error' => 'ZIP généré vide ou introuvable sur le disque.'];
        }
        @chmod($zipPath, 0664);
        \App\Support\WebFs::reclaimForWeb($zipPath);
        \App\Support\WebFs::reclaimForWeb($softwareDir);

        $installerRel = $software->pendingInstallerPath
            ?: ($softwareId . '/' . $installerName);

        $this->syncs->markPackaged($softwareId, $software->lastSyncSha256, $installerRel, $userId);

        $exportId = $this->exports->create([
            'software_id' => $softwareId,
            'version' => $software->currentVersion,
            'sha256' => $software->lastSyncSha256,
            'strategy' => $strategy,
            'installer_name' => $installerName,
            'filename' => $filename,
            'relative_path' => $relativePath,
            'file_size' => $fileSize,
            'created_by' => $userId,
        ]);

        $purged = $this->purgeSameVersionExports($softwareId, $software->currentVersion, $exportId);

        $this->auditLogs->log(
            $userId,
            'package.zip_exported',
            'software',
            $softwareId,
            [
                'export_id' => $exportId,
                'filename' => $filename,
                'version' => $software->currentVersion,
                'sha256' => $software->lastSyncSha256,
                'strategy' => $strategy,
                'purged_same_version' => $purged,
            ],
            $ip
        );

        return [
            'ok' => true,
            'path' => $zipPath,
            'filename' => $filename,
            'export_id' => $exportId,
            'purged_same_version' => $purged,
        ];
    }

    /**
     * Remove previous ZIP exports for the same software + version (disk + history).
     * Keeps {@see $keepExportId} (the freshly generated archive).
     *
     * @return int Number of exports removed
     */
    private function purgeSameVersionExports(int $softwareId, string $version, int $keepExportId): int
    {
        $removed = 0;
        foreach ($this->exports->forSoftwareAndVersion($softwareId, $version) as $export) {
            if ($export->id === $keepExportId) {
                continue;
            }
            $path = $this->absolutePathForExport($export);
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
            $this->exports->delete($export->id);
            $removed++;
        }

        return $removed;
    }

    /**
     * Absolute path for a historical export, if the file still exists.
     */
    public function absolutePathForExport(\App\Models\PackageExport $export): ?string
    {
        $full = rtrim($this->exportPath, '/') . '/' . ltrim($export->relativePath, '/');

        return is_readable($full) ? $full : null;
    }

    /**
     * Delete a historical ZIP (DB row + file on disk).
     *
     * @return array{ok: bool, error?: string}
     */
    public function deleteExport(int $softwareId, int $exportId, ?int $userId, ?string $ip): array
    {
        $export = $this->exports->findById($exportId);
        if ($export === null || $export->softwareId !== $softwareId) {
            return ['ok' => false, 'error' => 'ZIP historique introuvable.'];
        }

        $path = $this->absolutePathForExport($export);
        if ($path !== null && is_file($path)) {
            if (!@unlink($path)) {
                return ['ok' => false, 'error' => 'Impossible de supprimer le fichier ZIP sur le disque.'];
            }
        }

        $this->exports->delete($exportId);

        $this->auditLogs->log(
            $userId,
            'package.zip_deleted',
            'software',
            $softwareId,
            [
                'export_id' => $exportId,
                'filename' => $export->filename,
                'version' => $export->version,
            ],
            $ip
        );

        return ['ok' => true];
    }

    /**
     * Whether a valid Windows installer (.exe/.msi binary) is available for ZIP generation.
     */
    public function hasInstaller(Software $software): bool
    {
        return $this->resolveInstaller($software) !== null;
    }

    /**
     * @return list<\App\Models\PackageExport>
     */
    public function history(int $softwareId): array
    {
        return $this->exports->forSoftware($softwareId);
    }

    private function resolveInstaller(Software $software): ?string
    {
        foreach ($this->candidateInstallerPaths($software) as $full) {
            if (InstallerBinaryValidator::isValid($full, $software->installerType)) {
                return $full;
            }
        }

        return null;
    }

    private function pendingInstallerPathIfAny(Software $software): ?string
    {
        $candidates = $this->candidateInstallerPaths($software);

        return $candidates[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function candidateInstallerPaths(Software $software): array
    {
        $out = [];
        $rel = $software->pendingInstallerPath;
        if ($rel !== null && $rel !== '') {
            $full = rtrim($this->sourcesPath, '/') . '/' . ltrim($rel, '/');
            if (is_readable($full)) {
                $out[] = $full;
            }
        }

        $latest = $this->syncs->latestForSoftware($software->id);
        if ($latest !== null && $latest->localPath) {
            $full = rtrim($this->sourcesPath, '/') . '/' . ltrim($latest->localPath, '/');
            if (is_readable($full) && !in_array($full, $out, true)) {
                $out[] = $full;
            }
        }

        $dir = rtrim($this->sourcesPath, '/') . '/' . $software->id;
        if (is_dir($dir)) {
            $files = [];
            foreach (scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (!in_array($ext, ['exe', 'msi', 'msix', 'zip'], true)) {
                    continue;
                }
                $full = $dir . '/' . $f;
                if (is_readable($full) && !in_array($full, $out, true)) {
                    $files[] = $full;
                }
            }
            usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
            foreach ($files as $full) {
                $out[] = $full;
            }
        }

        return $out;
    }

    private function buildExeBravePackage(
        ZipArchive $zip,
        string $prefix,
        Software $software,
        PackagingProcedure $procedure,
        string $installerAbs,
        string $installerName,
    ): void {
        $zip->addFromString($prefix . 'install.ps1', $this->buildInstallScript($software, $procedure, $installerName));
        $zip->addFromString($prefix . 'uninstall.ps1', $this->buildUninstallScript($software, $procedure));
        $zip->addFromString($prefix . 'README-TEST-MANUEL.md', $this->buildManualTestReadme($software, $procedure, 'exe'));
        $zip->addFromString($prefix . 'sccm-commands.txt', $this->buildSccmCommands($software, 'exe'));
        $zip->addFromString($prefix . 'detection.txt', $this->buildDetection($software));

        $zip->addEmptyDir($prefix . 'sources');
        $zip->addFile($installerAbs, $prefix . 'sources/' . $installerName);
        $zip->addFromString(
            $prefix . 'sources/README.txt',
            "Installateur EXE synchronisé : {$installerName}\n"
            . "SHA256 : {$software->lastSyncSha256}\n"
            . "Technique : scripts PowerShell type Brave (témoin MECM).\n"
        );
    }

    private function buildMsiPackage(
        ZipArchive $zip,
        string $prefix,
        Software $software,
        PackagingProcedure $procedure,
        string $installerAbs,
        string $installerName,
    ): void {
        $productCode = $this->extractProductCode($procedure);
        $packagedName = $this->sanitizePackagedInstallerName($installerName);
        $extraProps = $this->msiSilentProperties($software);
        $propsLine = $extraProps === [] ? '' : (' ' . implode(' ', $extraProps));

        $zip->addFromString($prefix . 'install.ps1', $this->buildMsiInstallScript($software, $packagedName));
        $zip->addFromString($prefix . 'uninstall.ps1', $this->buildMsiUninstallScript($software, $packagedName, $productCode));
        $zip->addFromString($prefix . 'README-TEST-MANUEL.md', $this->buildManualTestReadme($software, $procedure, 'msi'));
        $zip->addFromString($prefix . 'sccm-commands.txt', $this->buildSccmCommands($software, 'msi', $packagedName, $productCode));
        $zip->addFromString($prefix . 'detection.txt', $this->buildMsiDetection($software, $productCode));

        $zip->addEmptyDir($prefix . 'sources');
        $zip->addFile($installerAbs, $prefix . 'sources/' . $packagedName);
        $zip->addFromString(
            $prefix . 'sources/README.txt',
            "MSI synchronisé : {$installerName}\n"
            . ($packagedName !== $installerName ? "Nom dans le ZIP : {$packagedName}\n" : '')
            . "SHA256 : {$software->lastSyncSha256}\n"
            . "Installation : msiexec /i sources\\{$packagedName} /qn /norestart{$propsLine}\n"
        );
    }

    private function extractProductCode(PackagingProcedure $procedure): ?string
    {
        foreach ($procedure->steps as $step) {
            if ($step->type === StepType::UninstallCommand) {
                $code = trim((string) ($step->payload['product_code'] ?? ''));
                if ($code !== '') {
                    return $code;
                }
            }
        }

        return null;
    }

    private function buildMsiInstallScript(Software $software, string $installerName): string
    {
        $name = $this->psSingleQuoted($software->name);
        $file = $this->psSingleQuoted($installerName);
        $witnessApp = $this->psSingleQuoted($this->witnessAppName($software));
        $witnessFile = $this->psSingleQuoted($this->witnessFileName($software));
        $extraProps = $this->msiSilentProperties($software);
        $extraLiteral = $extraProps === []
            ? ''
            : (", " . implode(', ', array_map(
                fn (string $p): string => $this->psSingleQuoted($p),
                $extraProps
            )));

        $script = <<<PS
#Requires -Version 5.1
\$ErrorActionPreference = 'Stop'
\$packageName = {$name}
\$msi = Join-Path \$PSScriptRoot (Join-Path 'sources' {$file})
\$cheminTemoin = 'C:\\SCCM_Temoins'
\$cheminApp = Join-Path \$cheminTemoin {$witnessApp}
\$fichierTemoin = Join-Path \$cheminApp {$witnessFile}
\$msiLog = Join-Path \$env:TEMP ('sccm_pf_msi_' + [guid]::NewGuid().ToString('N') + '.log')

function Write-Log { param([string]\$Message) Write-Host "[\$packageName] \$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') \$Message" }

function Wait-MsiMutex {
    param([int]\$TimeoutSec = 120)
    \$deadline = (Get-Date).AddSeconds(\$TimeoutSec)
    while ((Get-Date) -lt \$deadline) {
        \$clients = @(Get-Process msiexec -ErrorAction SilentlyContinue)
        # Un seul msiexec = souvent le service; >1 = install en cours
        if (\$clients.Count -le 1) { return \$true }
        Write-Log ("msiexec deja actif (" + \$clients.Count + " process) - attente...")
        Start-Sleep -Seconds 5
    }
    return \$false
}

try {
    if (-not (Test-Path -LiteralPath \$msi)) { Write-Log "MSI introuvable : \$msi"; exit 1603 }
    foreach (\$d in @(\$cheminTemoin, \$cheminApp)) {
        if (-not (Test-Path -LiteralPath \$d)) { New-Item -Path \$d -ItemType Directory -Force | Out-Null }
    }
    if (-not (Wait-MsiMutex -TimeoutSec 180)) {
        Write-Log 'Timeout: un autre msiexec bloque encore l installation (code 1618 probable).'
        exit 1618
    }
    Write-Log "Installation MSI : \$msi"
    \$argList = @('/i', \$msi, '/qn', '/norestart', '/l*v', \$msiLog{$extraLiteral})
    Write-Log ('Arguments : ' + (\$argList -join ' '))
    \$exitCode = 1603
    for (\$attempt = 1; \$attempt -le 4; \$attempt++) {
        \$p = Start-Process -FilePath 'msiexec.exe' -ArgumentList \$argList -Wait -PassThru -WindowStyle Hidden
        \$exitCode = [int]\$p.ExitCode
        Write-Log "Tentative \$attempt - code sortie : \$exitCode"
        if (\$exitCode -ne 1618) { break }
        Write-Log '1618 = autre installation en cours - nouvel essai dans 15s'
        Start-Sleep -Seconds 15
        [void](Wait-MsiMutex -TimeoutSec 120)
    }

    if (\$exitCode -ne 0 -and \$exitCode -ne 3010) {
        if (Test-Path -LiteralPath \$msiLog) {
            Write-Log "Log msiexec (extrait) : \$msiLog"
            Get-Content -LiteralPath \$msiLog -Tail 30 -ErrorAction SilentlyContinue | ForEach-Object { Write-Log \$_ }
        }
        exit \$exitCode
    }
    New-Item -Path \$fichierTemoin -ItemType File -Force | Out-Null
    Write-Log "Temoin cree : \$fichierTemoin"
    exit 0
}
catch {
    Write-Log "Erreur : \$(\$_.Exception.Message)"
    exit 1603
}
PS;

        return $this->toCrLf($script);
    }

    /**
     * Extra public properties for silent MSI installs (Firefox etc.).
     *
     * @return list<string>
     */
    private function msiSilentProperties(Software $software): array
    {
        $n = strtolower($software->name);
        if (str_contains($n, 'firefox')) {
            return [
                'INSTALL_MAINTENANCE_SERVICE=false',
                'DESKTOP_SHORTCUT=false',
                'TASKBAR_SHORTCUT=false',
            ];
        }
        if (str_contains($n, 'zoom')) {
            // MSI Full = machine-wide; /qn alone suffices, ALLUSERS=1 renforce le contexte systeme
            return [
                'ALLUSERS=1',
                'ZoomAutoUpdate=false',
            ];
        }

        return [];
    }

    /**
     * Avoid spaces/parentheses in packaged installer filenames (msiexec / paths).
     */
    private function sanitizePackagedInstallerName(string $name): string
    {
        $safe = preg_replace('/[^\w.\-]+/', '_', $name) ?? $name;
        $safe = trim($safe, '_');

        return $safe !== '' ? $safe : 'installer.bin';
    }

    private function buildMsiUninstallScript(Software $software, string $installerName, ?string $productCode): string
    {
        $name = $this->psSingleQuoted($software->name);
        $file = $this->psSingleQuoted($installerName);
        $witnessApp = $this->psSingleQuoted($this->witnessAppName($software));
        $witnessFile = $this->psSingleQuoted($this->witnessFileName($software));

        if ($productCode !== null && $productCode !== '') {
            $target = $this->psSingleQuoted($productCode);
            $argLine = "\$args = @('/x', {$target}, '/qn', '/norestart')";
        } else {
            $argLine = "\$msi = Join-Path \$PSScriptRoot (Join-Path 'sources' {$file})\n    \$args = @('/x', \$msi, '/qn', '/norestart')";
        }

        $script = <<<PS
#Requires -Version 5.1
\$ErrorActionPreference = 'Stop'
\$packageName = {$name}
\$cheminTemoin = 'C:\\SCCM_Temoins'
\$cheminApp = Join-Path \$cheminTemoin {$witnessApp}
\$fichierTemoin = Join-Path \$cheminApp {$witnessFile}

function Write-Log { param([string]\$Message) Write-Host "[\$packageName] \$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') \$Message" }

try {
    {$argLine}
    Write-Log 'Désinstallation MSI...'
    \$p = Start-Process -FilePath 'msiexec.exe' -ArgumentList \$args -Wait -PassThru -WindowStyle Hidden
    Write-Log "Code sortie : \$(\$p.ExitCode)"
    if (Test-Path -LiteralPath \$fichierTemoin) { Remove-Item -LiteralPath \$fichierTemoin -Force }
    exit 0
}
catch {
    Write-Log "Erreur : \$(\$_.Exception.Message)"
    exit 1603
}
PS;

        return $this->toCrLf($script);
    }

    private function buildMsiDetection(Software $software, ?string $productCode): string
    {
        $path = 'C:\\SCCM_Temoins\\' . $this->witnessAppName($software) . '\\' . $this->witnessFileName($software);
        $out = "Détection MECM — MSI\n====================\n\n";
        $out .= "Option 1 — Fichier témoin :\n  {$path}\n\n";
        if ($productCode) {
            $out .= "Option 2 — Code produit MSI :\n  {$productCode}\n";
        } else {
            $out .= "Option 2 — Renseigner le Product Code MSI dans la procédure (étape désinstallation).\n";
        }

        return $out;
    }


    /**
     * Technique Brave : installateur synchronisé + args silencieux + témoin MECM.
     */
    private function buildInstallScript(
        Software $software,
        PackagingProcedure $procedure,
        string $syncedInstallerName,
    ): string {
        $appName = $software->name;
        $safeTag = $this->psSingleQuoted($this->witnessAppName($software));
        $version = $this->psSingleQuoted($software->currentVersion);
        $witnessFile = $this->psSingleQuoted($this->witnessFileName($software));

        $silentArgs = '';
        $timeoutSec = 1800;
        $successPaths = [];
        foreach ($procedure->steps as $step) {
            if ($step->enabled && $step->type === StepType::InstallCommand) {
                $silentArgs = trim((string) ($step->payload['silent_args'] ?? ''));
                $timeoutSec = max(60, (int) ($step->payload['timeout_sec'] ?? 1800));
                $successPaths = $this->normalizeSuccessPaths($step->payload['success_paths'] ?? null);
                break;
            }
        }

        $lines = [];
        $lines[] = '#Requires -Version 5.1';
        $lines[] = '<#';
        $lines[] = '.SYNOPSIS';
        $lines[] = '    Installation silencieuse de ' . $appName . ' pour MECM (technique Brave / témoin).';
        $lines[] = '.DESCRIPTION';
        $lines[] = '    Généré par MECM Packaging Manager le ' . date('Y-m-d H:i:s') . '.';
        $lines[] = '    Remonte le code de sortie à MECM. Le témoin n\'est créé qu\'en cas de succès.';
        $lines[] = '    Si success_paths est défini, le produit détecté = succès même si l\'installateur ne quitte pas.';
        $lines[] = '#>';
        $lines[] = '';
        $lines[] = '$ErrorActionPreference = \'Stop\'';
        $lines[] = '';
        $lines[] = '# --- Métadonnées package ---';
        $lines[] = '$packageName = ' . $this->psSingleQuoted($appName);
        $lines[] = '$packageVersion = ' . $version;
        $lines[] = '$cheminTemoin = \'C:\\SCCM_Temoins\'';
        $lines[] = '$cheminApp = Join-Path $cheminTemoin ' . $safeTag;
        $lines[] = '$nomFichierTemoin = ' . $witnessFile;
        $lines[] = '$fichierTemoin = Join-Path $cheminApp $nomFichierTemoin';
        $lines[] = '$installateur = Join-Path $PSScriptRoot (Join-Path \'sources\' ' . $this->psSingleQuoted($syncedInstallerName) . ')';
        $lines[] = '$installTimeoutSec = ' . $timeoutSec;
        $lines[] = '$successPaths = @(' . $this->successPathsToPsArray($successPaths) . ')';
        $lines[] = '';
        $lines[] = 'function Write-Log {';
        $lines[] = '    param([string]$Message)';
        $lines[] = '    Write-Host "[$packageName] $(Get-Date -Format \'yyyy-MM-dd HH:mm:ss\') $Message"';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'function Test-SuccessPaths {';
        $lines[] = '    foreach ($sp in $successPaths) {';
        $lines[] = '        if ([string]::IsNullOrWhiteSpace($sp)) { continue }';
        $lines[] = '        $expanded = [Environment]::ExpandEnvironmentVariables($sp)';
        $lines[] = '        if ($expanded -and (Test-Path -LiteralPath $expanded)) {';
        $lines[] = '            Write-Log "Produit détecté : $expanded"';
        $lines[] = '            return $true';
        $lines[] = '        }';
        $lines[] = '    }';
        $lines[] = '    return $false';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'try {';
        $lines[] = '    Write-Log "Version déclarée : $packageVersion | Témoin : $fichierTemoin"';
        $lines[] = '    foreach ($dossier in @($cheminTemoin, $cheminApp)) {';
        $lines[] = '        if (-not (Test-Path -LiteralPath $dossier)) {';
        $lines[] = '            New-Item -Path $dossier -ItemType Directory -Force | Out-Null';
        $lines[] = '            Write-Log "Répertoire créé : $dossier"';
        $lines[] = '        }';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    if (-not (Test-Path -LiteralPath $installateur)) {';
        $lines[] = '        Write-Log "Installateur introuvable : $installateur"';
        $lines[] = '        exit 1603';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    Write-Log "Lancement de l\'installation : $installateur"';
        if (trim($silentArgs) !== '') {
            $lines[] = '    Write-Log \'Arguments silencieux : ' . str_replace("'", "''", $silentArgs) . '\'';
        } else {
            $lines[] = '    Write-Log \'Aucun argument silencieux (procédure vide) — lancement sans ArgumentList\'';
        }
        $lines[] = '    Write-Log "Timeout installateur : $installTimeoutSec s"';
        $lines[] = '    $argList = @(' . $this->splitArgsToPsArray($silentArgs) . ')';
        $lines[] = '    $pathsPresentBefore = Test-SuccessPaths';
        $lines[] = '    if ($pathsPresentBefore) {';
        $lines[] = '        Write-Log \'Produit deja present avant install — on attend la fin de l installateur (pas de sortie precoce).\'';
        $lines[] = '    }';
        // Pas de -Wait : certains installateurs (PDF24 / drivers) restent bloqués après install réelle.
        $lines[] = '    if ($argList.Count -gt 0) {';
        $lines[] = '        $process = Start-Process -FilePath $installateur -ArgumentList $argList -PassThru -WindowStyle Hidden';
        $lines[] = '    } else {';
        $lines[] = '        $process = Start-Process -FilePath $installateur -PassThru -WindowStyle Hidden';
        $lines[] = '    }';
        $lines[] = '    if ($null -eq $process) { Write-Log \'Start-Process a échoué.\'; exit 1603 }';
        $lines[] = '    $deadline = (Get-Date).AddSeconds($installTimeoutSec)';
        $lines[] = '    $productDetected = $false';
        $lines[] = '    while ($true) {';
        // Sortie precoce seulement si le produit APPARAIT pendant l'install (pas s'il etait deja la).
        $lines[] = '        if (-not $pathsPresentBefore -and (Test-SuccessPaths)) { $productDetected = $true; break }';
        $lines[] = '        if ($process.HasExited) { break }';
        $lines[] = '        if ((Get-Date) -ge $deadline) { Write-Log \'Timeout: installateur toujours actif\'; break }';
        $lines[] = '        Start-Sleep -Seconds 2';
        $lines[] = '    }';
        $lines[] = '    if (-not $process.HasExited) {';
        $lines[] = '        if ($productDetected) {';
        $lines[] = '            Write-Log \'Produit present - arret de l installateur encore actif...\'';
        $lines[] = '            Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue';
        $lines[] = '            Start-Sleep -Seconds 2';
        $lines[] = '        } else {';
        $lines[] = '            Write-Log \'Timeout sans détection produit — kill installateur\'';
        $lines[] = '            Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue';
        $lines[] = '            exit 1603';
        $lines[] = '        }';
        $lines[] = '    } else {';
        $lines[] = '        Write-Log "Code de sortie installateur : $($process.ExitCode)"';
        $lines[] = '        if (-not $productDetected) { $productDetected = Test-SuccessPaths }';
        $lines[] = '        if (-not $productDetected -and $null -ne $process.ExitCode -and [int]$process.ExitCode -ne 0) {';
        $lines[] = '            exit $process.ExitCode';
        $lines[] = '        }';
        $lines[] = '    }';
        $lines[] = '    Start-Sleep -Seconds 2';
        $lines[] = '';

        foreach ($procedure->steps as $step) {
            if (!$step->enabled) {
                continue;
            }
            if (in_array($step->type, [
                StepType::UninstallCommand,
                StepType::Note,
                StepType::InstallCommand,
                StepType::DeleteFile,
            ], true)) {
                continue;
            }
            if ($this->isMetaReferenceStep($step)) {
                continue;
            }

            $lines[] = '    # Étape #' . $step->sortOrder . ' — ' . ($step->label ?: $step->type->label());
            $lines = array_merge($lines, $this->stepToInstallPs($step));
            $lines[] = '';
        }

        $lines[] = '    if (-not (Test-Path -LiteralPath $fichierTemoin)) {';
        $lines[] = '        New-Item -Path $fichierTemoin -ItemType File -Force | Out-Null';
        $lines[] = '        Write-Log "Fichier témoin créé : $fichierTemoin"';
        $lines[] = '    } else {';
        $lines[] = '        Write-Log "Fichier témoin déjà présent : $fichierTemoin"';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    Write-Log \'Installation terminée avec succès.\'';
        $lines[] = '    exit 0';
        $lines[] = '}';
        $lines[] = 'catch {';
        $lines[] = '    Write-Log "Erreur : $($_.Exception.Message)"';
        $lines[] = '    exit 1603';
        $lines[] = '}';
        $lines[] = '';

        return $this->toCrLf(implode("\n", $lines));
    }

    /**
     * @return list<string>
     */
    private function stepToInstallPs(ProcedureStep $step): array
    {
        $p = $step->payload;

        return match ($step->type) {
            StepType::InstallCommand => $this->psInstallCommand($p),
            StepType::CopyFile => [
                '    Write-Log ' . $this->psSingleQuoted('Copie ' . ($p['source'] ?? '') . ' → ' . ($p['destination'] ?? '')),
                '    Copy-Item -LiteralPath ' . $this->psSingleQuoted((string) ($p['source'] ?? '')) .
                    ' -Destination ' . $this->psSingleQuoted((string) ($p['destination'] ?? '')) .
                    (!empty($p['overwrite']) ? ' -Force' : ''),
            ],
            StepType::Rename => [
                '    Rename-Item -LiteralPath ' . $this->psSingleQuoted((string) ($p['from_path'] ?? '')) .
                    ' -NewName ' . $this->psSingleQuoted((string) ($p['to_path'] ?? '')),
            ],
            StepType::CreateShortcut => $this->psShortcut($p),
            StepType::Registry => $this->psRegistry($p),
            StepType::Powershell => $this->psInlineOrPath($p),
            default => ['    Write-Log ' . $this->psSingleQuoted('Étape ignorée: ' . $step->type->value)],
        };
    }

    /**
     * @param array<string, mixed> $p
     * @return list<string>
     */
    private function psInstallCommand(array $p): array
    {
        $cmd = (string) ($p['command'] ?? '');
        $args = trim((string) ($p['silent_args'] ?? ''));
        $workdir = trim((string) ($p['working_dir'] ?? '.'));
        if ($workdir === '') {
            $workdir = '.';
        }

        $lines = [];
        $lines[] = '    $installerName = ' . $this->psSingleQuoted($cmd);
        $lines[] = '    $installateur = Join-Path $PSScriptRoot (Join-Path \'sources\' $installerName)';
        $lines[] = '    if (-not (Test-Path -LiteralPath $installateur)) { $installateur = Join-Path $PSScriptRoot $installerName }';
        $lines[] = '    if (-not (Test-Path -LiteralPath $installateur)) {';
        $lines[] = '        Write-Log "Installateur introuvable : $installateur"';
        $lines[] = '        exit 1603';
        $lines[] = '    }';
        $lines[] = '    Write-Log "Lancement de l\'installation : $installateur"';
        $lines[] = '    $argList = @(' . $this->splitArgsToPsArray($args) . ')';
        $extra = $workdir !== '.' ? ' -WorkingDirectory (Join-Path $PSScriptRoot ' . $this->psSingleQuoted($workdir) . ')' : '';
        $lines = array_merge($lines, $this->psStartProcessLines('$installateur', '$argList', '$process', $extra));
        $lines[] = '    if ($null -eq $process) { Write-Log \'Start-Process a échoué.\'; exit 1603 }';
        $lines[] = '    Write-Log "Code de sortie installateur : $($process.ExitCode)"';
        $lines[] = '    if ($process.ExitCode -ne 0) { exit $process.ExitCode }';

        return $lines;
    }

    /**
     * @param array<string, mixed> $p
     * @return list<string>
     */
    private function psShortcut(array $p): array
    {
        return [
            '    $WshShell = New-Object -ComObject WScript.Shell',
            '    $Shortcut = $WshShell.CreateShortcut(' . $this->psSingleQuoted((string) ($p['link_path'] ?? '')) . ')',
            '    $Shortcut.TargetPath = ' . $this->psSingleQuoted((string) ($p['target'] ?? '')),
            '    $Shortcut.Arguments = ' . $this->psSingleQuoted((string) ($p['arguments'] ?? '')),
            '    $Shortcut.IconLocation = ' . $this->psSingleQuoted((string) (($p['icon'] ?? '') !== '' && ($p['icon'] ?? '') !== null ? (string) $p['icon'] : (string) ($p['target'] ?? ''))),
            '    $Shortcut.Save()',
            '    Write-Log ' . $this->psSingleQuoted('Raccourci créé : ' . ($p['link_path'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $p
     * @return list<string>
     */
    private function psRegistry(array $p): array
    {
        $hive = (string) ($p['hive'] ?? 'HKLM');
        $key = (string) ($p['key'] ?? '');
        $path = $hive . ':\\' . ltrim(str_replace('/', '\\', $key), '\\');
        $action = (string) ($p['action'] ?? 'set');
        $valueName = (string) ($p['value_name'] ?? '');
        $valueData = (string) ($p['value_data'] ?? '');
        $valueType = (string) ($p['value_type'] ?? 'REG_SZ');

        if ($action === 'delete') {
            if ($valueName !== '') {
                return [
                    '    Remove-ItemProperty -Path ' . $this->psSingleQuoted($path) .
                        ' -Name ' . $this->psSingleQuoted($valueName) . ' -ErrorAction SilentlyContinue',
                ];
            }

            return [
                '    Remove-Item -Path ' . $this->psSingleQuoted($path) . ' -Recurse -Force -ErrorAction SilentlyContinue',
            ];
        }

        $typeMap = [
            'REG_SZ' => 'String',
            'REG_DWORD' => 'DWord',
            'REG_EXPAND_SZ' => 'ExpandString',
            'REG_MULTI_SZ' => 'MultiString',
            'REG_BINARY' => 'Binary',
        ];
        $psType = $typeMap[$valueType] ?? 'String';

        return [
            '    if (-not (Test-Path -LiteralPath ' . $this->psSingleQuoted($path) . ')) { New-Item -Path ' . $this->psSingleQuoted($path) . ' -Force | Out-Null }',
            '    New-ItemProperty -Path ' . $this->psSingleQuoted($path) .
                ' -Name ' . $this->psSingleQuoted($valueName !== '' ? $valueName : '(Default)') .
                ' -Value ' . $this->psSingleQuoted($valueData) .
                ' -PropertyType ' . $psType . ' -Force | Out-Null',
        ];
    }

    /**
     * @param array<string, mixed> $p
     * @return list<string>
     */
    private function psInlineOrPath(array $p): array
    {
        $path = trim((string) ($p['script_path'] ?? ''));
        $body = trim((string) ($p['script_body'] ?? ''));

        if ($path !== '') {
            return [
                '    $scriptPath = Join-Path $PSScriptRoot ' . $this->psSingleQuoted($path),
                '    if (-not (Test-Path -LiteralPath $scriptPath)) { $scriptPath = Join-Path (Join-Path $PSScriptRoot \'sources\') ' . $this->psSingleQuoted($path) . ' }',
                '    Write-Log "Exécution script : $scriptPath"',
                '    & $scriptPath',
                '    if ($LASTEXITCODE -and $LASTEXITCODE -ne 0) { exit $LASTEXITCODE }',
            ];
        }

        if ($body !== '') {
            $indented = preg_replace('/^/m', '    ', $body) ?? ('    ' . $body);

            return [
                '    Write-Log \'Exécution script PowerShell inline\'',
                $indented,
            ];
        }

        return ['    Write-Log \'Script PowerShell vide — ignoré\''];
    }

    private function buildUninstallScript(Software $software, PackagingProcedure $procedure): string
    {
        $safeTag = $this->psSingleQuoted($this->witnessAppName($software));
        $witnessFile = $this->psSingleQuoted($this->witnessFileName($software));

        $lines = [];
        $lines[] = '#Requires -Version 5.1';
        $lines[] = '$ErrorActionPreference = \'Stop\'';
        $lines[] = '$packageName = ' . $this->psSingleQuoted($software->name);
        $lines[] = '$cheminTemoin = \'C:\\SCCM_Temoins\'';
        $lines[] = '$cheminApp = Join-Path $cheminTemoin ' . $safeTag;
        $lines[] = '$fichierTemoin = Join-Path $cheminApp ' . $witnessFile;
        $lines[] = '';
        $lines[] = 'function Write-Log {';
        $lines[] = '    param([string]$Message)';
        $lines[] = '    Write-Host "[$packageName] $(Get-Date -Format \'yyyy-MM-dd HH:mm:ss\') $Message"';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'try {';

        $hasUninstall = false;
        foreach ($procedure->steps as $step) {
            if (!$step->enabled) {
                continue;
            }
            if ($step->type === StepType::UninstallCommand) {
                $hasUninstall = true;
                $p = $step->payload;
                $command = $this->unwrapPathQuotes(trim((string) ($p['command'] ?? '')));
                $silentArgs = trim((string) ($p['silent_args'] ?? ''));
                $productCode = trim((string) ($p['product_code'] ?? ''));
                if ($productCode !== '' && str_contains(strtolower($silentArgs), '{product-code}')) {
                    $silentArgs = str_ireplace('{product-code}', $productCode, $silentArgs);
                }

                $lines[] = '    Write-Log ' . $this->psSingleQuoted('Désinstallation : ' . ($step->label ?: $step->preview()));
                $lines[] = '    $cmdPattern = ' . $this->psSingleQuoted($command);
                $lines[] = '    $cmdPattern = [Environment]::ExpandEnvironmentVariables($cmdPattern).Trim()';
                $lines[] = '    if ($cmdPattern.Length -ge 2) {';
                $lines[] = '        $q = $cmdPattern[0]';
                $lines[] = '        if (($q -eq \'"\' -or $q -eq [char]39) -and $cmdPattern[-1] -eq $q) {';
                $lines[] = '            $cmdPattern = $cmdPattern.Substring(1, $cmdPattern.Length - 2).Trim()';
                $lines[] = '        }';
                $lines[] = '    }';
                $lines[] = '    $cmd = $null';
                $lines[] = '    if ($cmdPattern -match \'[\*\?\[]\') {';
                $lines[] = '        $resolved = @(Get-Item -Path $cmdPattern -ErrorAction SilentlyContinue | Sort-Object FullName -Descending)';
                $lines[] = '        if ($resolved.Count -gt 0) { $cmd = $resolved[0].FullName }';
                $lines[] = '    } elseif (Test-Path -LiteralPath $cmdPattern) {';
                $lines[] = '        $cmd = $cmdPattern';
                $lines[] = '    } else {';
                $lines[] = '        $found = Get-Command -Name $cmdPattern -ErrorAction SilentlyContinue';
                $lines[] = '        if ($null -ne $found) { $cmd = $found.Source }';
                $lines[] = '        else {';
                $lines[] = '            $leafName = \'\'';
                $lines[] = '            try { $leafName = [IO.Path]::GetFileName($cmdPattern) } catch { $leafName = \'\' }';
                $lines[] = '            if ($leafName -ieq \'msiexec.exe\') {';
                $lines[] = '                $sys = Join-Path $env:SystemRoot \'System32\\msiexec.exe\'';
                $lines[] = '                if (Test-Path -LiteralPath $sys) { $cmd = $sys }';
                $lines[] = '            }';
                $lines[] = '        }';
                $lines[] = '    }';
                // Install Inno « per-user » : Program Files absent → LocalAppData\Programs\<App>\unins000.exe
                $lines[] = '    if ($null -eq $cmd -and $cmdPattern -match \'(?i)[\\\\/]([^\\\\/]+)[\\\\/]unins\\d*\\.exe$\') {';
                $lines[] = '        $appFolder = $Matches[1]';
                $lines[] = '        $leaf = Split-Path -Leaf $cmdPattern';
                $lines[] = '        $perUser = Join-Path $env:LOCALAPPDATA (Join-Path \'Programs\' (Join-Path $appFolder $leaf))';
                $lines[] = '        if (Test-Path -LiteralPath $perUser) {';
                $lines[] = '            $cmd = $perUser';
                $lines[] = '            Write-Log "Désinstalleur per-user trouvé : $cmd"';
                $lines[] = '        }';
                $lines[] = '    }';
                // Dernier recours : UninstallString registre (HKLM + HKCU)
                $lines[] = '    if ($null -eq $cmd) {';
                $lines[] = '        $search = ' . $this->psSingleQuoted($software->name);
                $lines[] = '        $regKeys = @(';
                $lines[] = '            \'HKLM:\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*\',';
                $lines[] = '            \'HKLM:\\SOFTWARE\\WOW6432Node\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*\',';
                $lines[] = '            \'HKCU:\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*\'';
                $lines[] = '        )';
                $lines[] = '        $hit = Get-ItemProperty -Path $regKeys -ErrorAction SilentlyContinue |';
                $lines[] = '            Where-Object { $_.DisplayName -and $_.DisplayName -like ("*" + $search + "*") -and $_.UninstallString } |';
                $lines[] = '            Select-Object -First 1';
                $lines[] = '        if ($null -ne $hit) {';
                $lines[] = '            $us = [string]$hit.UninstallString';
                $lines[] = '            if ($us -match \'(?i)("?)([^"]*unins\\d*\\.exe)\\1\') { $cmd = $Matches[2] }';
                $lines[] = '            elseif ($us -match \'^"([^"]+)"\') { $cmd = $Matches[1] }';
                $lines[] = '            else { $cmd = ($us -split \'\\s+\')[0] }';
                $lines[] = '            if ($cmd) { Write-Log "Désinstalleur via registre : $cmd ($($hit.DisplayName))" }';
                $lines[] = '        }';
                $lines[] = '    }';

                // msiexec /x sans GUID fiable : résoudre via registre si besoin (Adobe Reader, etc.)
                $isMsiexec = str_contains(strtolower($command), 'msiexec');
                $needsProductLookup = $isMsiexec && (
                    $productCode === ''
                    || str_contains(strtolower($silentArgs), '{product-code}')
                );
                if ($isMsiexec && ($needsProductLookup || str_contains(strtolower($silentArgs), '/x'))) {
                    $lines[] = '    $productCode = ' . $this->psSingleQuoted($productCode);
                    $lines[] = '    if ($productCode -eq \'\' -or $productCode -eq \'{product-code}\') {';
                    $lines[] = '        $search = ' . $this->psSingleQuoted($software->name);
                    $lines[] = '        $keys = @(';
                    $lines[] = '            \'HKLM:\\SOFTWARE\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*\',';
                    $lines[] = '            \'HKLM:\\SOFTWARE\\WOW6432Node\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\*\'';
                    $lines[] = '        )';
                    $lines[] = '        $hit = Get-ItemProperty -Path $keys -ErrorAction SilentlyContinue |';
                    $lines[] = '            Where-Object {';
                    $lines[] = '                $_.DisplayName -and (';
                    $lines[] = '                    $_.DisplayName -like ("*" + $search + "*") -or';
                    $lines[] = '                    ($search -match \'Adobe\' -and $_.DisplayName -match \'Adobe (Acrobat|Reader)\')';
                    $lines[] = '                ) -and $_.PSChildName -match \'^\\{[0-9A-Fa-f-]+\\}$\'';
                    $lines[] = '            } | Select-Object -First 1';
                    $lines[] = '        if ($null -ne $hit) {';
                    $lines[] = '            $productCode = $hit.PSChildName';
                    $lines[] = '            Write-Log "ProductCode résolu via registre : $productCode ($($hit.DisplayName))"';
                    $lines[] = '        }';
                    $lines[] = '    }';
                    $lines[] = '    if ($productCode -eq \'\' -or $productCode -eq \'{product-code}\') {';
                    $lines[] = '        Write-Log \'ProductCode introuvable — application déjà absente ?\'';
                    $lines[] = '    } else {';
                    $lines[] = '        $args = @(\'/x\', $productCode, \'/qn\', \'/norestart\')';
                    $lines[] = '        if ($null -eq $cmd) { Write-Log \'msiexec introuvable\'; exit 1603 }';
                    $lines[] = '        Write-Log "Désinstalleur résolu : $cmd"';
                    $lines = array_merge($lines, array_map(
                        static fn (string $l): string => '        ' . $l,
                        $this->psStartProcessLines('$cmd', '$args', '$proc')
                    ));
                    $lines[] = '        Write-Log "Code sortie désinstall : $($proc.ExitCode)"';
                    $lines[] = '        $okCodes = @(0, 3010, 1605, 19)';
                    $lines[] = '        if ($null -ne $proc -and $okCodes -notcontains [int]$proc.ExitCode) { exit $proc.ExitCode }';
                    $lines[] = '    }';
                    $lines[] = '';
                    continue;
                }

                $lines[] = '    $args = @(' . $this->splitArgsToPsArray($silentArgs) . ')';
                $timeoutSec = max(60, (int) ($p['timeout_sec'] ?? 600));
                $preKill = $this->normalizeNameList($p['pre_kill'] ?? null);
                $gonePaths = $this->normalizeSuccessPaths($p['success_gone_paths'] ?? null);
                $lines[] = '    $uninstallTimeoutSec = ' . $timeoutSec;
                $lines[] = '    $preKill = @(' . $this->successPathsToPsArray($preKill) . ')';
                $lines[] = '    $successGonePaths = @(' . $this->successPathsToPsArray($gonePaths) . ')';
                $lines[] = '    function Test-ProductGone {';
                $lines[] = '        if ($successGonePaths.Count -eq 0) { return $false }';
                $lines[] = '        foreach ($sp in $successGonePaths) {';
                $lines[] = '            if ([string]::IsNullOrWhiteSpace($sp)) { continue }';
                $lines[] = '            $expanded = [Environment]::ExpandEnvironmentVariables($sp)';
                $lines[] = '            if ($expanded -and (Test-Path -LiteralPath $expanded)) { return $false }';
                $lines[] = '        }';
                $lines[] = '        return $true';
                $lines[] = '    }';
                $lines[] = '    if ($preKill.Count -gt 0) {';
                $lines[] = '        Write-Log (\'Arret processus : \' + ($preKill -join \', \'))';
                $lines[] = '        foreach ($procName in $preKill) {';
                $lines[] = '            Get-Process -Name $procName -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue';
                $lines[] = '        }';
                $lines[] = '        Start-Sleep -Seconds 2';
                $lines[] = '    }';
                $lines[] = '    if ($null -ne $cmd -and (($cmd -match \'(?i)msiexec\\.exe$\') -or (Test-Path -LiteralPath $cmd))) {';
                $lines[] = '        Write-Log "Désinstalleur résolu : $cmd"';
                $lines[] = '        Write-Log "Timeout désinstall : $uninstallTimeoutSec s"';
                $lines[] = '        if ($args.Count -gt 0) {';
                $lines[] = '            $proc = Start-Process -FilePath $cmd -ArgumentList $args -PassThru -WindowStyle Hidden';
                $lines[] = '        } else {';
                $lines[] = '            $proc = Start-Process -FilePath $cmd -PassThru -WindowStyle Hidden';
                $lines[] = '        }';
                $lines[] = '        $deadline = (Get-Date).AddSeconds($uninstallTimeoutSec)';
                $lines[] = '        $productGone = $false';
                $lines[] = '        while ($true) {';
                $lines[] = '            if (Test-ProductGone) { $productGone = $true; Write-Log \'Produit absent (chemins success_gone)\'; break }';
                $lines[] = '            if ($null -ne $proc -and $proc.HasExited) { break }';
                $lines[] = '            if ((Get-Date) -ge $deadline) { Write-Log \'Timeout: désinstalleur toujours actif\'; break }';
                $lines[] = '            Start-Sleep -Seconds 2';
                $lines[] = '        }';
                $lines[] = '        if ($null -ne $proc -and -not $proc.HasExited) {';
                $lines[] = '            if ($productGone) {';
                $lines[] = '                Write-Log \'Produit deja absent - arret du desinstalleur...\'';
                $lines[] = '            } else {';
                $lines[] = '                Write-Log \'Timeout - kill desinstalleur\'';
                $lines[] = '            }';
                $lines[] = '            Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue';
                $lines[] = '            Start-Sleep -Seconds 2';
                $lines[] = '        } elseif ($null -ne $proc) {';
                $lines[] = '            Write-Log "Code sortie désinstall : $($proc.ExitCode)"';
                $lines[] = '        }';
                $lines[] = '        if (-not $productGone) { $productGone = Test-ProductGone }';
                // Inno Setup : unins000.exe se clone dans %TEMP% puis quitte souvent avec code 1
                // pendant que le clone finit la désinstall — attendre disparition produit / unins.
                $lines[] = '        $isInnoUnins = ($cmd -match \'(?i)unins\\d*\\.exe$\')';
                $lines[] = '        if ($isInnoUnins -and -not $productGone) {';
                $lines[] = '            $graceSec = [Math]::Min(180, [Math]::Max(30, $uninstallTimeoutSec))';
                $lines[] = '            Write-Log "Inno : attente clone TEMP / fin désinstall (max ${graceSec}s, code initial souvent 1)..."';
                $lines[] = '            $graceDeadline = (Get-Date).AddSeconds($graceSec)';
                $lines[] = '            while ((Get-Date) -lt $graceDeadline) {';
                $lines[] = '                if (Test-ProductGone) { $productGone = $true; Write-Log \'Produit absent apres attente Inno\'; break }';
                $lines[] = '                if (-not (Test-Path -LiteralPath $cmd)) { Write-Log \'unins disparu — désinstall Inno en cours/OK\'; break }';
                $lines[] = '                $busy = @(Get-Process -ErrorAction SilentlyContinue | Where-Object {';
                $lines[] = '                    $_.Name -like \'unins*\' -or ($_.Path -and $_.Path -match \'(?i)\\\\Temp\\\\.*unins\')';
                $lines[] = '                })';
                $lines[] = '                if ($busy.Count -eq 0 -and (Test-ProductGone)) { $productGone = $true; break }';
                $lines[] = '                Start-Sleep -Seconds 2';
                $lines[] = '            }';
                $lines[] = '            if (-not $productGone) { $productGone = Test-ProductGone }';
                $lines[] = '        }';
                $lines[] = '        # Si unins000 a disparu, désinstall Inno souvent OK même sans success_gone';
                $lines[] = '        $uninsGone = -not (Test-Path -LiteralPath $cmd)';
                $lines[] = '        $okCodes = @(0, 3010, 1605, 19)';
                // WPKG / Inno : code 1 fréquent et acceptable si le produit a disparu
                $lines[] = '        if ($isInnoUnins) { $okCodes += 1 }';
                $lines[] = '        $exitOk = $false';
                $lines[] = '        if ($null -eq $proc) { $exitOk = $true }';
                $lines[] = '        elseif ($proc.HasExited -and ($null -eq $proc.ExitCode -or ($okCodes -contains [int]$proc.ExitCode))) {';
                $lines[] = '            if ($isInnoUnins -and [int]$proc.ExitCode -eq 1) {';
                $lines[] = '                $exitOk = ($productGone -or $uninsGone)';
                $lines[] = '            } else {';
                $lines[] = '                $exitOk = $true';
                $lines[] = '            }';
                $lines[] = '        }';
                $lines[] = '        if ($productGone -or $uninsGone -or $exitOk) {';
                $lines[] = '            Write-Log \'Désinstallation considérée réussie.\'';
                $lines[] = '        } else {';
                $lines[] = '            if ($null -ne $proc -and $proc.HasExited -and $null -ne $proc.ExitCode) { exit $proc.ExitCode }';
                $lines[] = '            exit 1603';
                $lines[] = '        }';
                $lines[] = '    } else {';
                $lines[] = '        Write-Log "Commande de désinstallation introuvable : $cmdPattern"';
                $lines[] = '        if (Test-ProductGone) {';
                $lines[] = '            Write-Log \'Produit deja absent — OK.\'';
                $lines[] = '        } else {';
                $lines[] = '            Write-Log ' . $this->psSingleQuoted(
                    'Echec: application probablement non installee (chemin uninstall absent).'
                );
                $lines[] = '            exit 1603';
                $lines[] = '        }';
                $lines[] = '    }';
                $lines[] = '';
            } elseif ($step->type === StepType::DeleteFile) {
                $p = $step->payload;
                $lines[] = '    if (Test-Path -LiteralPath ' . $this->psSingleQuoted((string) ($p['path'] ?? '')) . ') {';
                $lines[] = '        Remove-Item -LiteralPath ' . $this->psSingleQuoted((string) ($p['path'] ?? '')) .
                    (!empty($p['recursive']) ? ' -Recurse' : '') . ' -Force';
                $lines[] = '        Write-Log ' . $this->psSingleQuoted('Supprimé : ' . ($p['path'] ?? ''));
                $lines[] = '    }';
                $lines[] = '';
            } elseif ($step->type === StepType::Powershell && !$this->isMetaReferenceStep($step)
                && (str_contains(strtolower((string) ($step->label ?? '')), 'uninstall')
                    || str_contains(strtolower((string) ($step->label ?? '')), 'désinstall'))) {
                $lines = array_merge($lines, array_map(
                    static fn (string $l): string => str_starts_with($l, '    ') ? $l : '    ' . $l,
                    $this->psInlineOrPath($step->payload)
                ));
                $lines[] = '';
            }
        }

        if (!$hasUninstall) {
            $lines[] = '    Write-Log \'Aucune commande de désinstallation définie dans la procédure.\'';
        }

        $lines[] = '    Start-Sleep -Seconds 3';
        $lines[] = '    if (Test-Path -LiteralPath $fichierTemoin) {';
        $lines[] = '        Remove-Item -LiteralPath $fichierTemoin -Force';
        $lines[] = '        Write-Log "Témoin supprimé : $fichierTemoin"';
        $lines[] = '    }';
        $lines[] = '    Write-Log \'Désinstallation terminée.\'';
        $lines[] = '    exit 0';
        $lines[] = '}';
        $lines[] = 'catch {';
        $lines[] = '    Write-Log "Erreur : $($_.Exception.Message)"';
        $lines[] = '    exit 1603';
        $lines[] = '}';
        $lines[] = '';

        return $this->toCrLf(implode("\n", $lines));
    }

    private function buildManualTestReadme(Software $software, PackagingProcedure $procedure, string $strategy = 'exe'): string
    {
        $witness = 'C:\\SCCM_Temoins\\' . $this->witnessAppName($software) . '\\' . $this->witnessFileName($software);
        $stepCount = $this->countEnabledSteps($procedure);
        $generatedAt = $this->now();
        $name = $software->name;
        $publisher = $software->publisher;
        $version = $software->currentVersion;
        $arch = $software->architecture;
        $installer = $software->installerType;

        return <<<MD
# Test manuel de déploiement — {$name}

## Contenu du ZIP

| Fichier | Rôle |
|---------|------|
| `install.ps1` | Installation silencieuse + création témoin MECM |
| `uninstall.ps1` | Désinstallation + suppression témoin |
| `sources/` | Déposer ici l'installateur officiel |
| `icon/` | Icône package |
| `detection.txt` | Règle de détection MECM recommandée |
| `sccm-commands.txt` | Lignes de commande MECM |
| `procedure.json` | Export structuré de la procédure |

## Prérequis

1. Machine Windows de test (VM ou poste lab)
2. PowerShell 5.1+ en **administrateur**
3. Placer le fichier installateur dans `sources/` (voir `sources/README.txt`)

## Procédure de test

```powershell
cd <dossier_extrait>
# Vérifier que l'installateur est présent dans sources\\
Get-ChildItem .\\sources

# Installation
powershell.exe -ExecutionPolicy Bypass -File .\\install.ps1
echo \$LASTEXITCODE   # doit être 0

# Vérifier le témoin
Test-Path '{$witness}'

# Désinstallation
powershell.exe -ExecutionPolicy Bypass -File .\\uninstall.ps1
echo \$LASTEXITCODE

# Le témoin doit avoir disparu
Test-Path '{$witness}'
```

## Métadonnées

- **Éditeur** : {$publisher}
- **Version** : {$version}
- **Architecture** : {$arch}
- **Type** : {$installer}
- **Étapes procédure** : {$stepCount}

Généré le {$generatedAt}.
MD;
    }

    private function buildDocumentation(Software $software, PackagingProcedure $procedure): string
    {
        $stepsDoc = '';
        foreach ($procedure->steps as $step) {
            $status = $step->enabled ? 'ON' : 'OFF';
            $stepsDoc .= sprintf(
                "%d. [%s] %s — %s\n   %s\n\n",
                $step->sortOrder,
                $status,
                $step->type->label(),
                $step->label ?? '',
                $step->preview()
            );
        }

        $notes = $procedure->notes ?? '—';
        $desc = $software->description ?? '—';

        return "# Documentation — {$software->name}\n\n"
            . "## Description\n\n{$desc}\n\n"
            . "## Procédure : {$procedure->name}\n\n{$notes}\n\n"
            . "## Étapes\n\n{$stepsDoc}"
            . "## URLs\n\n"
            . '- Téléchargement : ' . ($software->downloadUrl ?? '—') . "\n"
            . '- Détection version : ' . ($software->versionCheckUrl ?? '—') . "\n"
            . '- Documentation : ' . ($software->documentationUrl ?? '—') . "\n";
    }

    private function buildChangelog(Software $software): string
    {
        return "# Changelog — {$software->name}\n\n"
            . "## {$software->currentVersion} (" . date('Y-m-d') . ")\n\n"
            . "- Package généré pour test de déploiement manuel\n"
            . '- Dernier packaging enregistré : ' . ($software->lastPackagedAt ?? 'jamais') . "\n"
            . "- Fréquence cible : {$software->updateFrequencyDays} jours\n";
    }

    private function buildSccmCommands(Software $software, string $strategy = 'exe', string $installerName = '', ?string $productCode = null): string
    {
        return "MECM — lignes de commande suggérées\n"
            . "=====================================\n\n"
            . "Installation (Programme) :\n"
            . "  powershell.exe -ExecutionPolicy Bypass -File .\\install.ps1\n\n"
            . "Désinstallation :\n"
            . "  powershell.exe -ExecutionPolicy Bypass -File .\\uninstall.ps1\n\n"
            . "Code retour attendu : 0\n"
            . "Nom application : {$software->name}\n"
            . "Version : {$software->currentVersion}\n"
            . "Architecture : {$software->architecture}\n"
            . 'Stratégie : ' . $strategy . "\n"
            . ($installerName !== '' ? "Installateur : {$installerName}\n" : '')
            . ($productCode ? "Product Code : {$productCode}\n" : '');
    }

    private function buildDetection(Software $software): string
    {
        $path = 'C:\\SCCM_Temoins\\' . $this->witnessAppName($software) . '\\' . $this->witnessFileName($software);

        return "Détection MECM recommandée (fichier témoin)\n"
            . "============================================\n\n"
            . "Type : Fichier\n"
            . "Chemin : {$path}\n"
            . "Condition : Le fichier existe\n\n"
            . "Alternative : adapter selon la procédure (clé registre, exe, etc.)\n";
    }

    private function buildProcedureJson(Software $software, PackagingProcedure $procedure): string
    {
        $steps = [];
        foreach ($procedure->steps as $step) {
            $steps[] = [
                'id' => $step->id,
                'type' => $step->type->value,
                'label' => $step->label,
                'sort_order' => $step->sortOrder,
                'enabled' => $step->enabled,
                'on_failure' => $step->onFailure,
                'payload' => $step->payload,
            ];
        }

        return (string) json_encode([
            'software' => [
                'id' => $software->id,
                'name' => $software->name,
                'publisher' => $software->publisher,
                'version' => $software->currentVersion,
                'architecture' => $software->architecture,
                'installer_type' => $software->installerType,
            ],
            'procedure' => [
                'id' => $procedure->id,
                'name' => $procedure->name,
                'notes' => $procedure->notes,
                'steps' => $steps,
            ],
            'exported_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function addIcon(ZipArchive $zip, string $prefix, Software $software): void
    {
        $zip->addEmptyDir($prefix . 'icon');

        // Toujours réutiliser l'icône existante de la fiche (pas de nouvelle upload à la génération)
        if ($software->iconPath) {
            $src = rtrim($this->iconPath, '/') . '/' . $software->iconPath;
            if (is_readable($src)) {
                $ext = strtolower(pathinfo($software->iconPath, PATHINFO_EXTENSION)) ?: 'png';
                $zip->addFile($src, $prefix . 'icon/sccm-icon.' . $ext);
                $zip->addFromString(
                    $prefix . 'icon/README.txt',
                    "Icône MECM (réutilisée depuis la fiche logiciel).\n"
                    . "Fichier : sccm-icon.{$ext}\n"
                    . "Format attendu : PNG 512×512, fond transparent, proportions conservées.\n"
                );

                return;
            }
        }

        $zip->addFromString(
            $prefix . 'icon/README.txt',
            "Aucune icône sur la fiche logiciel.\n"
            . "Ajoutez une icône MECM (normalisée en PNG 512×512 transparent) sur la fiche, puis régénérez le ZIP.\n"
        );
    }

    private function addSources(
        ZipArchive $zip,
        string $prefix,
        Software $software,
        PackagingProcedure $procedure,
    ): void {
        $zip->addEmptyDir($prefix . 'sources');

        $installerNames = [];
        foreach ($procedure->steps as $step) {
            if ($step->type === StepType::InstallCommand && !empty($step->payload['command'])) {
                $installerNames[] = (string) $step->payload['command'];
            }
        }
        $installerNames = array_values(array_unique($installerNames));

        $added = [];
        $packageDir = rtrim($this->sourcesPath, '/') . '/' . $software->id;
        if (is_dir($packageDir)) {
            foreach (scandir($packageDir) ?: [] as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $full = $packageDir . '/' . $file;
                if (is_file($full)) {
                    $zip->addFile($full, $prefix . 'sources/' . $file);
                    $added[] = $file;
                }
            }
        }

        $list = $installerNames !== []
            ? implode("\n", array_map(static fn ($n) => '- ' . $n, $installerNames))
            : '- (nom défini dans la procédure install_command)';

        $status = $added !== []
            ? "Fichiers inclus :\n" . implode("\n", array_map(static fn ($f) => '- ' . $f, $added))
            : "Aucun binaire inclus pour l'instant.\nDéposez l'installateur ici, ou dans storage/packages/{$software->id}/ sur le serveur puis régénérez le ZIP.";

        $zip->addFromString(
            $prefix . 'sources/README.txt',
            "Sources du package — {$software->name} {$software->currentVersion}\n\n"
            . "Installateur(s) attendu(s) :\n{$list}\n\n{$status}\n\n"
            . 'URL officielle : ' . ($software->downloadUrl ?? 'n/a') . "\n"
        );
    }

    /**
     * Steps that only document the generated scripts themselves (avoid recursion).
     */
    private function isMetaReferenceStep(ProcedureStep $step): bool
    {
        if ($step->type !== StepType::Powershell) {
            return false;
        }

        $path = strtolower(basename((string) ($step->payload['script_path'] ?? '')));
        if (in_array($path, ['install.ps1', 'uninstall.ps1'], true)) {
            return true;
        }

        $label = strtolower((string) ($step->label ?? ''));

        return str_contains($label, 'référence') || str_contains($label, 'reference');
    }

    private function witnessAppName(Software $software): string
    {
        $name = preg_replace('/[^a-zA-Z0-9]+/', '', $software->name) ?: 'App';

        return $name;
    }

    private function witnessFileName(Software $software): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $software->name) ?: 'app');

        return $slug . '.' . $software->currentVersion . '.log';
    }

    private function countEnabledSteps(PackagingProcedure $procedure): int
    {
        return count(array_filter($procedure->steps, static fn (ProcedureStep $s) => $s->enabled));
    }

    private function slugify(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-zA-Z0-9]+/', '_', $value) ?? 'package';
        $value = trim($value, '_');

        return $value !== '' ? $value : 'package';
    }

    private function psSingleQuoted(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Start-Process refuses -ArgumentList @() (null/empty) — omit the parameter when empty.
     *
     * @return list<string>
     */
    private function psStartProcessLines(
        string $fileVar,
        string $argListVar,
        string $processVar = '$process',
        string $extra = '',
    ): array {
        return [
            '    if (' . $argListVar . '.Count -gt 0) {',
            '        ' . $processVar . ' = Start-Process -FilePath ' . $fileVar
                . ' -ArgumentList ' . $argListVar . ' -Wait -PassThru -WindowStyle Hidden' . $extra,
            '    } else {',
            '        ' . $processVar . ' = Start-Process -FilePath ' . $fileVar
                . ' -Wait -PassThru -WindowStyle Hidden' . $extra,
            '    }',
        ];
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizeNameList(mixed $raw): array
    {
        return $this->normalizeSuccessPaths($raw);
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizeSuccessPaths(mixed $raw): array
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $text = trim((string) ($raw ?? ''));
            if ($text === '') {
                return [];
            }
            $parts = preg_split('/[\r\n;]+/', $text) ?: [];
        }

        $out = [];
        foreach ($parts as $p) {
            if (!is_string($p) && !is_numeric($p)) {
                continue;
            }
            $path = trim((string) $p);
            if ($path !== '') {
                $out[] = $path;
            }
        }

        return array_values(array_unique($out));
    }

    private function unwrapPathQuotes(string $path): string
    {
        $path = trim($path);
        while (strlen($path) >= 2) {
            $a = $path[0];
            $b = $path[strlen($path) - 1];
            if (($a === '"' && $b === '"') || ($a === "'" && $b === "'")) {
                $path = trim(substr($path, 1, -1));
                continue;
            }
            break;
        }

        return $path;
    }

    /**
     * @param list<string> $paths
     */
    private function successPathsToPsArray(array $paths): string
    {
        if ($paths === []) {
            return '';
        }

        return implode(', ', array_map(fn (string $p): string => $this->psSingleQuoted($p), $paths));
    }

    private function splitArgsToPsArray(string $args): string
    {
        if (trim($args) === '') {
            return '';
        }

        // Prefer keeping the whole silent args string as one argument list via splatting string
        // Split on spaces but keep quoted segments simple: use single ArgumentList string join
        $parts = preg_split('/\s+/', trim($args)) ?: [];
        $quoted = array_map(fn (string $p): string => $this->psSingleQuoted($p), array_values(array_filter($parts, static fn ($p) => $p !== '')));

        return implode(', ', $quoted);
    }

    private function toCrLf(string $content): string
    {
        return str_replace("\n", "\r\n", str_replace("\r\n", "\n", $content));
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
