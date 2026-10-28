<?php

use App\Support\View;

/** @var array<string, mixed> $permissionsReport */
/** @var string $permissionsDataRoot */
/** @var mixed $permissionsActions */
/** @var bool $permissionsNeedsSudo */
/** @var \App\Security\Csrf $csrf */

$checks = is_array($permissionsReport['checks'] ?? null) ? $permissionsReport['checks'] : [];
$summary = is_array($permissionsReport['summary'] ?? null) ? $permissionsReport['summary'] : ['ok' => 0, 'warn' => 0, 'fail' => 0];
$actions = is_array($permissionsActions) ? $permissionsActions : [];
$phpUser = (string) ($permissionsReport['php_user'] ?? '');
$overallOk = !empty($permissionsReport['ok']);
?>
<div class="panel mb-4" id="permissions">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1"><?= View::e(__('settings.permissions_title')) ?></h2>
            <p class="text-muted small mb-0"><?= View::e(__('settings.permissions_lead')) ?></p>
        </div>
        <div class="text-end small text-muted">
            <div><?= View::e(__('install.php_user')) ?>: <code><?= View::e($phpUser) ?></code></div>
            <div class="mt-1">
                <?php if ($overallOk): ?>
                    <span class="badge text-bg-success"><?= View::e(__('install.status_ok')) ?></span>
                <?php else: ?>
                    <span class="badge text-bg-danger"><?= View::e(__('install.status_fail')) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <form method="post" action="/settings/permissions/run" class="d-flex flex-wrap gap-2">
        <?= $csrf->field() ?>
        <button type="submit" class="btn btn-primary"><?= View::e(__('install.run')) ?></button>
    </form>
    <p class="small text-muted mt-3 mb-4"><?= View::e(__('install.run_help')) ?></p>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="border rounded p-3 h-100 text-center bg-body-tertiary">
                <div class="display-6 text-success"><?= (int) ($summary['ok'] ?? 0) ?></div>
                <div class="text-muted small"><?= View::e(__('install.summary_ok')) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 h-100 text-center bg-body-tertiary">
                <div class="display-6 text-warning"><?= (int) ($summary['warn'] ?? 0) ?></div>
                <div class="text-muted small"><?= View::e(__('install.summary_warn')) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 h-100 text-center bg-body-tertiary">
                <div class="display-6 text-danger"><?= (int) ($summary['fail'] ?? 0) ?></div>
                <div class="text-muted small"><?= View::e(__('install.summary_fail')) ?></div>
            </div>
        </div>
    </div>

    <h3 class="h6 mb-3"><?= View::e(__('install.checks_title')) ?></h3>
    <div class="table-responsive mb-0">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th><?= View::e(__('install.col_check')) ?></th>
                <th><?= View::e(__('install.col_status')) ?></th>
                <th><?= View::e(__('install.col_detail')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($checks as $check): ?>
                <?php if (!is_array($check)) {
                    continue;
                } ?>
                <?php
                $status = (string) ($check['status'] ?? '');
                $badge = match ($status) {
                    'ok' => 'text-bg-success',
                    'warn' => 'text-bg-warning',
                    default => 'text-bg-danger',
                };
                $labelStatus = match ($status) {
                    'ok' => __('install.status_ok'),
                    'warn' => __('install.status_warn'),
                    default => __('install.status_fail'),
                };
                ?>
                <tr>
                    <td><?= View::e((string) ($check['label'] ?? '')) ?></td>
                    <td><span class="badge <?= View::e($badge) ?>"><?= View::e($labelStatus) ?></span></td>
                    <td class="small text-muted"><code><?= View::e((string) ($check['detail'] ?? '')) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($actions !== []): ?>
        <div class="mt-4 pt-3 border-top">
            <h3 class="h6 mb-3"><?= View::e(__('install.actions_title')) ?></h3>
            <?php if (!empty($permissionsNeedsSudo)): ?>
                <div class="alert alert-warning">
                    <?= View::e(__('install.sudo_hint')) ?>
                    <pre class="mb-0 mt-2">sudo php bin/fix-permissions.php --migrate</pre>
                </div>
            <?php endif; ?>
            <ul class="list-unstyled small mb-0 install-actions">
                <?php foreach ($actions as $action): ?>
                    <?php if (!is_array($action)) {
                        continue;
                    } ?>
                    <?php
                    $st = (string) ($action['status'] ?? '');
                    $cls = match ($st) {
                        'ok' => 'text-success',
                        'warn' => 'text-warning',
                        default => 'text-danger',
                    };
                    ?>
                    <li class="<?= View::e($cls) ?>"><?= View::e((string) ($action['message'] ?? '')) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
