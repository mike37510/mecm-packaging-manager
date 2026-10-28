<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\BiosDellConfigRepository;

/**
 * Save / list / download / delete versioned Dell BIOS .ini files (BiosDell-N.ini).
 */
final class BiosDellConfigService
{
    /** @var list<string> */
    private const PACKAGE_SCRIPTS = [
        'Apply-DellBiosConfig.ps1',
        'Clear-DellBiosSetupPassword.ps1',
        'Get-DellBiosAllSettings.ps1',
    ];

    public function __construct(
        private readonly BiosDellConfigRepository $configs,
        private readonly AuditLogRepository $audit,
        private readonly BiosPackageBuilder $packages,
        private readonly string $storageDir,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listVersions(): array
    {
        return $this->configs->allNewestFirst();
    }

    /**
     * @return array{ok: true, id: int, version: int, filename: string}|array{ok: false, error: string}
     */
    public function save(string $content, ?string $note, ?int $userId, ?string $userName, ?string $ip): array
    {
        $content = str_replace("\0", '', $content);
        // Strip UTF-8 BOM if present
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = trim($content);
        if ($content === '') {
            return ['ok' => false, 'error' => 'invalid_content'];
        }

        // Ensure a leading [cctk] section (required by cctk.exe -i).
        if (preg_match('/^\[cctk\]\s*$/im', $content) === 1) {
            // Drop any preamble so the file starts with [cctk]
            if (!preg_match('/^\[cctk\]/i', $content)) {
                $content = preg_replace('/\A[\s\S]*?(?=^\[cctk\]\s*$)/im', '', $content) ?? $content;
                $content = ltrim($content);
            }
        } else {
            // Auto-prepend when the body is already key=value lines
            // (e.g. brackets stripped in transit by an HTTP filter).
            $looksLikeCctk = false;
            foreach (preg_split("/\n/", $content) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#')) {
                    continue;
                }
                if (preg_match('/^[A-Za-z][A-Za-z0-9_.]*=/', $line) === 1) {
                    $looksLikeCctk = true;
                    break;
                }
            }
            if (!$looksLikeCctk) {
                return ['ok' => false, 'error' => 'invalid_content'];
            }
            $content = "[cctk]\n" . $content;
        }

        $checked = 0;
        foreach (preg_split("/\n/", $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^\[[^\]]+\]$/', $line)) {
                continue;
            }
            if (str_contains($line, '=')) {
                $checked++;
            }
        }
        if ($checked < 1) {
            return ['ok' => false, 'error' => 'empty_selection'];
        }

        $content = str_replace("\n", "\r\n", $content) . "\r\n";

        try {
            $this->ensureDir();
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'write_failed'];
        }
        if (!is_writable($this->storageDir)) {
            return ['ok' => false, 'error' => 'write_failed'];
        }

        $version = $this->configs->nextVersion();
        $filename = 'BiosDell-' . $version . '.ini';
        $path = $this->pathFor($filename);
        if (@file_put_contents($path, $content) === false) {
            return ['ok' => false, 'error' => 'write_failed'];
        }

        $id = $this->configs->insert([
            'manufacturer' => 'dell',
            'version' => $version,
            'filename' => $filename,
            'option_count' => $checked,
            'note' => trim((string) $note),
            'created_by' => $userId,
            'created_by_name' => $userName ?? '',
            'bytes' => filesize($path) ?: strlen($content),
        ]);

        $this->audit->log($userId, 'bios_dell_config.save', 'bios_dell_config', $id, [
            'version' => $version,
            'filename' => $filename,
            'option_count' => $checked,
        ], $ip);

        return [
            'ok' => true,
            'id' => $id,
            'version' => $version,
            'filename' => $filename,
        ];
    }

    /**
     * Deployable ZIP: config + scripts + tools/ skeleton.
     *
     * @return array{ok: true, path: string, filename: string}|array{ok: false, error: string}
     */
    public function buildPackageZip(int $id): array
    {
        $row = $this->configs->find($id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $filename = (string) ($row['filename'] ?? '');
        if ($filename === '' || !preg_match('/^BiosDell-\d+\.ini$/', $filename)) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $path = $this->pathFor($filename);
        if (!is_file($path)) {
            return ['ok' => false, 'error' => 'file_missing'];
        }

        return $this->packages->build('dell', $path, $filename, self::PACKAGE_SCRIPTS);
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function delete(int $id, ?int $userId, ?string $ip): array
    {
        $row = $this->configs->find($id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $filename = (string) ($row['filename'] ?? '');
        if ($filename !== '' && preg_match('/^BiosDell-\d+\.ini$/', $filename)) {
            $path = $this->pathFor($filename);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->configs->delete($id);
        $this->audit->log($userId, 'bios_dell_config.delete', 'bios_dell_config', $id, [
            'version' => $row['version'] ?? null,
            'filename' => $filename,
        ], $ip);

        return ['ok' => true];
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0775, true) && !is_dir($this->storageDir)) {
            throw new \RuntimeException('Unable to create Dell BIOS storage directory.');
        }
    }

    private function pathFor(string $filename): string
    {
        return rtrim($this->storageDir, '/') . '/' . $filename;
    }
}
