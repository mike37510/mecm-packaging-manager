<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\UserRepository;
use App\Security\ModuleAccess;
use App\Security\Role;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * User administration (admin only).
 */
final class UserController extends BaseController
{
    public function index(Request $request, UserRepository $users): void
    {
        $this->auth->requireRole(Role::Admin);
        $authConfig = $this->authConfig();
        $this->render('users/index', [
            'users' => $users->all(),
            'roles' => Role::cases(),
            'modules' => ModuleAccess::modules(),
            'accessLevels' => ModuleAccess::levels(),
            'protectedAdminEmail' => mb_strtolower(trim((string) ($authConfig['admin']['email'] ?? ''))),
        ]);
    }

    public function store(Request $request, UserRepository $users, AuditLogRepository $auditLogs): void
    {
        $this->auth->requireRole(Role::Admin);
        $this->requireCsrf($request);

        $email = trim((string) $request->input('email', ''));
        $name = trim((string) $request->input('display_name', ''));
        $password = (string) $request->input('password', '');
        $role = (string) $request->input('role', 'viewer');

        $errors = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('flash.user_email_invalid');
        }
        if ($name === '') {
            $errors[] = __('flash.user_name_required');
        }
        if (strlen($password) < 8) {
            $errors[] = __('flash.user_password_short');
        }
        if (!in_array($role, Role::values(), true)) {
            $errors[] = __('flash.user_role_invalid');
        }
        if ($users->findByEmail($email) !== null) {
            $errors[] = __('flash.user_email_exists');
        }

        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            Response::redirect('/users');
        }

        $id = $users->create([
            'email' => $email,
            'password' => $password,
            'display_name' => $name,
            'role' => $role,
            'modules' => $this->modulesFromRequest($request, Role::from($role)),
            'is_active' => true,
        ]);

        $auditLogs->log($this->auth->id(), 'user.created', 'user', $id, ['email' => $email], $request->ip());
        Session::flash('success', __('flash.user_created'));
        Response::redirect('/users');
    }

    public function update(Request $request, UserRepository $users, AuditLogRepository $auditLogs, string $id): void
    {
        $this->auth->requireRole(Role::Admin);
        $this->requireCsrf($request);

        $userId = (int) $id;
        $user = $users->findById($userId);
        if ($user === null) {
            Session::flash('error', __('flash.user_not_found'));
            Response::redirect('/users');
        }

        $role = (string) $request->input('role', $user->role->value);
        if (!in_array($role, Role::values(), true)) {
            Session::flash('error', __('flash.user_role_invalid'));
            Response::redirect('/users');
        }

        $email = trim((string) $request->input('email', $user->email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', __('flash.user_email_invalid'));
            Response::redirect('/users');
        }
        $emailOwner = $users->findByEmail($email);
        if ($emailOwner !== null && $emailOwner->id !== $userId) {
            Session::flash('error', __('flash.user_email_exists'));
            Response::redirect('/users');
        }

        $authConfig = $this->authConfig();
        $protectedEmail = mb_strtolower(trim((string) ($authConfig['admin']['email'] ?? '')));
        if ($protectedEmail !== '' && mb_strtolower($user->email) === $protectedEmail && mb_strtolower($email) !== $protectedEmail) {
            Session::flash('error', __('flash.user_protected_admin_email'));
            Response::redirect('/users');
        }

        $data = [
            'email' => $email,
            'display_name' => trim((string) $request->input('display_name', $user->displayName)),
            'role' => $role,
            'modules' => $this->modulesFromRequest($request, Role::from($role)),
            'is_active' => $request->input('is_active') === '1',
        ];

        $password = (string) $request->input('password', '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                Session::flash('error', __('flash.user_password_short'));
                Response::redirect('/users');
            }
            $data['password'] = $password;
        }

        $users->update($userId, $data);
        $auditLogs->log($this->auth->id(), 'user.updated', 'user', $userId, null, $request->ip());
        Session::flash('success', __('flash.user_updated'));
        Response::redirect('/users');
    }

    public function destroy(Request $request, UserRepository $users, AuditLogRepository $auditLogs, string $id): void
    {
        $this->auth->requireRole(Role::Admin);
        $this->requireCsrf($request);

        $userId = (int) $id;
        if ($userId === $this->auth->id()) {
            Session::flash('error', __('flash.user_cannot_delete_self'));
            Response::redirect('/users');
        }

        $user = $users->findById($userId);
        if ($user === null) {
            Session::flash('error', __('flash.user_not_found'));
            Response::redirect('/users');
        }

        /** @var array{admin: array{email: string}} $authConfig */
        $authConfig = $this->authConfig();
        $protectedEmail = mb_strtolower(trim((string) ($authConfig['admin']['email'] ?? '')));
        if ($protectedEmail !== '' && mb_strtolower($user->email) === $protectedEmail) {
            Session::flash('error', __('flash.user_protected_admin'));
            Response::redirect('/users');
        }

        try {
            $auditLogs->log(
                $this->auth->id(),
                'user.deleted',
                'user',
                $userId,
                ['email' => $user->email],
                $request->ip()
            );
            $users->delete($userId);
        } catch (\Throwable $e) {
            error_log('User delete failed: ' . $e->getMessage());
            Session::flash('error', __('flash.user_delete_failed'));
            Response::redirect('/users');
        }

        Session::flash('success', __('flash.user_deleted'));
        Response::redirect('/users');
    }

    /**
     * @return array{softwares: string, drivers: string, bios: string}
     */
    private function modulesFromRequest(Request $request, Role $role): array
    {
        $raw = $request->input('modules', []);
        if (!is_array($raw)) {
            $raw = [];
        }

        return ModuleAccess::normalize($raw, $role);
    }

    /**
     * @return array{admin: array{email: string, password?: string, display_name?: string}}
     */
    private function authConfig(): array
    {
        /** @var array{admin: array{email: string, password?: string, display_name?: string}} $config */
        $config = require dirname(__DIR__, 2) . '/config/auth.php';

        return $config;
    }
}
