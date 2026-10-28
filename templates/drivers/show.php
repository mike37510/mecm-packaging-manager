<?php

use App\Support\View;

/** @var \App\Models\Driver $driver */
/** @var list<array<string, mixed>> $syncHistory */
/** @var bool $vtEnabled */
/** @var \App\Models\User|null $currentUser */
/** @var \App\Security\Csrf $csrf */

$canWrite = $currentUser && $currentUser->canWriteModule(\App\Security\ModuleAccess::MODULE_DRIVERS);
$vtEnabled = $vtEnabled ?? false;
$mfr = __('driver.mfr_' . $driver->manufacturer);
if ($mfr === 'driver.mfr_' . $driver->manufacturer) {
    $mfr = $driver->manufacturer;
}

$statusLabel = match ($driver->syncStatus) {
    'up_to_date' => __('driver.legend_ok'),
    'update_available' => __('driver.legend_ko'),
    'error' => __('driver.legend_err'),
    default => __('driver.legend_muted'),
};
$statusClass = match ($driver->syncStatus) {
    'up_to_date' => 'text-bg-success',
    'update_available' => 'text-bg-danger',
    'error' => 'text-bg-warning text-dark',
    default => 'text-bg-secondary',
};
$pipelineBtnLabel = $vtEnabled ? __('driver.pipeline_vt') : __('driver.pipeline_short');
$pipelineSubtitle = $vtEnabled ? __('driver.pipeline_subtitle_vt') : __('driver.pipeline_subtitle');
$flowLead = $vtEnabled ? __('driver.flow_lead_vt') : __('driver.flow_lead');
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <a href="/drivers" class="small text-muted text-decoration-none"><?= View::e(__('common.back')) ?></a>
        <h1 class="h3 mb-1"><?= View::e($driver->name) ?></h1>
        <p class="text-muted mb-0">
            <?= View::e($mfr) ?>
            <?php if ($driver->model !== ''): ?>
                · <?= View::e($driver->model) ?>
            <?php endif; ?>
            <?php
            $osLabel = \App\Services\HpDriverPackResolver::osLabel($driver->osTarget);
            if ($osLabel === '' && $driver->osTarget === '' && $driver->manufacturer === 'hp') {
                $osLabel = __('driver.os_auto');
            }
            if ($driver->manufacturer === 'hp' && $osLabel !== ''): ?>
                · <?= View::e($osLabel) ?>
            <?php endif; ?>
            · <?= View::e($driver->version) ?>
            · <?= View::e($driver->architecture) ?>
            <span class="badge <?= View::e($statusClass) ?> ms-1"><?= View::e($statusLabel) ?></span>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($canWrite): ?>
            <button type="button"
                    class="btn btn-primary pipeline-run-btn"
                    data-driver-id="<?= (int) $driver->id ?>"
                    data-csrf="<?= View::e($csrf->token()) ?>"
                    data-vm-enabled="0"
                    data-vt-enabled="<?= $vtEnabled ? '1' : '0' ?>">
                <?= View::e($pipelineBtnLabel) ?>
            </button>
            <a href="/drivers/<?= (int) $driver->id ?>/edit" class="btn btn-outline-secondary"><?= View::e(__('common.edit')) ?></a>
        <?php endif; ?>
        <?php if ($driver->hasExport()): ?>
            <a href="/drivers/<?= (int) $driver->id ?>/export-zip" class="btn <?= $canWrite ? 'btn-outline-primary' : 'btn-primary' ?>"><?= View::e(__('driver.download_zip')) ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if ($canWrite): ?>
    <div id="pipeline-overlay" class="pipeline-overlay" hidden
         role="dialog" aria-modal="true" aria-labelledby="pipeline-title"
         data-driver-id="<?= (int) $driver->id ?>"
         data-pipeline-start="/drivers/<?= (int) $driver->id ?>/pipeline"
         data-pipeline-reload="/drivers/<?= (int) $driver->id ?>"
         data-csrf="<?= View::e($csrf->token()) ?>"
         data-vm-enabled="0"
         data-vt-enabled="<?= $vtEnabled ? '1' : '0' ?>">
        <div class="pipeline-overlay-card" id="pipeline-panel">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1" id="pipeline-title"><?= View::e(__('driver.pipeline_title')) ?></h2>
                    <p class="small text-muted mb-0" id="pipeline-subtitle"><?= View::e($pipelineSubtitle) ?></p>
                </div>
                <span id="pipeline-overall" class="badge text-bg-secondary"><?= View::e(__('software.pipeline_waiting')) ?></span>
            </div>
            <div class="text-center mb-3" id="pipeline-spinner" hidden>
                <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
            </div>
            <ol class="list-group list-group-numbered mb-0" id="pipeline-steps">
                <li class="list-group-item d-flex justify-content-between align-items-start" data-step="sync">
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold"><?= View::e(__('driver.pipeline_step_sync')) ?></div>
                        <div class="small text-muted step-msg"><?= View::e(__('js.waiting')) ?></div>
                    </div>
                    <span class="badge text-bg-secondary step-badge"><?= View::e(__('js.pending')) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start" data-step="vt" <?= $vtEnabled ? '' : 'hidden' ?>>
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold"><?= View::e(__('driver.pipeline_step_vt')) ?></div>
                        <div class="small text-muted step-msg"><?= View::e(__('js.waiting')) ?></div>
                    </div>
                    <span class="badge text-bg-secondary step-badge"><?= View::e(__('js.pending')) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start" data-step="zip">
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold"><?= View::e(__('driver.pipeline_step_zip')) ?></div>
                        <div class="small text-muted step-msg"><?= View::e(__('js.waiting')) ?></div>
                    </div>
                    <span class="badge text-bg-secondary step-badge"><?= View::e(__('js.pending')) ?></span>
                </li>
            </ol>
            <details class="mt-3" id="pipeline-log-wrap" hidden open>
                <summary class="small"><?= View::e(__('software.pipeline_log')) ?></summary>
                <pre class="small bg-body p-3 rounded border mt-2 mb-0" id="pipeline-log" style="max-height:360px;overflow:auto;white-space:pre-wrap"></pre>
            </details>
            <div class="alert alert-warning mt-3 mb-0" id="pipeline-confirm-box" hidden>
                <div class="fw-semibold mb-1"><?= View::e(__('software.pipeline_confirm_title')) ?></div>
                <p class="small mb-3 mb-md-2" id="pipeline-confirm-msg"></p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-success btn-sm" id="pipeline-confirm-ok"></button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="pipeline-confirm-abort"></button>
                </div>
            </div>
            <div class="alert alert-warning mt-3 mb-0" id="pipeline-vt-box" hidden>
                <div class="fw-semibold mb-1"><?= View::e(__('software.pipeline_vt_title')) ?></div>
                <p class="small mb-2" id="pipeline-vt-summary"></p>
                <p class="small mb-2" id="pipeline-vt-hint"></p>
                <ul class="small mb-2 ps-3">
                    <li><?= View::e(__('software.pipeline_vt_case_fp')) ?></li>
                    <li><?= View::e(__('software.pipeline_vt_case_name')) ?></li>
                    <li><?= View::e(__('software.pipeline_vt_case_packer')) ?></li>
                    <li><?= View::e(__('software.pipeline_vt_case_malware')) ?></li>
                    <li><?= View::e(__('software.pipeline_vt_case_url')) ?></li>
                </ul>
                <p class="small mb-2" id="pipeline-vt-engines"></p>
                <p class="small mb-3">
                    <a id="pipeline-vt-link" href="#" target="_blank" rel="noopener noreferrer" hidden>
                        <?= View::e(__('software.pipeline_vt_report')) ?>
                    </a>
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-success btn-sm" id="pipeline-vt-ok">
                        <?= View::e(__('software.pipeline_vt_ok')) ?>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="pipeline-vt-abort">
                        <?= View::e(__('software.pipeline_vt_abort')) ?>
                    </button>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3" id="pipeline-actions">
                <button type="button" class="btn btn-outline-secondary" id="pipeline-close-btn" hidden><?= View::e(__('common.close')) ?></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="panel mb-4">
    <h2 class="h6"><?= View::e(__('driver.flow_title')) ?></h2>
    <p class="small text-muted mb-0"><?= View::e($flowLead) ?></p>
