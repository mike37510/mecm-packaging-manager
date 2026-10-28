<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Software;
use PHPUnit\Framework\TestCase;

final class SoftwareOverdueTest extends TestCase
{
    public function testNeverPackagedIsOverdue(): void
    {
        $sw = $this->software(null, 30);
        self::assertTrue($sw->isOverdue(new \DateTimeImmutable('2026-08-07')));
    }

    public function testWithinFrequencyIsNotOverdue(): void
    {
        $sw = $this->software('2026-07-20 00:00:00', 30);
        self::assertFalse($sw->isOverdue(new \DateTimeImmutable('2026-08-07')));
    }

    public function testPastFrequencyIsOverdue(): void
    {
        $sw = $this->software('2026-01-01 00:00:00', 30);
        self::assertTrue($sw->isOverdue(new \DateTimeImmutable('2026-08-07')));
    }

    public function testDaysUntilDueNegativeWhenOverdue(): void
    {
        $sw = $this->software('2026-01-01 00:00:00', 30);
        $days = $sw->daysUntilDue(new \DateTimeImmutable('2026-08-07'));
        self::assertNotNull($days);
        self::assertLessThan(0, $days);
    }

    public function testRecentSyncClearsOverdueEvenIfPackagingIsOld(): void
    {
        $sw = $this->software('2026-01-01 00:00:00', 30, '2026-08-06 12:00:00');
        self::assertFalse($sw->isOverdue(new \DateTimeImmutable('2026-08-07')));
    }

    private function software(?string $lastPackagedAt, int $freq, ?string $lastSyncAt = null): Software
    {
        return new Software(
            id: 1,
            name: 'Test',
            publisher: 'Pub',
            category: 'Cat',
            description: null,
            iconPath: null,
            currentVersion: '1.0',
            lastPackagedAt: $lastPackagedAt,
            updateFrequencyDays: $freq,
            downloadUrl: null,
            versionCheckUrl: null,
            detectionMethod: 'fixed_url',
            architecture: 'x64',
            installerType: 'exe',
            lastSyncAt: $lastSyncAt,
        );
    }
}
