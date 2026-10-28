<?php

use App\Security\ModuleAccess;
use App\Security\Role;
use App\Support\View;

/** @var list<\App\Models\User> $users */
/** @var list<\App\Security\Role> $roles */
/** @var list<string> $modules */
/** @var list<string> $accessLevels */
/** @var \App\Security\Csrf $csrf */
/** @var \App\Models\User|null $currentUser */
/** @var string $protectedAdminEmail */
$protectedAdminEmail = $protectedAdminEmail ?? '';
$modules = $modules ?? ModuleAccess::modules();
$accessLevels = $accessLevels ?? ModuleAccess::levels();

$levelOptionsJson = [];
foreach ($accessLevels as $level) {
    $levelOptionsJson[] = ['value' => $level, 'label' => ModuleAccess::label($level)];
}
$moduleMetaJson = [];
foreach ($modules as $module) {
    $moduleMetaJson[] = ['id' => $module, 'label' => ModuleAccess::moduleLabel($module)];
}
?>
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
        <h1 class="h3 mb-1"><?= View::e(__('users.title')) ?></h1>
        <p class="text-muted mb-0"><?= View::e(__('users.lead')) ?></p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#user-create-modal">
        <?= View::e(__('users.create_title')) ?>
    </button>
</div>

<div class="panel p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0 users-table">
            <thead>
            <tr>
                <th><?= View::e(__('users.name')) ?></th>
                <th><?= View::e(__('users.email')) ?></th>
                <th><?= View::e(__('users.role')) ?></th>
                <th><?= View::e(__('users.access_title')) ?></th>
                <th><?= View::e(__('users.active')) ?></th>
                <th style="width:7rem"><?= View::e(__('common.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php
                $isSelf = $currentUser && $user->id === $currentUser->id;
                $isProtected = $protectedAdminEmail !== ''
                    && mb_strtolower($user->email) === $protectedAdminEmail;
                $isAdmin = $user->role === Role::Admin;
                $modulesPayload = [];
                foreach ($modules as $module) {
                    $modulesPayload[$module] = $user->moduleLevel($module);
                }
                ?>
                <tr class="user-row"
                    role="button"
                    tabindex="0"
                    data-bs-toggle="modal"
                    data-bs-target="#user-edit-modal"
                    data-user-id="<?= (int) $user->id ?>"
                    data-display-name="<?= View::e($user->displayName) ?>"
                    data-email="<?= View::e($user->email) ?>"
                    data-role="<?= View::e($user->role->value) ?>"
                    data-active="<?= $user->active ? '1' : '0' ?>"
                    data-is-admin="<?= $isAdmin ? '1' : '0' ?>"
                    data-is-protected="<?= $isProtected ? '1' : '0' ?>"
                    data-is-self="<?= $isSelf ? '1' : '0' ?>"
                    data-modules="<?= View::e(json_encode($modulesPayload, JSON_UNESCAPED_UNICODE)) ?>">
                    <td>
                        <span class="fw-semibold user-row-name"><?= View::e($user->displayName) ?></span>
                        <?php if ($isProtected): ?>
                            <span class="badge text-bg-secondary ms-1" title="<?= View::e(__('users.config_title')) ?>"><?= View::e(__('users.config_badge')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-break"><?= View::e($user->email) ?></td>
                    <td><span class="badge text-bg-secondary"><?= View::e($user->role->label()) ?></span></td>
                    <td>
                        <?php if ($isAdmin): ?>
                            <span class="badge text-bg-primary"><?= View::e(__('users.access_full_admin')) ?></span>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($modules as $module): ?>
                                    <?php
                                    $lvl = $user->moduleLevel($module);
                                    $badgeClass = match ($lvl) {
                                        ModuleAccess::LEVEL_WRITE => 'text-bg-success',
                                        ModuleAccess::LEVEL_READ => 'text-bg-info',
                                        default => 'text-bg-secondary',
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>" title="<?= View::e(ModuleAccess::moduleLabel($module)) ?>">
                                        <?= View::e(ModuleAccess::moduleLabel($module)) ?>: <?= View::e(ModuleAccess::label($lvl)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= View::e($user->active ? __('common.yes') : __('common.no')) ?></td>
                    <td class="user-row-actions" onclick="event.stopPropagation()">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#user-edit-modal"
                                data-user-id="<?= (int) $user->id ?>"
                                data-display-name="<?= View::e($user->displayName) ?>"
                                data-email="<?= View::e($user->email) ?>"
                                data-role="<?= View::e($user->role->value) ?>"
                                data-active="<?= $user->active ? '1' : '0' ?>"
                                data-is-admin="<?= $isAdmin ? '1' : '0' ?>"
                                data-is-protected="<?= $isProtected ? '1' : '0' ?>"
                                data-is-self="<?= $isSelf ? '1' : '0' ?>"
                                data-modules="<?= View::e(json_encode($modulesPayload, JSON_UNESCAPED_UNICODE)) ?>">
                            <?= View::e(__('common.edit')) ?>
                        </button>
                        <?php if (!$isSelf && !$isProtected): ?>
                            <form method="post" action="/users/<?= (int) $user->id ?>/delete" class="d-inline m-0"
                                  onsubmit="return confirm(<?= json_encode(__('users.confirm_delete', ['email' => $user->email]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);">
                                <?= $csrf->field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit"><?= View::e(__('common.delete')) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="user-create-modal" tabindex="-1" aria-labelledby="user-create-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="/users" id="user-create-form">
                <?= $csrf->field() ?>
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="user-create-title"><?= View::e(__('users.create_title')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= View::e(__('common.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="create_display_name"><?= View::e(__('users.display_name')) ?></label>
                            <input class="form-control" name="display_name" id="create_display_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="create_email"><?= View::e(__('users.email')) ?></label>
                            <input class="form-control" type="email" name="email" id="create_email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="create_password"><?= View::e(__('users.password')) ?></label>
                            <input class="form-control" type="password" name="password" id="create_password" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="create_role"><?= View::e(__('users.role')) ?></label>
                            <select class="form-select" name="role" id="create_role">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= View::e($role->value) ?>" <?= $role === Role::Operator ? 'selected' : '' ?>><?= View::e($role->label()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div id="create-access-block">
                        <h3 class="h6 mb-1"><?= View::e(__('users.access_title')) ?></h3>
                        <p class="small text-muted mb-3"><?= View::e(__('users.access_lead')) ?></p>
                        <div class="row g-3" id="create-modules">
                            <?php foreach ($modules as $module): ?>
                                <div class="col-md-4">
                                    <label class="form-label" for="create_mod_<?= View::e($module) ?>"><?= View::e(ModuleAccess::moduleLabel($module)) ?></label>
                                    <select class="form-select" name="modules[<?= View::e($module) ?>]" id="create_mod_<?= View::e($module) ?>">
                                        <?php foreach ($accessLevels as $level): ?>
                                            <option value="<?= View::e($level) ?>" <?= $level === ModuleAccess::LEVEL_WRITE ? 'selected' : '' ?>><?= View::e(ModuleAccess::label($level)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="create-access-admin" class="alert alert-info small mb-0" hidden>
                        <?= View::e(__('users.access_full_admin')) ?> — <?= View::e(__('users.access_admin_note')) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= View::e(__('common.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><?= View::e(__('users.create')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="user-edit-modal" tabindex="-1" aria-labelledby="user-edit-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="/users/0" id="user-edit-form">
                <?= $csrf->field() ?>
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="user-edit-title"><?= View::e(__('users.edit_title')) ?></h2>
                        <p class="text-muted small mb-0" id="user-edit-subtitle"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= View::e(__('common.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="edit_display_name"><?= View::e(__('users.display_name')) ?></label>
                            <input class="form-control" name="display_name" id="edit_display_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit_email"><?= View::e(__('users.email')) ?></label>
                            <input class="form-control" type="email" name="email" id="edit_email" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_role"><?= View::e(__('users.role')) ?></label>
                            <select class="form-select" name="role" id="edit_role">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= View::e($role->value) ?>"><?= View::e($role->label()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_is_active"><?= View::e(__('users.active')) ?></label>
                            <select class="form-select" name="is_active" id="edit_is_active">
                                <option value="1"><?= View::e(__('common.yes')) ?></option>
                                <option value="0"><?= View::e(__('common.no')) ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_password"><?= View::e(__('users.password_new')) ?></label>
                            <input class="form-control" type="password" name="password" id="edit_password"
                                   placeholder="<?= View::e(__('users.password_optional')) ?>" autocomplete="new-password" minlength="8">
                        </div>
                    </div>
                    <hr class="my-3">
                    <div id="edit-access-block">
                        <h3 class="h6 mb-1"><?= View::e(__('users.access_title')) ?></h3>
                        <p class="small text-muted mb-3"><?= View::e(__('users.modal_access_lead')) ?></p>
                        <div class="row g-3" id="edit-modules">
                            <?php foreach ($modules as $module): ?>
                                <div class="col-md-4">
                                    <label class="form-label" for="edit_mod_<?= View::e($module) ?>"><?= View::e(ModuleAccess::moduleLabel($module)) ?></label>
                                    <select class="form-select" name="modules[<?= View::e($module) ?>]" id="edit_mod_<?= View::e($module) ?>">
                                        <?php foreach ($accessLevels as $level): ?>
                                            <option value="<?= View::e($level) ?>"><?= View::e(ModuleAccess::label($level)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="edit-access-admin" class="alert alert-info small mb-0" hidden>
                        <?= View::e(__('users.access_full_admin')) ?> — <?= View::e(__('users.access_admin_note')) ?>
                        <?php foreach ($modules as $module): ?>
                            <input type="hidden" name="modules[<?= View::e($module) ?>]" value="<?= View::e(ModuleAccess::LEVEL_WRITE) ?>" disabled data-admin-module="<?= View::e($module) ?>">
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <div id="edit-delete-slot"></div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= View::e(__('common.cancel')) ?></button>
                        <button type="submit" class="btn btn-primary"><?= View::e(__('users.save')) ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
  var editModalEl = document.getElementById('user-edit-modal');
  var editForm = document.getElementById('user-edit-form');
  if (!editModalEl || !editForm) return;

  var modulesMeta = <?= json_encode($moduleMetaJson, JSON_UNESCAPED_UNICODE) ?>;

  function parseModules(raw) {
    if (!raw) return {};
    try { return JSON.parse(raw); } catch (e) { return {}; }
  }

  function setAccessUi(isAdmin, modulesMap) {
    var block = document.getElementById('edit-access-block');
    var adminBox = document.getElementById('edit-access-admin');
    var moduleSelects = editForm.querySelectorAll('#edit-modules select');
    var adminHiddens = editForm.querySelectorAll('[data-admin-module]');

    if (isAdmin) {
      if (block) block.hidden = true;
      if (adminBox) adminBox.hidden = false;
      moduleSelects.forEach(function (sel) { sel.disabled = true; });
      adminHiddens.forEach(function (inp) { inp.disabled = false; });
    } else {
      if (block) block.hidden = false;
      if (adminBox) adminBox.hidden = true;
      moduleSelects.forEach(function (sel) { sel.disabled = false; });
      adminHiddens.forEach(function (inp) { inp.disabled = true; });
      modulesMeta.forEach(function (m) {
        var sel = document.getElementById('edit_mod_' + m.id);
        if (sel && modulesMap[m.id]) sel.value = modulesMap[m.id];
      });
    }
  }

  function fillEditModal(el) {
    var id = el.getAttribute('data-user-id');
    var name = el.getAttribute('data-display-name') || '';
    var email = el.getAttribute('data-email') || '';
    var role = el.getAttribute('data-role') || 'operator';
    var active = el.getAttribute('data-active') || '1';
    var isAdmin = el.getAttribute('data-is-admin') === '1' || role === 'admin';
    var isProtected = el.getAttribute('data-is-protected') === '1';
    var isSelf = el.getAttribute('data-is-self') === '1';
    var modulesMap = parseModules(el.getAttribute('data-modules'));

    editForm.action = '/users/' + id;
    document.getElementById('edit_display_name').value = name;
    document.getElementById('user-edit-subtitle').textContent = email;
    var emailInput = document.getElementById('edit_email');
    emailInput.value = email;
    emailInput.readOnly = isProtected;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_is_active').value = active;
    document.getElementById('edit_password').value = '';

    setAccessUi(isAdmin, modulesMap);

    var slot = document.getElementById('edit-delete-slot');
    slot.innerHTML = '';
    if (!isSelf && !isProtected) {
      var del = document.createElement('button');
      del.type = 'button';
      del.className = 'btn btn-outline-danger';
      del.textContent = <?= json_encode(__('common.delete'), JSON_UNESCAPED_UNICODE) ?>;
      del.addEventListener('click', function () {
        var msg = <?= json_encode(__('users.confirm_delete', ['email' => '__EMAIL__']), JSON_UNESCAPED_UNICODE) ?>;
        if (!confirm(msg.replace('__EMAIL__', email))) return;
        var f = document.createElement('form');
        f.method = 'post';
        f.action = '/users/' + id + '/delete';
        var csrf = editForm.querySelector('input[name="_csrf"]');
        if (csrf) {
          var c = document.createElement('input');
          c.type = 'hidden';
          c.name = '_csrf';
          c.value = csrf.value;
          f.appendChild(c);
        }
        document.body.appendChild(f);
        f.submit();
      });
      slot.appendChild(del);
    }
  }

  editModalEl.addEventListener('show.bs.modal', function (event) {
    var trigger = event.relatedTarget;
    if (!trigger || !trigger.getAttribute('data-user-id')) return;
    fillEditModal(trigger);
  });

  document.querySelectorAll('tr.user-row').forEach(function (row) {
    row.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        row.click();
      }
    });
  });

  var createRole = document.getElementById('create_role');
  var createBlock = document.getElementById('create-access-block');
  var createAdmin = document.getElementById('create-access-admin');
  function syncCreateAccess() {
    var admin = createRole && createRole.value === 'admin';
    if (createBlock) createBlock.hidden = !!admin;
    if (createAdmin) createAdmin.hidden = !admin;
    document.querySelectorAll('#create-modules select').forEach(function (sel) {
      sel.disabled = !!admin;
    });
  }
  if (createRole) {
    createRole.addEventListener('change', syncCreateAccess);
    syncCreateAccess();
  }

  document.getElementById('edit_role').addEventListener('change', function () {
    var isAdmin = this.value === 'admin';
    var map = {};
    modulesMeta.forEach(function (m) {
      var sel = document.getElementById('edit_mod_' + m.id);
      map[m.id] = sel ? sel.value : 'read';
    });
    setAccessUi(isAdmin, map);
  });
})();
</script>

<style>
.users-table .user-row { cursor: pointer; }
.users-table .user-row:hover { background: var(--bs-tertiary-bg, rgba(125,125,125,.08)); }
.users-table .user-row-name { text-decoration: underline; text-underline-offset: .15em; }
.users-table .user-row-actions { white-space: nowrap; }
.users-table .user-row-actions .btn { margin-right: .25rem; }
</style>
