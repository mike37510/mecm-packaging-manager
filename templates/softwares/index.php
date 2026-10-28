<?php

use App\Support\View;

/** @var list<\App\Models\Software> $softwares */
/** @var array{q: string, category: string, publisher: string, installer_type: string} $filters */
/** @var list<string> $categories */
/** @var list<string> $publishers */
/** @var list<string> $installerTypes */
/** @var string $uploadUrl */
/** @var \App\Models\User|null $currentUser */
/** @var \App\Security\Csrf $csrf */

$canWrite = $currentUser && $currentUser->canWriteModule(\App\Security\ModuleAccess::MODULE_SOFTWARES);
$syncableCount = 0;
foreach ($softwares as $s) {
    if ($s->hasDownloadUrl()) {
        $syncableCount++;
    }
}

/**
 * @return array{class: string, title: string}
 */
$syncDot = static function (\App\Models\Software $sw): array {
    if (!$sw->hasDownloadUrl()) {
        return ['class' => 'sync-dot sync-dot-muted', 'title' => __('software.no_download_url')];
    }

    return match ($sw->syncStatus) {
        'up_to_date' => ['class' => 'sync-dot sync-dot-ok', 'title' => __('software.legend_ok')],
        'update_available' => ['class' => 'sync-dot sync-dot-ko', 'title' => __('software.badge_update')],
        'error' => ['class' => 'sync-dot sync-dot-err', 'title' => __('software.badge_error')],
        default => ['class' => 'sync-dot sync-dot-muted', 'title' => __('software.badge_never')],
    };
};
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= View::e(__('software.list_title')) ?></h1>
        <p class="text-muted mb-0"><?= View::e(__('software.list_lead')) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($canWrite && $syncableCount > 0): ?>
            <button type="button"
                    class="btn btn-outline-primary"
                    id="sync-all-btn"
                    data-csrf="<?= View::e($csrf->token()) ?>">
                <?= View::e(__('software.sync_all')) ?>
            </button>
        <?php endif; ?>
        <?php if ($canWrite): ?>
            <a href="/softwares/import" class="btn btn-outline-secondary"><?= View::e(__('software.import_catalog')) ?></a>
            <a href="/softwares/create" class="btn btn-primary"><?= View::e(__('software.add')) ?></a>
        <?php endif; ?>
    </div>
</div>

<div id="sync-list-summary" class="alert alert-info py-2 small" hidden></div>

