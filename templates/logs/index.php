<?php

use App\Support\View;

/** @var list<array<string, mixed>> $logs */
/** @var array{q: string, user_id: string, action_group: string, software_id: string} $filters */
/** @var list<\App\Models\User> $users */
/** @var list<\App\Models\Software> $softwares */
/** @var array<string, string> $actionGroups */

$payloadSummary = static function (?array $data): string {
    if ($data === null || $data === []) {
        return __('common.na');
    }
    $bits = [];
    foreach (['name', 'filename', 'version', 'email', 'status', 'file', 'remote_version', 'message'] as $key) {
        if (isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null) {
            $bits[] = $key . '=' . (is_scalar($data[$key]) ? (string) $data[$key] : json_encode($data[$key]));
        }
    }
    if ($bits === []) {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded !== false ? $encoded : __('common.na');
    }

    return implode(' · ', $bits);
};
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= View::e(__('logs.title')) ?></h1>
        <p class="text-muted mb-0"><?= View::e(__('logs.lead')) ?></p>
    </div>
</div>

<div class="panel">
    <form method="get" action="/logs" id="logs-filters" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="q"><?= View::e(__('common.search')) ?></label>
            <input class="form-control" type="search" name="q" id="q"
                   value="<?= View::e($filters['q']) ?>"
                   placeholder="<?= View::e(__('logs.search_placeholder')) ?>"
                   autocomplete="off">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="user_id"><?= View::e(__('logs.actor')) ?></label>
            <select class="form-select" name="user_id" id="user_id">
                <option value=""><?= View::e(__('common.all')) ?></option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u->id ?>" <?= $filters['user_id'] === (string) $u->id ? 'selected' : '' ?>>
                        <?= View::e($u->displayName) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="action_group"><?= View::e(__('logs.type')) ?></label>
            <select class="form-select" name="action_group" id="action_group">
                <option value=""><?= View::e(__('common.all')) ?></option>
                <?php foreach ($actionGroups as $key => $label): ?>
                    <option value="<?= View::e($key) ?>" <?= $filters['action_group'] === $key ? 'selected' : '' ?>>
                        <?= View::e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="software_id"><?= View::e(__('logs.software')) ?></label>
            <select class="form-select" name="software_id" id="software_id">
                <option value=""><?= View::e(__('common.all')) ?></option>
                <?php foreach ($softwares as $sw): ?>
                    <option value="<?= (int) $sw->id ?>" <?= $filters['software_id'] === (string) $sw->id ? 'selected' : '' ?>>
                        <?= View::e($sw->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <a class="btn btn-link px-0" href="/logs"><?= View::e(__('common.reset')) ?></a>
        </div>
    </form>
</div>

<div class="panel">
    <?php if ($logs === []): ?>
        <p class="text-muted mb-0"><?= View::e(__('logs.empty')) ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th><?= View::e(__('logs.date')) ?></th>
                    <th><?= View::e(__('logs.actor')) ?></th>
                    <th><?= View::e(__('logs.action')) ?></th>
                    <th><?= View::e(__('logs.entity')) ?></th>
                    <th><?= View::e(__('logs.details')) ?></th>
                    <th><?= View::e(__('logs.ip')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $row): ?>
                    <?php
                    $action = (string) ($row['action'] ?? '');
                    $badge = 'text-bg-secondary';
                    if (str_starts_with($action, 'vm.validation.passed') || str_contains($action, 'up_to_date') || $action === 'auth.login') {
                        $badge = 'text-bg-success';
                    } elseif (str_contains($action, 'failed') || str_contains($action, 'deleted') || str_contains($action, 'error')) {
                        $badge = 'text-bg-danger';
                    } elseif (str_starts_with($action, 'vm.') || str_contains($action, 'update_available') || str_starts_with($action, 'package.')) {
                        $badge = 'text-bg-primary';
                    } elseif (str_starts_with($action, 'sync') || str_contains($action, 'sync_')) {
                        $badge = 'text-bg-info';
                    }
                    ?>
                    <tr>
                        <td class="small text-muted text-nowrap"><?= View::e((string) ($row['created_at'] ?? '')) ?></td>
                        <td>
                            <?php if (!empty($row['display_name'])): ?>
                                <strong><?= View::e((string) $row['display_name']) ?></strong>
                                <?php if (!empty($row['email'])): ?>
                                    <div class="small text-muted"><?= View::e((string) $row['email']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted"><?= View::e(__('logs.system')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $badge ?>"><?= View::e((string) ($row['action_label'] ?? $action)) ?></span>
                            <div class="small text-muted"><code><?= View::e($action) ?></code></div>
                        </td>
                        <td>
                            <?php if (!empty($row['software_url'])): ?>
                                <a href="<?= View::e((string) $row['software_url']) ?>"><?= View::e((string) ($row['entity_label'] ?? '')) ?></a>
                            <?php elseif (!empty($row['entity_label'])): ?>
                                <?= View::e((string) $row['entity_label']) ?>
                            <?php else: ?>
                                <span class="text-muted"><?= View::e(__('common.na')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small" style="max-width:28rem;word-break:break-word">
                            <?= View::e($payloadSummary($row['payload_data'] ?? null)) ?>
                        </td>
                        <td class="small text-muted"><?= View::e((string) ($row['ip_address'] ?? __('common.na'))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mb-0 mt-3"><?= View::e(__('common.entries_shown', ['count' => (string) count($logs)])) ?></p>
    <?php endif; ?>
</div>
