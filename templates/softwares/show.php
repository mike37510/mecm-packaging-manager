<?php

use App\Support\View;

/** @var \App\Models\Software $software */
/** @var string $tab */
/** @var \App\Models\PackagingProcedure|null $procedure */
/** @var list<array<string, mixed>> $history */
/** @var list<\App\Models\SoftwareSync> $syncHistory */
/** @var list<\App\Models\PackageExport> $zipHistory */
/** @var list<array<string, mixed>> $vmValidations */
/** @var bool $canGenerateZip */
/** @var bool $vmTestEnabled */
/** @var bool $vtEnabled */
/** @var list<\App\Domain\Procedure\StepType> $stepTypes */
/** @var \App\Security\Csrf $csrf */
/** @var \App\Models\User|null $currentUser */
/** @var string $uploadUrl */
/** @var array<string, mixed> $flash */

$canWrite = $currentUser && $currentUser->canWriteModule(\App\Security\ModuleAccess::MODULE_SOFTWARES);
$isAdmin = $currentUser && $currentUser->role->canManageUsers();
$updateReady = $software->isUpdateAvailable();
$zipHistory = $zipHistory ?? [];
$vmValidations = $vmValidations ?? [];
$canGenerateZip = $canGenerateZip ?? false;
$vmTestEnabled = $vmTestEnabled ?? false;
$vtEnabled = $vtEnabled ?? false;
$pipelineBtnLabel = match (true) {
    $vtEnabled && $vmTestEnabled => __('software.pipeline_full_vt'),
    $vtEnabled => __('software.pipeline_vt'),
    $vmTestEnabled => __('software.pipeline_full'),
    default => __('software.pipeline_short'),
};
$pipelineSubtitle = match (true) {
    $vtEnabled && $vmTestEnabled => __('software.pipeline_subtitle_vt_vm'),
    $vtEnabled => __('software.pipeline_subtitle_vt'),
    $vmTestEnabled => __('software.pipeline_subtitle_vm'),
    default => __('software.pipeline_subtitle_no_vm'),
};
$syncFlowLead = match (true) {
    $vtEnabled && $vmTestEnabled => __('software.sync_flow_lead_vt_vm'),
    $vtEnabled => __('software.sync_flow_lead_vt'),
    $vmTestEnabled => __('software.sync_flow_lead_vm'),
    default => __('software.sync_flow_lead'),
};
?>
<div class="d-flex justify-content-between align-items-start mb-3">
    <div class="d-flex gap-3 align-items-center">
        <?php if ($software->iconPath): ?>
            <img class="software-icon" style="width:48px;height:48px" src="<?= View::e($uploadUrl . '/' . $software->iconPath) ?>" alt="">
        <?php endif; ?>
        <div>
            <h1 class="h3 mb-0"><?= View::e($software->name) ?></h1>
            <div class="text-muted"><?= View::e($software->publisher) ?> · <?= View::e($software->category) ?></div>
            <?php $packagedToReplace = $software->packagedVersionToReplace(); ?>
            <div class="mt-1">
                <?php if ($software->syncStatus === 'update_available'): ?>
                    <span class="badge text-bg-warning"><?= View::e(__('software.badge_update')) ?></span>
                <?php elseif ($software->syncStatus === 'up_to_date'): ?>
                    <span class="badge text-bg-success"><?= View::e(__('software.badge_up_to_date')) ?></span>
                <?php elseif ($software->syncStatus === 'error'): ?>
                    <span class="badge text-bg-danger"><?= View::e(__('software.badge_error')) ?></span>
                <?php else: ?>
                    <span class="badge text-bg-secondary"><?= View::e(__('software.badge_never')) ?></span>
                <?php endif; ?>
                <span class="badge text-bg-light"><?= View::e(strtoupper($software->installerType)) ?></span>
            </div>
            <?php if ($updateReady && $packagedToReplace !== null): ?>
                <div class="small mt-2 text-warning-emphasis">
                    <?= View::e(__('software.version_replaces', [
                        'new' => $software->currentVersion,
                        'old' => $packagedToReplace,
                    ])) ?>
                </div>
            <?php elseif ($updateReady): ?>
                <div class="small mt-2 text-warning-emphasis">
                    <?= View::e(__('software.version_new_pending', ['version' => $software->currentVersion])) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($canWrite): ?>
            <button type="button"
                    class="btn btn-primary pipeline-run-btn"
                    data-software-id="<?= (int) $software->id ?>"
                    data-csrf="<?= View::e($csrf->token()) ?>"
                    data-vm-enabled="<?= $vmTestEnabled ? '1' : '0' ?>"
                    data-vt-enabled="<?= $vtEnabled ? '1' : '0' ?>">
                <?= View::e($pipelineBtnLabel) ?>
            </button>
            <a class="btn btn-outline-primary" href="/softwares/<?= (int) $software->id ?>/edit"><?= View::e(__('common.edit')) ?></a>
            <form method="post" action="/softwares/<?= (int) $software->id ?>/delete" onsubmit="return confirm(<?= json_encode(__('software.confirm_delete'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);">
                <?= $csrf->field() ?>
                <button class="btn btn-outline-danger" type="submit"><?= View::e(__('common.delete')) ?></button>
            </form>
        <?php elseif ($canGenerateZip): ?>
            <a class="btn btn-success" href="/softwares/<?= (int) $software->id ?>/export-zip"
               data-loading="<?= View::e(__('software.zip_generating_long')) ?>"
               data-loading-download><?= View::e(__('software.zip_download')) ?></a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="/softwares/<?= (int) $software->id ?>/export-catalog"
           title="<?= View::e(__('software.export_catalog_help')) ?>"><?= View::e(__('software.export_catalog')) ?></a>
        <a class="btn btn-outline-secondary" href="/softwares"><?= View::e(__('common.back')) ?></a>
    </div>
