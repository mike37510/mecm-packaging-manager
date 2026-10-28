<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ensure directories stay writable by the web user (www-data).
 *
 * CLI scripts run as root often leave root-owned package/export dirs;
 * the pipeline worker then fails with "permissions".
 */
final class WebFs
{
    public static function webUser(): string
    {
        return (string) (getenv('WEB_USER') ?: 'www-data');
    }

    public static function webGroup(): string
    {
        return (string) (getenv('WEB_GROUP') ?: 'www-data');
    }

    public static function isRoot(): bool
    {
        return function_exists('posix_geteuid') && posix_geteuid() === 0;
    }

    /**
     * Create directory if needed, chmod, and when running as root chown to the web user.
     */
    public static function ensureWritableDirectory(string $dir): bool
    {
        $dir = rtrim($dir, '/');
        if ($dir === '') {
            return false;
        }

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        // setgid so new files inherit the web group when possible
        @chmod($dir, 02775);

        if (!is_writable($dir) && self::isRoot()) {
            self::chownPath($dir, self::webUser(), self::webGroup());
            @chmod($dir, 02775);
        }

        if (!is_writable($dir)) {
            return false;
        }

        $probe = $dir . '/.write_probe_' . getmypid();
        if (@file_put_contents($probe, 'ok') === false) {
            if (self::isRoot()) {
                self::chownPath($dir, self::webUser(), self::webGroup());
                @chmod($dir, 02775);
                if (@file_put_contents($probe, 'ok') === false) {
                    return false;
                }
            } else {
                return false;
            }
        }
        @unlink($probe);

        return true;
    }

    /**
     * After a root CLI write, hand the path back to the web user.
     */
    public static function reclaimForWeb(string $path): void
    {
        if (!self::isRoot() || $path === '' || !file_exists($path)) {
            return;
        }
        self::chownPath($path, self::webUser(), self::webGroup());
        @chmod($path, is_dir($path) ? 02775 : 0664);
    }

    /**
     * Recursively reclaim a tree (packages/, exports/, …) when CLI ran as root.
     */
    public static function reclaimTreeForWeb(string $tree): void
    {
        if (!self::isRoot() || $tree === '' || !is_dir($tree)) {
            return;
        }
        $user = self::webUser();
        $group = self::webGroup();
        $cmd = 'chown -R '
            . escapeshellarg($user . ':' . $group) . ' '
            . escapeshellarg($tree) . ' 2>/dev/null';
        exec($cmd);
    }

    private static function chownPath(string $path, string $user, string $group): bool
    {
        $uid = function_exists('posix_getpwnam') ? (@posix_getpwnam($user)['uid'] ?? null) : null;
        $gid = function_exists('posix_getgrnam') ? (@posix_getgrnam($group)['gid'] ?? null) : null;
        if ($uid === null || $gid === null) {
            return false;
        }

        return @chown($path, $uid) && @chgrp($path, $gid);
    }
}
