<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DriverRepository;
use App\Repositories\SoftwareRepository;

/**
 * Dashboard metrics for software and drivers.
 */
final class DashboardService
{
    public function __construct(
        private readonly SoftwareRepository $softwares,
        private readonly DriverRepository $drivers,
    ) {
    }

    /**
     * @return array{
     *   total_softwares: int,
     *   overdue: list<\App\Models\Software>,
     *   overdue_count: int,
     *   total_drivers: int,
     *   drivers_attention: list<\App\Models\Driver>,
     *   drivers_attention_count: int,
     *   drivers_ok_count: int
     * }
     */
    public function summary(): array
    {
        $overdue = $this->softwares->findOverdue();
        $driversAttention = $this->drivers->findNeedingAttention();
        $totalDrivers = $this->drivers->count();

        return [
            'total_softwares' => $this->softwares->count(),
            'overdue' => $overdue,
            'overdue_count' => count($overdue),
            'total_drivers' => $totalDrivers,
            'drivers_attention' => $driversAttention,
            'drivers_attention_count' => count($driversAttention),
            'drivers_ok_count' => max(0, $totalDrivers - count($driversAttention)),
        ];
    }
}
