<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * Application audit / activity log (JSON file store).
 */
final class AuditLogRepository
{
    use JsonTimestamps;

    private JsonCollection $logs;
    private JsonCollection $users;

    public function __construct(JsonStore $store)
    {
        $this->logs = $store->collection('audit_logs');
        $this->users = $store->collection('users');
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function log(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $payload = null,
        ?string $ip = null,
    ): void {
        $this->logs->insert([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'ip_address' => $ip,
            'created_at' => $this->now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 20): array
    {
        $rows = $this->logs->all();
        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));

            return $cmp !== 0 ? $cmp : ((int) $b['id']) <=> ((int) $a['id']);
        });

        return array_map(
            fn (array $row): array => $this->enrich($row),
            array_slice($rows, 0, max(0, $limit))
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        $rows = $this->logs->filter(
            static fn (array $r): bool => (string) ($r['entity_type'] ?? '') === $entityType
                && (int) ($r['entity_id'] ?? 0) === $entityId
        );
        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));

            return $cmp !== 0 ? $cmp : ((int) $b['id']) <=> ((int) $a['id']);
        });

        return array_map(
            fn (array $row): array => $this->enrich($row),
            array_slice($rows, 0, max(0, $limit))
        );
    }

    /**
     * Global search / filter for the Logs page.
     *
     * @param array{
     *   q?: string,
     *   user_id?: int,
     *   action_group?: string,
     *   software_id?: int
     * } $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters, int $limit = 300): array
    {
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $userId = isset($filters['user_id']) ? (int) $filters['user_id'] : 0;
        $group = trim((string) ($filters['action_group'] ?? ''));
        $softwareId = isset($filters['software_id']) ? (int) $filters['software_id'] : 0;

        $rows = $this->logs->filter(function (array $r) use ($q, $userId, $group, $softwareId): bool {
            if ($userId > 0 && (int) ($r['user_id'] ?? 0) !== $userId) {
                return false;
            }
            if ($softwareId > 0) {
                if ((string) ($r['entity_type'] ?? '') !== 'software' || (int) ($r['entity_id'] ?? 0) !== $softwareId) {
                    return false;
                }
            }
            $action = (string) ($r['action'] ?? '');
            if ($group !== '' && !$this->matchesActionGroup($action, $group)) {
                return false;
            }
            if ($q !== '') {
                $hay = mb_strtolower(
                    $action . ' '
                    . (string) ($r['entity_type'] ?? '') . ' '
                    . (string) ($r['entity_id'] ?? '') . ' '
                    . (is_array($r['payload'] ?? null) ? json_encode($r['payload']) : (string) ($r['payload'] ?? ''))
                    . ' '
                    . (string) ($r['ip_address'] ?? '')
                );
                $user = isset($r['user_id']) ? $this->users->find((int) $r['user_id']) : null;
                if (is_array($user)) {
                    $hay .= ' ' . mb_strtolower((string) ($user['display_name'] ?? '') . ' ' . (string) ($user['email'] ?? ''));
                }
                if (!str_contains($hay, $q)) {
                    return false;
                }
            }

            return true;
        });

        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));

            return $cmp !== 0 ? $cmp : ((int) $b['id']) <=> ((int) $a['id']);
        });

        return array_map(
            fn (array $row): array => $this->enrich($row),
            array_slice($rows, 0, max(0, $limit))
        );
    }

    private function matchesActionGroup(string $action, string $group): bool
    {
        return match ($group) {
            'auth' => str_starts_with($action, 'auth.'),
            'software' => str_starts_with($action, 'software.') && !str_contains($action, 'sync_'),
            'sync' => str_contains($action, 'sync_'),
            'package' => str_starts_with($action, 'package.'),
            'vm' => str_starts_with($action, 'vm.'),
            'procedure' => str_starts_with($action, 'procedure.'),
            'user' => str_starts_with($action, 'user.'),
            'settings' => str_starts_with($action, 'settings.'),
            default => true,
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enrich(array $row): array
    {
        $userId = isset($row['user_id']) ? (int) $row['user_id'] : 0;
        $user = $userId > 0 ? $this->users->find($userId) : null;
        $row['display_name'] = $user['display_name'] ?? null;
        $row['email'] = $user['email'] ?? null;

        // Keep payload as JSON string for templates that json_decode historically.
        if (isset($row['payload']) && is_array($row['payload'])) {
            $row['payload'] = json_encode($row['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $row;
    }
}
