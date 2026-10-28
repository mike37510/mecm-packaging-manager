<?php

declare(strict_types=1);

namespace App\Models;

/**
 * MECM / SCCM deploy metadata for one software (assistant Déployer).
 *
 * @phpstan-type DeployPayload array<string, mixed>
 */
final class DeployProfile
{
    /**
     * @param DeployPayload $payload
     */
    public function __construct(
        public readonly int $id,
        public readonly int $softwareId,
        public readonly array $payload,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $payload = $row['payload'] ?? [];
        if (!is_array($payload)) {
            $payload = [];
        }

        return new self(
            id: (int) $row['id'],
            softwareId: (int) $row['software_id'],
            payload: $payload,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
