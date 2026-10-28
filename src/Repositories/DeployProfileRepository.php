<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\DeployProfile;
use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * One deploy profile per software (MECM metadata for deploy.json / deploy.ps1).
 */
final class DeployProfileRepository
{
    use JsonTimestamps;

    private JsonCollection $profiles;

    public function __construct(JsonStore $store)
    {
        $this->profiles = $store->collection('deploy_profiles');
    }

    public function findBySoftware(int $softwareId): ?DeployProfile
    {
        $row = $this->profiles->first(
            static fn (array $r): bool => (int) ($r['software_id'] ?? 0) === $softwareId
        );

        return $row ? DeployProfile::fromRow($row) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsert(int $softwareId, array $payload): DeployProfile
    {
        $existing = $this->findBySoftware($softwareId);
        if ($existing !== null) {
            $this->profiles->put($existing->id, [
                'id' => $existing->id,
                'software_id' => $softwareId,
                'payload' => $payload,
                'created_at' => $existing->createdAt ?? $this->now(),
                'updated_at' => $this->now(),
            ]);

            $fresh = $this->findBySoftware($softwareId);
            if ($fresh === null) {
                throw new \RuntimeException('Deploy profile introuvable après mise à jour.');
            }

            return $fresh;
        }

        $id = $this->profiles->insert([
            'software_id' => $softwareId,
            'payload' => $payload,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);

        $created = $this->profiles->find($id);
        if ($created === null) {
            throw new \RuntimeException('Deploy profile introuvable après création.');
        }

        return DeployProfile::fromRow($created);
    }
}
