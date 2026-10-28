<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * Authentication controllers.
 */
final class AuthController extends BaseController
{
    public function loginForm(Request $request): void
    {
        if ($this->auth->check()) {
            Response::redirect('/');
        }

        $this->render('auth/login', [], 'layouts/guest');
    }

    public function login(Request $request, AuditLogRepository $auditLogs): void
    {
        $this->requireCsrf($request);

        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            Session::flash('error', __('flash.login_required_fields'));
            Response::redirect('/login');
        }

        if (!$this->auth->attempt($email, $password)) {
            $auditLogs->log(null, 'auth.login_failed', 'user', null, ['email' => $email], $request->ip());
            Session::flash('error', __('flash.login_invalid'));
            Response::redirect('/login');
        }

        $auditLogs->log($this->auth->id(), 'auth.login', 'user', $this->auth->id(), null, $request->ip());
        Response::redirect('/');
    }

    public function logout(Request $request, AuditLogRepository $auditLogs): void
    {
        $this->requireCsrf($request);
        $userId = $this->auth->id();
        $this->auth->logout();
        $auditLogs->log($userId, 'auth.logout', 'user', $userId, null, $request->ip());
        Response::redirect('/login');
    }
}
