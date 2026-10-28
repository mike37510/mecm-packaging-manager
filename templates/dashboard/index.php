<?php

use App\Support\View;

/** @var array{
 *   total_softwares: int,
 *   overdue: list<\App\Models\Software>,
 *   overdue_count: int,
 *   total_drivers: int,
 *   drivers_attention: list<\App\Models\Driver>,
 *   drivers_attention_count: int,
 *   drivers_ok_count: int
 * } $summary */
$onTrack = max(0, $summary['total_softwares'] - $summary['overdue_count']);

$mfrLabel = static function (string $code): string {
    $key = 'driver.mfr_' . $code;

    return __($key) !== $key ? __($key) : $code;
};
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= View::e(__('dashboard.title')) ?></h1>
        <p class="text-muted mb-0"><?= View::e(__('dashboard.lead')) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/softwares/create" class="btn btn-primary"><?= View::e(__('dashboard.new_software')) ?></a>
        <a href="/drivers/create" class="btn btn-outline-primary"><?= View::e(__('dashboard.new_driver')) ?></a>
    </div>
</div>

<h2 class="h5 mb-3"><?= View::e(__('dashboard.section_software')) ?></h2>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-value"><?= (int) $summary['total_softwares'] ?></div>
            <div class="stat-label"><?= View::e(__('dashboard.stat_total')) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card danger">
            <div class="stat-value"><?= (int) $summary['overdue_count'] ?></div>
            <div class="stat-label"><?= View::e(__('dashboard.stat_overdue')) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card ok">
            <div class="stat-value"><?= (int) $onTrack ?></div>
            <div class="stat-label"><?= View::e(__('dashboard.stat_on_track')) ?></div>
        </div>
    </div>
</div>

<div class="panel mb-4">
    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
        <h3 class="h5 mb-0"><?= View::e(__('dashboard.overdue')) ?></h3>
        <a href="/softwares" class="small"><?= View::e(__('dashboard.see_all')) ?></a>
    </div>
    <p class="small text-muted mb-3"><?= View::e(__('dashboard.overdue_hint')) ?></p>
    <?php if ($summary['overdue'] === []): ?>
        <p class="text-muted mb-0"><?= View::e(__('dashboard.no_overdue')) ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th><?= View::e(__('dashboard.col_software')) ?></th>
                    <th><?= View::e(__('dashboard.col_version')) ?></th>
                    <th><?= View::e(__('dashboard.col_sync')) ?></th>
                    <th><?= View::e(__('dashboard.col_last_packaged')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($summary['overdue'], 0, 10) as $sw): ?>
                    <tr>
                        <td><a href="/softwares/<?= (int) $sw->id ?>"><?= View::e($sw->name) ?></a></td>
                        <td><code><?= View::e($sw->currentVersion) ?></code></td>
                        <td class="small"><?= View::e($sw->syncStatusLabel()) ?></td>
                        <td class="text-muted small"><?= View::e($sw->lastPackagedAt ?? __('common.never')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<h2 class="h5 mb-3"><?= View::e(__('dashboard.section_drivers')) ?></h2>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-value"><?= (int) $summary['total_drivers'] ?></div>
            <div class="stat-label"><?= View::e(__('dashboard.stat_drivers_total')) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card danger">
            <div class="stat-value"><?= (int) $summary['drivers_attention_count'] ?></div>
            <div class="stat-label"><?= View::e(__('dashboard.stat_drivers_attention')) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card ok">
            <div class="stat-value"><?= (int) $summary['drivers_ok_count'] ?></div>
            <div class="stat-label"><?= View::e(__('dashboard.stat_drivers_ok')) ?></div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
        <h3 class="h5 mb-0"><?= View::e(__('dashboard.drivers_attention')) ?></h3>
        <a href="/drivers" class="small"><?= View::e(__('dashboard.see_all')) ?></a>
    </div>
    <p class="small text-muted mb-3"><?= View::e(__('dashboard.drivers_attention_hint')) ?></p>
    <?php if ($summary['drivers_attention'] === []): ?>
        <p class="text-muted mb-0"><?= View::e(__('dashboard.no_drivers_attention')) ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th><?= View::e(__('dashboard.col_driver')) ?></th>
                    <th><?= View::e(__('dashboard.col_manufacturer')) ?></th>
                    <th><?= View::e(__('dashboard.col_version')) ?></th>
                    <th><?= View::e(__('dashboard.col_status')) ?></th>
                    <th><?= View::e(__('dashboard.col_last_check')) ?></th>
                    <th><?= View::e(__('dashboard.col_last_zip')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($summary['drivers_attention'], 0, 10) as $driver): ?>
                    <tr>
                        <td><a href="/drivers/<?= (int) $driver->id ?>"><?= View::e($driver->name) ?></a></td>
                        <td><?= View::e($mfrLabel($driver->manufacturer)) ?></td>
                        <td><code><?= View::e($driver->version !== '' ? $driver->version : '—') ?></code></td>
                        <td class="small"><?= View::e($driver->syncStatusLabel()) ?></td>
                        <td class="text-muted small"><?= View::e($driver->lastSyncAt ?? __('common.never')) ?></td>
                        <td class="text-muted small"><?= View::e($driver->lastExportFilename ?? __('common.never')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
