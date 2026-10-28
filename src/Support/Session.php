<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Flash session helpers and session bootstrap.
 */
final class Session
{
    /**
     * Keep PHP sessions and temp files on the app storage volume, not /tmp.
     * /tmp often lives on a small Docker overlay that can fill independently.
     */
    public static function prepareRuntimeTemp(?string $appRoot = null): void
    {
        $root = $appRoot ?? dirname(__DIR__, 2);
        $cache = $root . '/storage/cache';
        $sessions = $cache . '/sessions';
        $tmp = $cache . '/tmp';

        foreach ([$sessions, $tmp] as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
                continue;
            }
        }

        if (is_dir($sessions) && is_writable($sessions)) {
            session_save_path($sessions);
        }
        if (is_dir($tmp) && is_writable($tmp)) {
            ini_set('sys_temp_dir', $tmp);
            ini_set('upload_tmp_dir', $tmp);
            putenv('TMPDIR=' . $tmp);
        }
    }

    public static function start(string $name): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        self::prepareRuntimeTemp();

        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function allFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flash) ? $flash : [];
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }
}
