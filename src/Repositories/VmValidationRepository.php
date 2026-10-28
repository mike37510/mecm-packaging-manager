<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * History of remote VM validation runs.
 */
final class VmValidationRepository
{
    use JsonTimestamps;

    private JsonCollection $runs;

    public function __construct(JsonStore $store)
    {
        $this->runs = $store->collection('vm_validations');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return $this->runs->insert(array_merge($data, [
            'created_at' => $this->now(),
        ]));
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function update(int $id, array $patch): void
    {
        $row = $this->runs->find($id);
        if ($row === null) {
            return;
        }
        $this->runs->put($id, array_merge($row, $patch, ['id' => $id]));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->runs->find($id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forSoftware(int $softwareId, int $limit = 30): array
    {
        $rows = $this->runs->filter(
            static fn (array $r): bool => (int) ($r['software_id'] ?? 0) === $softwareId
        );
        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));

            return $cmp !== 0 ? $cmp : ((int) $b['id']) <=> ((int) $a['id']);
        });

        return array_slice($rows, 0, max(0, $limit));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forExport(int $exportId, int $limit = 10): array
    {
        $rows = $this->runs->filter(
            static fn (array $r): bool => (int) ($r['export_id'] ?? 0) === $exportId
        );
        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return array_slice($rows, 0, max(0, $limit));
    }

    public function delete(int $id): void
    {
        $this->runs->delete($id);
    }
}
