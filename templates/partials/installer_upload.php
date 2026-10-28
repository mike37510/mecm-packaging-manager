<?php

use App\Support\View;

/** @var \App\Models\Software $software */
/** @var \App\Security\Csrf $csrf */
/** @var bool $canWrite */
?>
<div id="installer-upload" class="border rounded p-3 mb-4 bg-body-tertiary">
    <h3 class="h6 mb-2"><?= View::e(__('software.installer_upload_title')) ?></h3>
    <p class="small text-muted mb-3"><?= View::e(__('software.installer_upload_lead')) ?></p>
    <?php if ($canWrite): ?>
        <form method="post" action="/softwares/<?= (int) $software->id ?>/installer" enctype="multipart/form-data"
              data-loading="<?= View::e(__('software.installer_uploading')) ?>">
            <?= $csrf->field() ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="installer"><?= View::e(__('software.installer_file')) ?></label>
                    <input class="form-control" type="file" name="installer" id="installer" required
                           accept=".exe,.msi,.msix,.zip,application/octet-stream">
                    <div class="form-text"><?= View::e(__('software.installer_upload_help')) ?></div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100"><?= View::e(__('software.installer_upload_submit')) ?></button>
                </div>
            </div>
        </form>
        <?php if ($software->hasManualInstaller()): ?>
            <form method="post" action="/softwares/<?= (int) $software->id ?>/installer/prefer-url" class="mt-3"
                  onsubmit="return confirm(<?= json_encode(__('software.installer_prefer_url_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);">
                <?= $csrf->field() ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary"><?= View::e(__('software.installer_prefer_url')) ?></button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <p class="small text-muted mb-0"><?= View::e(__('software.installer_upload_readonly')) ?></p>
    <?php endif; ?>
</div>
