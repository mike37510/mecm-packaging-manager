<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Driver;
use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * Persistence for driver catalog entries.
 */
final class DriverRepository
{
    use JsonTimestamps;

    private JsonCollection $drivers;

    public function __construct(JsonStore $store)
    {
        $this->drivers = $store->collection('drivers');
    }

    public function findById(int $id): ?Driver
    {
        $row = $this->drivers->find($id);

        return $row ? Driver::fromRow($row) : null;
    }

    /**
     * @param array{q?: string, manufacturer?: string, sync_status?: string} $filters
     * @return list<Driver>
     */
    public function search(array $filters = []): array
    {
        $rows = $this->drivers->all();

        if (!empty($filters['q'])) {
            $q = mb_strtolower((string) $filters['q']);
            $rows = array_values(array_filter(
                $rows,
                static function (array $r) use ($q): bool {
                    $hay = mb_strtolower(
                        (string) ($r['name'] ?? '') . ' '
                        . (string) ($r['manufacturer'] ?? '') . ' '
                        . (string) ($r['model'] ?? '')
                    );

                    return str_contains($hay, $q);
                }
            ));
        }

        if (!empty($filters['manufacturer'])) {
            $mfr = (string) $filters['manufacturer'];
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => (string) ($r['manufacturer'] ?? '') === $mfr
            ));
        }

        if (!empty($filters['sync_status'])) {
            $st = (string) $filters['sync_status'];
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => (string) ($r['sync_status'] ?? 'never') === $st
            ));
        }

        usort(
            $rows,
            static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        return array_map(static fn (array $r) => Driver::fromRow($r), $rows);
    }

    public function count(): int
    {
        return $this->drivers->count();
    }

    /**
     * Drivers that still need a pipeline run (never checked, new version, or last check failed).
     *
     * @return list<Driver>
     */
    public function findNeedingAttention(): array
    {
        $items = array_values(array_filter(
            $this->search(),
            static fn (Driver $d): bool => in_array($d->syncStatus, ['never', 'update_available', 'error'], true)
        ));

        $rank = static fn (string $status): int => match ($status) {
            'error' => 0,
            'update_available' => 1,
            default => 2,
        };

        usort($items, static function (Driver $a, Driver $b) use ($rank): int {
            $byStatus = $rank($a->syncStatus) <=> $rank($b->syncStatus);
            if ($byStatus !== 0) {
                return $byStatus;
            }

            return strcasecmp($a->name, $b->name);
        });

        return $items;
    }

    /**
     * @return list<string>
     */
    public function usedManufacturers(): array
    {
        $set = [];
        foreach ($this->drivers->all() as $row) {
            $mfr = trim((string) ($row['manufacturer'] ?? ''));
            if ($mfr !== '') {
                $set[$mfr] = true;
            }
        }
        $list = array_keys($set);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $now = $this->now();
        $row = $this->normalize($data);
        $row['created_at'] = $now;
        $row['updated_at'] = $now;

        return $this->drivers->insert($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $existing = $this->drivers->find($id);
        if ($existing === null) {
            return;
        }

        $row = array_merge($existing, $this->normalize($data));
        $row['id'] = $id;
        $row['updated_at'] = $this->now();
        $this->drivers->put($id, $row);
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function patch(int $id, array $patch): void
    {
        $existing = $this->drivers->find($id);
        if ($existing === null) {
            return;
        }
        $row = array_merge($existing, $patch);
        $row['id'] = $id;
        $row['updated_at'] = $this->now();
        $this->drivers->put($id, $row);
    }

    public function delete(int $id): void
    {
        $this->drivers->delete($id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'manufacturer' => trim((string) ($data['manufacturer'] ?? '')),
            'model' => trim((string) ($data['model'] ?? '')),
            'version' => trim((string) ($data['version'] ?? '')),
            'architecture' => trim((string) ($data['architecture'] ?? 'x64')),
            'os_target' => trim((string) ($data['os_target'] ?? '')),
            'download_url' => trim((string) ($data['download_url'] ?? '')),
            'catalog_url' => trim((string) ($data['catalog_url'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'expected_sha256' => $data['expected_sha256'] ?? null,
            'last_sync_sha256' => $data['last_sync_sha256'] ?? null,
            'last_packaged_sha256' => $data['last_packaged_sha256'] ?? null,
            'last_sync_at' => $data['last_sync_at'] ?? null,
            'last_packaged_at' => $data['last_packaged_at'] ?? null,
            'last_sync_filename' => $data['last_sync_filename'] ?? null,
            'last_sync_size' => $data['last_sync_size'] ?? null,
            'last_sync_error' => $data['last_sync_error'] ?? null,
            'last_sync_fingerprint' => $data['last_sync_fingerprint'] ?? null,
            'last_packaged_fingerprint' => $data['last_packaged_fingerprint'] ?? null,
            'sync_status' => $data['sync_status'] ?? 'never',
            'last_export_path' => $data['last_export_path'] ?? null,
            'last_export_filename' => $data['last_export_filename'] ?? null,
        ];
    }
}
