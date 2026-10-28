<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;

/**
 * Site-wide UI options (locale, …).
 */
final class SiteConfigService
{
    public const STORAGE_FILE = 'site_config.json';

    /** @var array{locale: string} */
    public const DEFAULTS = [
        'locale' => 'fr',
    ];

    public function __construct(
        private readonly string $dataPath,
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    /**
     * @return array{locale: string, updated_at: ?string}
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

        $locale = strtolower(trim((string) ($raw['locale'] ?? '')));
        if ($locale !== 'en') {
            $locale = 'fr';
        }

        return [
            'locale' => $locale,
            'updated_at' => isset($raw['updated_at']) ? (string) $raw['updated_at'] : null,
        ];
    }

    public function locale(): string
    {
        return $this->get()['locale'];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, error?: string}
     */
    public function save(array $input, ?int $userId, ?string $ip): array
    {
        $locale = strtolower(trim((string) ($input['locale'] ?? 'fr')));
        if (!in_array($locale, ['fr', 'en'], true)) {
            return ['ok' => false, 'error' => 'Invalid locale.'];
        }

        $payload = [
            'locale' => $locale,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $file = $this->filePath();
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'error' => 'JSON encode failed.'];
        }

        $tmp = $file . '.tmp';
        if (file_put_contents($tmp, $json . "\n") === false) {
            return ['ok' => false, 'error' => 'Cannot write storage/data/.'];
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'Cannot finalize site_config.json.'];
        }
        @chmod($file, 0664);

        $this->auditLogs->log($userId, 'settings.site_updated', 'settings', null, [
            'locale' => $locale,
        ], $ip);

        return ['ok' => true];
    }

    private function filePath(): string
    {
        return rtrim($this->dataPath, '/') . '/' . self::STORAGE_FILE;
    }
}