<div class="panel">
    <form method="get" action="/softwares" id="softwares-filters" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="q"><?= View::e(__('common.search')) ?></label>
            <input class="form-control" type="search" name="q" id="q" value="<?= View::e($filters['q']) ?>" placeholder="<?= View::e(__('software.search_placeholder')) ?>" autocomplete="off">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="category"><?= View::e(__('software.category')) ?></label>
            <select class="form-select" name="category" id="category">
                <option value=""><?= View::e(__('common.all_f')) ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= View::e($cat) ?>" <?= $filters['category'] === $cat ? 'selected' : '' ?>><?= View::e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="publisher"><?= View::e(__('software.publisher')) ?></label>
            <select class="form-select" name="publisher" id="publisher">
                <option value=""><?= View::e(__('common.all')) ?></option>
                <?php foreach ($publishers as $pub): ?>
                    <option value="<?= View::e($pub) ?>" <?= $filters['publisher'] === $pub ? 'selected' : '' ?>><?= View::e($pub) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="installer_type"><?= View::e(__('software.installer')) ?></label>
            <select class="form-select" name="installer_type" id="installer_type">
                <option value=""><?= View::e(__('common.all')) ?></option>
                <?php foreach ($installerTypes as $type): ?>
                    <option value="<?= View::e($type) ?>" <?= $filters['installer_type'] === $type ? 'selected' : '' ?>><?= View::e(strtoupper($type)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <a class="btn btn-link px-0" href="/softwares"><?= View::e(__('common.reset')) ?></a>
        </div>
    </form>
</div>

<div class="panel">
    <?php if ($softwares === []): ?>
        <p class="text-muted mb-0"><?= View::e(__('software.empty')) ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="softwares-table"
                   data-catalog-sync
                   data-sync-url-prefix="/softwares/"
                   data-sync-id-field="softwareId">
                <thead>
                <tr>
                    <th style="width:48px"></th>
                    <th><?= View::e(__('software.name')) ?></th>
                    <th><?= View::e(__('software.publisher')) ?></th>
                    <th><?= View::e(__('software.version')) ?></th>
                    <th><?= View::e(__('software.type')) ?></th>
                    <th><?= View::e(__('software.arch_short')) ?></th>
                    <th title="<?= View::e(__('software.overdue_hint')) ?>"><?= View::e(__('software.last_packaged')) ?></th>
                    <th class="text-center" style="width:72px" title="<?= View::e(__('software.status')) ?>">Sync</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($softwares as $sw): ?>
                    <?php
                    $dot = $syncDot($sw);
                    $overdue = $sw->isOverdue();
                    ?>
                    <tr data-software-id="<?= (int) $sw->id ?>"
                        data-syncable="<?= $sw->hasDownloadUrl() ? '1' : '0' ?>">
                        <td>
                            <?php if ($sw->iconPath): ?>
                                <img class="software-icon" src="<?= View::e($uploadUrl . '/' . $sw->iconPath) ?>" alt="">
                            <?php else: ?>
                                <span class="software-icon placeholder"><?= View::e(__('common.na')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/softwares/<?= (int) $sw->id ?>"><?= View::e($sw->name) ?></a>
                            <div class="text-muted small"><?= View::e($sw->category) ?></div>
                        </td>
                        <td><?= View::e($sw->publisher) ?></td>
                        <td>
                            <code class="software-version"><?= View::e($sw->currentVersion) ?></code>
                            <?php
                            $replaceOld = $sw->isUpdateAvailable() ? $sw->packagedVersionToReplace() : null;
                            if ($replaceOld !== null):
                            ?>
                                <div class="small text-warning-emphasis"><?= View::e(__('software.version_replaces_list', ['old' => $replaceOld])) ?></div>
                            <?php elseif ($sw->isUpdateAvailable()): ?>
                                <div class="small text-warning-emphasis"><?= View::e(__('software.version_new_list')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge text-bg-light badge-installer"><?= View::e(strtoupper($sw->installerType)) ?></span></td>
                        <td><span class="badge text-bg-secondary badge-arch"><?= View::e($sw->architecture) ?></span></td>
                        <td class="small text-muted">
                            <?= View::e($sw->lastPackagedAt ?? __('common.na')) ?>
                            <?php if ($overdue): ?>
                                <span class="software-overdue text-danger" title="<?= View::e(__('software.overdue_hint')) ?>"> · <?= View::e(__('software.overdue_badge')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="sync-cell text-center">
                            <span class="<?= View::e($dot['class']) ?>"
                                  data-status="<?= View::e($sw->syncStatus) ?>"
                                  title="<?= View::e($dot['title']) ?>"
                                  aria-label="<?= View::e($dot['title']) ?>"></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mb-0 mt-3 d-flex flex-wrap gap-3 align-items-center">
            <span><span class="sync-dot sync-dot-ok" aria-hidden="true"></span> <?= View::e(__('software.legend_ok')) ?></span>
            <span><span class="sync-dot sync-dot-ko" aria-hidden="true"></span> <?= View::e(__('software.legend_ko')) ?></span>
            <span><span class="sync-dot sync-dot-err" aria-hidden="true"></span> <?= View::e(__('software.legend_err')) ?></span>
            <span><span class="sync-dot sync-dot-muted" aria-hidden="true"></span> <?= View::e(__('software.legend_muted')) ?></span>
        </p>
        <p class="small text-muted mb-0 mt-3" id="overdue-hint">
            <?= View::e(__('software.overdue_hint')) ?>
            <a href="/docs#status"><?= View::e(__('software.overdue_docs')) ?></a>.
        </p>
    <?php endif; ?>
</div>
