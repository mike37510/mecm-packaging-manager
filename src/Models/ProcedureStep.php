<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Procedure\StepType;

/**
 * A single ordered step within a packaging procedure.
 */
final class ProcedureStep
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $id,
        public readonly int $procedureId,
        public readonly StepType $type,
        public readonly int $sortOrder,
        public readonly bool $enabled,
        public readonly string $onFailure,
        public readonly array $payload,
        public readonly ?string $label = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $payload = $row['payload'] ?? '{}';
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        return new self(
            id: (int) $row['id'],
            procedureId: (int) $row['procedure_id'],
            type: StepType::from((string) $row['step_type']),
            sortOrder: (int) $row['sort_order'],
            enabled: (bool) $row['enabled'],
            onFailure: (string) $row['on_failure'],
            payload: $payload,
            label: $row['label'] !== null ? (string) $row['label'] : null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }

    public function preview(): string
    {
        $p = $this->payload;

        return match ($this->type) {
            StepType::InstallCommand, StepType::UninstallCommand => trim(($p['command'] ?? '') . ' ' . ($p['silent_args'] ?? '')),
            StepType::CopyFile => sprintf('Copy %s → %s', $p['source'] ?? '', $p['destination'] ?? ''),
            StepType::DeleteFile => 'Delete ' . ($p['path'] ?? ''),
            StepType::Rename => sprintf('Rename %s → %s', $p['from_path'] ?? '', $p['to_path'] ?? ''),
            StepType::CreateShortcut => sprintf('Shortcut %s → %s', $p['link_path'] ?? '', $p['target'] ?? ''),
            StepType::Registry => sprintf('%s %s\\%s', $p['action'] ?? '', $p['hive'] ?? '', $p['key'] ?? ''),
            StepType::Powershell => $p['script_path'] ?? (mb_substr((string) ($p['script_body'] ?? ''), 0, 80)),
            StepType::Note => mb_substr((string) ($p['text'] ?? ''), 0, 100),
        };
    }
}