</div>

<?php if ($canWrite): ?>
    <div id="pipeline-overlay" class="pipeline-overlay" hidden
         role="dialog" aria-modal="true" aria-labelledby="pipeline-title"
         data-software-id="<?= (int) $software->id ?>"
         data-pipeline-start="/softwares/<?= (int) $software->id ?>/pipeline"
         data-pipeline-reload="/softwares/<?= (int) $software->id ?>?tab=sync"
         data-csrf="<?= View::e($csrf->token()) ?>"
         data-vm-enabled="<?= $vmTestEnabled ? '1' : '0' ?>"
         data-vt-enabled="<?= $vtEnabled ? '1' : '0' ?>">
        <div class="pipeline-overlay-card" id="pipeline-panel">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1" id="pipeline-title"><?= View::e(__('software.pipeline_title')) ?></h2>
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
                        <div class="fw-semibold"><?= View::e(__('software.pipeline_step_sync')) ?></div>
                        <div class="small text-muted step-msg"><?= View::e(__('js.waiting')) ?></div>
                    </div>
                    <span class="badge text-bg-secondary step-badge"><?= View::e(__('js.pending')) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start" data-step="vt" <?= $vtEnabled ? '' : 'hidden' ?>>
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold"><?= View::e(__('software.pipeline_step_vt')) ?></div>
                        <div class="small text-muted step-msg"><?= View::e(__('js.waiting')) ?></div>
                    </div>
                    <span class="badge text-bg-secondary step-badge"><?= View::e(__('js.pending')) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start" data-step="zip">
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold"><?= View::e(__('software.pipeline_step_zip')) ?></div>
                        <div class="small text-muted step-msg"><?= View::e(__('js.waiting')) ?></div>
                    </div>
                    <span class="badge text-bg-secondary step-badge"><?= View::e(__('js.pending')) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start" data-step="vm" <?= $vmTestEnabled ? '' : 'hidden' ?>>
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold"><?= View::e(__('software.pipeline_step_vm')) ?></div>
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
                <p class="small mb-3 mb-md-2" id="pipeline-confirm-msg">
                    <?= View::e(__('software.pipeline_confirm_msg')) ?>
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-success btn-sm" id="pipeline-confirm-ok">
                        <?= View::e(__('software.pipeline_confirm_ok')) ?>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="pipeline-confirm-abort">
                        <?= View::e(__('software.pipeline_confirm_abort')) ?>
                    </button>
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

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab === 'info' ? 'active' : '' ?>" href="?tab=info"><?= View::e(__('software.tab_info')) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'procedure' ? 'active' : '' ?>" href="?tab=procedure"><?= View::e(__('software.tab_procedure')) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'sync' ? 'active' : '' ?>" href="?tab=sync"><?= View::e(__('software.tab_sync')) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'deploy' ? 'active' : '' ?>" href="?tab=deploy"><?= View::e(__('software.tab_deploy')) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'history' ? 'active' : '' ?>" href="?tab=history"><?= View::e(__('software.tab_history')) ?></a></li>
</ul>

