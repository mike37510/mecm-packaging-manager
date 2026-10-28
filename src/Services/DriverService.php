<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Driver;
use App\Repositories\AuditLogRepository;
use App\Repositories\DriverRepository;

/**
 * Driver catalog CRUD.
 */
final class DriverService
{
    public const MANUFACTURERS = [
        'dell',
        'hp',
    ];

    public const ARCHITECTURES = ['x64', 'x86', 'arm64', 'multi'];

    public function __construct(
        private readonly DriverRepository $drivers,
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    /**
     * @param array{q?: string, manufacturer?: string, sync_status?: string} $filters
     * @return list<Driver>
     */
    public function search(array $filters = []): array
    {
        return $this->drivers->search($filters);
    }

    public function find(int $id): ?Driver
    {
        return $this->drivers->findById($id);
    }

    /**
     * @return list<string>
     */
    public function usedManufacturers(): array
    {
        return $this->drivers->usedManufacturers();
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, id?: int, errors?: list<string>}
     */
    public function create(array $input, ?int $userId, ?string $ip): array
    {
        $errors = $this->validate($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $id = $this->drivers->create($this->payload($input));
        $this->auditLogs->log($userId, 'driver.created', 'driver', $id, [
            'name' => $input['name'] ?? '',
            'manufacturer' => $input['manufacturer'] ?? '',
        ], $ip);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors?: list<string>}
     */
    public function update(int $id, array $input, ?int $userId, ?string $ip): array
    {
        $driver = $this->drivers->findById($id);
        if ($driver === null) {
            return ['ok' => false, 'errors' => [__('flash.driver_not_found')]];
        }

        $errors = $this->validate($input, $driver);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->drivers->update($id, $this->payload($input, $driver));
        $this->auditLogs->log($userId, 'driver.updated', 'driver', $id, [
            'name' => $input['name'] ?? '',
        ], $ip);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function delete(int $id, ?int $userId, ?string $ip): array
    {
        $driver = $this->drivers->findById($id);
        if ($driver === null) {
            return ['ok' => false, 'error' => __('flash.driver_not_found')];
        }

        if ($driver->lastExportPath && is_file($driver->lastExportPath)) {
            @unlink($driver->lastExportPath);
        }

        $this->drivers->delete($id);
        $this->auditLogs->log($userId, 'driver.deleted', 'driver', $id, [
            'name' => $driver->name,
        ], $ip);

        return ['ok' => true];
    }

    /**
     * Manufacturers selectable in forms. Existing leftover codes stay editable.
     *
     * @return list<string>
     */
    public static function choicesFor(?Driver $existing = null): array
    {
        $list = self::MANUFACTURERS;
        if ($existing !== null && $existing->manufacturer !== '' && !in_array($existing->manufacturer, $list, true)) {
            $list[] = $existing->manufacturer;
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $input
     * @return list<string>
     */
    private function validate(array $input, ?Driver $existing = null): array
    {
        $errors = [];
        if (trim((string) ($input['name'] ?? '')) === '') {
            $errors[] = __('driver.err_name');
        }
        $mfr = trim((string) ($input['manufacturer'] ?? ''));
        if ($mfr === '' || !in_array($mfr, self::choicesFor($existing), true)) {
            $errors[] = __('driver.err_manufacturer');
        }
        $url = trim((string) ($input['download_url'] ?? ''));
        if ($url === '') {
            $errors[] = __('driver.err_url');
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = __('driver.err_url_invalid');
        }
        $model = trim((string) ($input['model'] ?? ''));
        if ($mfr === 'hp' && HpDriverPackResolver::isCatalogUrl($url) && $model === '') {
            $errors[] = __('driver.err_hp_model');
        }
        $osTarget = trim((string) ($input['os_target'] ?? ''));
        $existingOs = $existing !== null ? $existing->osTarget : '';
        if ($osTarget !== '' && !HpDriverPackResolver::isAllowedOsTarget($osTarget, $existingOs)) {
            $errors[] = __('driver.err_hp_os');
        }
        $sha = strtolower(trim((string) ($input['expected_sha256'] ?? '')));
        if ($sha !== '' && preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
            $errors[] = __('driver.err_sha');
        }
        $arch = trim((string) ($input['architecture'] ?? 'x64'));
        if (!in_array($arch, self::ARCHITECTURES, true)) {
            $errors[] = __('driver.err_arch');
        }

        return $errors;
    }

    public static function isVendorCatalogUrl(string $url): bool
    {
        return DellDriverPackResolver::isCatalogUrl($url)
            || HpDriverPackResolver::isCatalogUrl($url);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function payload(array $input, ?Driver $existing = null): array
    {
        $url = trim((string) ($input['download_url'] ?? ''));
        $mfr = trim((string) ($input['manufacturer'] ?? ''));
        $arch = trim((string) ($input['architecture'] ?? 'x64'));
        $osTarget = $mfr === 'hp' ? trim((string) ($input['os_target'] ?? '')) : '';
        $existingOs = $existing !== null ? $existing->osTarget : '';
        if ($osTarget !== '' && !HpDriverPackResolver::isAllowedOsTarget($osTarget, $existingOs)) {
            $osTarget = '';
        }

        $catalogUrl = null;
        $downloadUrl = $url;
        if (DellDriverPackResolver::isCatalogUrl($url) || HpDriverPackResolver::isCatalogUrl($url)) {
            $catalogUrl = $url;
            $sameCatalog = $existing !== null && $existing->catalogUrl === $url;
            if ($sameCatalog && $existing->downloadUrl !== '' && !self::isVendorCatalogUrl($existing->downloadUrl)) {
                $downloadUrl = $existing->downloadUrl;
            }
        } elseif ($mfr === 'hp' && HpDriverPackResolver::isDirectPackUrl($url)) {
            $model = trim((string) ($input['model'] ?? ''));
            if ($model !== '') {
                $existingCatalog = $existing?->catalogUrl;
                $catalogUrl = ($existingCatalog !== null && HpDriverPackResolver::isCatalogUrl($existingCatalog))
                    ? $existingCatalog
                    : HpDriverPackResolver::defaultMatrixUrl($arch);
            }
        }

        $data = [
            'name' => trim((string) ($input['name'] ?? '')),
            'manufacturer' => $mfr,
            'model' => trim((string) ($input['model'] ?? '')),
            'version' => $existing !== null && $existing->version !== '' ? $existing->version : 'unknown',
            'architecture' => $arch,
            'os_target' => $osTarget,
            'download_url' => $downloadUrl,
            'catalog_url' => $catalogUrl,
            'description' => trim((string) ($input['description'] ?? '')),
            'expected_sha256' => $this->normalizeSha((string) ($input['expected_sha256'] ?? '')),
        ];

        if ($existing !== null) {
            $data['last_sync_sha256'] = $existing->lastSyncSha256;
            $data['last_packaged_sha256'] = $existing->lastPackagedSha256;
            $data['last_sync_at'] = $existing->lastSyncAt;
            $data['last_packaged_at'] = $existing->lastPackagedAt;
            $data['last_sync_filename'] = $existing->lastSyncFilename;
            $data['last_sync_size'] = $existing->lastSyncSize;
            $data['last_sync_error'] = $existing->lastSyncError;
            $data['last_sync_fingerprint'] = $existing->lastSyncFingerprint;
            $data['last_packaged_fingerprint'] = $existing->lastPackagedFingerprint;
            $data['sync_status'] = $existing->syncStatus;
            $data['last_export_path'] = $existing->lastExportPath;
            $data['last_export_filename'] = $existing->lastExportFilename;
            if ($catalogUrl === null && $existing->catalogUrl !== null) {
                $data['catalog_url'] = null;
            }
        }

        return $data;
    }

    private function normalizeSha(string $value): ?string
    {
        $sha = strtolower(trim($value));
        if ($sha === '' || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
            return null;
        }

        return $sha;
    }
}
