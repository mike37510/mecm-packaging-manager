<?php

declare(strict_types=1);

namespace App\Security;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\Session;
use App\Support\Response;

/**
 * Session-based authentication service.
 */
final class Auth
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !$user->isActive()) {
            return false;
        }

        if (!password_verify($password, $user->passwordHash)) {
            return false;
        }

        Session::regenerate();
        Session::set('user_id', $user->id);

        return true;
    }

    public function logout(): void
    {
        Session::forget('user_id');
        Session::regenerate();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?User
    {
        $id = Session::get('user_id');
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }

        return $this->users->findById((int) $id);
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user?->id;
    }

    public function requireLogin(): User
    {
        $user = $this->user();
        if ($user === null) {
            Session::flash('error', __('flash.login_required'));
            \App\Support\Response::redirect('/login');
        }

        return $user;
    }

    public function requireRole(Role ...$roles): User
    {
        $user = $this->requireLogin();
        foreach ($roles as $role) {
            if ($user->role === $role) {
                return $user;
            }
        }

        Response::abort(403, __('flash.access_denied'));
    }

    /**
     * Require access to a module brick (softwares / drivers / bios).
     *
     * @param 'softwares'|'drivers'|'bios' $module
     * @param 'read'|'write' $minLevel
     */
    public function requireModule(string $module, string $minLevel = ModuleAccess::LEVEL_READ): User
    {
        $user = $this->requireLogin();
        if (!$user->canAccessModule($module, $minLevel)) {
            Response::abort(403, __('flash.access_denied'));
        }

        return $user;
    }

    /**
     * @deprecated Prefer requireModule($module, ModuleAccess::LEVEL_WRITE)
     */
    public function requireWrite(): User
    {
        $user = $this->requireLogin();
        if (!$user->role->canWrite()) {
            Response::abort(403, __('flash.access_denied'));
        }

        return $user;
    }
}
