<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Software;
use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * Persistence for software catalog entries (JSON file store).
 */
final class SoftwareRepository
{
    use JsonTimestamps;

    private JsonCollection $softwares;

    public function __construct(JsonStore $store)
    {
        $this->softwares = $store->collection('softwares');
    }

    public function findById(int $id): ?Software
    {
        $row = $this->softwares->find($id);
        if ($row === null || !empty($row['deleted_at'])) {
            return null;
        }

        return Software::fromRow($row);
    }

    /**
     * @param array{q?: string, category?: string, publisher?: string, installer_type?: string} $filters
     * @return list<Software>
     */
    public function search(array $filters = []): array
    {
        $rows = $this->softwares->filter(static fn (array $r): bool => empty($r['deleted_at']));

        if (!empty($filters['q'])) {
            $q = mb_strtolower((string) $filters['q']);
            $rows = array_values(array_filter(
                $rows,
                static function (array $r) use ($q): bool {
                    $hay = mb_strtolower(
                        (string) ($r['name'] ?? '') . ' '
                        . (string) ($r['publisher'] ?? '') . ' '
                        . (string) ($r['category'] ?? '')
                    );

                    return str_contains($hay, $q);
                }
            ));
        }

        if (!empty($filters['category'])) {
            $cat = (string) $filters['category'];
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => (string) ($r['category'] ?? '') === $cat
            ));
        }

        if (!empty($filters['publisher'])) {
            $pub = (string) $filters['publisher'];
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => (string) ($r['publisher'] ?? '') === $pub
            ));
        }

        if (!empty($filters['installer_type'])) {
            $type = (string) $filters['installer_type'];
            $rows = array_values(array_filter(
                $rows,
                static fn (array $r): bool => (string) ($r['installer_type'] ?? '') === $type
            ));
        }

        usort(
            $rows,
            static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        return array_map(static fn (array $r) => Software::fromRow($r), $rows);
    }

    /**
     * @return list<Software>
     */
    public function all(): array
    {
        return $this->search();
    }

    public function count(): int
    {
        return count($this->softwares->filter(static fn (array $r): bool => empty($r['deleted_at'])));
    }

    /**
     * Softwares whose last packaging + frequency is before now (or never packaged).
     *
     * @return list<Software>
     */
    public function findOverdue(): array
    {
        $items = array_values(array_filter(
            $this->all(),
            static fn (Software $s): bool => $s->isOverdue()
        ));

        usort($items, static function (Software $a, Software $b): int {
            if ($a->lastPackagedAt === null && $b->lastPackagedAt === null) {
                return 0;
            }
            if ($a->lastPackagedAt === null) {
                return -1;
            }
            if ($b->lastPackagedAt === null) {
                return 1;
            }

            return strcmp($a->lastPackagedAt, $b->lastPackagedAt);
        });

        return $items;
    }

    /**
     * @return list<string>
     */
    public function distinctCategories(): array
    {
        $vals = [];
        foreach ($this->all() as $s) {
            if ($s->category !== '') {
                $vals[$s->category] = true;
            }
        }
        $list = array_keys($vals);
        sort($list, SORT_STRING | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * @return list<string>
     */
    public function distinctPublishers(): array
    {
        $vals = [];
        foreach ($this->all() as $s) {
            if ($s->publisher !== '') {
                $vals[$s->publisher] = true;
            }
        }
        $list = array_keys($vals);
        sort($list, SORT_STRING | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $now = $this->now();
        $row = $this->normalize($data);
        $row['sync_status'] = $data['sync_status'] ?? 'never';
        $row['last_sync_sha256'] = $data['last_sync_sha256'] ?? null;
        $row['last_packaged_sha256'] = $data['last_packaged_sha256'] ?? null;
        $row['last_sync_at'] = $data['last_sync_at'] ?? null;
        $row['pending_installer_path'] = $data['pending_installer_path'] ?? null;
        $row['installer_source'] = $data['installer_source'] ?? 'sync';
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        $row['deleted_at'] = null;

        return $this->softwares->insert($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $existing = $this->softwares->find($id);
        if ($existing === null || !empty($existing['deleted_at'])) {
            return;
        }

        $row = array_merge($existing, $this->normalize($data));
        $row['updated_at'] = $this->now();
        $this->softwares->put($id, $row);
    }

    public function softDelete(int $id): void
    {
        $existing = $this->softwares->find($id);
        if ($existing === null) {
            return;
        }
        $now = $this->now();
        $existing['deleted_at'] = $now;
        $existing['updated_at'] = $now;
        $this->softwares->put($id, $existing);
    }

    /**
     * Met à jour uniquement le type d'installeur (ex. bascule EXE → MSI si MSI officiel trouvé).
     */
    public function updateInstallerType(int $id, string $installerType): void
    {
        $allowed = ['exe', 'msi', 'msix', 'zip', 'inno', 'nsis', 'other'];
        if (!in_array($installerType, $allowed, true)) {
            return;
        }

        $existing = $this->softwares->find($id);
        if ($existing === null || !empty($existing['deleted_at'])) {
            return;
        }
        $existing['installer_type'] = $installerType;
        $existing['updated_at'] = $this->now();
        $this->softwares->put($id, $existing);
    }

    /**
     * Used by SyncRepository for sync / package state patches.
     *
     * @param array<string, mixed> $patch
     */
    public function patch(int $id, array $patch): void
    {
        $existing = $this->softwares->find($id);
        if ($existing === null || !empty($existing['deleted_at'])) {
            return;
        }
        $row = array_merge($existing, $patch);
        $row['id'] = $id;
        $row['updated_at'] = $this->now();
        $this->softwares->put($id, $row);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        return [
            'name' => $data['name'],
            'publisher' => $data['publisher'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'icon_path' => $data['icon_path'] ?? null,
            'current_version' => $data['current_version'] ?? 'unknown',
            'last_packaged_at' => $data['last_packaged_at'] ?? null,
            'update_frequency_days' => (int) ($data['update_frequency_days'] ?? 1),
            'download_url' => $data['download_url'] ?? null,
            'version_check_url' => $data['version_check_url'] ?? null,
            'documentation_url' => $data['documentation_url'] ?? null,
            'detection_method' => $data['detection_method'] ?? 'fixed_url',
            'architecture' => $data['architecture'] ?? 'x64',
            'installer_type' => $data['installer_type'] ?? 'exe',
        ];
    }

    /**
     * Rename a category on every software record that still uses the old label.
     */
    public function renameCategory(string $from, string $to): int
    {
        $from = trim($from);
        $to = trim($to);
        if ($from === '' || $to === '' || $from === $to) {
            return 0;
        }

        $updated = 0;
        foreach ($this->softwares->all() as $row) {
            if (!empty($row['deleted_at'])) {
                continue;
            }
            if ((string) ($row['category'] ?? '') !== $from) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $row['category'] = $to;
            $row['updated_at'] = $this->now();
            $this->softwares->put($id, $row);
            $updated++;
        }

        return $updated;
    }
}
