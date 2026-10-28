<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Historical generated deployment ZIP.
 */
final class PackageExport
{
    public function __construct(
        public readonly int $id,
        public readonly int $softwareId,
        public readonly string $version,
        public readonly ?string $sha256,
        public readonly string $strategy,
        public readonly ?string $installerName,
        public readonly string $filename,
        public readonly string $relativePath,
        public readonly int $fileSize,
        public readonly ?int $createdBy,
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
            version: (string) $row['version'],
            sha256: $row['sha256'] !== null ? (string) $row['sha256'] : null,
            strategy: (string) $row['strategy'],
            installerName: $row['installer_name'] !== null ? (string) $row['installer_name'] : null,
            filename: (string) $row['filename'],
            relativePath: (string) $row['relative_path'],
            fileSize: (int) $row['file_size'],
            createdBy: $row['created_by'] !== null ? (int) $row['created_by'] : null,
            createdAt: $row['created_at'] ?? null,
        );
    }

    public function strategyLabel(): string
    {
        return $this->strategy === 'msi' ? 'MSI' : 'EXE (Brave)';
    }

    public function sizeLabel(): string
    {
        $bytes = $this->fileSize;
        if ($bytes < 1024) {
            return $bytes . ' o';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' Ko';
        }

        return round($bytes / (1024 * 1024), 1) . ' Mo';
    }
}
