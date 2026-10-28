<?php

use App\Support\View;

/** @var \App\Models\Software $software */
/** @var array<string, mixed> $deployForm */
/** @var \App\Models\DeployProfile|null $deployProfile */
/** @var \App\Security\Csrf $csrf */
/** @var \App\Models\User|null $currentUser */

$f = static function (string $key, mixed $default = '') use ($deployForm): string {
    $v = $deployForm[$key] ?? $default;

    return View::e(is_scalar($v) || $v === null ? (string) $v : '');
};

$canWrite = $currentUser && $currentUser->canWriteModule(\App\Security\ModuleAccess::MODULE_SOFTWARES);
?>
<div class="panel">
    <h2 class="h5 mb-2"><?= View::e(__('deploy.title')) ?></h2>
    <p class="text-muted small mb-3">
        <?= View::e(__('deploy.lead')) ?>
    </p>

    <?php if ($deployProfile): ?>
        <p class="small text-success mb-3"><?= View::e(__('deploy.profile_saved', ['date' => (string) ($deployProfile->updatedAt ?? $deployProfile->createdAt ?? __('common.na'))])) ?></p>
    <?php else: ?>
        <p class="small text-muted mb-3"><?= View::e(__('deploy.profile_none')) ?></p>
    <?php endif; ?>

    <form method="post" action="/softwares/<?= (int) $software->id ?>/deploy" class="deploy-assistant">
        <?= $csrf->field() ?>

        <h3 class="h6 mt-3"><?= View::e(__('deploy.section_application')) ?></h3>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="application_name"><?= View::e(__('deploy.application_name')) ?></label>
                <input class="form-control" id="application_name" name="application_name" required value="<?= $f('application_name') ?>" <?= $canWrite ? '' : 'readonly' ?>>
                <div class="form-text"><?= View::e(__('deploy.application_name_help')) ?></div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="publisher"><?= View::e(__('deploy.publisher')) ?></label>
                <input class="form-control" id="publisher" name="publisher" value="<?= $f('publisher') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="software_version"><?= View::e(__('deploy.software_version')) ?></label>
                <input class="form-control" id="software_version" name="software_version" value="<?= $f('software_version') ?>" readonly title="<?= View::e(__('deploy.software_version_title')) ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label" for="description"><?= View::e(__('deploy.description')) ?></label>
                <textarea class="form-control" id="description" name="description" rows="3" <?= $canWrite ? '' : 'readonly' ?>><?= $f('description') ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="category"><?= View::e(__('deploy.category')) ?></label>
                <input class="form-control" id="category" name="category" value="<?= $f('category') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
        </div>

        <h3 class="h6 mt-4"><?= View::e(__('deploy.section_software_center')) ?></h3>
        <p class="small text-muted mb-2"><?= View::e(__('deploy.software_center_lead')) ?></p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="localized_name"><?= View::e(__('deploy.localized_name')) ?></label>
                <input class="form-control" id="localized_name" name="localized_name" value="<?= $f('localized_name') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="info_url"><?= View::e(__('deploy.info_url')) ?></label>
                <input class="form-control" type="url" id="info_url" name="info_url" value="<?= $f('info_url') ?>" <?= $canWrite ? '' : 'readonly' ?>>
                <div class="form-text"><?= View::e(__('deploy.info_url_help')) ?></div>
            </div>
            <div class="col-12">
                <label class="form-label" for="localized_description"><?= View::e(__('deploy.localized_description')) ?></label>
                <textarea class="form-control" id="localized_description" name="localized_description" rows="3" <?= $canWrite ? '' : 'readonly' ?>><?= $f('localized_description') ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="link_text"><?= View::e(__('deploy.link_text')) ?></label>
                <input class="form-control" id="link_text" name="link_text" value="<?= $f('link_text') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="keywords"><?= View::e(__('deploy.keywords')) ?></label>
                <input class="form-control" id="keywords" name="keywords" value="<?= $f('keywords') ?>" placeholder="<?= View::e(__('deploy.keywords_placeholder')) ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="privacy_url"><?= View::e(__('deploy.privacy_url')) ?></label>
                <input class="form-control" type="url" id="privacy_url" name="privacy_url" value="<?= $f('privacy_url') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
        </div>

        <h3 class="h6 mt-4"><?= View::e(__('deploy.section_deployment_type')) ?></h3>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="technology"><?= View::e(__('deploy.technology')) ?></label>
                <select class="form-select" id="technology" name="technology" <?= $canWrite ? '' : 'disabled' ?>>
                    <option value="script" <?= ($deployForm['technology'] ?? '') === 'script' ? 'selected' : '' ?>><?= View::e(__('deploy.technology_script')) ?></option>
                    <option value="msi" <?= ($deployForm['technology'] ?? '') === 'msi' ? 'selected' : '' ?>><?= View::e(__('deploy.technology_msi')) ?></option>
                </select>
                <?php if (!$canWrite): ?><input type="hidden" name="technology" value="<?= $f('technology') ?>"><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="max_runtime_minutes"><?= View::e(__('deploy.max_runtime_minutes')) ?></label>
                <input class="form-control" type="number" min="5" max="720" id="max_runtime_minutes" name="max_runtime_minutes" value="<?= $f('max_runtime_minutes', '60') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <input type="hidden" name="deployment_type_name" value="<?= View::e((string) ($deployForm['deployment_type_name'] ?? 'Install') ?: 'Install') ?>">
            <div class="col-12">
                <label class="form-label" for="install_command"><?= View::e(__('deploy.install_command')) ?></label>
                <input class="form-control font-monospace" id="install_command" name="install_command" required value="<?= $f('install_command') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-12">
                <label class="form-label" for="uninstall_command"><?= View::e(__('deploy.uninstall_command')) ?></label>
                <input class="form-control font-monospace" id="uninstall_command" name="uninstall_command" required value="<?= $f('uninstall_command') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="detection_type"><?= View::e(__('deploy.detection_type')) ?></label>
                <select class="form-select" id="detection_type" name="detection_type" <?= $canWrite ? '' : 'disabled' ?>>
                    <option value="file" <?= ($deployForm['detection_type'] ?? '') === 'file' ? 'selected' : '' ?>><?= View::e(__('deploy.detection_file')) ?></option>
                    <option value="msi" <?= ($deployForm['detection_type'] ?? '') === 'msi' ? 'selected' : '' ?>><?= View::e(__('deploy.detection_msi')) ?></option>
                </select>
                <?php if (!$canWrite): ?><input type="hidden" name="detection_type" value="<?= $f('detection_type') ?>"><?php endif; ?>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="detection_path"><?= View::e(__('deploy.detection_path')) ?></label>
                <input class="form-control font-monospace" id="detection_path" name="detection_path" value="<?= $f('detection_path') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="product_code"><?= View::e(__('deploy.product_code')) ?></label>
                <input class="form-control font-monospace" id="product_code" name="product_code" value="<?= $f('product_code') ?>" placeholder="{GUID}" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="installation_behavior"><?= View::e(__('deploy.installation_behavior')) ?></label>
                <select class="form-select" id="installation_behavior" name="installation_behavior" <?= $canWrite ? '' : 'disabled' ?>>
                    <option value="system" <?= ($deployForm['installation_behavior'] ?? '') === 'system' ? 'selected' : '' ?>><?= View::e(__('deploy.behavior_system')) ?></option>
                    <option value="user" <?= ($deployForm['installation_behavior'] ?? '') === 'user' ? 'selected' : '' ?>><?= View::e(__('deploy.behavior_user')) ?></option>
                </select>
                <?php if (!$canWrite): ?><input type="hidden" name="installation_behavior" value="<?= $f('installation_behavior') ?>"><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="reboot_behavior"><?= View::e(__('deploy.reboot_behavior')) ?></label>
                <select class="form-select" id="reboot_behavior" name="reboot_behavior" <?= $canWrite ? '' : 'disabled' ?>>
                    <option value="based_on_exit_code" <?= ($deployForm['reboot_behavior'] ?? '') === 'based_on_exit_code' ? 'selected' : '' ?>><?= View::e(__('deploy.reboot_exit_code')) ?></option>
                    <option value="no_reboot" <?= ($deployForm['reboot_behavior'] ?? '') === 'no_reboot' ? 'selected' : '' ?>><?= View::e(__('deploy.reboot_none')) ?></option>
                    <option value="force_reboot" <?= ($deployForm['reboot_behavior'] ?? '') === 'force_reboot' ? 'selected' : '' ?>><?= View::e(__('deploy.reboot_force')) ?></option>
                </select>
                <?php if (!$canWrite): ?><input type="hidden" name="reboot_behavior" value="<?= $f('reboot_behavior') ?>"><?php endif; ?>
            </div>
        </div>

        <h3 class="h6 mt-4"><?= View::e(__('deploy.section_mecm_targets')) ?></h3>
        <p class="small text-muted mb-2"><?= View::e(__('deploy.mecm_targets_lead')) ?></p>
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label" for="site_code"><?= View::e(__('deploy.site_code')) ?></label>
                <input class="form-control" id="site_code" name="site_code" required maxlength="3" pattern="[A-Za-z0-9]{3}"
                       placeholder="SIT" value="<?= $f('site_code') ?>" style="text-transform:uppercase" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="site_server"><?= View::e(__('deploy.site_server')) ?></label>
                <input class="form-control" id="site_server" name="site_server" required
                       placeholder="sccm.domain.local" value="<?= $f('site_server') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="distribution_point"><?= View::e(__('deploy.distribution_point')) ?></label>
                <input class="form-control" id="distribution_point" name="distribution_point" required
                       placeholder="dp.domain.local" value="<?= $f('distribution_point') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="collection_id"><?= View::e(__('deploy.collection_id')) ?></label>
                <input class="form-control font-monospace" id="collection_id" name="collection_id" required
                       placeholder="SIT00015" value="<?= $f('collection_id') ?>"
                       style="text-transform:uppercase" pattern="[A-Za-z0-9]+" <?= $canWrite ? '' : 'readonly' ?>>
                <div class="form-text"><?= View::e(__('deploy.collection_id_help')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="deployment_purpose"><?= View::e(__('deploy.purpose')) ?></label>
                <select class="form-select" id="deployment_purpose" name="deployment_purpose" <?= $canWrite ? '' : 'disabled' ?>>
                    <option value="available" <?= ($deployForm['deployment_purpose'] ?? '') === 'available' ? 'selected' : '' ?>><?= View::e(__('deploy.purpose_available')) ?></option>
                    <option value="required" <?= ($deployForm['deployment_purpose'] ?? '') === 'required' ? 'selected' : '' ?>><?= View::e(__('deploy.purpose_required')) ?></option>
                </select>
                <?php if (!$canWrite): ?><input type="hidden" name="deployment_purpose" value="<?= $f('deployment_purpose', 'available') ?>"><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="user_notification"><?= View::e(__('deploy.user_notification')) ?></label>
                <select class="form-select" id="user_notification" name="user_notification" <?= $canWrite ? '' : 'disabled' ?>>
                    <option value="software_center" <?= ($deployForm['user_notification'] ?? '') === 'software_center' ? 'selected' : '' ?>><?= View::e(__('deploy.notify_software_center')) ?></option>
                    <option value="display_all" <?= ($deployForm['user_notification'] ?? '') === 'display_all' ? 'selected' : '' ?>><?= View::e(__('deploy.notify_display_all')) ?></option>
                    <option value="hide_all" <?= ($deployForm['user_notification'] ?? '') === 'hide_all' ? 'selected' : '' ?>><?= View::e(__('deploy.notify_hide_all')) ?></option>
                </select>
                <?php if (!$canWrite): ?><input type="hidden" name="user_notification" value="<?= $f('user_notification', 'software_center') ?>"><?php endif; ?>
            </div>
            <div class="col-md-7">
                <label class="form-label" for="admin_folder"><?= View::e(__('deploy.admin_folder')) ?></label>
                <input class="form-control" id="admin_folder" name="admin_folder" placeholder="<?= View::e(__('deploy.admin_folder_placeholder')) ?>" value="<?= $f('admin_folder') ?>" <?= $canWrite ? '' : 'readonly' ?>>
            </div>
            <div class="col-12">
                <label class="form-label" for="notes"><?= View::e(__('deploy.notes')) ?></label>
                <textarea class="form-control" id="notes" name="notes" rows="2" <?= $canWrite ? '' : 'readonly' ?>><?= $f('notes') ?></textarea>
            </div>
        </div>

        <?php if ($canWrite): ?>
            <div class="mt-4 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><?= View::e(__('deploy.save_zip')) ?></button>
            </div>
        <?php endif; ?>
    </form>
</div>
