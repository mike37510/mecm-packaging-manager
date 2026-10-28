<?php

use App\Support\View;

/** @var array<string, mixed> $mecm */
/** @var array<string, mixed> $vt */
/** @var bool $vtKeySet */
/** @var bool $vtConfigured */
/** @var array<string, mixed> $mail */
/** @var bool $mailPasswordSet */
/** @var bool $mailConfigured */
/** @var \App\Security\Csrf $csrf */
/** @var array<string, mixed> $flash */

/** @var array<string, mixed> $permissionsReport */
/** @var string $permissionsDataRoot */
/** @var mixed $permissionsActions */
/** @var bool $permissionsNeedsSudo */
/** @var string $siteLocale */
/** @var array<string, mixed> $bios */

$vtKeySet = $vtKeySet ?? false;
$vtConfigured = $vtConfigured ?? false;
$vt = $vt ?? ['enabled' => false, 'api_key' => '', 'updated_at' => null];
$mailPasswordSet = $mailPasswordSet ?? false;
$mailConfigured = $mailConfigured ?? false;
$mail = $mail ?? [
    'enabled' => false,
    'host' => '',
    'port' => 587,
    'encryption' => 'tls',
    'username' => '',
    'password' => '',
    'from_email' => '',
    'from_name' => 'MECM Packaging Manager',
    'app_url' => '',
    'updated_at' => null,
];
$siteLocale = ($siteLocale ?? 'fr') === 'en' ? 'en' : 'fr';
$bios = $bios ?? ['log_local_path' => '', 'log_share_path' => '', 'updated_at' => null];
$permissionsReport = $permissionsReport ?? ['ok' => false, 'checks' => [], 'summary' => ['ok' => 0, 'warn' => 0, 'fail' => 0], 'php_user' => ''];
$permissionsDataRoot = $permissionsDataRoot ?? '';
$permissionsActions = $permissionsActions ?? null;
$permissionsNeedsSudo = $permissionsNeedsSudo ?? false;
$mecm = $mecm ?? [
    'site_code' => '',
    'site_server' => '',
    'distribution_point' => '',
    'collection_id' => '',
    'deployment_purpose' => 'available',
];
?>
<div class="mb-3">
    <h1 class="h3 mb-1"><?= View::e(__('settings.title')) ?></h1>
    <p class="text-muted mb-0"><?= View::e(__('settings.lead')) ?></p>
</div>

<form id="mecm-settings-form" method="post" action="/settings/mecm" style="display:none" aria-hidden="true">
    <?= $csrf->field() ?>
</form>

<ul class="nav nav-tabs settings-module-tabs mb-3" id="settings-module-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button type="button" class="nav-link active" id="tab-general" data-bs-toggle="tab"
                data-bs-target="#rubrique-general" role="tab" aria-controls="rubrique-general" aria-selected="true">
            <?= View::e(__('settings.toc_general')) ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button type="button" class="nav-link" id="tab-softwares" data-bs-toggle="tab"
                data-bs-target="#rubrique-softwares" role="tab" aria-controls="rubrique-softwares" aria-selected="false">
            <?= View::e(__('settings.toc_softwares')) ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button type="button" class="nav-link" id="tab-drivers" data-bs-toggle="tab"
                data-bs-target="#rubrique-drivers" role="tab" aria-controls="rubrique-drivers" aria-selected="false">
            <?= View::e(__('settings.toc_drivers')) ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button type="button" class="nav-link" id="tab-bios" data-bs-toggle="tab"
                data-bs-target="#rubrique-bios" role="tab" aria-controls="rubrique-bios" aria-selected="false">
            <?= View::e(__('settings.toc_bios')) ?>
        </button>
    </li>
</ul>

<div class="tab-content" id="settings-module-panes">
<section class="tab-pane fade show active settings-rubrique" id="rubrique-general" role="tabpanel"
         aria-labelledby="tab-general" tabindex="0">
    <header class="settings-rubrique-head">
        <h2 class="settings-rubrique-title" id="rubrique-general-title"><?= View::e(__('settings.rubrique_general')) ?></h2>
        <p class="settings-rubrique-lead mb-0"><?= View::e(__('settings.rubrique_general_lead')) ?></p>
    </header>
