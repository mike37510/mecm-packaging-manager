<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;

/**
 * Per-user lab VM / SSH settings (users with Software write access).
 * Stored in storage/data/vm_configs.json keyed by user id.
 * Legacy global storage/data/vm_config.json is migrated once to the admin owner.
 */
final class VmConfigService
{
    public const STORAGE_FILE = 'vm_configs.json';
    public const LEGACY_FILE = 'vm_config.json';
    public const LEGACY_MIGRATED_MARKER = 'vm_config.json.migrated';

    /** Default owner for the one-shot migration of the former global VM config. */
    public const LEGACY_OWNER_EMAIL = 'mickael.rousseau@univ-tours.fr';

    /**
     * @param array<string, mixed> $toolDefaults ssh/scp/sshpass binaries (+ optional form placeholders)
     */
    public function __construct(
        private readonly string $dataPath,
        private readonly array $toolDefaults,
        private readonly AuditLogRepository $auditLogs,
        private readonly UserRepository $users,
    ) {
        $this->migrateLegacyGlobalConfig();
    }

    /**
     * Effective config for a user (tools defaults + personal settings).
     *
     * @return array<string, mixed>
     */
    public function getForUser(?int $userId): array
    {
        $base = $this->emptyConfig();
        if ($userId === null || $userId < 1) {
            return $base;
        }

        $all = $this->readAll();
        $key = (string) $userId;
        if (!isset($all[$key]) || !is_array($all[$key])) {
            return $base;
        }

        return $this->mergeRow($base, $all[$key]);
    }

    public function passwordIsSetForUser(int $userId): bool
    {
        return trim((string) ($this->getForUser($userId)['password'] ?? '')) !== '';
    }

