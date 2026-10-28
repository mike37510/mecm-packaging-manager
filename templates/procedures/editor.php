<?php

use App\Support\View;

/** @var \App\Models\Software $software */
/** @var \App\Models\PackagingProcedure|null $procedure */
/** @var list<\App\Domain\Procedure\StepType> $stepTypes */
/** @var \App\Security\Csrf $csrf */
/** @var bool $canWrite */
/** @var array<string, mixed> $wizardDefaults */
/** @var list<string> $detectionMethods */
/** @var list<string> $architectures */
/** @var list<string> $installerTypes */

$wizardDefaults = $wizardDefaults ?? [];
$detectionMethods = $detectionMethods ?? \App\Services\SoftwareService::DETECTION_METHODS;
$architectures = $architectures ?? \App\Services\SoftwareService::ARCHITECTURES;
$installerTypes = $installerTypes ?? \App\Services\SoftwareService::INSTALLER_TYPES;
$detectionLabels = [
    'fixed_url' => __('software.detection_fixed_url'),
    'github' => __('software.detection_github'),
];
$isEmpty = $procedure !== null && $procedure->steps === [];
$wizardInstallerType = (string) ($wizardDefaults['installer_type'] ?? $software->installerType);
$wizardIsMsi = in_array($wizardInstallerType, ['msi', 'msix'], true);
$wizardDetection = (string) ($wizardDefaults['detection_method'] ?? $software->detectionMethod);
$wizardHasInstaller = $software->canGenerateZip();
?>
<?php if ($procedure === null): ?>
    <div class="panel"><p class="text-muted mb-0"><?= View::e(__('procedure.unavailable')) ?></p></div>
<?php else: ?>

