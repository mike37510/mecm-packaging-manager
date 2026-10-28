<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Security\ModuleAccess;
use App\Security\Role;
use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * Persistence for application users (JSON file store).
 */
final class UserRepository
{
    use JsonTimestamps;

    private JsonCollection $users;

    public function __construct(JsonStore $store)
    {
        $this->users = $store->collection('users');
    }

    public function findById(int $id): ?User
    {
        $row = $this->users->find($id);

        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $needle = mb_strtolower(trim($email));
        $row = $this->users->first(
            static fn (array $r): bool => mb_strtolower((string) ($r['email'] ?? '')) === $needle
        );

        return $row ? User::fromRow($row) : null;
    }

    /**
     * @return list<User>
     */
    public function all(): array
    {
        $rows = $this->users->all();
        usort(
            $rows,
            static fn (array $a, array $b): int => strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? ''))
        );

        return array_map(static fn (array $r) => User::fromRow($r), $rows);
    }

    /**
     * @param array{email: string, password: string, display_name: string, role: string, is_active?: bool, modules?: array<string, string>} $data
     */
    public function create(array $data): int
    {
        $now = $this->now();
        $role = Role::from((string) $data['role']);
        $modules = ModuleAccess::normalize(
            isset($data['modules']) && is_array($data['modules']) ? $data['modules'] : null,
            $role
        );

        return $this->users->insert([
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'display_name' => $data['display_name'],
            'role' => $data['role'],
            'modules' => $modules,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array{email?: string, password?: string, display_name?: string, role?: string, is_active?: bool, modules?: array<string, string>} $data
     */
    public function update(int $id, array $data): void
    {
        $row = $this->users->find($id);
        if ($row === null) {
            return;
        }

        foreach (['email', 'display_name', 'role'] as $key) {
            if (array_key_exists($key, $data)) {
                $row[$key] = $data[$key];
            }
        }

        if (array_key_exists('is_active', $data)) {
            $row['is_active'] = (bool) $data['is_active'];
        }

        if (!empty($data['password'])) {
            $row['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $role = Role::from((string) ($row['role'] ?? 'viewer'));
        if (array_key_exists('modules', $data) && is_array($data['modules'])) {
            $row['modules'] = ModuleAccess::normalize($data['modules'], $role);
        } elseif (!isset($row['modules']) || !is_array($row['modules'])) {
            $row['modules'] = ModuleAccess::defaultsForRole($role);
        } else {
            $row['modules'] = ModuleAccess::normalize($row['modules'], $role);
        }

        $row['updated_at'] = $this->now();
        $this->users->put($id, $row);
    }

    public function delete(int $id): void
    {
        $this->users->delete($id);
    }

    public function count(): int
    {
        return $this->users->count();
    }

    /**
     * Seed the bootstrap admin from config/auth.php if missing.
     * Existing accounts keep the password/name set in the UI (do not overwrite on every request).
     *
     * @param array{email: string, password_hash: string, display_name?: string} $admin
     */
    public function syncAdminFromConfig(array $admin): void
    {
        $email = trim((string) ($admin['email'] ?? ''));
        $passwordHash = (string) ($admin['password_hash'] ?? '');
        $displayName = trim((string) ($admin['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = 'Administrateur';
        }

        if ($email === '' || $passwordHash === '') {
            throw new \InvalidArgumentException('config/auth.php : email et password_hash admin requis.');
        }

        if (!str_starts_with($passwordHash, '$2y$') && !str_starts_with($passwordHash, '$2a$') && !str_starts_with($passwordHash, '$2b$')) {
            throw new \InvalidArgumentException(
                'config/auth.php : password_hash doit être un hash bcrypt (généré avec password_hash()).'
            );
        }

        $existing = $this->findByEmail($email);
        $now = $this->now();

        if ($existing === null) {
            $this->users->insert([
                'email' => $email,
                'password_hash' => $passwordHash,
                'display_name' => $displayName,
                'role' => 'admin',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $row = $this->users->find($existing->id);
        if ($row === null) {
            return;
        }

        $needsWrite = false;
        if (($row['role'] ?? '') !== 'admin') {
            $row['role'] = 'admin';
            $needsWrite = true;
        }
        if (empty($row['is_active'])) {
            $row['is_active'] = true;
            $needsWrite = true;
        }
        if ($needsWrite) {
            $row['updated_at'] = $now;
            $this->users->put($existing->id, $row);
        }
    }
}
