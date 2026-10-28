<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\BiosHpConfigRepository;

/**
 * Save / list / download / delete versioned HP BIOS .cfg files (BiosHP-N.cfg).
 */
final class BiosHpConfigService
{
    /** @var list<string> */
    private const PACKAGE_SCRIPTS = [
        'Apply-HPBiosConfig.ps1',
        'Clear-HPBiosSetupPassword.ps1',
        'Get-HPBiosAllSettings.ps1',
    ];

    public function __construct(
        private readonly BiosHpConfigRepository $configs,
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
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = trim($content);
        if ($content === '' || !str_starts_with($content, 'BIOSConfig')) {
            return ['ok' => false, 'error' => 'invalid_content'];
        }
        // Normalize to CRLF for BCU
        $content = str_replace("\n", "\r\n", $content) . "\r\n";

        $checked = 0;
        foreach (preg_split("/\r\n/", $content) ?: [] as $line) {
            if ($line !== '' && $line[0] !== ';' && !str_starts_with($line, 'BIOSConfig') && !str_starts_with($line, "\t")) {
                $checked++;
            }
        }
        if ($checked < 1) {
            return ['ok' => false, 'error' => 'empty_selection'];
        }

        try {
            $this->ensureDir();
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'write_failed'];
        }
        if (!is_writable($this->storageDir)) {
            return ['ok' => false, 'error' => 'write_failed'];
        }

        $version = $this->configs->nextVersion();
        $filename = 'BiosHP-' . $version . '.cfg';
        $path = $this->pathFor($filename);
        $written = @file_put_contents($path, $content);
        if ($written === false) {
            return ['ok' => false, 'error' => 'write_failed'];
        }

        $id = $this->configs->insert([
            'manufacturer' => 'hp',
            'version' => $version,
            'filename' => $filename,
            'option_count' => $checked,
            'note' => trim((string) $note),
            'created_by' => $userId,
            'created_by_name' => $userName ?? '',
            'bytes' => filesize($path) ?: strlen($content),
        ]);

        $this->audit->log($userId, 'bios_hp_config.save', 'bios_hp_config', $id, [
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
     * Deployable ZIP: config + scripts + Modules/ skeleton.
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
        if ($filename === '' || !preg_match('/^BiosHP-\d+\.cfg$/', $filename)) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $path = $this->pathFor($filename);
        if (!is_file($path)) {
            return ['ok' => false, 'error' => 'file_missing'];
        }

        return $this->packages->build('hp', $path, $filename, self::PACKAGE_SCRIPTS);
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
        if ($filename !== '' && preg_match('/^BiosHP-\d+\.cfg$/', $filename)) {
            $path = $this->pathFor($filename);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->configs->delete($id);
        $this->audit->log($userId, 'bios_hp_config.delete', 'bios_hp_config', $id, [
            'version' => $row['version'] ?? null,
            'filename' => $filename,
        ], $ip);

        return ['ok' => true];
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0775, true) && !is_dir($this->storageDir)) {
            throw new \RuntimeException('Unable to create BIOS storage directory.');
        }
    }

    private function pathFor(string $filename): string
    {
        return rtrim($this->storageDir, '/') . '/' . $filename;
    }
}