<?php if ($canWrite): ?>
<div id="procedure-wizard" class="pipeline-overlay procedure-wizard" hidden
     role="dialog" aria-modal="true" aria-labelledby="pw-title"
     data-software-id="<?= (int) $software->id ?>"
     data-csrf="<?= View::e($csrf->token()) ?>"
             data-has-steps="<?= $isEmpty ? '0' : '1' ?>"
             data-has-installer="<?= $wizardHasInstaller ? '1' : '0' ?>"
     data-defaults="<?= View::e(json_encode($wizardDefaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
     data-strategy-msi="<?= View::e(__('procedure.wizard_strategy_msi')) ?>"
     data-strategy-exe="<?= View::e(__('procedure.wizard_strategy_exe')) ?>"
     data-strategy-msi-help="<?= View::e(__('procedure.wizard_strategy_msi_help')) ?>"
     data-strategy-exe-help="<?= View::e(__('procedure.wizard_strategy_exe_help')) ?>">
    <div class="pipeline-overlay-card procedure-wizard-card">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1" id="pw-title"><?= View::e(__('procedure.wizard_title')) ?></h2>
                <p class="small text-muted mb-0" id="pw-subtitle"><?= View::e(__('procedure.wizard_subtitle')) ?></p>
            </div>
            <button type="button" class="btn-close" id="pw-close-x" aria-label="<?= View::e(__('common.close')) ?>"></button>
        </div>

        <ol class="list-group list-group-horizontal-md mb-3 pw-progress" id="pw-progress">
            <li class="list-group-item flex-fill text-center active" data-pw-step="0"><?= View::e(__('procedure.wizard_nav_packaging')) ?></li>
            <li class="list-group-item flex-fill text-center" data-pw-step="1"><?= View::e(__('procedure.wizard_nav_intro')) ?></li>
            <li class="list-group-item flex-fill text-center" data-pw-step="2"><?= View::e(__('procedure.wizard_nav_install')) ?></li>
            <li class="list-group-item flex-fill text-center" data-pw-step="3"><?= View::e(__('procedure.wizard_nav_uninstall')) ?></li>
            <li class="list-group-item flex-fill text-center" data-pw-step="4"><?= View::e(__('procedure.wizard_nav_detect')) ?></li>
            <li class="list-group-item flex-fill text-center" data-pw-step="5"><?= View::e(__('procedure.wizard_nav_review')) ?></li>
        </ol>

        <div class="alert alert-danger py-2 small" id="pw-error" hidden></div>

        <div class="pw-pane" data-pane="0">
            <h3 class="h6"><?= View::e(__('procedure.wizard_packaging_title')) ?></h3>
            <p class="small text-muted"><?= View::e(__('procedure.wizard_packaging_lead')) ?></p>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="pw_detection_method"><?= View::e(__('software.detection_method')) ?> <?= View::e(__('common.required_mark')) ?></label>
                    <select class="form-select" id="pw_detection_method" required>
                        <?php foreach ($detectionMethods as $m):
                            $label = $detectionLabels[$m] ?? $m;
                            $selected = ($wizardDefaults['detection_method'] ?? $software->detectionMethod) === $m;
                        ?>
                            <option value="<?= View::e($m) ?>" <?= $selected ? 'selected' : '' ?>><?= View::e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        <?= View::e(__('software.detection_help')) ?>
                        <a href="/docs#detection" target="_blank" rel="noopener"><?= View::e(__('software.detection_docs_link')) ?></a>
                    </div>
                </div>
                <div class="col-md-8" data-url-for="fixed_url"<?= $wizardDetection === 'github' ? ' hidden' : '' ?>>
                    <label class="form-label" for="pw_download_url"><?= View::e(__('software.download_url')) ?> <?= View::e(__('common.required_mark')) ?></label>
                    <input class="form-control" type="url" id="pw_download_url"
                           value="<?= View::e((string) ($wizardDefaults['download_url'] ?? '')) ?>"
                           placeholder="https://example.com/setup.msi">
                    <div class="form-text"><?= View::e(__('software.download_url_help')) ?></div>
                </div>
                <div class="col-md-8" data-url-for="github"<?= $wizardDetection === 'github' ? '' : ' hidden' ?>>
                    <label class="form-label" for="pw_version_check_url"><?= View::e(__('software.version_check_url')) ?> <?= View::e(__('common.required_mark')) ?></label>
                    <input class="form-control" type="url" id="pw_version_check_url"
                           value="<?= View::e((string) ($wizardDefaults['version_check_url'] ?? '')) ?>"
                           placeholder="https://github.com/owner/repo/releases">
                    <div class="form-text"><?= View::e(__('software.version_check_url_help')) ?></div>
                </div>
                <div class="col-12">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-primary" id="pw-probe-btn">
                            <?= View::e(__('procedure.wizard_probe')) ?>
                        </button>
                        <button type="button" class="btn btn-link btn-sm px-0" id="pw-skip-probe">
                            <?= View::e(__('procedure.wizard_skip_probe')) ?>
                        </button>
                    </div>
                    <div class="small text-muted mt-1" id="pw-probe-status"><?= View::e(__('procedure.wizard_probe_hint')) ?></div>
                    <div class="alert alert-success py-2 small mt-2 mb-0" id="pw-probe-ok" hidden></div>
                </div>
            </div>
            <div class="row g-3 mt-1" id="pw-after-probe"<?= $wizardHasInstaller ? '' : ' hidden' ?>>
                <div class="col-12">
                    <h4 class="h6 mb-0"><?= View::e(__('procedure.wizard_after_probe_title')) ?></h4>
                    <p class="small text-muted mb-0"><?= View::e(__('procedure.wizard_packaging_next')) ?></p>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="pw_installer_type"><?= View::e(__('software.installer_type')) ?></label>
                    <select class="form-select" id="pw_installer_type" required>
                        <?php foreach ($installerTypes as $t): ?>
                            <option value="<?= View::e($t) ?>" <?= $wizardInstallerType === $t ? 'selected' : '' ?>><?= View::e(strtoupper($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="pw_architecture"><?= View::e(__('software.architecture')) ?></label>
                    <select class="form-select" id="pw_architecture" required>
                        <?php foreach ($architectures as $a): ?>
                            <option value="<?= View::e($a) ?>" <?= ($wizardDefaults['architecture'] ?? $software->architecture) === $a ? 'selected' : '' ?>><?= View::e($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="pw_icon"><?= View::e(__('software.icon')) ?> <span class="text-muted">(<?= View::e(__('common.optional')) ?>)</span></label>
                    <input class="form-control" type="file" id="pw_icon"
                           accept="image/png,image/jpeg,image/gif,image/webp,image/x-icon,.ico,.png,.jpg,.jpeg">
                    <div class="form-text"><?= View::e(__('software.icon_help')) ?></div>
                </div>
            </div>
        </div>

        <div class="pw-pane" data-pane="1" hidden>
            <h3 class="h6"><?= View::e(__('procedure.wizard_intro_title')) ?></h3>
            <p class="small"><?= View::e(__('procedure.wizard_intro_lead')) ?></p>
            <div class="border rounded p-3 mb-3 bg-body-tertiary">
                <div class="small text-muted mb-1"><?= View::e(__('procedure.wizard_strategy_label')) ?></div>
                <strong id="pw-strategy-label"><?= View::e($wizardIsMsi ? __('procedure.wizard_strategy_msi') : __('procedure.wizard_strategy_exe')) ?></strong>
                <div class="small text-muted mt-2" id="pw-strategy-help">
                    <?= View::e($wizardIsMsi ? __('procedure.wizard_strategy_msi_help') : __('procedure.wizard_strategy_exe_help')) ?>
                </div>
                <div class="small mt-2">
                    <?= View::e(__('software.info_installer')) ?> :
                    <code id="pw-strategy-type"><?= View::e(strtoupper($wizardInstallerType)) ?></code>
                    · <?= View::e(__('procedure.wizard_installer_file')) ?> :
                    <code id="pw-strategy-file"><?= View::e((string) ($wizardDefaults['installer_name'] ?? '—')) ?></code>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="pw_procedure_name"><?= View::e(__('procedure.name')) ?></label>
                <input class="form-control" id="pw_procedure_name" name="procedure_name"
                       value="<?= View::e((string) ($wizardDefaults['procedure_name'] ?? $procedure->name)) ?>">
            </div>
            <p class="small text-muted mb-0"><?= View::e(__('procedure.wizard_intro_next')) ?></p>
        </div>

        <div class="pw-pane" data-pane="2" hidden>
            <h3 class="h6"><?= View::e(__('procedure.wizard_install_title')) ?></h3>
            <p class="small text-muted"><?= View::e(__('procedure.wizard_install_lead')) ?></p>
            <div class="mb-3">
                <label class="form-label" for="pw_install_command"><?= View::e(__('step.command')) ?> <?= View::e(__('common.required_mark')) ?></label>
                <input class="form-control font-monospace" id="pw_install_command" required
                       value="<?= View::e((string) ($wizardDefaults['install_command'] ?? '')) ?>"
                       placeholder="<?= View::e($wizardIsMsi ? 'app-setup.msi' : 'setup.exe') ?>">
                <div class="form-text"><?= View::e(__('procedure.wizard_install_command_help')) ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="pw_install_silent"><?= View::e(__('step.silent_args')) ?></label>
                <input class="form-control font-monospace" id="pw_install_silent"
                       value="<?= View::e((string) ($wizardDefaults['install_silent_args'] ?? '')) ?>">
                <div class="form-text"><?= View::e(__('procedure.wizard_silent_help')) ?></div>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label" for="pw_install_workdir"><?= View::e(__('step.working_dir')) ?></label>
                    <input class="form-control font-monospace" id="pw_install_workdir"
                           value="<?= View::e((string) ($wizardDefaults['install_working_dir'] ?? '.')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="pw_install_timeout"><?= View::e(__('step.timeout_sec')) ?></label>
                    <input class="form-control" type="number" min="60" id="pw_install_timeout"
                           value="<?= View::e((string) ($wizardDefaults['install_timeout_sec'] ?? '600')) ?>">
                </div>
            </div>
        </div>

        <div class="pw-pane" data-pane="3" hidden>
            <h3 class="h6"><?= View::e(__('procedure.wizard_uninstall_title')) ?></h3>
            <p class="small text-muted"><?= View::e(__('procedure.wizard_uninstall_lead')) ?></p>
            <div class="mb-3">
                <label class="form-label" for="pw_uninstall_command"><?= View::e(__('step.command')) ?> <?= View::e(__('common.required_mark')) ?></label>
                <input class="form-control font-monospace" id="pw_uninstall_command" required
                       value="<?= View::e((string) ($wizardDefaults['uninstall_command'] ?? '')) ?>">
                <div class="form-text"><?= View::e(__('procedure.wizard_uninstall_command_help')) ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="pw_uninstall_silent"><?= View::e(__('step.silent_args')) ?></label>
                <input class="form-control font-monospace" id="pw_uninstall_silent"
                       value="<?= View::e((string) ($wizardDefaults['uninstall_silent_args'] ?? '')) ?>">
            </div>
            <div class="mb-3 pw-msi-only"<?= $wizardIsMsi ? '' : ' hidden' ?>>
                <label class="form-label" for="pw_product_code"><?= View::e(__('step.product_code')) ?></label>
                <input class="form-control font-monospace" id="pw_product_code"
                       placeholder="{XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX}"
                       value="<?= View::e((string) ($wizardDefaults['product_code'] ?? '')) ?>">
                <div class="form-text"><?= View::e(__('procedure.wizard_product_code_help')) ?></div>
            </div>
            <div class="mb-0">
                <label class="form-label" for="pw_uninstall_timeout"><?= View::e(__('step.timeout_sec')) ?></label>
                <input class="form-control" type="number" min="60" id="pw_uninstall_timeout"
                       value="<?= View::e((string) ($wizardDefaults['uninstall_timeout_sec'] ?? '600')) ?>">
            </div>
        </div>

        <div class="pw-pane" data-pane="4" hidden>
            <h3 class="h6"><?= View::e(__('procedure.wizard_detect_title')) ?></h3>
            <p class="small text-muted"><?= View::e(__('procedure.wizard_detect_lead')) ?></p>
            <div class="mb-3">
                <label class="form-label" for="pw_success_paths"><?= View::e(__('step.success_paths')) ?></label>
                <textarea class="form-control font-monospace" id="pw_success_paths" rows="3"
                          placeholder="C:\Program Files\App\app.exe"><?= View::e((string) ($wizardDefaults['success_paths'] ?? '')) ?></textarea>
                <div class="form-text"><?= View::e(__('procedure.wizard_success_paths_help')) ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="pw_success_gone"><?= View::e(__('step.success_gone_paths')) ?></label>
                <textarea class="form-control font-monospace" id="pw_success_gone" rows="2"><?= View::e((string) ($wizardDefaults['success_gone_paths'] ?? '')) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="pw_pre_kill"><?= View::e(__('step.pre_kill')) ?></label>
                <textarea class="form-control font-monospace" id="pw_pre_kill" rows="2"
                          placeholder="app.exe&#10;tray.exe"><?= View::e((string) ($wizardDefaults['pre_kill'] ?? '')) ?></textarea>
            </div>
            <div class="border rounded p-3 bg-body-tertiary">
                <div class="small text-muted mb-1"><?= View::e(__('procedure.wizard_witness_label')) ?></div>
                <code class="small" id="pw_witness"><?= View::e((string) ($wizardDefaults['witness_path'] ?? '')) ?></code>
                <div class="form-text mb-0"><?= View::e(__('procedure.wizard_witness_help')) ?></div>
            </div>
        </div>

        <div class="pw-pane" data-pane="5" hidden>
            <h3 class="h6"><?= View::e(__('procedure.wizard_review_title')) ?></h3>
            <p class="small text-muted"><?= View::e(__('procedure.wizard_review_lead')) ?></p>
            <dl class="row small mb-3" id="pw-review">
                <dt class="col-sm-4"><?= View::e(__('procedure.wizard_nav_packaging')) ?></dt>
                <dd class="col-sm-8" data-review="packaging"></dd>
                <dt class="col-sm-4"><?= View::e(__('procedure.name')) ?></dt>
                <dd class="col-sm-8" data-review="procedure_name"></dd>
                <dt class="col-sm-4"><?= View::e(__('procedure.wizard_nav_install')) ?></dt>
                <dd class="col-sm-8 font-monospace" data-review="install"></dd>
                <dt class="col-sm-4"><?= View::e(__('procedure.wizard_nav_uninstall')) ?></dt>
                <dd class="col-sm-8 font-monospace" data-review="uninstall"></dd>
                <dt class="col-sm-4"><?= View::e(__('procedure.wizard_nav_detect')) ?></dt>
                <dd class="col-sm-8 font-monospace" data-review="detect"></dd>
            </dl>
            <div class="mb-3">
                <label class="form-label" for="pw_notes"><?= View::e(__('procedure.notes')) ?> <span class="text-muted">(<?= View::e(__('common.optional')) ?>)</span></label>
                <textarea class="form-control" id="pw_notes" rows="2" placeholder="<?= View::e(__('procedure.wizard_notes_placeholder')) ?>"></textarea>
            </div>
            <?php if (!$isEmpty): ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="pw_replace_core" checked>
                    <label class="form-check-label" for="pw_replace_core"><?= View::e(__('procedure.wizard_replace_core')) ?></label>
                </div>
            <?php else: ?>
                <input type="hidden" id="pw_replace_core" value="1">
            <?php endif; ?>
            <p class="small text-muted mb-0"><?= View::e(__('procedure.wizard_review_footer')) ?></p>
        </div>

        <div class="d-flex justify-content-between gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" id="pw-prev" hidden><?= View::e(__('procedure.wizard_prev')) ?></button>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="pw-cancel"><?= View::e(__('common.cancel')) ?></button>
                <button type="button" class="btn btn-primary" id="pw-next"><?= View::e(__('procedure.wizard_next')) ?></button>
                <button type="button" class="btn btn-success" id="pw-finish" hidden><?= View::e(__('procedure.wizard_finish')) ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <h2 class="h5 mb-3"><?= View::e(__('procedure.meta_title')) ?></h2>
    <?php if ($canWrite): ?>
        <form method="post" action="/softwares/<?= (int) $software->id ?>/procedure">
            <?= $csrf->field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="proc_name"><?= View::e(__('procedure.name')) ?></label>
                    <input class="form-control" id="proc_name" name="name" value="<?= View::e($procedure->name) ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="proc_notes"><?= View::e(__('procedure.notes')) ?></label>
                    <textarea class="form-control" id="proc_notes" name="notes" rows="2"><?= View::e((string) ($procedure->notes ?? '')) ?></textarea>
                </div>
            </div>
            <button class="btn btn-sm btn-primary mt-3" type="submit"><?= View::e(__('procedure.save')) ?></button>
        </form>
    <?php else: ?>
        <p class="mb-1"><strong><?= View::e($procedure->name) ?></strong></p>
        <p class="text-muted mb-0"><?= nl2br(View::e((string) ($procedure->notes ?? __('common.na')))) ?></p>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2 class="h5 mb-0"><?= View::e(__('procedure.steps_title', ['count' => (string) count($procedure->steps)])) ?></h2>
        <div class="d-flex align-items-center gap-2">
            <?php if ($canWrite): ?>
                <button type="button" class="btn btn-sm btn-primary" id="pw-open-btn">
                    <?= View::e($isEmpty ? __('procedure.wizard_start') : __('procedure.wizard_rerun')) ?>
                </button>
            <?php endif; ?>
            <?php if (!$isEmpty): ?>
                <span class="text-muted small"><?= View::e(__('procedure.reorder')) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isEmpty): ?>
        <div class="border rounded p-4 text-center bg-body-tertiary">
            <p class="mb-2 fw-semibold"><?= View::e(__('procedure.empty_title')) ?></p>
            <p class="text-muted small mb-3"><?= View::e(__('procedure.empty')) ?></p>
            <?php if ($canWrite): ?>
                <button type="button" class="btn btn-primary" id="pw-open-btn-empty">
                    <?= View::e(__('procedure.wizard_start')) ?>
                </button>
                <div class="form-text mt-2"><?= View::e(__('procedure.empty_or_manual')) ?></div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="step-list" id="procedure-steps"
            data-software-id="<?= (int) $software->id ?>"
            data-csrf="<?= View::e($csrf->token()) ?>">
            <?php foreach ($procedure->steps as $step): ?>
                <li class="step-item <?= $step->enabled ? '' : 'disabled-step' ?>" data-step-id="<?= (int) $step->id ?>">
                    <div class="d-flex justify-content-between gap-2">
                        <div>
                            <div class="step-type">
                                #<?= (int) $step->sortOrder ?>
                                <?= View::e($step->type->label()) ?>
                                <?php if ($step->label): ?>
                                    <span class="text-muted">— <?= View::e($step->label) ?></span>
                                <?php endif; ?>
                                <?php if (!$step->enabled): ?>
                                    <span class="badge text-bg-secondary"><?= View::e(__('common.disabled')) ?></span>
                                <?php endif; ?>
                                <span class="badge text-bg-light"><?= View::e($step->onFailure) ?></span>
                            </div>
                            <div class="step-preview"><?= View::e($step->preview()) ?></div>
                        </div>
                        <?php if ($canWrite): ?>
                            <div class="d-flex gap-1 align-items-start">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#edit-step-<?= (int) $step->id ?>"><?= View::e(__('procedure.edit_step')) ?></button>
                                <form method="post" action="/softwares/<?= (int) $software->id ?>/procedure/steps/<?= (int) $step->id ?>/delete"
                                      onsubmit="return confirm(<?= json_encode(__('procedure.confirm_delete_step'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);">
                                    <?= $csrf->field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit">×</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($canWrite): ?>
                        <div class="collapse mt-3" id="edit-step-<?= (int) $step->id ?>">
                            <form method="post" action="/softwares/<?= (int) $software->id ?>/procedure/steps/<?= (int) $step->id ?>" class="border-top pt-3">
                                <?= $csrf->field() ?>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label"><?= View::e(__('procedure.label')) ?></label>
                                        <input class="form-control form-control-sm" name="label" value="<?= View::e((string) ($step->label ?? '')) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label"><?= View::e(__('procedure.on_failure')) ?></label>
                                        <select class="form-select form-select-sm" name="on_failure">
                                            <option value="stop" <?= $step->onFailure === 'stop' ? 'selected' : '' ?>>stop</option>
                                            <option value="continue" <?= $step->onFailure === 'continue' ? 'selected' : '' ?>>continue</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="enabled" value="1" id="en_<?= (int) $step->id ?>" <?= $step->enabled ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="en_<?= (int) $step->id ?>"><?= View::e(__('procedure.enabled')) ?></label>
                                        </div>
                                    </div>
                                </div>
                                <?php foreach ($step->type->fields() as $field): ?>
                                    <?php
                                    $fname = $field['name'];
                                    $fval = $step->payload[$fname] ?? '';
                                    $fid = 'edit_' . $step->id . '_' . $fname;
                                    ?>
                                    <?php if ($field['type'] === 'checkbox'): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="<?= View::e($fname) ?>" value="1" id="<?= View::e($fid) ?>" <?= !empty($fval) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="<?= View::e($fid) ?>"><?= View::e($field['label']) ?></label>
                                        </div>
                                    <?php elseif ($field['type'] === 'textarea'): ?>
                                        <div class="mb-2">
                                            <label class="form-label" for="<?= View::e($fid) ?>"><?= View::e($field['label']) ?></label>
                                            <textarea class="form-control form-control-sm font-monospace" name="<?= View::e($fname) ?>" id="<?= View::e($fid) ?>" rows="3"><?= View::e(is_scalar($fval) ? (string) $fval : '') ?></textarea>
                                        </div>
                                    <?php elseif ($field['type'] === 'select'): ?>
                                        <div class="mb-2">
                                            <label class="form-label" for="<?= View::e($fid) ?>"><?= View::e($field['label']) ?></label>
                                            <select class="form-select form-select-sm" name="<?= View::e($fname) ?>" id="<?= View::e($fid) ?>">
                                                <option value=""><?= View::e(__('common.na')) ?></option>
                                                <?php foreach ($field['options'] ?? [] as $opt): ?>
                                                    <option value="<?= View::e($opt) ?>" <?= (string) $fval === $opt ? 'selected' : '' ?>><?= View::e($opt) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-2">
                                            <label class="form-label" for="<?= View::e($fid) ?>"><?= View::e($field['label']) ?></label>
                                            <input class="form-control form-control-sm" type="<?= $field['type'] === 'number' ? 'number' : 'text' ?>"
                                                   name="<?= View::e($fname) ?>" id="<?= View::e($fid) ?>"
                                                   value="<?= View::e(is_scalar($fval) ? (string) $fval : '') ?>">
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <button class="btn btn-sm btn-primary" type="submit"><?= View::e(__('procedure.update')) ?></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php if ($canWrite): ?>
<div class="panel">
    <h2 class="h5 mb-3"><?= View::e(__('procedure.add_step')) ?></h2>
    <form method="post" action="/softwares/<?= (int) $software->id ?>/procedure/steps">
        <?= $csrf->field() ?>
        <div class="row g-3 mb-2">
            <div class="col-md-4">
                <label class="form-label" for="step_type"><?= View::e(__('procedure.type')) ?> <?= View::e(__('common.required_mark')) ?></label>
                <select class="form-select" name="step_type" id="step_type" required>
                    <option value=""><?= View::e(__('common.choose')) ?></option>
                    <?php foreach ($stepTypes as $type): ?>
                        <option value="<?= View::e($type->value) ?>"><?= View::e($type->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="step_label"><?= View::e(__('procedure.label')) ?></label>
                <input class="form-control" name="label" id="step_label" placeholder="<?= View::e(__('common.optional')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="on_failure"><?= View::e(__('procedure.on_failure')) ?></label>
                <select class="form-select" name="on_failure" id="on_failure">
                    <option value="stop">stop</option>
                    <option value="continue">continue</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="enabled" value="1" id="step_enabled" checked>
                    <label class="form-check-label" for="step_enabled"><?= View::e(__('procedure.enabled')) ?></label>
                </div>
            </div>
        </div>
        <div id="step-fields"></div>
        <button class="btn btn-primary" type="submit"><?= View::e(__('procedure.add_step_submit')) ?></button>
    </form>
</div>
<?php endif; ?>

<?php endif; ?>
