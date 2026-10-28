<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * Metadata for saved HP BIOS .cfg versions (files live under storage/bios/hp/).
 */
final class BiosHpConfigRepository
{
    use JsonTimestamps;

    private JsonCollection $configs;

    public function __construct(JsonStore $store)
    {
        $this->configs = $store->collection('bios_hp_configs');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allNewestFirst(): array
    {
        $rows = $this->configs->all();
        usort(
            $rows,
            static function (array $a, array $b): int {
                $va = (int) ($a['version'] ?? 0);
                $vb = (int) ($b['version'] ?? 0);
                if ($va !== $vb) {
                    return $vb <=> $va;
                }

                return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
            }
        );

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->configs->find($id);
    }

    public function nextVersion(): int
    {
        $max = 0;
        foreach ($this->configs->all() as $row) {
            $max = max($max, (int) ($row['version'] ?? 0));
        }

        return $max + 1;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insert(array $row): int
    {
        $row['created_at'] = $row['created_at'] ?? $this->now();

        return $this->configs->insert($row);
    }

    public function delete(int $id): void
    {
        $this->configs->delete($id);
    }
}
