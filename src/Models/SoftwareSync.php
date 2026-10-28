<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Result of a remote installer sync / hash comparison.
 */
final class SoftwareSync
{
    public function __construct(
        public readonly int $id,
        public readonly int $softwareId,
        public readonly string $sourceUrl,
        public readonly string $fileName,
        public readonly ?string $localPath,
        public readonly string $sha256,
        public readonly int $fileSize,
        public readonly ?string $previousSha256,
        public readonly string $status,
        public readonly ?string $message,
        public readonly ?string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            softwareId: (int) $row['software_id'],
            sourceUrl: (string) $row['source_url'],
            fileName: (string) $row['file_name'],
            localPath: $row['local_path'] !== null ? (string) $row['local_path'] : null,
            sha256: (string) $row['sha256'],
            fileSize: (int) $row['file_size'],
            previousSha256: $row['previous_sha256'] !== null ? (string) $row['previous_sha256'] : null,
            status: (string) $row['status'],
            message: $row['message'] !== null ? (string) $row['message'] : null,
            createdAt: $row['created_at'] ?? null,
        );
    }

    public function isUpdateAvailable(): bool
    {
        return $this->status === 'update_available';
    }
}
