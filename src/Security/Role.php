<?php

declare(strict_types=1);

namespace App\Security;

/**
 * User roles and permission helpers.
 */
enum Role: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $r) => $r->value, self::cases());
    }

    public function canWrite(): bool
    {
        return $this === self::Admin || $this === self::Operator;
    }

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('role.admin'),
            self::Operator => __('role.operator'),
            self::Viewer => __('role.viewer'),
        };
    }
}