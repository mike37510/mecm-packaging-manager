<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Packaging procedure belonging to a software.
 */
final class PackagingProcedure
{
    /**
     * @param list<ProcedureStep> $steps
     */
    public function __construct(
        public readonly int $id,
        public readonly int $softwareId,
        public readonly string $name,
        public readonly bool $isActive,
        public readonly ?string $notes,
        public readonly array $steps = [],
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param list<ProcedureStep> $steps
     */
    public static function fromRow(array $row, array $steps = []): self
    {
        return new self(
            id: (int) $row['id'],
            softwareId: (int) $row['software_id'],
            name: (string) $row['name'],
            isActive: (bool) $row['is_active'],
            notes: $row['notes'] !== null ? (string) $row['notes'] : null,
            steps: $steps,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }
}
