<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\Session;

/**
 * CSRF token generation and validation (double-submit via session).
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public function validate(?string $token): bool
    {
        $sessionToken = Session::get(self::SESSION_KEY);
        if (!is_string($sessionToken) || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}
