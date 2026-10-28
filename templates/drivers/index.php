<?php

use App\Support\View;

/** @var list<\App\Models\Driver> $drivers */
/** @var array{q: string, manufacturer: string, sync_status: string} $filters */
/** @var list<string> $manufacturers */
/** @var \App\Models\User|null $currentUser */
/** @var \App\Security\Csrf $csrf */

$canWrite = $currentUser && $currentUser->canWriteModule(\App\Security\ModuleAccess::MODULE_DRIVERS);
$syncableCount = 0;
foreach ($drivers as $d) {
    if ($d->hasDownloadUrl()) {
        $syncableCount++;
    }
}

$mfrLabel = static function (string $code): string {
    $key = 'driver.mfr_' . $code;

    return __($key) !== $key ? __($key) : $code;
};

$syncDot = static function (\App\Models\Driver $d): array {
    if (!$d->hasDownloadUrl()) {
        return ['class' => 'sync-dot sync-dot-muted', 'title' => __('driver.legend_muted')];
    }

    return match ($d->syncStatus) {
        'up_to_date' => ['class' => 'sync-dot sync-dot-ok', 'title' => __('driver.legend_ok')],
        'update_available' => ['class' => 'sync-dot sync-dot-ko', 'title' => __('driver.legend_ko')],
        'error' => ['class' => 'sync-dot sync-dot-err', 'title' => __('driver.legend_err')],
        default => ['class' => 'sync-dot sync-dot-muted', 'title' => __('driver.legend_muted')],
    };
};
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">
            <?= View::e(__('driver.list_title')) ?>
            <span class="badge text-bg-warning text-dark align-middle ms-1"><?= View::e(__('driver.list_beta')) ?></span>
        </h1>
        <p class="text-muted mb-0"><?= View::e(__('driver.list_lead')) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($canWrite && $syncableCount > 0): ?>
            <button type="button"
                    class="btn btn-outline-primary"
                    id="sync-all-btn"
                    data-csrf="<?= View::e($csrf->token()) ?>"
                    data-sync-none="<?= View::e(__('driver.sync_none')) ?>"
                    data-sync-finished="<?= View::e(__('driver.sync_finished')) ?>"
                    data-list-missing="<?= View::e(__('driver.sync_list_missing')) ?>">
                <?= View::e(__('driver.sync_all')) ?>
            </button>
        <?php endif; ?>
        <?php if ($canWrite): ?>
            <a href="/drivers/create" class="btn btn-primary"><?= View::e(__('driver.add')) ?></a>
        <?php endif; ?>
    </div>
</div>

<div id="sync-list-summary" class="alert alert-info py-2 small" hidden></div>

<div class="panel">
    <form method="get" action="/drivers" id="drivers-filters" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="q"><?= View::e(__('common.search')) ?></label>
            <input class="form-control" type="search" name="q" id="q" value="<?= View::e($filters['q']) ?>"
                   placeholder="<?= View::e(__('driver.search_placeholder')) ?>" autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="manufacturer"><?= View::e(__('driver.filter_manufacturer')) ?></label>
            <select class="form-select" name="manufacturer" id="manufacturer">
                <option value=""><?= View::e(__('common.all')) ?></option>
                <?php foreach ($manufacturers as $code): ?>
                    <option value="<?= View::e($code) ?>" <?= $filters['manufacturer'] === $code ? 'selected' : '' ?>>
                        <?= View::e($mfrLabel($code)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <a class="btn btn-link px-0" href="/drivers"><?= View::e(__('common.reset')) ?></a>
        </div>
    </form>

    <?php if ($drivers === []): ?>
        <p class="text-muted mt-4 mb-0"><?= View::e(__('driver.empty')) ?></p>
    <?php else: ?>
        <div class="table-responsive mt-4">
            <table class="table align-middle mb-0" id="drivers-table"
                   data-catalog-sync
                   data-sync-url-prefix="/drivers/"
                   data-sync-id-field="driverId">
                <thead>
                <tr>
                    <th><?= View::e(__('driver.name')) ?></th>
                    <th><?= View::e(__('driver.manufacturer')) ?></th>
                    <th><?= View::e(__('driver.model')) ?></th>
                    <th><?= View::e(__('driver.version')) ?></th>
                    <th class="text-center"><?= View::e(__('driver.col_status')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($drivers as $d): ?>
                    <?php $dot = $syncDot($d); ?>
                    <tr data-driver-id="<?= (int) $d->id ?>"
                        data-syncable="<?= $d->hasDownloadUrl() ? '1' : '0' ?>">
                        <td>
                            <a href="/drivers/<?= (int) $d->id ?>"><?= View::e($d->name) ?></a>
                        </td>
                        <td><?= View::e($mfrLabel($d->manufacturer)) ?></td>
                        <td class="text-muted"><?= View::e($d->model !== '' ? $d->model : '—') ?></td>
                        <td><code class="driver-version"><?= View::e($d->version !== '' ? $d->version : '—') ?></code></td>
                        <td class="sync-cell text-center">
                            <span class="<?= View::e($dot['class']) ?>"
                                  data-status="<?= View::e($d->syncStatus) ?>"
                                  title="<?= View::e($dot['title']) ?>"
                                  aria-label="<?= View::e($dot['title']) ?>"></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mb-0 mt-3 d-flex flex-wrap gap-3 align-items-center">
            <span><span class="sync-dot sync-dot-ok" aria-hidden="true"></span> <?= View::e(__('driver.legend_ok')) ?></span>
            <span><span class="sync-dot sync-dot-ko" aria-hidden="true"></span> <?= View::e(__('driver.legend_ko')) ?></span>
            <span><span class="sync-dot sync-dot-err" aria-hidden="true"></span> <?= View::e(__('driver.legend_err')) ?></span>
            <span><span class="sync-dot sync-dot-muted" aria-hidden="true"></span> <?= View::e(__('driver.legend_muted')) ?></span>
        </p>
    <?php endif; ?>
</div>
