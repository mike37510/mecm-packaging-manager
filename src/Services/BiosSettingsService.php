<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;

/**
 * Admin BIOS packaging options (log paths injected into Apply-*.ps1 ZIPs).
 */
final class BiosSettingsService
{
    public const STORAGE_FILE = 'bios_config.json';

    /** @var array{log_local_path: string, log_share_path: string} */
    public const DEFAULTS = [
        'log_local_path' => '',
        'log_share_path' => '',
    ];

    public function __construct(
        private readonly string $dataPath,
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    /**
     * @return array{log_local_path: string, log_share_path: string, updated_at: ?string}
     */
    public function get(): array
    {
        $defaults = self::DEFAULTS;
        $file = $this->filePath();
        if (!is_readable($file)) {
            return $defaults + ['updated_at' => null];
        }

        $raw = json_decode((string) file_get_contents($file), true);
        if (!is_array($raw)) {
            return $defaults + ['updated_at' => null];
        }

        return [
            'log_local_path' => $this->normalizeLocalPath((string) ($raw['log_local_path'] ?? '')),
            'log_share_path' => $this->normalizeSharePath((string) ($raw['log_share_path'] ?? '')),
            'updated_at' => isset($raw['updated_at']) ? (string) $raw['updated_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, error?: string}
     */
    public function save(array $input, ?int $userId, ?string $ip): array
    {
        $local = $this->normalizeLocalPath((string) ($input['log_local_path'] ?? ''));
        $share = $this->normalizeSharePath((string) ($input['log_share_path'] ?? ''));

        if ($local !== '' && !$this->isPlausibleWindowsPath($local)) {
            return ['ok' => false, 'error' => 'invalid_local'];
        }
        if ($share !== '' && !$this->isPlausibleUnc($share)) {
            return ['ok' => false, 'error' => 'invalid_share'];
        }

        $payload = [
            'log_local_path' => $local,
            'log_share_path' => $share,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $file = $this->filePath();
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'error' => 'encode_failed'];
        }

        $tmp = $file . '.tmp';
        if (file_put_contents($tmp, $json . "\n") === false) {
            return ['ok' => false, 'error' => 'write_failed'];
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'write_failed'];
        }
        @chmod($file, 0664);

        $this->auditLogs->log($userId, 'settings.bios_updated', 'settings', null, [
            'log_local_path' => $local,
            'log_share_path' => $share,
        ], $ip);

        return ['ok' => true];
    }

    private function normalizeLocalPath(string $path): string
    {
        $path = trim(str_replace(["\0", "\r", "\n"], '', $path));
        $path = rtrim($path, " \t\\/");

        return $path;
    }

    private function normalizeSharePath(string $path): string
    {
        $path = trim(str_replace(["\0", "\r", "\n"], '', $path));
        $path = str_replace('/', '\\', $path);
        $path = rtrim($path, " \t\\");

        return $path;
    }

    private function isPlausibleWindowsPath(string $path): bool
    {
        // e.g. C:\Logs\BIOS or %TEMP%\BIOS
        return (bool) preg_match('/^(?:[A-Za-z]:\\\\|%[A-Za-z][A-Za-z0-9_]*%)/', $path);
    }

    private function isPlausibleUnc(string $path): bool
    {
        // e.g. \\server\share or \\server\share\folder
        return (bool) preg_match('/^\\\\\\\\[^\\\\\/]+\\\\[^\\\\\/]+/', $path);
    }

    private function filePath(): string
    {
        return rtrim($this->dataPath, '/') . '/' . self::STORAGE_FILE;
    }
}
