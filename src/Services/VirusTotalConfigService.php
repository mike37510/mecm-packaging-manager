<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;

/**
 * VirusTotal URL scan settings (pipeline). Stored in storage/data/virustotal_config.json.
 */
final class VirusTotalConfigService
{
    public const STORAGE_FILE = 'virustotal_config.json';

    public function __construct(
        private readonly string $dataPath,
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    /**
     * @return array{enabled: bool, api_key: string, updated_at: ?string}
     */
    public function get(): array
    {
        $defaults = ['enabled' => false, 'api_key' => '', 'updated_at' => null];
        $file = $this->filePath();
        if (!is_readable($file)) {
            return $defaults;
        }

        $raw = json_decode((string) file_get_contents($file), true);
        if (!is_array($raw)) {
            return $defaults;
        }

        return [
            'enabled' => !empty($raw['enabled']),
            'api_key' => trim((string) ($raw['api_key'] ?? '')),
            'updated_at' => isset($raw['updated_at']) ? (string) $raw['updated_at'] : null,
        ];
    }

    public function isEnabled(): bool
    {
        $cfg = $this->get();

        return $cfg['enabled'] && $cfg['api_key'] !== '';
    }

    public function apiKey(): string
    {
        return $this->get()['api_key'];
    }

    public function apiKeyIsSet(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, error?: string}
     */
    public function save(array $input, ?int $userId, ?string $ip): array
    {
        $existing = $this->get();
        $enabled = !empty($input['enabled']);
        $apiKey = trim((string) ($input['api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = $existing['api_key'];
        }
        if ($enabled && $apiKey === '') {
            return ['ok' => false, 'error' => 'Clé API VirusTotal obligatoire lorsque le contrôle est activé.'];
        }
        if ($apiKey !== '' && !preg_match('/^[a-f0-9]{64}$/i', $apiKey)) {
            return ['ok' => false, 'error' => 'Clé API VirusTotal invalide (64 caractères hexadécimaux).'];
        }

        $payload = [
            'enabled' => $enabled,
            'api_key' => $apiKey,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $file = $this->filePath();
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'error' => 'Encodage JSON impossible.'];
        }

        $tmp = $file . '.tmp';
        if (file_put_contents($tmp, $json . "\n") === false) {
            return ['ok' => false, 'error' => 'Écriture impossible dans storage/data/.'];
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'Impossible d’enregistrer virustotal_config.json.'];
        }
        @chmod($file, 0660);

        $this->auditLogs->log($userId, 'settings.virustotal_updated', 'settings', null, [
            'enabled' => $enabled,
            'api_key_changed' => trim((string) ($input['api_key'] ?? '')) !== '',
        ], $ip);

        return ['ok' => true];
    }

    private function filePath(): string
    {
        return rtrim($this->dataPath, '/') . '/' . self::STORAGE_FILE;
    }
}
