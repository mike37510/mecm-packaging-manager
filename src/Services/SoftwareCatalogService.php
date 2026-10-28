<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Procedure\StepPayloadValidator;
use App\Domain\Procedure\StepType;
use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryRepository;

/**
 * Export / import d’une fiche logiciel + procédure (sans binaire installateur).
 *
 * Cas d’usage : exporter un logiciel modèle, l’importer, puis ajuster nom / URL / args.
 */
final class SoftwareCatalogService
{
    public const FORMAT = 'sccm-pf-software-export';
    public const VERSION = 1;

    public function __construct(
        private readonly SoftwareService $softwares,
        private readonly ProcedureService $procedures,
        private readonly StepPayloadValidator $stepValidator,
        private readonly AuditLogRepository $auditLogs,
        private readonly ?CategoryRepository $categories = null,
    ) {
    }

    /**
     * @return array{ok: bool, error?: string, payload?: array<string, mixed>, filename?: string}
     */
    public function export(int $softwareId): array
    {
        $software = $this->softwares->find($softwareId);
        if ($software === null) {
            return ['ok' => false, 'error' => 'Logiciel introuvable.'];
        }

        $procedure = $this->procedures->getForSoftware($softwareId);
        $steps = [];
        if ($procedure !== null) {
            foreach ($procedure->steps as $step) {
                $steps[] = [
                    'type' => $step->type->value,
                    'label' => $step->label,
                    'sort_order' => $step->sortOrder,
                    'enabled' => $step->enabled,
                    'on_failure' => $step->onFailure,
                    'payload' => $step->payload,
                ];
            }
            usort(
                $steps,
                static fn (array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order'])
            );
        }

        $payload = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => date('c'),
            'source' => [
                'id' => $software->id,
                'name' => $software->name,
            ],
            'software' => [
                'name' => $software->name,
                'publisher' => $software->publisher,
                'category' => $software->category,
                'description' => $software->description,
                'current_version' => $software->currentVersion,
                'update_frequency_days' => $software->updateFrequencyDays,
                'download_url' => $software->downloadUrl,
                'version_check_url' => $software->versionCheckUrl,
                'documentation_url' => $software->documentationUrl,
                'detection_method' => $software->detectionMethod,
                'architecture' => $software->architecture,
                'installer_type' => $software->installerType,
            ],
            'procedure' => [
                'name' => $procedure?->name ?? ('Procédure ' . $software->name),
                'notes' => $procedure?->notes,
                'steps' => $steps,
            ],
        ];

        $slug = preg_replace('/[^\w\-]+/', '_', $software->name) ?: 'software';
        $filename = 'software_' . $slug . '_' . date('Ymd_His') . '.json';

        return ['ok' => true, 'payload' => $payload, 'filename' => $filename];
    }

    /**
     * @param array<string, mixed>|string $raw Decoded array or JSON string
     * @return array{ok: bool, errors: list<string>, id?: int}
     */
    public function import(array|string $raw, ?string $nameOverride, ?int $userId, ?string $ip): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return ['ok' => false, 'errors' => ['JSON invalide.']];
            }
            $raw = $decoded;
        }

        $format = (string) ($raw['format'] ?? '');
        $version = (int) ($raw['version'] ?? 0);
        if ($format !== self::FORMAT) {
            return ['ok' => false, 'errors' => ['Format non reconnu (attendu : ' . self::FORMAT . ').']];
        }
        if ($version !== self::VERSION) {
            return ['ok' => false, 'errors' => ['Version d’export non supportée (' . $version . ').']];
        }

        $softwareIn = $raw['software'] ?? null;
        if (!is_array($softwareIn)) {
            return ['ok' => false, 'errors' => ['Bloc « software » manquant.']];
        }

        if ($nameOverride !== null && trim($nameOverride) !== '') {
            $softwareIn['name'] = trim($nameOverride);
        }

        // Évite les doublons de nom trop évidents
        $baseName = trim((string) ($softwareIn['name'] ?? ''));
        if ($baseName !== '' && $this->nameExists($baseName)) {
            $softwareIn['name'] = $baseName . ' (copie)';
        }

        $catName = trim((string) ($softwareIn['category'] ?? ''));
        if ($catName !== '' && $this->categories !== null && !$this->categories->exists($catName)) {
            $this->categories->create($catName);
        }

        $created = $this->softwares->create($softwareIn, null, $userId, $ip);
        if (!($created['ok'] ?? false) || empty($created['id'])) {
            return ['ok' => false, 'errors' => $created['errors'] ?? ['Création du logiciel impossible.']];
        }

        $newId = (int) $created['id'];
        $procIn = is_array($raw['procedure'] ?? null) ? $raw['procedure'] : [];
        $procName = trim((string) ($procIn['name'] ?? '')) ?: null;
        $procNotes = isset($procIn['notes']) ? (string) $procIn['notes'] : null;
        if ($procName !== null || $procNotes !== null) {
            $this->procedures->updateMeta(
                $newId,
                $procName ?? ('Procédure ' . ($softwareIn['name'] ?? '')),
                $procNotes,
                $userId,
                $ip
            );
        }

        $steps = $procIn['steps'] ?? [];
        if (!is_array($steps)) {
            $steps = [];
        }

        $stepErrors = [];
        foreach ($steps as $idx => $step) {
            if (!is_array($step)) {
                $stepErrors[] = 'Étape #' . ($idx + 1) . ' invalide.';
                continue;
            }
            $type = (string) ($step['type'] ?? '');
            try {
                StepType::from($type);
            } catch (\ValueError) {
                $stepErrors[] = 'Étape #' . ($idx + 1) . ' : type inconnu « ' . $type . ' ».';
                continue;
            }

            $payload = is_array($step['payload'] ?? null) ? $step['payload'] : [];
            $input = array_merge($payload, [
                'step_type' => $type,
                'label' => $step['label'] ?? null,
                'on_failure' => (($step['on_failure'] ?? 'stop') === 'continue') ? 'continue' : 'stop',
                'enabled' => array_key_exists('enabled', $step)
                    ? (bool) $step['enabled']
                    : true,
            ]);

            // Pré-validation pour message clair
            $check = $this->stepValidator->validate(StepType::from($type), $input);
            if (!$check['ok']) {
                foreach ($check['errors'] as $err) {
                    $stepErrors[] = 'Étape #' . ($idx + 1) . ' : ' . $err;
                }
                continue;
            }

            $added = $this->procedures->addStep($newId, $input, $userId, $ip);
            if (!($added['ok'] ?? false)) {
                foreach ($added['errors'] ?? ['échec'] as $err) {
                    $stepErrors[] = 'Étape #' . ($idx + 1) . ' : ' . $err;
                }
            }
        }

        $this->auditLogs->log(
            $userId,
            'software.catalog_imported',
            'software',
            $newId,
            [
                'source_id' => $raw['source']['id'] ?? null,
                'source_name' => $raw['source']['name'] ?? null,
                'steps' => count($steps),
                'step_errors' => count($stepErrors),
            ],
            $ip
        );

        if ($stepErrors !== []) {
            return [
                'ok' => true,
                'errors' => $stepErrors,
                'id' => $newId,
            ];
        }

        return ['ok' => true, 'errors' => [], 'id' => $newId];
    }

    private function nameExists(string $name): bool
    {
        $needle = mb_strtolower(trim($name));
        foreach ($this->softwares->search([]) as $s) {
            if (mb_strtolower($s->name) === $needle) {
                return true;
            }
        }

        return false;
    }
}
