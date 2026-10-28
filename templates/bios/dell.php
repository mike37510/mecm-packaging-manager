<?php

use App\Support\View;

/**
 * @var list<array<string, mixed>> $dellVersions
 * @var \App\Models\User|null $currentUser
 * @var \App\Security\Csrf $csrf
 */
$canWrite = $currentUser && $currentUser->canWriteModule(\App\Security\ModuleAccess::MODULE_BIOS);
$versionsBack = '/bios/dell';
$dellVersions = $dellVersions ?? [];
?>
<div class="mb-3">
    <a href="/bios" class="small text-muted text-decoration-none"><?= View::e(__('common.back')) ?></a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= View::e(__('bios.mfr_dell_title')) ?></h1>
        <p class="text-muted mb-0"><?= View::e(__('bios.mfr_dell_page_lead')) ?></p>
    </div>
</div>

<div class="bios-tool-grid mb-4">
    <a href="/bios/dell/cfg-editor" class="bios-tool-card panel text-decoration-none">
        <h2 class="h5 mb-1 text-body"><?= View::e(__('bios.dell_editor_card_title')) ?></h2>
        <p class="text-muted mb-2 small"><?= View::e(__('bios.dell_editor_card_lead')) ?></p>
        <span class="btn btn-sm btn-primary"><?= View::e(__('bios.dell_editor_open')) ?></span>
    </a>
</div>

<?php
$versions = $dellVersions;
$downloadBase = '/bios/dell/cfg-versions';
$i18nPrefix = 'dell';
require __DIR__ . '/partials/versions.php';
?>

<div class="panel mb-4">
    <h2 class="h5 mb-2"><?= View::e(__('bios.dell_options_src_title')) ?></h2>
    <p class="mb-2"><?= View::e(__('bios.dell_options_src_body')) ?></p>
    <ul class="mb-0">
        <li>
            <a href="https://www.dell.com/support/manuals/en-us/command-configure/dcc_5.x_ref_guide/bios-options?guid=guid-44c059be-b76d-4b2f-b8ef-655f736c40ce&lang=en-us" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.dell_options_src_link_ref')) ?>
            </a>
        </li>
        <li>
            <a href="https://dl.dell.com/topicspdf/command-configure_reference-guide4_en-us.pdf" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.dell_options_src_link_pdf')) ?>
            </a>
        </li>
    </ul>
</div>

<div class="panel mb-4">
    <h2 class="h5 mb-2"><?= View::e(__('bios.dell_dcc_title')) ?></h2>
    <p class="mb-2"><?= View::e(__('bios.dell_dcc_body')) ?></p>
    <ul class="mb-3">
        <li>
            <a href="https://www.dell.com/support/kbdoc/en-us/000177000/running-dell-command-and-configure-commands-in-microsoft-deployment-toolkit-or-system-center-configuration-manager" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.dell_dcc_link_sccm')) ?>
            </a>
        </li>
        <li>
            <a href="https://www.dell.com/support/manuals/en-us/command-configure/dcc_5.x_ref_guide/-i-or-infile?guid=guid-2f935b9a-a74b-4d1e-a579-a254c9892cc6&lang=en-us" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.dell_dcc_link_docs')) ?>
            </a>
        </li>
        <li>
            <?= View::e(__('bios.dell_dcc_apply_label')) ?>
            <code class="small">cctk.exe -i BiosDell-N.ini</code>
        </li>
    </ul>
    <p class="text-muted small mb-0"><?= View::e(__('bios.dell_dcc_note')) ?></p>
</div>

<div class="panel">
    <h2 class="h5 mb-2"><?= View::e(__('bios.dell_howto_title')) ?></h2>
    <ol class="mb-0 ps-3">
        <li class="mb-2"><?= View::e(__('bios.dell_howto_1')) ?></li>
        <li class="mb-2"><?= View::e(__('bios.dell_howto_2')) ?></li>
        <li class="mb-2"><?= View::e(__('bios.dell_howto_3')) ?></li>
        <li><?= View::e(__('bios.dell_howto_4')) ?></li>
    </ol>
</div>
