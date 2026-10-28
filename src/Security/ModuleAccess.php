<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Per-user access level for application modules (bricks).
 */
final class ModuleAccess
{
    public const MODULE_SOFTWARES = 'softwares';
    public const MODULE_DRIVERS = 'drivers';
    public const MODULE_BIOS = 'bios';

    public const LEVEL_NONE = 'none';
    public const LEVEL_READ = 'read';
    public const LEVEL_WRITE = 'write';

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return [self::MODULE_SOFTWARES, self::MODULE_DRIVERS, self::MODULE_BIOS];
    }

    /**
     * @return list<string>
     */
    public static function levels(): array
    {
        return [self::LEVEL_NONE, self::LEVEL_READ, self::LEVEL_WRITE];
    }

    public static function isValidModule(string $module): bool
    {
        return in_array($module, self::modules(), true);
    }

    public static function isValidLevel(string $level): bool
    {
        return in_array($level, self::levels(), true);
    }

    /**
     * Rank for comparisons (none < read < write).
     */
    public static function rank(string $level): int
    {
        return match ($level) {
            self::LEVEL_WRITE => 2,
            self::LEVEL_READ => 1,
            default => 0,
        };
    }

    public static function allows(string $actual, string $required): bool
    {
        return self::rank($actual) >= self::rank($required);
    }

    /**
     * Default ACL for a role when none stored yet.
     *
     * @return array{softwares: string, drivers: string, bios: string}
     */
    public static function defaultsForRole(Role $role): array
    {
        return match ($role) {
            Role::Admin => [
                self::MODULE_SOFTWARES => self::LEVEL_WRITE,
                self::MODULE_DRIVERS => self::LEVEL_WRITE,
                self::MODULE_BIOS => self::LEVEL_WRITE,
            ],
            Role::Operator => [
                self::MODULE_SOFTWARES => self::LEVEL_WRITE,
                self::MODULE_DRIVERS => self::LEVEL_WRITE,
                self::MODULE_BIOS => self::LEVEL_WRITE,
            ],
            Role::Viewer => [
                self::MODULE_SOFTWARES => self::LEVEL_READ,
                self::MODULE_DRIVERS => self::LEVEL_READ,
                self::MODULE_BIOS => self::LEVEL_READ,
            ],
        };
    }

    /**
     * Normalize a raw modules map from storage / form.
     *
     * @param array<string, mixed>|null $raw
     * @return array{softwares: string, drivers: string, bios: string}
     */
    public static function normalize(?array $raw, Role $role): array
    {
        $defaults = self::defaultsForRole($role);
        if ($role === Role::Admin) {
            return $defaults;
        }

        $out = $defaults;
        if (!is_array($raw)) {
            return $out;
        }

        foreach (self::modules() as $module) {
            $level = strtolower(trim((string) ($raw[$module] ?? '')));
            if (self::isValidLevel($level)) {
                $out[$module] = $level;
            }
        }

        return $out;
    }

    public static function label(string $level): string
    {
        return match ($level) {
            self::LEVEL_WRITE => __('users.access_write'),
            self::LEVEL_READ => __('users.access_read'),
            default => __('users.access_none'),
        };
    }

    public static function moduleLabel(string $module): string
    {
        return match ($module) {
            self::MODULE_SOFTWARES => __('nav.softwares'),
            self::MODULE_DRIVERS => __('nav.drivers'),
            self::MODULE_BIOS => __('nav.bios'),
            default => $module,
        };
    }
}