    public function isConfiguredForUser(?int $userId): bool
    {
        $cfg = $this->getForUser($userId);

        return !empty($cfg['enabled'])
            && trim((string) ($cfg['host'] ?? '')) !== ''
            && trim((string) ($cfg['username'] ?? '')) !== ''
            && trim((string) ($cfg['password'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, error?: string, config?: array<string, mixed>}
     */
    public function saveForUser(int $userId, array $input, ?string $ip): array
    {
        if ($userId < 1) {
            return ['ok' => false, 'error' => 'Utilisateur invalide.'];
        }

        $host = trim((string) ($input['host'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $remoteDir = trim((string) ($input['remote_dir'] ?? ''));
        $port = (int) ($input['port'] ?? 0);
        $timeout = (int) ($input['timeout_seconds'] ?? 0);
        $enabled = !empty($input['enabled']);
        $password = (string) ($input['password'] ?? '');

        if ($host === '') {
            return ['ok' => false, 'error' => 'Hôte / IP obligatoire.'];
        }
        if ($port < 1 || $port > 65535) {
            return ['ok' => false, 'error' => 'Port SSH invalide (1–65535).'];
        }
        if ($username === '') {
            return ['ok' => false, 'error' => 'Identifiant SSH obligatoire.'];
        }
        if ($remoteDir === '') {
            return ['ok' => false, 'error' => 'Répertoire distant obligatoire.'];
        }
        if ($timeout < 60) {
            return ['ok' => false, 'error' => 'Timeout minimum : 60 secondes.'];
        }

        $existing = $this->getForUser($userId);
        $existingPassword = (string) ($existing['password'] ?? '');
        if (trim($password) === '') {
            $password = $existingPassword;
        }
        if (trim($password) === '' && $enabled) {
            return ['ok' => false, 'error' => 'Mot de passe SSH obligatoire lorsque la VM est activée.'];
        }

        $payload = [
            'enabled' => $enabled,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'remote_dir' => $remoteDir,
            'timeout_seconds' => $timeout,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $all = $this->readAll();
        $all[(string) $userId] = $payload;
        $write = $this->writeAll($all);
        if (!$write['ok']) {
            return $write;
        }

        $this->auditLogs->log($userId, 'account.vm_updated', 'user', $userId, [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'enabled' => $enabled,
            'remote_dir' => $remoteDir,
            'timeout_seconds' => $timeout,
            'password_changed' => trim((string) ($input['password'] ?? '')) !== '',
        ], $ip);

        return ['ok' => true, 'config' => $this->getForUser($userId)];
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyConfig(): array
    {
        $tools = [
            'sshpass_bin' => (string) ($this->toolDefaults['sshpass_bin'] ?? 'sshpass'),
            'ssh_bin' => (string) ($this->toolDefaults['ssh_bin'] ?? 'ssh'),
            'scp_bin' => (string) ($this->toolDefaults['scp_bin'] ?? 'scp'),
        ];

        return array_merge($tools, [
            'enabled' => false,
            'host' => '',
            'port' => 22,
            'username' => '',
            'password' => '',
            'remote_dir' => 'C:\\SCCM_Lab',
            'timeout_seconds' => 900,
            'updated_at' => null,
        ]);
    }

    /**
     * One-shot: copy legacy global vm_config.json onto Mickael's user entry.
     * Prefers the live legacy file over an older *.migrated backup.
     */
    private function migrateLegacyGlobalConfig(): void
    {
        $dir = rtrim($this->dataPath, '/');
        $legacy = $dir . '/' . self::LEGACY_FILE;
        $marker = $dir . '/' . self::LEGACY_MIGRATED_MARKER;

        $source = null;
        if (is_readable($legacy)) {
            $source = $legacy;
        } elseif (is_readable($marker)) {
            $source = $marker;
        } else {
            return;
        }

        $owner = $this->users->findByEmail(self::LEGACY_OWNER_EMAIL);
        if ($owner === null) {
            return;
        }

        $all = $this->readAll();
        $key = (string) $owner->id;
        $already = isset($all[$key]) && is_array($all[$key])
            && trim((string) ($all[$key]['host'] ?? '')) !== '';

        if (!$already) {
            $raw = file_get_contents($source);
            $ui = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($ui)) {
                if ($source === $legacy) {
                    @unlink($marker);
                    @rename($legacy, $marker);
                }

                return;
            }

            $clean = $this->sanitizeStoredRow($ui);
            if ($clean === []) {
                return;
            }
            $clean['updated_at'] = $clean['updated_at'] ?? date('Y-m-d H:i:s');
            $all[$key] = $clean;
            $write = $this->writeAll($all);
            if (!$write['ok']) {
                return;
            }
            $this->auditLogs->log($owner->id, 'account.vm_migrated_from_global', 'user', $owner->id, [
                'from' => basename($source),
                'email' => self::LEGACY_OWNER_EMAIL,
                'host' => $clean['host'] ?? null,
            ], null);
        }

        if (is_readable($legacy)) {
            @unlink($marker);
            @rename($legacy, $marker);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readAll(): array
    {
        $file = rtrim($this->dataPath, '/') . '/' . self::STORAGE_FILE;
        if (!is_readable($file)) {
            return [];
        }
        $raw = file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $userKey => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) $userKey;
            if ($id === '' || !ctype_digit($id)) {
                continue;
            }
            $out[$id] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $all
     * @return array{ok: bool, error?: string}
     */
    private function writeAll(array $all): array
    {
        $file = rtrim($this->dataPath, '/') . '/' . self::STORAGE_FILE;
        $json = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'error' => 'Encodage JSON impossible.'];
        }

        $tmp = $file . '.tmp';
        if (file_put_contents($tmp, $json . "\n") === false) {
            return ['ok' => false, 'error' => 'Écriture impossible dans storage/data/.'];
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'Impossible d\'enregistrer la configuration VM.'];
        }
        @chmod($file, 0660);

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mergeRow(array $base, array $row): array
    {
        $clean = $this->sanitizeStoredRow($row);

        return array_merge($base, $clean);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function sanitizeStoredRow(array $row): array
    {
        $allowed = ['enabled', 'host', 'port', 'username', 'password', 'remote_dir', 'timeout_seconds', 'updated_at'];
        $clean = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $clean[$key] = $row[$key];
        }
        if (isset($clean['port'])) {
            $clean['port'] = (int) $clean['port'];
        }
        if (isset($clean['timeout_seconds'])) {
            $clean['timeout_seconds'] = (int) $clean['timeout_seconds'];
        }
        if (isset($clean['enabled'])) {
            $clean['enabled'] = (bool) $clean['enabled'];
        }

        return $clean;
    }
}
