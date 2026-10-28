<?php

use App\Support\View;

/**
 * @var list<array<string, mixed>> $hpVersions
 * @var \App\Models\User|null $currentUser
 * @var \App\Security\Csrf $csrf
 */
$canWrite = $currentUser && $currentUser->canWriteModule(\App\Security\ModuleAccess::MODULE_BIOS);
$versionsBack = '/bios/hp';
$hpVersions = $hpVersions ?? [];
?>
<div class="mb-3">
    <a href="/bios" class="small text-muted text-decoration-none"><?= View::e(__('common.back')) ?></a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= View::e(__('bios.mfr_hp_title')) ?></h1>
        <p class="text-muted mb-0"><?= View::e(__('bios.mfr_hp_page_lead')) ?></p>
    </div>
</div>

<div class="bios-tool-grid mb-4">
    <a href="/bios/hp/cfg-editor" class="bios-tool-card panel text-decoration-none">
        <h2 class="h5 mb-1 text-body"><?= View::e(__('bios.hp_editor_card_title')) ?></h2>
        <p class="text-muted mb-2 small"><?= View::e(__('bios.hp_editor_card_lead')) ?></p>
        <span class="btn btn-sm btn-primary"><?= View::e(__('bios.hp_editor_open')) ?></span>
    </a>
</div>

<?php
$versions = $hpVersions ?? [];
$downloadBase = '/bios/hp/cfg-versions';
$i18nPrefix = 'hp';
require __DIR__ . '/partials/versions.php';
?>

<div class="panel mb-4">
    <h2 class="h5 mb-2"><?= View::e(__('bios.hp_options_src_title')) ?></h2>
    <p class="mb-2"><?= View::e(__('bios.hp_options_src_body')) ?></p>
    <ul class="mb-0">
        <li>
            <a href="https://developers.hp.com/hp-client-management/doc/get-hpbiossettingslist" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.hp_options_src_link_cmsl')) ?>
            </a>
        </li>
        <li>
            <a href="https://ftp.hp.com/pub/caps-softpaq/cmit/whitepapers/BIOS_Configuration_Utility_User_Guide.pdf" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.hp_options_src_link_bcu')) ?>
            </a>
        </li>
        <li>
            <a href="https://ftp.hp.com/pub/caps-softpaq/cmit/HP_BCU.html" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.hp_options_src_link_download')) ?>
            </a>
        </li>
    </ul>
</div>

<div class="panel mb-4">
    <h2 class="h5 mb-2"><?= View::e(__('bios.hp_cmsl_title')) ?></h2>
    <p class="mb-2"><?= View::e(__('bios.hp_cmsl_body')) ?></p>
    <ul class="mb-3">
        <li>
            <a href="https://developers.hp.com/hp-client-management/doc/client-management-script-library" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.hp_cmsl_link_docs')) ?>
            </a>
        </li>
        <li>
            <a href="https://www.hp.com/us-en/solutions/client-management-solutions/download.html" target="_blank" rel="noopener noreferrer">
                <?= View::e(__('bios.hp_cmsl_link_download')) ?>
            </a>
        </li>
        <li>
            <?= View::e(__('bios.hp_cmsl_install_label')) ?>
            <code class="small">Install-Module -Name HPCMSL -Force -AcceptLicense</code>
        </li>
    </ul>
    <p class="text-muted small mb-0"><?= View::e(__('bios.hp_cmsl_note')) ?></p>
</div>

<div class="panel">
    <h2 class="h5 mb-2"><?= View::e(__('bios.hp_howto_title')) ?></h2>
    <ol class="mb-0 ps-3">
        <li class="mb-2"><?= View::e(__('bios.hp_howto_1')) ?></li>
        <li class="mb-2"><?= View::e(__('bios.hp_howto_2')) ?></li>
        <li class="mb-2"><?= View::e(__('bios.hp_howto_3')) ?></li>
        <li><?= View::e(__('bios.hp_howto_4')) ?></li>
    </ol>
</div>
