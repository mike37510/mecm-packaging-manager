<?php

declare(strict_types=1);

namespace App\Models;

use App\Security\ModuleAccess;
use App\Security\Role;

/**
 * Authenticated application user.
 */
final class User
{
    /**
     * @param array{softwares: string, drivers: string, bios: string} $modules
     */
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $displayName,
        public readonly Role $role,
        public readonly bool $active,
        public readonly array $modules,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $role = Role::from((string) $row['role']);
        $rawModules = $row['modules'] ?? null;
        $modules = ModuleAccess::normalize(is_array($rawModules) ? $rawModules : null, $role);

        return new self(
            id: (int) $row['id'],
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            displayName: (string) $row['display_name'],
            role: $role,
            active: (bool) $row['is_active'],
            modules: $modules,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function moduleLevel(string $module): string
    {
        if ($this->role === Role::Admin) {
            return ModuleAccess::LEVEL_WRITE;
        }

        return $this->modules[$module] ?? ModuleAccess::LEVEL_NONE;
    }

    public function canAccessModule(string $module, string $minLevel = ModuleAccess::LEVEL_READ): bool
    {
        if (!ModuleAccess::isValidModule($module)) {
            return false;
        }

        return ModuleAccess::allows($this->moduleLevel($module), $minLevel);
    }

    public function canSeeModule(string $module): bool
    {
        return $this->canAccessModule($module, ModuleAccess::LEVEL_READ);
    }

    public function canWriteModule(string $module): bool
    {
        return $this->canAccessModule($module, ModuleAccess::LEVEL_WRITE);
    }
}
