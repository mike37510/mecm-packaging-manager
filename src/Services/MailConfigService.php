<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;

/**
 * SMTP / mailing settings. Stored in storage/data/mail_config.json.
 */
final class MailConfigService
{
    public const STORAGE_FILE = 'mail_config.json';

    public function __construct(
        private readonly string $dataPath,
        private readonly AuditLogRepository $auditLogs,
        private readonly string $appUrl = '',
    ) {
    }

    /**
     * @return array{
     *   enabled: bool,
     *   host: string,
     *   port: int,
     *   encryption: string,
     *   username: string,
     *   password: string,
     *   from_email: string,
     *   from_name: string,
     *   app_url: string,
     *   updated_at: ?string
     * }
     */
    public function get(): array
    {
        $defaults = [
            'enabled' => false,
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_email' => '',
            'from_name' => 'MECM Packaging Manager',
            'app_url' => $this->appUrl,
            'updated_at' => null,
        ];
        $file = $this->filePath();
        if (!is_readable($file)) {
            return $defaults;
        }

        $raw = json_decode((string) file_get_contents($file), true);
        if (!is_array($raw)) {
            return $defaults;
        }

        $encryption = strtolower(trim((string) ($raw['encryption'] ?? 'tls')));
        if (!in_array($encryption, ['none', 'tls', 'ssl'], true)) {
            $encryption = 'tls';
        }

        $port = (int) ($raw['port'] ?? 587);
        if ($port < 1 || $port > 65535) {
            $port = 587;
        }

        $storedUrl = trim((string) ($raw['app_url'] ?? ''));

        return [
            'enabled' => !empty($raw['enabled']),
            'host' => trim((string) ($raw['host'] ?? '')),
            'port' => $port,
            'encryption' => $encryption,
            'username' => trim((string) ($raw['username'] ?? '')),
            'password' => (string) ($raw['password'] ?? ''),
            'from_email' => trim((string) ($raw['from_email'] ?? '')),
            'from_name' => trim((string) ($raw['from_name'] ?? '')) ?: 'MECM Packaging Manager',
            'app_url' => $storedUrl !== '' ? $storedUrl : $this->appUrl,
            'updated_at' => isset($raw['updated_at']) ? (string) $raw['updated_at'] : null,
        ];
    }

    public function isEnabled(): bool
    {
        $cfg = $this->get();

        return $cfg['enabled']
            && $cfg['host'] !== ''
            && $cfg['from_email'] !== '';
    }

    public function passwordIsSet(): bool
    {
        return $this->get()['password'] !== '';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, error?: string}
     */
    public function save(array $input, ?int $userId, ?string $ip): array
    {
        $existing = $this->get();
        $enabled = !empty($input['enabled']);
        $host = trim((string) ($input['host'] ?? ''));
        $port = (int) ($input['port'] ?? 587);
        $encryption = strtolower(trim((string) ($input['encryption'] ?? 'tls')));
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($password === '') {
            $password = $existing['password'];
        }
        $fromEmail = trim((string) ($input['from_email'] ?? ''));
        $fromName = trim((string) ($input['from_name'] ?? ''));
        $appUrl = rtrim(trim((string) ($input['app_url'] ?? '')), '/');

        if (!in_array($encryption, ['none', 'tls', 'ssl'], true)) {
            return ['ok' => false, 'error' => __('flash.mail_encryption_invalid')];
        }
        if ($port < 1 || $port > 65535) {
            return ['ok' => false, 'error' => __('flash.mail_port_invalid')];
        }
        if ($enabled) {
            if ($host === '') {
                return ['ok' => false, 'error' => __('flash.mail_host_required')];
            }
            if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => __('flash.mail_from_invalid')];
            }
        } elseif ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => __('flash.mail_from_invalid')];
        }
        if ($appUrl !== '' && !preg_match('#^https?://#i', $appUrl)) {
            return ['ok' => false, 'error' => __('flash.mail_app_url_invalid')];
        }

        $payload = [
            'enabled' => $enabled,
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'from_email' => $fromEmail,
            'from_name' => $fromName !== '' ? $fromName : 'MECM Packaging Manager',
            'app_url' => $appUrl,
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

            return ['ok' => false, 'error' => 'Impossible d’enregistrer mail_config.json.'];
        }
        @chmod($file, 0660);

        $this->auditLogs->log($userId, 'settings.mail_updated', 'settings', null, [
            'enabled' => $enabled,
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'password_changed' => trim((string) ($input['password'] ?? '')) !== '',
        ], $ip);

        return ['ok' => true];
    }

    private function filePath(): string
    {
        return rtrim($this->dataPath, '/') . '/' . self::STORAGE_FILE;
    }
}