<?php if ($tab === 'info'): ?>
    <div class="panel">
        <div class="row g-3">
            <?php $packagedToReplace = $packagedToReplace ?? $software->packagedVersionToReplace(); ?>
            <div class="col-md-3">
                <div class="text-muted small">
                    <?= View::e($updateReady ? __('software.info_version_new') : __('software.info_version')) ?>
                </div>
                <code><?= View::e($software->currentVersion) ?></code>
                <?php if ($updateReady): ?>
                    <div class="small text-warning-emphasis mt-1"><?= View::e(__('software.info_version_new_hint')) ?></div>
                <?php endif; ?>
            </div>
            <?php if ($packagedToReplace !== null): ?>
            <div class="col-md-3">
                <div class="text-muted small"><?= View::e(__('software.info_version_packaged')) ?></div>
                <code><?= View::e($packagedToReplace) ?></code>
                <?php if ($updateReady): ?>
                    <div class="small text-muted mt-1"><?= View::e(__('software.info_version_packaged_hint')) ?></div>
                <?php endif; ?>
            </div>
            <?php elseif ($software->lastPackagedVersion !== null && !$updateReady): ?>
            <div class="col-md-3">
                <div class="text-muted small"><?= View::e(__('software.info_version_packaged')) ?></div>
                <code><?= View::e($software->lastPackagedVersion) ?></code>
            </div>
            <?php endif; ?>
            <div class="col-md-3"><div class="text-muted small"><?= View::e(__('software.info_installer')) ?></div><?= View::e(strtoupper($software->installerType)) ?>
                <div class="small text-muted mt-1">
                    <?= View::e($software->isMsiFamily() ? __('software.info_installer_msi') : __('software.info_installer_script')) ?>
                </div>
            </div>
            <div class="col-md-3"><div class="text-muted small"><?= View::e(__('software.info_architecture')) ?></div><?= View::e($software->architecture) ?></div>
            <div class="col-md-3"><div class="text-muted small"><?= View::e(__('software.info_detection')) ?></div><?= View::e($software->detectionMethod) ?></div>
            <div class="col-md-3"><div class="text-muted small"><?= View::e(__('software.info_frequency')) ?></div><?= (int) $software->updateFrequencyDays ?> <?= View::e(__('common.days')) ?></div>
            <div class="col-md-3"><div class="text-muted small"><?= View::e(__('software.info_last_packaged')) ?></div><?= View::e($software->lastPackagedAt ?? __('common.never')) ?></div>
            <div class="col-md-3"><div class="text-muted small"><?= View::e(__('software.info_last_sync')) ?></div><?= View::e($software->lastSyncAt ?? __('common.never')) ?></div>
            <div class="col-md-3"><div class="text-muted small"><?= View::e(__('software.info_sha256')) ?></div>
                <code class="small"><?= View::e($software->lastSyncSha256 ? substr($software->lastSyncSha256, 0, 16) . '…' : __('common.na')) ?></code>
            </div>
            <div class="col-12"><div class="text-muted small"><?= View::e(__('software.info_description')) ?></div><?= nl2br(View::e($software->description ?? __('common.na'))) ?></div>
            <?php
            $isGithub = ($software->detectionMethod === 'github')
                || str_contains((string) ($software->versionCheckUrl ?? ''), 'github.com')
                || str_contains((string) ($software->downloadUrl ?? ''), 'github.com');
            $sourceUrl = $isGithub
                ? (trim((string) ($software->versionCheckUrl ?: $software->downloadUrl)) ?: null)
                : (trim((string) ($software->downloadUrl ?: $software->versionCheckUrl)) ?: null);
            $sourceLabel = $isGithub ? __('software.info_version_check_url') : __('software.info_download_url');
            ?>
            <div class="col-12"><div class="text-muted small"><?= View::e($sourceLabel) ?></div>
                <?php if ($sourceUrl): ?>
                    <a href="<?= View::e($sourceUrl) ?>" target="_blank" rel="noopener"><?= View::e($sourceUrl) ?></a>
                <?php elseif ($isGithub): ?>
                    <?= View::e(__('common.na')) ?>
                <?php else: ?>
                    <span class="text-danger"><?= View::e(__('software.info_download_required')) ?></span>
                <?php endif; ?>
            </div>
            <div class="col-12"><div class="text-muted small"><?= View::e(__('software.info_documentation_url')) ?></div>
                <?php if ($software->documentationUrl): ?>
                    <a href="<?= View::e($software->documentationUrl) ?>" target="_blank" rel="noopener"><?= View::e($software->documentationUrl) ?></a>
                <?php else: ?>
                    <span class="text-muted"><?= View::e(__('software.info_documentation_none')) ?></span>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <div class="text-muted small"><?= View::e(__('software.local_installer')) ?></div>
                <code class="small"><?= View::e($software->pendingInstallerPath ?? __('common.na')) ?></code>
                <?php if ($software->hasManualInstaller()): ?>
                    <span class="badge text-bg-warning ms-1"><?= View::e(__('software.installer_source_upload')) ?></span>
                <?php elseif ($software->pendingInstallerPath): ?>
                    <span class="badge text-bg-secondary ms-1"><?= View::e(__('software.installer_source_sync')) ?></span>
                <?php endif; ?>
            </div>
            <div class="col-12">
                <?php if (!$canGenerateZip && $canWrite): ?>
                    <div class="alert alert-warning py-2 small mb-3">
                        <?= View::e(__('software.installer_upload_info_hint')) ?>
                    </div>
                <?php endif; ?>
                <?php require __DIR__ . '/../partials/installer_upload.php'; ?>
            </div>
            <div class="col-12">
                <div class="text-muted small mb-2"><?= View::e(__('software.icon_section')) ?></div>
                <div class="d-flex flex-wrap align-items-start gap-3">
                    <?php if ($software->iconPath): ?>
                        <img class="software-icon" style="width:96px;height:96px;object-fit:contain"
                             src="<?= View::e($uploadUrl . '/' . $software->iconPath) ?>" alt="<?= View::e(__('software.icon_alt')) ?>">
                    <?php else: ?>
                        <span class="software-icon placeholder" style="width:96px;height:96px"><?= View::e(__('software.icon_none')) ?></span>
                    <?php endif; ?>
                    <?php if ($canWrite): ?>
                        <form method="post" action="/softwares/<?= (int) $software->id ?>/icon" enctype="multipart/form-data"
                              class="flex-grow-1" style="max-width:420px"
                              data-loading="<?= View::e(__('software.icon_uploading')) ?>">
                            <?= $csrf->field() ?>
                            <input class="form-control form-control-sm mb-2" type="file" name="icon" id="sccm_icon" required
                                   accept="image/png,image/jpeg,image/gif,image/webp,image/x-icon,.ico,.png,.jpg">
                            <div class="form-text mb-2">
                                <?= View::e(__('software.icon_upload_help')) ?>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <?= View::e($software->iconPath ? __('software.icon_replace') : __('software.icon_add')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($tab === 'sync'): ?>
    <div class="panel">
        <?php if (!$canGenerateZip && $canWrite): ?>
            <div class="alert alert-warning">
                <?= View::e(__('software.zip_unavailable_hint')) ?>
            </div>
        <?php endif; ?>

        <?php require __DIR__ . '/../partials/installer_upload.php'; ?>

        <h2 class="h5 mb-3"><?= View::e(__('software.sync_flow_title')) ?></h2>
        <p class="text-muted mb-3">
            <?= View::e($syncFlowLead) ?>
        </p>

        <div class="d-flex gap-2 mb-4 flex-wrap">
            <?php if ($canWrite): ?>
                <button type="button" class="btn btn-primary pipeline-run-btn"
                        data-software-id="<?= (int) $software->id ?>"
                        data-csrf="<?= View::e($csrf->token()) ?>"
                        data-vm-enabled="<?= $vmTestEnabled ? '1' : '0' ?>"
                        data-vt-enabled="<?= $vtEnabled ? '1' : '0' ?>">
                    <?= View::e(__('software.pipeline_run')) ?>
                </button>
                <?php if ($canGenerateZip): ?>
                    <a class="btn btn-outline-success" href="/softwares/<?= (int) $software->id ?>/export-zip"
                       data-loading="<?= View::e(__('software.zip_generating')) ?>"
                       data-loading-download><?= View::e(__('software.zip_download_regen')) ?></a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="text-muted small"><?= View::e(__('software.status')) ?></div>
                <strong><?= View::e($software->syncStatusLabel()) ?></strong>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= View::e(__('software.local_installer')) ?></div>
                <code class="small"><?= View::e($software->pendingInstallerPath ?? __('common.na')) ?></code>
                <?php if ($software->hasManualInstaller()): ?>
                    <div class="mt-1"><span class="badge text-bg-warning"><?= View::e(__('software.installer_source_upload')) ?></span></div>
                <?php elseif ($software->pendingInstallerPath): ?>
                    <div class="mt-1"><span class="badge text-bg-secondary"><?= View::e(__('software.installer_source_sync')) ?></span></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= View::e(__('software.sha256')) ?></div>
                <code class="small" style="word-break:break-all"><?= View::e($software->lastSyncSha256 ?? __('common.na')) ?></code>
            </div>
        </div>

        <h3 class="h6 mt-4"><?= View::e(__('software.zip_history_title')) ?></h3>
        <p class="small text-muted mb-2"><?= View::e(__('software.zip_history_lead')) ?></p>
        <?php if ($vmTestEnabled && $canWrite): ?>
            <p class="small text-muted mb-2">
                <?= View::e(__('software.zip_retest_hint')) ?>
            </p>
        <?php endif; ?>
        <?php if ($zipHistory === []): ?>
            <p class="text-muted"><?= View::e(__('software.zip_empty')) ?><?= $canGenerateZip ? View::e(__('software.zip_empty_hint')) : '' ?></p>
        <?php else: ?>
            <div class="table-responsive mb-4">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th><?= View::e(__('software.zip_col_date')) ?></th>
                        <th><?= View::e(__('software.zip_col_version')) ?></th>
                        <th><?= View::e(__('software.zip_col_strategy')) ?></th>
                        <th><?= View::e(__('software.zip_col_sha256')) ?></th>
                        <th><?= View::e(__('software.zip_col_size')) ?></th>
                        <th class="text-end"><?= View::e(__('common.actions')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($zipHistory as $zip): ?>
                        <tr>
                            <td class="small text-muted"><?= View::e((string) $zip->createdAt) ?></td>
                            <td><code><?= View::e($zip->version) ?></code></td>
                            <td class="small"><?= View::e($zip->strategyLabel()) ?></td>
                            <td class="small"><code><?= View::e($zip->sha256 ? substr($zip->sha256, 0, 12) . '…' : __('common.na')) ?></code></td>
                            <td class="small"><?= View::e($zip->sizeLabel()) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-success"
                                   href="/softwares/<?= (int) $software->id ?>/exports/<?= (int) $zip->id ?>/download"
                                   data-loading="<?= View::e(__('software.zip_downloading')) ?>"
                                   data-loading-download><?= View::e(__('common.download')) ?></a>
                                <?php if ($canWrite && $vmTestEnabled): ?>
                                    <form method="post"
                                          action="/softwares/<?= (int) $software->id ?>/exports/<?= (int) $zip->id ?>/test-vm"
                                          class="d-inline"
                                          onsubmit="return confirm(<?= json_encode(__('software.vm_test_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);">
                                        <?= $csrf->field() ?>
                                        <button type="submit"
                                                class="btn btn-sm btn-primary"
                                                data-loading="<?= View::e(__('software.vm_test_loading')) ?>">
                                            <?= View::e(__('software.vm_test')) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($isAdmin): ?>
                                    <form method="post"
                                          action="/softwares/<?= (int) $software->id ?>/exports/<?= (int) $zip->id ?>/delete"
                                          class="d-inline"
                                          onsubmit="return confirm(<?= json_encode(__('software.zip_delete_confirm', ['filename' => $zip->filename]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);">
                                        <?= $csrf->field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><?= View::e(__('common.delete')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($flash['vm_log'])): ?>
            <?php $vmLogOpen = !empty($flash['error']); ?>
            <div class="alert <?= $vmLogOpen ? 'alert-danger' : 'alert-secondary' ?> mb-4">
                <details <?= $vmLogOpen ? 'open' : '' ?>>
                    <summary class="fw-semibold" style="cursor:pointer">
                        <?= View::e($vmLogOpen ? __('software.vm_log_failed') : __('software.vm_log_title')) ?>
                    </summary>
                    <pre class="small bg-body p-3 rounded border mt-2 mb-0" style="max-height:480px;overflow:auto;white-space:pre-wrap"><?= View::e((string) $flash['vm_log']) ?></pre>
                </details>
            </div>
        <?php endif; ?>

        <h3 class="h6 mt-2"><?= View::e(__('software.vm_history_title')) ?></h3>
        <?php if (!$vmTestEnabled): ?>
            <p class="text-muted small">
                <?= View::e(__('software.vm_not_configured')) ?>
                <?php if ($canWrite): ?>
                    — <a href="/account/vm"><?= View::e(__('nav.account_vm')) ?></a>
                <?php endif; ?>
            </p>
        <?php elseif ($vmValidations === []): ?>
            <p class="text-muted"><?= View::e(__('software.vm_empty')) ?></p>
        <?php else: ?>
            <div class="table-responsive mb-4">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th><?= View::e(__('software.vm_col_date')) ?></th>
                        <th><?= View::e(__('software.vm_col_version')) ?></th>
                        <th><?= View::e(__('software.vm_col_status')) ?></th>
                        <th><?= View::e(__('software.vm_col_install')) ?></th>
                        <th><?= View::e(__('software.vm_col_detect')) ?></th>
                        <th><?= View::e(__('software.vm_col_uninstall')) ?></th>
                        <th><?= View::e(__('software.vm_col_witness')) ?></th>
                        <th><?= View::e(__('software.vm_col_message')) ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($vmValidations as $runIdx => $run): ?>
                        <?php
                        $st = (string) ($run['status'] ?? '');
                        $badge = match ($st) {
                            'passed' => 'text-bg-success',
                            'failed' => 'text-bg-danger',
                            'running' => 'text-bg-warning text-dark',
                            default => 'text-bg-secondary',
                        };
                        $detIn = $run['detection_after_install'] ?? null;
                        $detOut = $run['detection_after_uninstall'] ?? null;
                        $presence = static function (mixed $v): string {
                            if ($v === null) {
                                return __('common.na');
                            }

                            return $v ? __('common.present') : __('common.absent');
                        };
                        $runLog = trim((string) ($run['log'] ?? ''));
                        $logId = 'vm-log-' . (int) ($run['id'] ?? $runIdx);
                        $openLog = $runIdx === 0 && $st === 'failed' && $runLog !== '' && empty($flash['vm_log']);
                        ?>
                        <tr>
                            <td class="small text-muted"><?= View::e((string) ($run['created_at'] ?? '')) ?></td>
                            <td><code><?= View::e((string) ($run['version'] ?? '')) ?></code></td>
                            <td><span class="badge <?= $badge ?>"><?= View::e($st) ?></span></td>
                            <td class="small"><code><?= View::e((string) ($run['install_exit'] ?? __('common.na'))) ?></code></td>
                            <td class="small">
                                <?= View::e($presence($detIn)) ?> / <?= View::e($presence($detOut)) ?>
                            </td>
                            <td class="small"><code><?= View::e((string) ($run['uninstall_exit'] ?? __('common.na'))) ?></code></td>
                            <td class="small"><code title="<?= View::e((string) ($run['witness_path'] ?? '')) ?>"><?= View::e(($run['witness_path'] ?? '') !== '' ? basename(str_replace('\\', '/', (string) $run['witness_path'])) : __('common.na')) ?></code></td>
                            <td class="small"><?= View::e((string) ($run['message'] ?? '')) ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($runLog !== ''): ?>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#<?= View::e($logId) ?>"
                                            aria-expanded="<?= $openLog ? 'true' : 'false' ?>"
                                            aria-controls="<?= View::e($logId) ?>">
                                        <?= View::e(__('software.vm_view_log')) ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($canWrite && !empty($run['id'])): ?>
                                    <form method="post"
                                          action="/softwares/<?= (int) $software->id ?>/vm-runs/<?= (int) $run['id'] ?>/delete"
                                          class="d-inline"
                                          onsubmit="return confirm(<?= json_encode(__('software.vm_delete_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);">
                                        <?= $csrf->field() ?>
                                        <button type="submit" class="btn-history-x" title="<?= View::e(__('common.delete')) ?>" aria-label="<?= View::e(__('software.vm_delete_aria')) ?>">×</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($runLog !== ''): ?>
                            <tr class="border-0">
                                <td colspan="9" class="p-0 border-0">
                                    <div id="<?= View::e($logId) ?>" class="collapse<?= $openLog ? ' show' : '' ?>">
                                        <pre class="small bg-body-tertiary p-3 m-2 rounded border" style="max-height:420px;overflow:auto;white-space:pre-wrap"><?= View::e($runLog) ?></pre>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="h6"><?= View::e(__('software.sync_history_title')) ?></h3>
        <?php if ($syncHistory === []): ?>
            <p class="text-muted mb-0"><?= View::e(__('software.sync_history_empty')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th><?= View::e(__('software.sync_col_date')) ?></th>
                        <th><?= View::e(__('software.sync_col_status')) ?></th>
                        <th><?= View::e(__('software.sync_col_file')) ?></th>
                        <th><?= View::e(__('software.sync_col_sha256')) ?></th>
                        <th><?= View::e(__('software.sync_col_message')) ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($syncHistory as $sync): ?>
                        <tr>
                            <td class="small text-muted"><?= View::e((string) $sync->createdAt) ?></td>
                            <td><code><?= View::e($sync->status) ?></code></td>
                            <td class="small"><?= View::e($sync->fileName) ?></td>
                            <td class="small"><code><?= View::e(substr($sync->sha256, 0, 12)) ?>…</code></td>
                            <td class="small"><?= View::e((string) ($sync->message ?? '')) ?></td>
                            <td class="text-end">
                                <?php if ($canWrite): ?>
                                    <form method="post"
                                          action="/softwares/<?= (int) $software->id ?>/syncs/<?= (int) $sync->id ?>/delete"
                                          class="d-inline"
                                          onsubmit="return confirm(<?= json_encode(__('software.sync_delete_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);">
                                        <?= $csrf->field() ?>
                                        <button type="submit" class="btn-history-x" title="<?= View::e(__('common.delete')) ?>" aria-label="<?= View::e(__('software.vm_delete_aria')) ?>">×</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php elseif ($tab === 'procedure'): ?>
    <?php require __DIR__ . '/../procedures/editor.php'; ?>
<?php elseif ($tab === 'deploy'): ?>
    <?php
    /** @var array<string, mixed> $deployForm */
    /** @var \App\Models\DeployProfile|null $deployProfile */
    require __DIR__ . '/../deploy/assistant.php';
    ?>
<?php else: ?>
    <div class="panel">
        <?php if ($history === []): ?>
            <p class="text-muted mb-0"><?= View::e(__('software.history_empty')) ?></p>
        <?php else: ?>
            <table class="table table-sm mb-0">
                <thead><tr><th><?= View::e(__('software.history_col_date')) ?></th><th><?= View::e(__('software.history_col_user')) ?></th><th><?= View::e(__('software.history_col_action')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($history as $row): ?>
                    <tr>
                        <td class="small text-muted"><?= View::e((string) $row['created_at']) ?></td>
                        <td><?= View::e((string) ($row['display_name'] ?? __('common.na'))) ?></td>
                        <td><code><?= View::e((string) $row['action']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>
