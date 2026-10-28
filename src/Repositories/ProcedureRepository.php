<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PackagingProcedure;
use App\Models\ProcedureStep;
use App\Support\JsonCollection;
use App\Support\JsonStore;
use App\Support\JsonTimestamps;

/**
 * Persistence for packaging procedures and steps (JSON file store).
 */
final class ProcedureRepository
{
    use JsonTimestamps;

    private JsonCollection $procedures;
    private JsonCollection $steps;

    public function __construct(JsonStore $store)
    {
        $this->procedures = $store->collection('packaging_procedures');
        $this->steps = $store->collection('procedure_steps');
    }

    public function findActiveBySoftware(int $softwareId): ?PackagingProcedure
    {
        $matches = $this->procedures->filter(
            static fn (array $r): bool => (int) ($r['software_id'] ?? 0) === $softwareId
                && !empty($r['is_active'])
        );
        usort($matches, static fn (array $a, array $b): int => ((int) $b['id']) <=> ((int) $a['id']));
        $row = $matches[0] ?? null;
        if ($row === null) {
            return null;
        }

        return PackagingProcedure::fromRow($row, $this->stepsForProcedure((int) $row['id']));
    }

    public function findById(int $id): ?PackagingProcedure
    {
        $row = $this->procedures->find($id);
        if ($row === null) {
            return null;
        }

        return PackagingProcedure::fromRow($row, $this->stepsForProcedure($id));
    }

    /**
     * Ensure an active procedure exists for the software; create a default one if missing.
     */
    public function getOrCreateActive(int $softwareId, string $defaultName = 'Procédure principale'): PackagingProcedure
    {
        $existing = $this->findActiveBySoftware($softwareId);
        if ($existing !== null) {
            return $existing;
        }

        $now = $this->now();
        $id = $this->procedures->insert([
            'software_id' => $softwareId,
            'name' => $defaultName,
            'is_active' => true,
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findById($id) ?? throw new \RuntimeException('Failed to create procedure.');
    }

    public function updateNotes(int $procedureId, ?string $notes, ?string $name = null): void
    {
        $row = $this->procedures->find($procedureId);
        if ($row === null) {
            return;
        }
        $row['notes'] = $notes;
        if ($name !== null) {
            $row['name'] = $name;
        }
        $row['updated_at'] = $this->now();
        $this->procedures->put($procedureId, $row);
    }

    /**
     * @return list<ProcedureStep>
     */
    public function stepsForProcedure(int $procedureId): array
    {
        $rows = $this->steps->filter(
            static fn (array $r): bool => (int) ($r['procedure_id'] ?? 0) === $procedureId
        );
        usort($rows, static function (array $a, array $b): int {
            $cmp = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));

            return $cmp !== 0 ? $cmp : ((int) $a['id']) <=> ((int) $b['id']);
        });

        return array_map(static fn (array $r) => ProcedureStep::fromRow($r), $rows);
    }

    public function findStep(int $stepId): ?ProcedureStep
    {
        $row = $this->steps->find($stepId);

        return $row ? ProcedureStep::fromRow($row) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function addStep(
        int $procedureId,
        string $stepType,
        array $payload,
        string $onFailure = 'stop',
        bool $enabled = true,
        ?string $label = null,
    ): int {
        $max = 0;
        foreach ($this->steps->filter(static fn (array $r): bool => (int) ($r['procedure_id'] ?? 0) === $procedureId) as $row) {
            $max = max($max, (int) ($row['sort_order'] ?? 0));
        }

        $now = $this->now();
        $id = $this->steps->insert([
            'procedure_id' => $procedureId,
            'step_type' => $stepType,
            'sort_order' => $max + 1,
            'enabled' => $enabled,
            'on_failure' => $onFailure,
            'label' => $label,
            'payload' => $payload,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->touchProcedure($procedureId);

        return $id;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateStep(
        int $stepId,
        array $payload,
        string $onFailure,
        bool $enabled,
        ?string $label = null,
    ): void {
        $row = $this->steps->find($stepId);
        if ($row === null) {
            return;
        }

        $row['payload'] = $payload;
        $row['on_failure'] = $onFailure;
        $row['enabled'] = $enabled;
        $row['label'] = $label;
        $row['updated_at'] = $this->now();
        $this->steps->put($stepId, $row);

        $this->touchProcedure((int) $row['procedure_id']);
    }

    public function deleteStep(int $stepId): void
    {
        $step = $this->findStep($stepId);
        $this->steps->delete($stepId);
        if ($step) {
            $this->touchProcedure($step->procedureId);
        }
    }

    /**
     * @param list<int> $orderedStepIds
     */
    public function reorderSteps(int $procedureId, array $orderedStepIds): void
    {
        $order = 1;
        foreach ($orderedStepIds as $stepId) {
            $row = $this->steps->find((int) $stepId);
            if ($row === null || (int) ($row['procedure_id'] ?? 0) !== $procedureId) {
                continue;
            }
            $row['sort_order'] = $order++;
            $row['updated_at'] = $this->now();
            $this->steps->put((int) $stepId, $row);
        }

        $this->touchProcedure($procedureId);
    }

    private function touchProcedure(int $procedureId): void
    {
        $row = $this->procedures->find($procedureId);
        if ($row === null) {
            return;
        }
        $row['updated_at'] = $this->now();
        $this->procedures->put($procedureId, $row);
    }
}
