<?php

use App\Support\View;

/**
 * @var list<array<string, mixed>> $lenovoVersions
 * @var \App\Models\User|null $currentUser
 * @var \App\Security\Csrf $csrf
 */
$canWrite = $currentUser && $currentUser->canWriteModule(\App\Security\ModuleAccess::MODULE_BIOS);
$versionsBack = '/bios/lenovo';
$lenovoVersions = $lenovoVersions ?? [];
?>
<div class="mb-3">
    <a href="/bios" class="small text-muted text-decoration-none"><?= View::e(__('common.back')) ?></a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= View::e(__('bios.mfr_lenovo_title')) ?></h1>
        <p class="text-muted mb-0"><?= View::e(__('bios.mfr_lenovo_page_lead')) ?></p>
    </div>
</div>

<div class="bios-tool-grid mb-4">
    <a href="/bios/lenovo/cfg-editor" class="bios-tool-card panel text-decoration-none">
        <h2 class="h5 mb-1 text-body"><?= View::e(__('bios.lenovo_editor_card_title')) ?></h2>
        <p class="text-muted mb-2 small"><?= View::e(__('bios.lenovo_editor_card_lead')) ?></p>
        <span class="btn btn-sm btn-primary"><?= View::e(__('bios.lenovo_editor_open')) ?></span>
    </a>
</div>

<?php
$versions = $lenovoVersions;
$downloadBase = '/bios/lenovo/cfg-versions';
$i18nPrefix = 'lenovo';
require __DIR__ . '/partials/versions.php';
?>

<div class="panel mb-4">
    <h2 class="h5 mb-2"><?= View::e(__('bios.lenovo_options_src_title')) ?></h2>
    <p class="mb-2"><?= View::e(__('bios.lenovo_options_src_body')) ?></p>
    <ul class="mb-0">
        <li>
            <a href="https://docs.lenovocdrt.com/guides/tbct_v2/tbc_module_reference/" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.lenovo_options_src_link_module')) ?>
            </a>
        </li>
        <li>
            <a href="https://docs.lenovocdrt.com/guides/tbct_v2/" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.lenovo_options_src_link_guide')) ?>
            </a>
        </li>
    </ul>
</div>

<div class="panel mb-4">
    <h2 class="h5 mb-2"><?= View::e(__('bios.lenovo_tbct_title')) ?></h2>
    <p class="mb-2"><?= View::e(__('bios.lenovo_tbct_body')) ?></p>
    <ul class="mb-3">
        <li>
            <a href="https://docs.lenovocdrt.com/guides/tbct_v2/" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.lenovo_tbct_link_docs')) ?>
            </a>
        </li>
        <li>
            <a href="https://docs.lenovocdrt.com/guides/tbct_v2/tbc_module_reference/" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.lenovo_tbct_link_module')) ?>
            </a>
        </li>
        <li>
            <?= View::e(__('bios.lenovo_tbct_apply_label')) ?>
            <code class="small">Import-LnvWmiSettings -ConfigFile BiosLenovo-N.ini; Save-LnvWmiSettings</code>
        </li>
    </ul>
    <p class="text-muted small mb-0"><?= View::e(__('bios.lenovo_tbct_note')) ?></p>
</div>

<div class="panel">
    <h2 class="h5 mb-2"><?= View::e(__('bios.lenovo_howto_title')) ?></h2>
    <ol class="mb-0 ps-3">
        <li class="mb-2"><?= View::e(__('bios.lenovo_howto_1')) ?></li>
        <li class="mb-2"><?= View::e(__('bios.lenovo_howto_2')) ?></li>
        <li class="mb-2"><?= View::e(__('bios.lenovo_howto_3')) ?></li>
        <li><?= View::e(__('bios.lenovo_howto_4')) ?></li>
    </ol>
</div>
