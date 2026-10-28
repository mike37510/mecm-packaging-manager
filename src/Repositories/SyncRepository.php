<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SoftwareSync;
use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * Persistence for installer sync checks (JSON file store).
 */
final class SyncRepository
{
    use JsonTimestamps;

    private JsonCollection $syncs;
    private SoftwareRepository $softwares;

    public function __construct(
        JsonStore $store,
        SoftwareRepository $softwares,
    ) {
        $this->syncs = $store->collection('software_syncs');
        $this->softwares = $softwares;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return $this->syncs->insert([
            'software_id' => (int) $data['software_id'],
            'source_url' => (string) $data['source_url'],
            'file_name' => (string) $data['file_name'],
            'local_path' => $data['local_path'] ?? null,
            'sha256' => (string) $data['sha256'],
            'file_size' => (int) ($data['file_size'] ?? 0),
            'previous_sha256' => $data['previous_sha256'] ?? null,
            'status' => (string) $data['status'],
            'message' => $data['message'] ?? null,
            'created_at' => $this->now(),
        ]);
    }

    public function latestForSoftware(int $softwareId): ?SoftwareSync
    {
        $rows = $this->syncs->filter(
            static fn (array $r): bool => (int) ($r['software_id'] ?? 0) === $softwareId
        );
        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));

            return $cmp !== 0 ? $cmp : ((int) $b['id']) <=> ((int) $a['id']);
        });
        $row = $rows[0] ?? null;

        return $row ? SoftwareSync::fromRow($row) : null;
    }

    /**
     * @return list<SoftwareSync>
     */
    public function historyForSoftware(int $softwareId, int $limit = 20): array
    {
        $rows = $this->syncs->filter(
            static fn (array $r): bool => (int) ($r['software_id'] ?? 0) === $softwareId
        );
        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));

            return $cmp !== 0 ? $cmp : ((int) $b['id']) <=> ((int) $a['id']);
        });

        return array_map(
            static fn (array $r) => SoftwareSync::fromRow($r),
            array_slice($rows, 0, max(0, $limit))
        );
    }

    public function updateSoftwareSyncState(
        int $softwareId,
        string $status,
        ?string $sha256,
        ?string $pendingPath,
        ?string $remoteVersion = null,
    ): void {
        $software = $this->softwares->findById($softwareId);
        if ($software === null) {
            return;
        }

        $patch = [
            'sync_status' => $status,
            'last_sync_at' => $this->now(),
            'pending_installer_path' => $pendingPath,
        ];
        if ($sha256 !== null && $sha256 !== '') {
            $patch['last_sync_sha256'] = $sha256;
        }
        if ($remoteVersion !== null && trim($remoteVersion) !== '') {
            $patch['current_version'] = trim($remoteVersion);
        }

        $this->softwares->patch($softwareId, $patch);
    }

    public function markPackaged(
        int $softwareId,
        ?string $sha256 = null,
        ?string $installerPath = null,
        ?int $packagedBy = null,
    ): void {
        $software = $this->softwares->findById($softwareId);
        if ($software === null) {
            return;
        }

        $patch = [
            'last_packaged_at' => $this->now(),
            'sync_status' => 'up_to_date',
            'last_packaged_version' => $software->currentVersion,
            'renewal_notified_at' => null,
        ];
        if ($sha256 !== null && $sha256 !== '') {
            $patch['last_packaged_sha256'] = $sha256;
        } elseif ($software->lastSyncSha256 !== null) {
            $patch['last_packaged_sha256'] = $software->lastSyncSha256;
        }
        if ($installerPath !== null && $installerPath !== '') {
            $patch['pending_installer_path'] = $installerPath;
        }
        if ($packagedBy !== null && $packagedBy > 0) {
            $patch['last_packaged_by'] = $packagedBy;
        }

        $this->softwares->patch($softwareId, $patch);
    }

    public function findById(int $id): ?SoftwareSync
    {
        $row = $this->syncs->find($id);

        return $row ? SoftwareSync::fromRow($row) : null;
    }

    public function delete(int $id): void
    {
        $this->syncs->delete($id);
    }
}