</div>

<div class="panel mb-4">
    <dl class="row mb-0">
        <dt class="col-sm-3"><?= View::e(__('driver.catalog_url')) ?></dt>
        <dd class="col-sm-9">
            <?php if ($driver->catalogUrl): ?>
                <a href="<?= View::e($driver->catalogUrl) ?>"><?= View::e($driver->catalogUrl) ?></a>
            <?php else: ?>
                <span class="text-muted">—</span>
            <?php endif; ?>
        </dd>
        <?php if ($driver->manufacturer === 'hp'): ?>
            <dt class="col-sm-3"><?= View::e(__('driver.os_target')) ?></dt>
            <dd class="col-sm-9"><?= View::e($osLabel !== '' ? $osLabel : __('driver.os_auto')) ?></dd>
        <?php endif; ?>
        <dt class="col-sm-3"><?= View::e(__('driver.pack_url')) ?></dt>
        <dd class="col-sm-9">
            <?php if ($driver->downloadUrl !== '' && !\App\Services\DriverService::isVendorCatalogUrl($driver->downloadUrl)): ?>
                <a href="<?= View::e($driver->downloadUrl) ?>"><?= View::e($driver->downloadUrl) ?></a>
            <?php elseif ($driver->downloadUrl !== ''): ?>
                <span class="text-muted"><?= View::e(__('driver.pack_url_pending')) ?></span>
            <?php else: ?>
                <span class="text-muted">—</span>
            <?php endif; ?>
        </dd>
        <dt class="col-sm-3"><?= View::e(__('driver.hash')) ?></dt>
        <dd class="col-sm-9">
            <?php $shownSha = $driver->lastSyncSha256 ?? $driver->expectedSha256; ?>
            <?php if ($shownSha !== null): ?>
                <code class="small"><?= View::e($shownSha) ?></code>
            <?php else: ?>
                <span class="text-muted"><?= View::e(__('driver.hash_unknown')) ?></span>
            <?php endif; ?>
            <div class="form-text mb-0"><?= View::e(__('driver.hash_unknown_help')) ?></div>
        </dd>
        <dt class="col-sm-3"><?= View::e(__('driver.file')) ?></dt>
        <dd class="col-sm-9"><?= View::e($driver->lastSyncFilename ?? '—') ?></dd>
        <dt class="col-sm-3"><?= View::e(__('driver.size')) ?></dt>
        <dd class="col-sm-9">
            <?php if ($driver->lastSyncSize !== null && $driver->lastSyncSize > 0): ?>
                <?= View::e(number_format($driver->lastSyncSize / 1048576, 1, ',', ' ')) ?> Mo
                <span class="text-muted small">(<?= View::e(number_format($driver->lastSyncSize, 0, ',', ' ')) ?> octets)</span>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd>
        <dt class="col-sm-3"><?= View::e(__('driver.last_sync')) ?></dt>
        <dd class="col-sm-9"><?= View::e($driver->lastSyncAt ?? __('common.never')) ?></dd>
        <dt class="col-sm-3"><?= View::e(__('driver.last_zip')) ?></dt>
        <dd class="col-sm-9"><?= View::e($driver->lastExportFilename ?? '—') ?></dd>
        <?php if ($driver->description): ?>
            <dt class="col-sm-3"><?= View::e(__('driver.description')) ?></dt>
            <dd class="col-sm-9"><?= View::e($driver->description) ?></dd>
        <?php endif; ?>
        <?php if ($driver->lastSyncError): ?>
            <dt class="col-sm-3"><?= View::e(__('driver.legend_err')) ?></dt>
            <dd class="col-sm-9 text-danger"><?= View::e($driver->lastSyncError) ?></dd>
        <?php endif; ?>
    </dl>
