<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Software catalog entry.
 */
final class Software
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $publisher,
        public readonly string $category,
        public readonly ?string $description,
        public readonly ?string $iconPath,
        public readonly string $currentVersion,
        public readonly ?string $lastPackagedAt,
        public readonly int $updateFrequencyDays,
        public readonly ?string $downloadUrl,
        public readonly ?string $versionCheckUrl,
        public readonly string $detectionMethod,
        public readonly string $architecture,
        public readonly string $installerType,
        public readonly ?string $documentationUrl = null,
        public readonly ?string $lastSyncSha256 = null,
        public readonly ?string $lastPackagedSha256 = null,
        public readonly ?string $lastPackagedVersion = null,
        public readonly ?int $lastPackagedBy = null,
        public readonly ?string $lastSyncAt = null,
        public readonly string $syncStatus = 'never',
        public readonly ?string $pendingInstallerPath = null,
        /** sync = provenant d'une URL ; upload = déposé manuellement (prioritaire) */
        public readonly string $installerSource = 'sync',
        public readonly ?string $renewalNotifiedAt = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly ?string $deletedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            publisher: (string) $row['publisher'],
            category: (string) $row['category'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            iconPath: $row['icon_path'] !== null ? (string) $row['icon_path'] : null,
            currentVersion: (string) $row['current_version'],
            lastPackagedAt: $row['last_packaged_at'] !== null ? (string) $row['last_packaged_at'] : null,
            updateFrequencyDays: (int) $row['update_frequency_days'],
            downloadUrl: $row['download_url'] !== null ? (string) $row['download_url'] : null,
            versionCheckUrl: $row['version_check_url'] !== null ? (string) $row['version_check_url'] : null,
            detectionMethod: (string) $row['detection_method'],
            architecture: (string) $row['architecture'],
            installerType: (string) $row['installer_type'],
            documentationUrl: isset($row['documentation_url']) && $row['documentation_url'] !== null && $row['documentation_url'] !== ''
                ? (string) $row['documentation_url'] : null,
            lastSyncSha256: isset($row['last_sync_sha256']) && $row['last_sync_sha256'] !== null
                ? (string) $row['last_sync_sha256'] : null,
            lastPackagedSha256: isset($row['last_packaged_sha256']) && $row['last_packaged_sha256'] !== null
                ? (string) $row['last_packaged_sha256'] : null,
            lastPackagedVersion: isset($row['last_packaged_version']) && $row['last_packaged_version'] !== null
                && trim((string) $row['last_packaged_version']) !== ''
                ? (string) $row['last_packaged_version'] : null,
            lastPackagedBy: isset($row['last_packaged_by']) && $row['last_packaged_by'] !== null && (int) $row['last_packaged_by'] > 0
                ? (int) $row['last_packaged_by'] : null,
            lastSyncAt: isset($row['last_sync_at']) && $row['last_sync_at'] !== null
                ? (string) $row['last_sync_at'] : null,
            syncStatus: isset($row['sync_status']) ? (string) $row['sync_status'] : 'never',
            pendingInstallerPath: isset($row['pending_installer_path']) && $row['pending_installer_path'] !== null
                ? (string) $row['pending_installer_path'] : null,
            installerSource: isset($row['installer_source']) && in_array((string) $row['installer_source'], ['sync', 'upload'], true)
                ? (string) $row['installer_source']
                : 'sync',
            renewalNotifiedAt: isset($row['renewal_notified_at']) && $row['renewal_notified_at'] !== null
                && trim((string) $row['renewal_notified_at']) !== ''
                ? (string) $row['renewal_notified_at'] : null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
            deletedAt: $row['deleted_at'] ?? null,
        );
    }

    public function hasManualInstaller(): bool
    {
        return $this->installerSource === 'upload'
            && $this->pendingInstallerPath !== null
            && $this->pendingInstallerPath !== '';
    }

    public function isUpdateAvailable(): bool
    {
        return $this->syncStatus === 'update_available';
    }

    /**
     * Packaged version that the current synced version will replace (when an update is pending).
     */
    public function packagedVersionToReplace(): ?string
    {
        if ($this->lastPackagedVersion === null || $this->lastPackagedVersion === '') {
            return null;
        }
        if (self::versionsEquivalent($this->lastPackagedVersion, $this->currentVersion)) {
            return null;
        }

        return $this->lastPackagedVersion;
    }

    /**
     * True when two version labels are the same product version despite formatting
     * (e.g. 4.3.0.008 vs 4.3.0008).
     */
    public static function versionsEquivalent(string $a, string $b): bool
    {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if (strcasecmp($a, $b) === 0) {
            return true;
        }

        $digitsA = preg_replace('/\D+/', '', $a) ?? '';
        $digitsB = preg_replace('/\D+/', '', $b) ?? '';

        return $digitsA !== '' && $digitsA === $digitsB;
    }

    /**
     * ZIP can be generated after at least one successful sync that kept an installer on disk.
     */
    public function canGenerateZip(): bool
    {
        return in_array($this->syncStatus, ['update_available', 'up_to_date'], true)
            || ($this->pendingInstallerPath !== null && $this->pendingInstallerPath !== '');
    }

    public function isExeFamily(): bool
    {
        return in_array($this->installerType, ['exe', 'inno', 'nsis', 'other'], true);
    }

    public function isMsiFamily(): bool
    {
        return in_array($this->installerType, ['msi', 'msix'], true);
    }

    /**
     * Last review for the frequency reminder: most recent sync or packaging.
     */
    public function lastReviewedAt(): ?\DateTimeImmutable
    {
        $best = null;
        foreach ([$this->lastSyncAt, $this->lastPackagedAt] as $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
            if ($parsed === false) {
                continue;
            }
            if ($best === null || $parsed > $best) {
                $best = $parsed;
            }
        }

        return $best;
    }

    /**
     * Whether a renewal reminder mail should still be sent for the current overdue cycle.
     */
    public function needsRenewalReminder(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        if (!$this->isOverdue($now)) {
            return false;
        }

        $reviewed = $this->lastReviewedAt();
        if ($this->renewalNotifiedAt === null || $this->renewalNotifiedAt === '') {
            return true;
        }

        $notified = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->renewalNotifiedAt)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d', $this->renewalNotifiedAt);
        if ($notified === false) {
            return true;
        }

        if ($reviewed === null) {
            return false;
        }

        return $notified < $reviewed;
    }

    /**
     * Whether a review is overdue (last sync or packaging + frequency).
     * Without a download URL, sync cannot run — do not flag as overdue
     * (false positive next to the muted “no URL” sync dot).
     */
    public function isOverdue(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        if (!$this->hasDownloadUrl()) {
            return false;
        }

        $last = $this->lastReviewedAt();
        if ($last === null) {
            return true;
        }

        $due = $last->modify('+' . $this->updateFrequencyDays . ' days');

        return $due < $now;
    }

    public function daysUntilDue(\DateTimeImmutable $now = new \DateTimeImmutable()): ?int
    {
        $last = $this->lastReviewedAt();
        if ($last === null) {
            return null;
        }

        $due = $last->modify('+' . $this->updateFrequencyDays . ' days');
        $diff = $now->diff($due);

        return ($diff->invert ? -1 : 1) * (int) $diff->days;
    }

    public function syncStatusLabel(): string
    {
        return match ($this->syncStatus) {
            'up_to_date' => __('sync_status.up_to_date'),
            'update_available' => __('sync_status.update_available'),
            'error' => __('sync_status.error'),
            default => __('sync_status.never'),
        };
    }

    public function syncStatusBadgeClass(): string
    {
        return match ($this->syncStatus) {
            'up_to_date' => 'text-bg-success',
            'update_available' => 'text-bg-warning text-dark',
            'error' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }

    public function hasDownloadUrl(): bool
    {
        return $this->downloadUrl !== null && trim($this->downloadUrl) !== '';
    }
}
