<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * History of driver URL hash checks.
 */
final class DriverSyncRepository
{
    use JsonTimestamps;

    private JsonCollection $syncs;

    public function __construct(JsonStore $store)
    {
        $this->syncs = $store->collection('driver_syncs');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $now = $this->now();
        $data['created_at'] = $now;

        return $this->syncs->insert($data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historyForDriver(int $driverId, int $limit = 30): array
    {
        $rows = $this->syncs->filter(
            static fn (array $r): bool => (int) ($r['driver_id'] ?? 0) === $driverId
        );
        usort(
            $rows,
            static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''))
        );

        return array_slice($rows, 0, $limit);
    }
}