</div>

<div class="panel mb-4">
    <h2 class="h6"><?= View::e(__('driver.how_title')) ?></h2>
    <p class="small text-muted mb-0"><?= View::e(__('driver.how_body')) ?></p>
</div>

<div class="panel mb-4">
    <h2 class="h6"><?= View::e(__('driver.history')) ?></h2>
    <?php if ($syncHistory === []): ?>
        <p class="text-muted mb-0"><?= View::e(__('driver.history_empty')) ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th><?= View::e(__('driver.col_date')) ?></th>
                    <th><?= View::e(__('driver.col_status')) ?></th>
                    <th><?= View::e(__('driver.col_file')) ?></th>
                    <th><?= View::e(__('driver.col_hash')) ?></th>
                    <th><?= View::e(__('driver.col_message')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($syncHistory as $row): ?>
                    <?php $rowSha = \App\Models\Driver::usableSha256($row['sha256'] ?? null); ?>
                    <tr>
                        <td class="small text-muted"><?= View::e((string) ($row['created_at'] ?? '')) ?></td>
                        <td class="small"><?= View::e((string) ($row['status'] ?? '')) ?></td>
                        <td class="small"><?= View::e((string) ($row['file_name'] ?? '')) ?></td>
                        <td>
                            <?php if ($rowSha !== null): ?>
                                <code class="small"><?= View::e(substr($rowSha, 0, 16)) ?>…</code>
                            <?php else: ?>
                                <span class="text-muted small"><?= View::e(__('driver.hash_unknown')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= View::e((string) ($row['message'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($canWrite): ?>
    <form method="post" action="/drivers/<?= (int) $driver->id ?>/delete"
          onsubmit="return confirm(<?= json_encode(__('driver.delete_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);">
        <?= $csrf->field() ?>
        <button type="submit" class="btn btn-outline-danger btn-sm"><?= View::e(__('common.delete')) ?></button>
    </form>
<?php endif; ?>
