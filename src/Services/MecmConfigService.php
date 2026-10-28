<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;

/**
 * Global MECM defaults (site, DP, collection ID) for deploy.json / deploy.ps1.
 */
final class MecmConfigService
{
    public const STORAGE_FILE = 'mecm_config.json';

    /** @var array{site_code: string, site_server: string, distribution_point: string, collection_id: string, deployment_purpose: string, driver_source_unc: string, driver_package_unc: string, driver_category: string} */
    public const DEFAULTS = [
        'site_code' => 'SIT',
        'site_server' => 'sccm.domain.local',
        'distribution_point' => 'dp.domain.local',
        'collection_id' => '',
        'deployment_purpose' => 'available',
        'driver_source_unc' => '',
        'driver_package_unc' => '',
        'driver_category' => '',
    ];

    public function __construct(
        private readonly string $dataPath,
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    /**
     * @return array{
     *   site_code: string,
     *   site_server: string,
     *   distribution_point: string,
     *   collection_id: string,
     *   deployment_purpose: string,
     *   driver_source_unc: string,
     *   driver_package_unc: string,
     *   driver_category: string,
     *   updated_at: ?string
     * }
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

        $site = trim((string) ($raw['site_code'] ?? ''));
        $server = trim((string) ($raw['site_server'] ?? ''));
        $dp = trim((string) ($raw['distribution_point'] ?? ''));
        // Prefer collection_id ; migrate legacy collection_name if still present as empty id
        $collectionId = trim((string) ($raw['collection_id'] ?? ''));
        $purpose = strtolower(trim((string) ($raw['deployment_purpose'] ?? '')));
        if ($purpose !== 'required') {
            $purpose = 'available';
        }

        return [
            'site_code' => $site !== '' ? $site : $defaults['site_code'],
            'site_server' => $server !== '' ? $server : $defaults['site_server'],
            'distribution_point' => $dp !== '' ? $dp : $defaults['distribution_point'],
            'collection_id' => $collectionId,
            'deployment_purpose' => $purpose,
            'driver_source_unc' => $this->normalizeUnc((string) ($raw['driver_source_unc'] ?? '')),
            'driver_package_unc' => $this->normalizeUnc((string) ($raw['driver_package_unc'] ?? '')),
            'driver_category' => trim((string) ($raw['driver_category'] ?? '')),
            'updated_at' => isset($raw['updated_at']) ? (string) $raw['updated_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, error?: string}
     */
    public function save(array $input, ?int $userId, ?string $ip): array
    {
        $site = strtoupper(trim((string) ($input['site_code'] ?? '')));
        $server = strtolower(trim((string) ($input['site_server'] ?? '')));
        $dp = strtolower(trim((string) ($input['distribution_point'] ?? '')));
        $collectionId = strtoupper(trim((string) ($input['collection_id'] ?? '')));
        $purpose = strtolower(trim((string) ($input['deployment_purpose'] ?? 'available')));
        if ($purpose !== 'required') {
            $purpose = 'available';
        }
        $driverSource = $this->normalizeUnc((string) ($input['driver_source_unc'] ?? ''));
        $driverPackage = $this->normalizeUnc((string) ($input['driver_package_unc'] ?? ''));
        $driverCategory = trim((string) ($input['driver_category'] ?? ''));

        if ($site === '') {
            return ['ok' => false, 'error' => 'Site code MECM obligatoire.'];
        }
        if (!preg_match('/^[A-Z0-9]{3}$/', $site)) {
            return ['ok' => false, 'error' => 'Site code invalide (3 caractères, ex. SIT).'];
        }
        if ($server === '') {
            return ['ok' => false, 'error' => 'Serveur principal de site obligatoire.'];
        }
        if ($dp === '') {
            return ['ok' => false, 'error' => 'Distribution Point obligatoire.'];
        }
        if ($collectionId !== '' && !preg_match('/^[A-Z0-9]+$/', $collectionId)) {
            return ['ok' => false, 'error' => 'ID de collection invalide (ex. SIT00015).'];
        }
        if ($driverSource !== '' && !$this->isUnc($driverSource)) {
            return ['ok' => false, 'error' => 'Partage source pilotes : chemin UNC attendu (\\\\serveur\\partage\\…).'];
        }
        if ($driverPackage !== '' && !$this->isUnc($driverPackage)) {
            return ['ok' => false, 'error' => 'Partage packages pilotes : chemin UNC attendu (\\\\serveur\\partage\\…).'];
        }

        $payload = [
            'site_code' => $site,
            'site_server' => $server,
            'distribution_point' => $dp,
            'collection_id' => $collectionId,
            'deployment_purpose' => $purpose,
            'driver_source_unc' => $driverSource,
            'driver_package_unc' => $driverPackage,
            'driver_category' => $driverCategory,
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

            return ['ok' => false, 'error' => 'Impossible de finaliser mecm_config.json.'];
        }
        @chmod($file, 0664);

        $this->auditLogs->log($userId, 'settings.mecm_updated', 'settings', null, [
            'site_code' => $site,
            'site_server' => $server,
            'distribution_point' => $dp,
            'collection_id' => $collectionId,
            'deployment_purpose' => $purpose,
            'driver_source_unc' => $driverSource,
            'driver_package_unc' => $driverPackage,
            'driver_category' => $driverCategory,
        ], $ip);

        return ['ok' => true];
    }

    private function normalizeUnc(string $path): string
    {
        $path = trim(str_replace('/', '\\', $path));

        return rtrim($path, '\\');
    }

    private function isUnc(string $path): bool
    {
        return str_starts_with($path, '\\\\') && strlen($path) > 3;
    }

    private function filePath(): string
    {
        return rtrim($this->dataPath, '/') . '/' . self::STORAGE_FILE;
    }
}
