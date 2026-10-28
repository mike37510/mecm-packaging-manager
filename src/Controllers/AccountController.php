<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;
use App\Security\ModuleAccess;
use App\Services\VmConfigService;
use App\Services\VmValidationService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * Self-service account actions (any logged-in user).
 * VM lab config: users with Software write access.
 */
final class AccountController extends BaseController
{
    public function passwordForm(Request $request): void
    {
        $this->auth->requireLogin();
        $this->render('account/password', []);
    }

    public function changePassword(
        Request $request,
        UserRepository $users,
        AuditLogRepository $auditLogs,
    ): void {
        $user = $this->auth->requireLogin();
        $this->requireCsrf($request);

        $current = (string) $request->input('current_password', '');
        $new = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('new_password_confirm', '');

        if ($current === '' || $new === '' || $confirm === '') {
            Session::flash('error', __('flash.account_password_required'));
            Response::redirect('/account/password');
        }

        if (!password_verify($current, $user->passwordHash)) {
            $auditLogs->log($user->id, 'account.password_change_failed', 'user', $user->id, [
                'reason' => 'bad_current',
            ], $request->ip());
            Session::flash('error', __('flash.account_password_bad_current'));
            Response::redirect('/account/password');
        }

        if (strlen($new) < 8) {
            Session::flash('error', __('flash.user_password_short'));
            Response::redirect('/account/password');
        }

        if ($new !== $confirm) {
            Session::flash('error', __('flash.account_password_mismatch'));
            Response::redirect('/account/password');
        }

        if (password_verify($new, $user->passwordHash)) {
            Session::flash('error', __('flash.account_password_unchanged'));
            Response::redirect('/account/password');
        }

        $users->update($user->id, ['password' => $new]);
        $auditLogs->log($user->id, 'account.password_changed', 'user', $user->id, null, $request->ip());
        Session::regenerate();
        Session::flash('success', __('flash.account_password_changed'));
        Response::redirect('/account/password');
    }

    public function vmForm(
        Request $request,
        VmConfigService $vmConfig,
        VmValidationService $vmValidator,
    ): void {
        $user = $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $cfg = $vmConfig->getForUser($user->id);
        $this->render('account/vm', [
            'vm' => $cfg,
            'passwordSet' => $vmConfig->passwordIsSetForUser($user->id),
            'vmConfigured' => $vmValidator->isConfigured($user->id),
        ]);
    }

    public function updateVm(Request $request, VmConfigService $vmConfig): void
    {
        $user = $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $vmConfig->saveForUser($user->id, [
            'enabled' => $request->input('enabled') ? true : false,
            'host' => $request->input('host'),
            'port' => $request->input('port'),
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'remote_dir' => $request->input('remote_dir'),
            'timeout_seconds' => $request->input('timeout_seconds'),
        ], $request->ip());

        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? __('flash.settings_save_failed'));
            Response::redirect('/account/vm');
        }

        Session::flash('success', __('flash.account_vm_saved'));
        Response::redirect('/account/vm');
    }

    public function pingVm(Request $request, VmValidationService $vmValidator): void
    {
        $user = $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $vmValidator->ping($user->id);
        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? __('flash.ping_failed'));
            if (!empty($result['stdout'])) {
                Session::flash('vm_log', (string) $result['stdout']);
            }
            Response::redirect('/account/vm');
        }

        Session::flash('success', __('flash.ping_ok', [
            'stdout' => trim((string) ($result['stdout'] ?? 'PONG')),
        ]));
        Response::redirect('/account/vm');
    }
}