<div class="panel mb-4" id="interface">
    <h2 class="h5 mb-1"><?= View::e(__('settings.interface_title')) ?></h2>
    <p class="text-muted small mb-3"><?= View::e(__('settings.interface_lead')) ?></p>
    <form method="post" action="/settings/site" class="row g-3 align-items-end">
        <?= $csrf->field() ?>
        <div class="col-md-4">
            <label class="form-label" for="locale"><?= View::e(__('settings.locale')) ?></label>
            <select class="form-select" id="locale" name="locale">
                <option value="fr" <?= $siteLocale === 'fr' ? 'selected' : '' ?>><?= View::e(__('settings.locale_fr')) ?></option>
                <option value="en" <?= $siteLocale === 'en' ? 'selected' : '' ?>><?= View::e(__('settings.locale_en')) ?></option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><?= View::e(__('settings.save_interface')) ?></button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../partials/settings_permissions.php'; ?>

<div class="panel mb-4" id="mailing">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1"><?= View::e(__('settings.mail_title')) ?></h2>
            <p class="text-muted small mb-0">
                <?= View::e(__('settings.mail_lead')) ?>
                <?php if ($mailConfigured): ?>
                    <span class="badge text-bg-success ms-1"><?= View::e(__('settings.mail_ready')) ?></span>
                <?php elseif (!empty($mail['enabled'])): ?>
                    <span class="badge text-bg-warning text-dark ms-1"><?= View::e(__('settings.mail_incomplete')) ?></span>
                <?php else: ?>
                    <span class="badge text-bg-secondary ms-1"><?= View::e(__('settings.mail_off')) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <form method="post" action="/settings/mail/test" class="m-0"
                  data-loading="<?= View::e(__('settings.mail_test_loading')) ?>">
                <?= $csrf->field() ?>
                <button type="submit" class="btn btn-outline-primary btn-sm" <?= $mailConfigured ? '' : 'disabled title="' . View::e(__('settings.mail_test_disabled')) . '"' ?>>
                    <?= View::e(__('settings.mail_test')) ?>
                </button>
            </form>
            <form method="post" action="/settings/mail/renewals" class="m-0"
                  data-loading="<?= View::e(__('settings.mail_renewals_loading')) ?>"
                  onsubmit="return confirm(<?= json_encode(__('settings.mail_renewals_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);">
                <?= $csrf->field() ?>
                <button type="submit" class="btn btn-outline-secondary btn-sm" <?= $mailConfigured ? '' : 'disabled title="' . View::e(__('settings.mail_test_disabled')) . '"' ?>>
                    <?= View::e(__('settings.mail_renewals_run')) ?>
                </button>
            </form>
        </div>
    </div>

    <form method="post" action="/settings/mail" class="row g-3">
        <?= $csrf->field() ?>

        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch"
                       name="enabled" id="mail_enabled" value="1"
                    <?= !empty($mail['enabled']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="mail_enabled"><?= View::e(__('settings.mail_enabled')) ?></label>
            </div>
        </div>

        <div class="col-md-5">
            <label class="form-label" for="mail_host"><?= View::e(__('settings.mail_host')) ?></label>
            <input class="form-control" name="host" id="mail_host"
                   value="<?= View::e((string) ($mail['host'] ?? '')) ?>"
                   placeholder="smtp.example.org">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="mail_port"><?= View::e(__('settings.mail_port')) ?></label>
            <input class="form-control" type="number" name="port" id="mail_port" min="1" max="65535"
                   value="<?= (int) ($mail['port'] ?? 587) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="mail_encryption"><?= View::e(__('settings.mail_encryption')) ?></label>
            <?php $enc = (string) ($mail['encryption'] ?? 'tls'); ?>
            <select class="form-select" name="encryption" id="mail_encryption">
                <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>><?= View::e(__('settings.mail_enc_tls')) ?></option>
                <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>><?= View::e(__('settings.mail_enc_ssl')) ?></option>
                <option value="none" <?= $enc === 'none' ? 'selected' : '' ?>><?= View::e(__('settings.mail_enc_none')) ?></option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="mail_username"><?= View::e(__('settings.mail_username')) ?></label>
            <input class="form-control" name="smtp_username" id="mail_username"
                   autocomplete="off" data-lpignore="true" data-1p-ignore="true"
                   value="<?= View::e((string) ($mail['username'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="mail_password"><?= View::e(__('settings.mail_password')) ?></label>
            <input class="form-control" type="password" name="smtp_password" id="mail_password"
                   autocomplete="new-password" data-lpignore="true" data-1p-ignore="true"
                   value=""
                   placeholder="<?= View::e($mailPasswordSet ? __('settings.mail_password_keep') : '') ?>">
            <?php if ($mailPasswordSet): ?>
                <div class="form-text"><?= View::e(__('settings.mail_password_help')) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="mail_from_email"><?= View::e(__('settings.mail_from_email')) ?></label>
            <input class="form-control" type="text" name="from_email" id="mail_from_email"
                   inputmode="email" autocomplete="off" data-lpignore="true" data-1p-ignore="true"
                   value="<?= View::e((string) ($mail['from_email'] ?? '')) ?>"
                   placeholder="noreply@example.org">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="mail_from_name"><?= View::e(__('settings.mail_from_name')) ?></label>
            <input class="form-control" name="from_name" id="mail_from_name"
                   value="<?= View::e((string) ($mail['from_name'] ?? '')) ?>">
        </div>
        <div class="col-md-8">
            <label class="form-label" for="mail_app_url"><?= View::e(__('settings.mail_app_url')) ?></label>
            <input class="form-control" name="app_url" id="mail_app_url"
                   value="<?= View::e((string) ($mail['app_url'] ?? '')) ?>"
                   placeholder="https://packaging.example.org">
            <div class="form-text"><?= View::e(__('settings.mail_app_url_help')) ?></div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary"><?= View::e(__('settings.mail_save')) ?></button>
        </div>
    </form>
</div>

<div class="panel mb-4" id="virustotal">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1"><?= View::e(__('settings.vt_title')) ?></h2>
            <p class="text-muted small mb-0">
                <?= View::e(__('settings.vt_lead')) ?>
                <?php if ($vtConfigured): ?>
                    <span class="badge text-bg-success ms-1"><?= View::e(__('settings.vt_ready')) ?></span>
                <?php elseif (!empty($vt['enabled'])): ?>
                    <span class="badge text-bg-warning text-dark ms-1"><?= View::e(__('settings.vt_incomplete')) ?></span>
                <?php else: ?>
                    <span class="badge text-bg-secondary ms-1"><?= View::e(__('settings.vt_off')) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <form method="post" action="/settings/virustotal/ping" class="m-0"
              data-loading="<?= View::e(__('settings.vt_ping_loading')) ?>">
            <?= $csrf->field() ?>
            <button type="submit" class="btn btn-outline-primary btn-sm" <?= $vtKeySet ? '' : 'disabled title="' . View::e(__('settings.vt_ping_disabled')) . '"' ?>>
                <?= View::e(__('settings.vt_ping')) ?>
            </button>
        </form>
    </div>

    <form method="post" action="/settings/virustotal" class="row g-3">
        <?= $csrf->field() ?>

        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch"
                       name="enabled" id="vt_enabled" value="1"
                    <?= !empty($vt['enabled']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="vt_enabled"><?= View::e(__('settings.vt_enabled')) ?></label>
            </div>
        </div>

        <div class="col-md-8">
            <label class="form-label" for="vt_api_key"><?= View::e(__('settings.vt_api_key')) ?><?= $vtKeySet ? '' : ' ' . View::e(__('common.required_mark')) ?></label>
            <input class="form-control font-monospace" type="password" name="api_key" id="vt_api_key"
                   value=""
                   placeholder="<?= View::e($vtKeySet ? __('settings.vt_api_key_keep') : __('settings.vt_api_key_placeholder')) ?>"
                   autocomplete="off"
                   minlength="64" maxlength="64"
                   pattern="[A-Fa-f0-9]{64}">
            <div class="form-text">
                <?= $vtKeySet ? View::e(__('settings.vt_api_key_help')) : View::e(__('settings.vt_api_key_hint')) ?>
                <a href="https://www.virustotal.com/gui/my-apikey" target="_blank" rel="noopener noreferrer"><?= View::e(__('settings.vt_api_key_link')) ?></a>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary"><?= View::e(__('settings.vt_save')) ?></button>
        </div>
    </form>
</div>

<div class="panel mb-4" id="mecm-site">
    <h2 class="h5 mb-1"><?= View::e(__('settings.mecm_site_title')) ?></h2>
    <p class="text-muted small mb-3"><?= View::e(__('settings.mecm_site_lead')) ?></p>
    <fieldset class="settings-mecm-block border-0 p-0">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label" for="mecm_site_code"><?= View::e(__('settings.mecm_site_code')) ?> <?= View::e(__('common.required_mark')) ?></label>
                    <input form="mecm-settings-form" class="form-control" name="site_code" id="mecm_site_code" required maxlength="3"
                           pattern="[A-Za-z0-9]{3}"
                           value="<?= View::e((string) ($mecm['site_code'] ?? '')) ?>"
                           placeholder="SIT" style="text-transform:uppercase">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="mecm_site_server"><?= View::e(__('settings.mecm_site_server')) ?> <?= View::e(__('common.required_mark')) ?></label>
                    <input form="mecm-settings-form" class="form-control" name="site_server" id="mecm_site_server" required
                           value="<?= View::e((string) ($mecm['site_server'] ?? '')) ?>"
                           placeholder="mecm.domain.local">
                    <div class="form-text"><?= View::e(__('settings.mecm_site_server_help')) ?></div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="mecm_dp"><?= View::e(__('settings.mecm_dp')) ?> <?= View::e(__('common.required_mark')) ?></label>
                    <input form="mecm-settings-form" class="form-control" name="distribution_point" id="mecm_dp" required
                           value="<?= View::e((string) ($mecm['distribution_point'] ?? '')) ?>"
                           placeholder="dp.domain.local">
                    <div class="form-text"><?= View::e(__('settings.mecm_dp_help')) ?></div>
                </div>
            </div>
        </fieldset>
    <div class="mt-3">
        <button type="submit" form="mecm-settings-form" class="btn btn-primary"><?= View::e(__('settings.mecm_save')) ?></button>
        <div class="form-text"><?= View::e(__('settings.mecm_save_hint')) ?></div>
    </div>
</div>
</section>

<section class="tab-pane fade settings-rubrique" id="rubrique-softwares" role="tabpanel"
         aria-labelledby="tab-softwares" tabindex="0">
    <header class="settings-rubrique-head">
        <h2 class="settings-rubrique-title" id="rubrique-softwares-title"><?= View::e(__('settings.rubrique_softwares')) ?></h2>
        <p class="settings-rubrique-lead mb-0"><?= View::e(__('settings.rubrique_softwares_lead')) ?></p>
    </header>
<?php require __DIR__ . '/../partials/settings_categories.php'; ?>

<div class="alert alert-secondary small mb-4" role="note">
    <?= View::e(__('settings.vm_moved_hint')) ?>
    <a href="/account/vm"><?= View::e(__('nav.account_vm')) ?></a>
</div>

<div class="panel mb-4" id="mecm-software">
    <h2 class="h5 mb-1"><?= View::e(__('settings.mecm_software_title')) ?> <span class="badge text-bg-primary ms-1"><?= View::e(__('settings.mecm_scope_software')) ?></span></h2>
    <p class="text-muted small mb-3"><?= View::e(__('settings.mecm_software_lead')) ?></p>
    <fieldset class="settings-mecm-block border-0 p-0">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="mecm_collection"><?= View::e(__('settings.mecm_collection')) ?></label>
                    <input form="mecm-settings-form" class="form-control font-monospace" name="collection_id" id="mecm_collection"
                           value="<?= View::e((string) ($mecm['collection_id'] ?? '')) ?>"
                           placeholder="SIT00015" style="text-transform:uppercase">
                    <div class="form-text"><?= View::e(__('settings.mecm_collection_help')) ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="mecm_purpose"><?= View::e(__('settings.mecm_purpose')) ?></label>
                    <select form="mecm-settings-form" class="form-select" name="deployment_purpose" id="mecm_purpose">
                        <option value="available" <?= (($mecm['deployment_purpose'] ?? '') === 'available') ? 'selected' : '' ?>><?= View::e(__('settings.mecm_purpose_available')) ?></option>
                        <option value="required" <?= (($mecm['deployment_purpose'] ?? '') === 'required') ? 'selected' : '' ?>><?= View::e(__('settings.mecm_purpose_required')) ?></option>
                    </select>
                    <div class="form-text"><?= View::e(__('settings.mecm_purpose_help')) ?></div>
                </div>
            </div>
        </fieldset>
    <div class="mt-3">
        <button type="submit" form="mecm-settings-form" class="btn btn-primary"><?= View::e(__('settings.mecm_save')) ?></button>
    </div>
</div>
</section>

<section class="tab-pane fade settings-rubrique" id="rubrique-drivers" role="tabpanel"
         aria-labelledby="tab-drivers" tabindex="0">
    <header class="settings-rubrique-head">
        <h2 class="settings-rubrique-title" id="rubrique-drivers-title"><?= View::e(__('settings.rubrique_drivers')) ?></h2>
        <p class="settings-rubrique-lead mb-0"><?= View::e(__('settings.rubrique_drivers_lead')) ?></p>
    </header>
<div class="panel mb-4" id="mecm-drivers">
    <h2 class="h5 mb-1"><?= View::e(__('settings.mecm_drivers_title')) ?> <span class="badge text-bg-primary ms-1"><?= View::e(__('settings.mecm_scope_drivers')) ?></span></h2>
    <p class="text-muted small mb-3"><?= View::e(__('settings.mecm_drivers_lead')) ?></p>
    <fieldset class="settings-mecm-block border-0 p-0">
            <div class="alert alert-info small mb-3">
                <p class="fw-semibold mb-2"><?= View::e(__('settings.driver_unc_diff_title')) ?></p>
                <p class="mb-2"><?= View::e(__('settings.driver_unc_diff_source')) ?></p>
                <p class="mb-0"><?= View::e(__('settings.driver_unc_diff_package')) ?></p>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="driver_source_unc"><?= View::e(__('settings.driver_source_unc')) ?></label>
                    <input form="mecm-settings-form" class="form-control font-monospace" name="driver_source_unc" id="driver_source_unc"
                           value="<?= View::e((string) ($mecm['driver_source_unc'] ?? '')) ?>"
                           placeholder="\\fileserver\MECM\Drivers\Inbox">
                    <div class="form-text"><?= View::e(__('settings.driver_source_unc_help')) ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="driver_package_unc"><?= View::e(__('settings.driver_package_unc')) ?></label>
                    <input form="mecm-settings-form" class="form-control font-monospace" name="driver_package_unc" id="driver_package_unc"
                           value="<?= View::e((string) ($mecm['driver_package_unc'] ?? '')) ?>"
                           placeholder="\\fileserver\MECM\Drivers\Packages">
                    <div class="form-text"><?= View::e(__('settings.driver_package_unc_help')) ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="driver_category"><?= View::e(__('settings.driver_category')) ?></label>
                    <input form="mecm-settings-form" class="form-control" name="driver_category" id="driver_category"
                           value="<?= View::e((string) ($mecm['driver_category'] ?? '')) ?>"
                           placeholder="Packaging Factory">
                    <div class="form-text"><?= View::e(__('settings.driver_category_help')) ?></div>
                </div>
            </div>
        </fieldset>
    <div class="mt-3">
        <button type="submit" form="mecm-settings-form" class="btn btn-primary"><?= View::e(__('settings.mecm_save')) ?></button>
    </div>
</div>
</section>

<section class="tab-pane fade settings-rubrique" id="rubrique-bios" role="tabpanel"
         aria-labelledby="tab-bios" tabindex="0">
    <header class="settings-rubrique-head">
        <h2 class="settings-rubrique-title" id="rubrique-bios-title"><?= View::e(__('settings.rubrique_bios')) ?></h2>
        <p class="settings-rubrique-lead mb-0"><?= View::e(__('settings.rubrique_bios_lead')) ?></p>
    </header>
<div class="panel mb-4" id="bios">
    <h2 class="h5 mb-1"><?= View::e(__('settings.bios_title')) ?></h2>
    <p class="text-muted small mb-3"><?= View::e(__('settings.bios_lead')) ?></p>
    <form method="post" action="/settings/bios" class="row g-3">
        <?= $csrf->field() ?>
        <div class="col-md-6">
            <label class="form-label" for="bios_log_local_path"><?= View::e(__('settings.bios_log_local')) ?></label>
            <input class="form-control font-monospace" name="log_local_path" id="bios_log_local_path"
                   value="<?= View::e((string) ($bios['log_local_path'] ?? '')) ?>"
                   placeholder="<?= View::e(__('settings.bios_log_local_placeholder')) ?>"
                   autocomplete="off" spellcheck="false">
            <div class="form-text"><?= View::e(__('settings.bios_log_local_help')) ?></div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="bios_log_share_path"><?= View::e(__('settings.bios_log_share')) ?></label>
            <input class="form-control font-monospace" name="log_share_path" id="bios_log_share_path"
                   value="<?= View::e((string) ($bios['log_share_path'] ?? '')) ?>"
                   placeholder="<?= View::e(__('settings.bios_log_share_placeholder')) ?>"
                   autocomplete="off" spellcheck="false">
            <div class="form-text"><?= View::e(__('settings.bios_log_share_help')) ?></div>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary"><?= View::e(__('settings.bios_save')) ?></button>
        </div>
    </form>
</div>

</section>
</div><!-- /#settings-module-panes -->

<p class="small text-muted mb-0 mt-3">
    <?= View::e(__('settings.storage_hint')) ?>
</p>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tabsRoot = document.getElementById('settings-module-tabs');
    if (!tabsRoot || typeof bootstrap === 'undefined') {
        return;
    }

    var paneIds = {
        'rubrique-general': 'tab-general',
        'rubrique-softwares': 'tab-softwares',
        'rubrique-drivers': 'tab-drivers',
        'rubrique-bios': 'tab-bios',
        'permissions': 'tab-general',
        'interface': 'tab-general',
        'mailing': 'tab-general',
        'virustotal': 'tab-general',
        'mecm-site': 'tab-general',
        'vm': 'tab-softwares',
        'mecm-software': 'tab-softwares',
        'mecm-drivers': 'tab-drivers',
        'bios': 'tab-bios'
    };

    function activateFromHash() {
        var raw = (window.location.hash || '').replace(/^#/, '');
        if (!raw) {
            return;
        }
        var tabBtnId = paneIds[raw];
        var scrollTarget = null;
        if (!tabBtnId) {
            var el = document.getElementById(raw);
            if (!el) {
                return;
            }
            var pane = el.closest('.tab-pane');
            if (!pane || !pane.id || !paneIds[pane.id]) {
                return;
            }
            tabBtnId = paneIds[pane.id];
            scrollTarget = el;
        } else if (raw !== 'rubrique-general' && raw !== 'rubrique-softwares'
            && raw !== 'rubrique-drivers' && raw !== 'rubrique-bios') {
            scrollTarget = document.getElementById(raw);
        }
        var btn = document.getElementById(tabBtnId);
        if (!btn) {
            return;
        }
        bootstrap.Tab.getOrCreateInstance(btn).show();
        if (scrollTarget) {
            window.setTimeout(function () {
                scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 50);
        }
    }

    tabsRoot.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function (ev) {
            var target = ev.target.getAttribute('data-bs-target');
            if (target && target.charAt(0) === '#') {
                history.replaceState(null, '', target);
            }
        });
    });

    activateFromHash();
    window.addEventListener('hashchange', activateFromHash);
});
</script>
