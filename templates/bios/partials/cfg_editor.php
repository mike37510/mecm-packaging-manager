<?php

use App\Support\View;

/**
 * Shared BIOS config editor chrome (HP BCU / Dell CCTK).
 *
 * @var string $backHref
 * @var string $title
 * @var string $lead
 * @var bool $canWrite
 * @var string $noteLabel
 * @var string $notePlaceholder
 * @var string $saveLabel
 * @var string $savingLabel
 * @var string $needSelection
 * @var string $saveUrl
 * @var string $readonlyRoleMsg
 * @var string $filterLabel
 * @var string $filterPlaceholder
 * @var string $loadingLabel
 * @var string $helpClose
 * @var string $optionsScript
 * @var string $helpScript
 * @var string $editorScript
 * @var string $optionsJsV
 * @var string $helpJsV
 * @var string $editorJsV
 * @var array<string, mixed> $editorI18n
 * @var \App\Security\Csrf $csrf
 */
$canWrite = $canWrite ?? false;
?>
<div class="mb-3">
    <a href="<?= View::e($backHref) ?>" class="small text-muted text-decoration-none"><?= View::e(__('common.back')) ?></a>
</div>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= View::e($title) ?></h1>
        <p class="text-muted mb-0"><?= View::e($lead) ?></p>
    </div>
    <?php if ($canWrite): ?>
        <div class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1" for="bios-editor-note"><?= View::e($noteLabel) ?></label>
                <input type="text" class="form-control form-control-sm" id="bios-editor-note" maxlength="200"
                       placeholder="<?= View::e($notePlaceholder) ?>" autocomplete="off" style="min-width:14rem">
            </div>
            <button type="button" class="btn btn-primary" id="bios-editor-save"
                    data-save-url="<?= View::e($saveUrl) ?>"
                    data-csrf="<?= View::e($csrf->token()) ?>"
                    data-need-selection="<?= View::e($needSelection) ?>"
                    data-saving="<?= View::e($savingLabel) ?>">
                <?= View::e($saveLabel) ?>
            </button>
        </div>
    <?php else: ?>
        <p class="text-muted small mb-0"><?= View::e($readonlyRoleMsg) ?></p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/versions.php'; ?>

<div class="panel mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-8">
            <label class="form-label" for="bios-editor-search"><?= View::e($filterLabel) ?></label>
            <input type="search" class="form-control" id="bios-editor-search"
                   placeholder="<?= View::e($filterPlaceholder) ?>" autocomplete="off">
        </div>
        <div class="col-md-4">
            <p class="text-muted small mb-0" id="bios-editor-stats"></p>
        </div>
    </div>
</div>

<div id="bios-editor-loading" class="panel text-center text-muted py-4"><?= View::e($loadingLabel) ?></div>
<div id="bios-editor-error" class="alert alert-danger" hidden></div>
<div id="bios-editor-save-feedback" class="alert" hidden></div>
<div id="bios-editor-list" class="bios-option-list"></div>

<div class="modal fade" id="bios-option-help-modal" tabindex="-1" aria-labelledby="bios-option-help-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="bios-option-help-title"></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= View::e($helpClose) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold" id="bios-option-help-short"></p>
                <div id="bios-option-help-detail" class="bios-help-detail"></div>
                <p class="bios-help-values small text-muted mt-3 mb-0" id="bios-option-help-values"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= View::e($helpClose) ?></button>
            </div>
        </div>
    </div>
</div>

<script>
window.BIOS_EDITOR_I18N = <?= json_encode($editorI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= View::e($optionsScript) ?>?v=<?= View::e($optionsJsV) ?>"></script>
<script src="<?= View::e($helpScript) ?>?v=<?= View::e($helpJsV) ?>"></script>
<script src="<?= View::e($editorScript) ?>?v=<?= View::e($editorJsV) ?>"></script>
