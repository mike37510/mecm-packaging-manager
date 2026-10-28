<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Driver catalog entry (vendor pack URL + hash tracking).
 */
final class Driver
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $manufacturer,
        public readonly string $model,
        public readonly string $version,
        public readonly string $architecture,
        public readonly string $downloadUrl,
        public readonly ?string $catalogUrl = null,
        public readonly string $osTarget = '',
        public readonly ?string $description = null,
        public readonly ?string $expectedSha256 = null,
        public readonly ?string $lastSyncSha256 = null,
        public readonly ?string $lastPackagedSha256 = null,
        public readonly ?string $lastSyncAt = null,
        public readonly ?string $lastPackagedAt = null,
        public readonly ?string $lastSyncFilename = null,
        public readonly ?int $lastSyncSize = null,
        public readonly ?string $lastSyncError = null,
        public readonly ?string $lastSyncFingerprint = null,
        public readonly ?string $lastPackagedFingerprint = null,
        public readonly string $syncStatus = 'never',
        public readonly ?string $lastExportPath = null,
        public readonly ?string $lastExportFilename = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $size = $row['last_sync_size'] ?? null;

        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            manufacturer: (string) $row['manufacturer'],
            model: (string) ($row['model'] ?? ''),
            version: (string) ($row['version'] ?? ''),
            architecture: (string) ($row['architecture'] ?? 'x64'),
            downloadUrl: (string) ($row['download_url'] ?? ''),
            catalogUrl: self::nullableString($row['catalog_url'] ?? null),
            osTarget: (string) ($row['os_target'] ?? ''),
            description: isset($row['description']) && $row['description'] !== null && $row['description'] !== ''
                ? (string) $row['description']
                : null,
            expectedSha256: self::usableSha256($row['expected_sha256'] ?? null),
            lastSyncSha256: self::usableSha256($row['last_sync_sha256'] ?? null),
            lastPackagedSha256: self::usableSha256($row['last_packaged_sha256'] ?? null),
            lastSyncAt: self::nullableString($row['last_sync_at'] ?? null),
            lastPackagedAt: self::nullableString($row['last_packaged_at'] ?? null),
            lastSyncFilename: self::nullableString($row['last_sync_filename'] ?? null),
            lastSyncSize: is_numeric($size) ? (int) $size : null,
            lastSyncError: self::nullableString($row['last_sync_error'] ?? null),
            lastSyncFingerprint: self::nullableString($row['last_sync_fingerprint'] ?? null),
            lastPackagedFingerprint: self::nullableString($row['last_packaged_fingerprint'] ?? null),
            syncStatus: isset($row['sync_status']) ? (string) $row['sync_status'] : 'never',
            lastExportPath: self::nullableString($row['last_export_path'] ?? null),
            lastExportFilename: self::nullableString($row['last_export_filename'] ?? null),
            createdAt: self::nullableString($row['created_at'] ?? null),
            updatedAt: self::nullableString($row['updated_at'] ?? null),
        );
    }

    public function hasDownloadUrl(): bool
    {
        return trim($this->downloadUrl) !== '' || ($this->catalogUrl !== null && trim($this->catalogUrl) !== '');
    }

    public function sourceUrl(): string
    {
        if ($this->catalogUrl !== null && trim($this->catalogUrl) !== '') {
            return $this->catalogUrl;
        }

        return $this->downloadUrl;
    }

    public function hasExport(): bool
    {
        return $this->lastExportPath !== null && $this->lastExportPath !== '' && is_file($this->lastExportPath);
    }

    public function syncStatusLabel(): string
    {
        return match ($this->syncStatus) {
            'up_to_date' => __('driver.legend_ok'),
            'update_available' => __('driver.legend_ko'),
            'error' => __('driver.legend_err'),
            default => __('driver.legend_muted'),
        };
    }

    /**
     * Real SHA-256 only (rejects empty / all-zero placeholders).
     */
    public static function usableSha256(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $sha = strtolower(trim((string) $value));
        if (preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
            return null;
        }
        if ($sha === str_repeat('0', 64)) {
            return null;
        }

        return $sha;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
