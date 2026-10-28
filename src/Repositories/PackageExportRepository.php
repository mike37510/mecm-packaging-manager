<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PackageExport;
use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * Persistence for generated package ZIP history (JSON file store).
 */
final class PackageExportRepository
{
    use JsonTimestamps;

    private JsonCollection $exports;

    public function __construct(JsonStore $store)
    {
        $this->exports = $store->collection('package_exports');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return $this->exports->insert([
            'software_id' => (int) $data['software_id'],
            'version' => (string) $data['version'],
            'sha256' => $data['sha256'] ?? null,
            'strategy' => (string) $data['strategy'],
            'installer_name' => $data['installer_name'] ?? null,
            'filename' => (string) $data['filename'],
            'relative_path' => (string) $data['relative_path'],
            'file_size' => (int) ($data['file_size'] ?? 0),
            'created_by' => $data['created_by'] ?? null,
            'created_at' => $this->now(),
        ]);
    }

    public function findById(int $id): ?PackageExport
    {
        $row = $this->exports->find($id);

        return $row ? PackageExport::fromRow($row) : null;
    }

    /**
     * @return list<PackageExport>
     */
    public function forSoftware(int $softwareId, int $limit = 100): array
    {
        $rows = $this->exports->filter(
            static fn (array $r): bool => (int) ($r['software_id'] ?? 0) === $softwareId
        );
        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));

            return $cmp !== 0 ? $cmp : ((int) $b['id']) <=> ((int) $a['id']);
        });

        return array_map(
            static fn (array $r) => PackageExport::fromRow($r),
            array_slice($rows, 0, max(0, $limit))
        );
    }

    public function latestForSoftware(int $softwareId): ?PackageExport
    {
        $list = $this->forSoftware($softwareId, 1);

        return $list[0] ?? null;
    }

    /**
     * @return list<PackageExport>
     */
    public function forSoftwareAndVersion(int $softwareId, string $version): array
    {
        $needle = $this->normalizeVersion($version);
        $rows = $this->exports->filter(
            function (array $r) use ($softwareId, $needle): bool {
                if ((int) ($r['software_id'] ?? 0) !== $softwareId) {
                    return false;
                }

                return $this->normalizeVersion((string) ($r['version'] ?? '')) === $needle;
            }
        );

        return array_map(
            static fn (array $r) => PackageExport::fromRow($r),
            $rows
        );
    }

    private function normalizeVersion(string $version): string
    {
        return strtolower(trim($version));
    }

    public function delete(int $id): void
    {
        $this->exports->delete($id);
    }
}
