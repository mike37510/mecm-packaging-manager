<?php

use App\Support\View;

/** @var array<string, mixed> $vm */
/** @var bool $passwordSet */
/** @var bool $vmConfigured */
/** @var \App\Security\Csrf $csrf */
/** @var array<string, mixed> $flash */

$passwordSet = $passwordSet ?? false;
$vmConfigured = $vmConfigured ?? false;
$vm = $vm ?? [
    'enabled' => false,
    'host' => '',
    'port' => 22,
    'username' => '',
    'remote_dir' => 'C:\\SCCM_Lab',
    'timeout_seconds' => 900,
];
?>
<div class="mb-4">
    <h1 class="h3 mb-1"><?= View::e(__('account.vm_title')) ?></h1>
    <p class="text-muted mb-0"><?= View::e(__('account.vm_lead')) ?></p>
</div>

<div class="panel mb-4" id="vm">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1"><?= View::e(__('account.vm_panel_title')) ?></h2>
            <p class="text-muted small mb-0">
                <?= View::e(__('account.vm_panel_lead')) ?>
                <?php if ($vmConfigured): ?>
                    <span class="badge text-bg-success ms-1"><?= View::e(__('settings.vm_ready')) ?></span>
                <?php else: ?>
                    <span class="badge text-bg-warning text-dark ms-1"><?= View::e(__('settings.vm_incomplete')) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <form method="post" action="/account/vm/ping" class="m-0"
              data-loading="<?= View::e(__('settings.vm_ping_loading')) ?>">
            <?= $csrf->field() ?>
            <button type="submit" class="btn btn-outline-primary btn-sm" <?= $vmConfigured ? '' : 'disabled title="' . View::e(__('settings.vm_ping_disabled')) . '"' ?>>
                <?= View::e(__('settings.vm_ping')) ?>
            </button>
        </form>
    </div>

    <form method="post" action="/account/vm" class="row g-3">
        <?= $csrf->field() ?>

        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch"
                       name="enabled" id="enabled" value="1"
                    <?= !empty($vm['enabled']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="enabled"><?= View::e(__('settings.vm_enabled')) ?></label>
            </div>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="host"><?= View::e(__('settings.vm_host')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <input class="form-control" name="host" id="host" required
                   value="<?= View::e((string) ($vm['host'] ?? '')) ?>"
                   placeholder="192.168.1.1">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="port"><?= View::e(__('settings.vm_port')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <input class="form-control" type="number" name="port" id="port" required min="1" max="65535"
                   value="<?= (int) ($vm['port'] ?? 22) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="username"><?= View::e(__('settings.vm_username')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <input class="form-control" name="username" id="username" required
                   value="<?= View::e((string) ($vm['username'] ?? '')) ?>"
                   placeholder="admin" autocomplete="username">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="password"><?= View::e(__('settings.vm_password')) ?><?= $passwordSet ? '' : ' ' . View::e(__('common.required_mark')) ?></label>
            <input class="form-control" type="password" name="password" id="password"
                   value=""
                   placeholder="<?= View::e($passwordSet ? __('settings.vm_password_keep') : __('settings.vm_password_placeholder')) ?>"
                   autocomplete="new-password"
                <?= $passwordSet ? '' : 'required' ?>>
            <?php if ($passwordSet): ?>
                <div class="form-text"><?= View::e(__('settings.vm_password_help')) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-8">
            <label class="form-label" for="remote_dir"><?= View::e(__('settings.vm_remote_dir')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <input class="form-control" name="remote_dir" id="remote_dir" required
                   value="<?= View::e((string) ($vm['remote_dir'] ?? 'C:\\SCCM_Lab')) ?>"
                   placeholder="C:\SCCM_Lab">
            <div class="form-text"><?= View::e(__('settings.vm_remote_dir_help')) ?></div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="timeout_seconds"><?= View::e(__('settings.vm_timeout')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <input class="form-control" type="number" name="timeout_seconds" id="timeout_seconds"
                   required min="60" max="7200"
                   value="<?= (int) ($vm['timeout_seconds'] ?? 900) ?>">
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary"><?= View::e(__('account.vm_save')) ?></button>
            <a href="/account/password" class="btn btn-outline-secondary"><?= View::e(__('nav.change_password')) ?></a>
        </div>
    </form>
</div>

<?php if (!empty($flash['vm_log'])): ?>
    <div class="panel">
        <h2 class="h6"><?= View::e(__('settings.ping_output')) ?></h2>
        <pre class="small bg-body-tertiary p-3 rounded border mb-0" style="max-height:240px;overflow:auto"><?= View::e((string) $flash['vm_log']) ?></pre>
    </div>
<?php endif; ?>
