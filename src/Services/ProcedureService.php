<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Procedure\StepPayloadValidator;
use App\Domain\Procedure\StepType;
use App\Models\PackagingProcedure;
use App\Repositories\AuditLogRepository;
use App\Repositories\ProcedureRepository;
use App\Repositories\SoftwareRepository;

/**
 * Packaging procedure editing operations.
 */
final class ProcedureService
{
    public function __construct(
        private readonly ProcedureRepository $procedures,
        private readonly SoftwareRepository $softwares,
        private readonly StepPayloadValidator $validator,
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    public function getForSoftware(int $softwareId): ?PackagingProcedure
    {
        if ($this->softwares->findById($softwareId) === null) {
            return null;
        }

        return $this->procedures->getOrCreateActive($softwareId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: list<string>, step_id?: int}
     */
    public function addStep(int $softwareId, array $input, ?int $userId, ?string $ip): array
    {
        $procedure = $this->getForSoftware($softwareId);
        if ($procedure === null) {
            return ['ok' => false, 'errors' => ['Logiciel introuvable.']];
        }

        $typeValue = (string) ($input['step_type'] ?? '');
        try {
            $type = StepType::from($typeValue);
        } catch (\ValueError) {
            return ['ok' => false, 'errors' => ['Type d\'étape invalide.']];
        }

        $result = $this->validator->validate($type, $input);
        if (!$result['ok']) {
            return ['ok' => false, 'errors' => $result['errors']];
        }

        $onFailure = (($input['on_failure'] ?? 'stop') === 'continue') ? 'continue' : 'stop';
        $enabled = !isset($input['enabled']) || filter_var($input['enabled'], FILTER_VALIDATE_BOOLEAN);
        $label = trim((string) ($input['label'] ?? '')) ?: null;

        $stepId = $this->procedures->addStep(
            $procedure->id,
            $type->value,
            $result['payload'],
            $onFailure,
            $enabled,
            $label
        );

        $this->auditLogs->log(
            $userId,
            'procedure.step_added',
            'software',
            $softwareId,
            ['step_id' => $stepId, 'type' => $type->value],
            $ip
        );

        return ['ok' => true, 'errors' => [], 'step_id' => $stepId];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: list<string>}
     */
    public function updateStep(int $softwareId, int $stepId, array $input, ?int $userId, ?string $ip): array
    {
        $procedure = $this->getForSoftware($softwareId);
        $step = $this->procedures->findStep($stepId);
        if ($procedure === null || $step === null || $step->procedureId !== $procedure->id) {
            return ['ok' => false, 'errors' => ['Étape introuvable.']];
        }

        $result = $this->validator->validate($step->type, $input);
        if (!$result['ok']) {
            return ['ok' => false, 'errors' => $result['errors']];
        }

        $onFailure = (($input['on_failure'] ?? 'stop') === 'continue') ? 'continue' : 'stop';
        $enabled = array_key_exists('enabled', $input)
            && in_array($input['enabled'], [1, '1', true, 'true', 'on'], true);
        $label = trim((string) ($input['label'] ?? '')) ?: null;

        $this->procedures->updateStep($stepId, $result['payload'], $onFailure, $enabled, $label);
        $this->auditLogs->log(
            $userId,
            'procedure.step_updated',
            'software',
            $softwareId,
            ['step_id' => $stepId],
            $ip
        );

        return ['ok' => true, 'errors' => []];
    }

    /**
     * @return array{ok: bool, errors: list<string>}
     */
    public function deleteStep(int $softwareId, int $stepId, ?int $userId, ?string $ip): array
    {
        $procedure = $this->getForSoftware($softwareId);
        $step = $this->procedures->findStep($stepId);
        if ($procedure === null || $step === null || $step->procedureId !== $procedure->id) {
            return ['ok' => false, 'errors' => ['Étape introuvable.']];
        }

        $this->procedures->deleteStep($stepId);
        $this->auditLogs->log(
            $userId,
            'procedure.step_deleted',
            'software',
            $softwareId,
            ['step_id' => $stepId],
            $ip
        );

        return ['ok' => true, 'errors' => []];
    }

    /**
     * @param list<int|string> $orderedIds
     * @return array{ok: bool, errors: list<string>}
     */
    public function reorder(int $softwareId, array $orderedIds, ?int $userId, ?string $ip): array
    {
        $procedure = $this->getForSoftware($softwareId);
        if ($procedure === null) {
            return ['ok' => false, 'errors' => ['Logiciel introuvable.']];
        }

        $ids = array_map('intval', $orderedIds);
        $existing = array_map(static fn ($s) => $s->id, $procedure->steps);
        sort($existing);
        $check = $ids;
        sort($check);
        if ($existing !== $check) {
            return ['ok' => false, 'errors' => ['Liste d\'étapes incohérente.']];
        }

        $this->procedures->reorderSteps($procedure->id, $ids);
        $this->auditLogs->log($userId, 'procedure.reordered', 'software', $softwareId, null, $ip);

        return ['ok' => true, 'errors' => []];
    }

    /**
     * @return array{ok: bool, errors: list<string>}
     */
    public function updateMeta(int $softwareId, ?string $name, ?string $notes, ?int $userId, ?string $ip): array
    {
        $procedure = $this->getForSoftware($softwareId);
        if ($procedure === null) {
            return ['ok' => false, 'errors' => ['Logiciel introuvable.']];
        }

        $name = trim((string) $name) ?: $procedure->name;
        $this->procedures->updateNotes($procedure->id, $notes !== null ? trim($notes) : null, $name);
        $this->auditLogs->log($userId, 'procedure.updated', 'software', $softwareId, null, $ip);

        return ['ok' => true, 'errors' => []];
    }

    /**
     * Assistant guidé : crée (ou remplace) les étapes install + uninstall à partir d’un questionnaire.
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: list<string>, created?: list<int>}
     */
    public function applyWizard(int $softwareId, array $input, ?int $userId, ?string $ip): array
    {
        $software = $this->softwares->findById($softwareId);
        $procedure = $this->getForSoftware($softwareId);
        if ($software === null || $procedure === null) {
            return ['ok' => false, 'errors' => ['Logiciel introuvable.']];
        }

        $installCommand = $this->unwrapPathQuotes(trim((string) ($input['install_command'] ?? '')));
        $uninstallCommand = $this->unwrapPathQuotes(trim((string) ($input['uninstall_command'] ?? '')));
        if ($installCommand === '') {
            return ['ok' => false, 'errors' => [__('procedure.wizard_err_install_command')]];
        }
        if ($uninstallCommand === '') {
            return ['ok' => false, 'errors' => [__('procedure.wizard_err_uninstall_command')]];
        }

        $installInput = [
            'command' => $installCommand,
            'silent_args' => trim((string) ($input['install_silent_args'] ?? '')),
            'working_dir' => trim((string) ($input['install_working_dir'] ?? '')) ?: '.',
            'timeout_sec' => trim((string) ($input['install_timeout_sec'] ?? '')) ?: '600',
            'success_paths' => trim((string) ($input['success_paths'] ?? '')),
        ];
        $uninstallInput = [
            'command' => $uninstallCommand,
            'silent_args' => trim((string) ($input['uninstall_silent_args'] ?? '')),
            'product_code' => trim((string) ($input['product_code'] ?? '')),
            'timeout_sec' => trim((string) ($input['uninstall_timeout_sec'] ?? '')) ?: '600',
            'pre_kill' => trim((string) ($input['pre_kill'] ?? '')),
            'success_gone_paths' => trim((string) ($input['success_gone_paths'] ?? '')),
        ];

        $installCheck = $this->validator->validate(StepType::InstallCommand, $installInput);
        if (!$installCheck['ok']) {
            return ['ok' => false, 'errors' => $installCheck['errors']];
        }
        $uninstallCheck = $this->validator->validate(StepType::UninstallCommand, $uninstallInput);
        if (!$uninstallCheck['ok']) {
            return ['ok' => false, 'errors' => $uninstallCheck['errors']];
        }

        $replace = !empty($input['replace_core'])
            || filter_var($input['replace_core'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || $procedure->steps === [];

        if ($replace) {
            foreach ($procedure->steps as $step) {
                if (in_array($step->type, [StepType::InstallCommand, StepType::UninstallCommand], true)) {
                    $this->procedures->deleteStep($step->id);
                }
            }
        } else {
            foreach ($procedure->steps as $step) {
                if ($step->type === StepType::InstallCommand || $step->type === StepType::UninstallCommand) {
                    return [
                        'ok' => false,
                        'errors' => [__('procedure.wizard_err_already_has_core')],
                    ];
                }
            }
        }

        $created = [];
        $created[] = $this->procedures->addStep(
            $procedure->id,
            StepType::InstallCommand->value,
            $installCheck['payload'],
            'stop',
            true,
            trim((string) ($input['install_label'] ?? '')) ?: __('procedure.wizard_label_install')
        );
        $created[] = $this->procedures->addStep(
            $procedure->id,
            StepType::UninstallCommand->value,
            $uninstallCheck['payload'],
            'stop',
            true,
            trim((string) ($input['uninstall_label'] ?? '')) ?: __('procedure.wizard_label_uninstall')
        );

        $note = trim((string) ($input['notes'] ?? ''));
        if ($note !== '') {
            $noteCheck = $this->validator->validate(StepType::Note, ['text' => $note]);
            if ($noteCheck['ok']) {
                $created[] = $this->procedures->addStep(
                    $procedure->id,
                    StepType::Note->value,
                    $noteCheck['payload'],
                    'continue',
                    true,
                    __('procedure.wizard_label_note')
                );
            }
            $this->procedures->updateNotes(
                $procedure->id,
                $note,
                trim((string) ($input['procedure_name'] ?? '')) ?: $procedure->name
            );
        } elseif (trim((string) ($input['procedure_name'] ?? '')) !== '') {
            $this->procedures->updateNotes(
                $procedure->id,
                $procedure->notes,
                trim((string) $input['procedure_name'])
            );
        }

        // Réordonner : install → uninstall → le reste
        $fresh = $this->procedures->getOrCreateActive($softwareId);
        $ordered = [];
        foreach ($fresh->steps as $step) {
            if ($step->type === StepType::InstallCommand) {
                $ordered[] = $step->id;
            }
        }
        foreach ($fresh->steps as $step) {
            if ($step->type === StepType::UninstallCommand) {
                $ordered[] = $step->id;
            }
        }
        foreach ($fresh->steps as $step) {
            if (!in_array($step->type, [StepType::InstallCommand, StepType::UninstallCommand], true)) {
                $ordered[] = $step->id;
            }
        }
        if ($ordered !== []) {
            $this->procedures->reorderSteps($procedure->id, $ordered);
        }

        $this->auditLogs->log($userId, 'procedure.wizard_applied', 'software', $softwareId, [
            'step_ids' => $created,
            'strategy' => $software->isMsiFamily() ? 'msi' : 'exe',
        ], $ip);

        return ['ok' => true, 'errors' => [], 'created' => $created];
    }

    /**
     * Préremplissage assistant selon le type d’installateur.
     *
     * @return array<string, mixed>
     */
    public function wizardDefaults(int $softwareId): array
    {
        $software = $this->softwares->findById($softwareId);
        if ($software === null) {
            return [];
        }

        $installerName = $software->pendingInstallerPath
            ? basename(str_replace('\\', '/', $software->pendingInstallerPath))
            : ($software->isMsiFamily() ? 'setup.msi' : 'setup.exe');

        $appSlug = preg_replace('/[^a-zA-Z0-9]+/', '', $software->name) ?: 'App';
        $fileSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $software->name) ?: 'app');
        $witness = 'C:\\SCCM_Temoins\\' . $appSlug . '\\' . $fileSlug . '.' . $software->currentVersion . '.log';

        $presets = self::commandPresets($software->installerType, $installerName);

        return array_merge([
            'installer_type' => $software->installerType,
            'architecture' => $software->architecture,
            'detection_method' => $software->detectionMethod,
            'download_url' => (string) ($software->downloadUrl ?? ''),
            'version_check_url' => (string) ($software->versionCheckUrl ?? ''),
            'installer_name' => $installerName,
            'witness_path' => $witness,
            'procedure_name' => $software->name . ' — procédure',
            'success_paths' => '',
            'success_gone_paths' => '',
            'pre_kill' => '',
            'product_code' => '',
        ], $presets);
    }

    /**
     * Commandes install / uninstall connues selon le type (MSI = msiexec ; EXE = setup + silent).
     *
     * @return array<string, string>
     */
    public static function commandPresets(string $installerType, string $installerName = ''): array
    {
        $type = strtolower(trim($installerType));
        $name = trim($installerName);

        if (in_array($type, ['msi', 'msix'], true)) {
            $file = $name !== '' ? $name : 'setup.msi';

            return [
                'strategy' => 'msi',
                'install_command' => $file,
                'install_silent_args' => '/qn /norestart',
                'install_working_dir' => '.',
                'install_timeout_sec' => '600',
                'uninstall_command' => 'msiexec.exe',
                'uninstall_silent_args' => '/x {PRODUCT_CODE} /qn /norestart',
                'uninstall_timeout_sec' => '600',
            ];
        }

        $file = $name !== '' ? $name : 'setup.exe';
        $silent = match ($type) {
            'inno' => '/VERYSILENT /NORESTART /SUPPRESSMSGBOXES /SP-',
            'nsis' => '/S',
            default => '/S',
        };
        $uninstallCmd = match ($type) {
            'inno' => 'unins000.exe',
            'nsis' => 'uninstall.exe',
            default => 'unins000.exe',
        };
        $uninstallSilent = match ($type) {
            'nsis' => '/S',
            default => '/VERYSILENT /NORESTART',
        };

        return [
            'strategy' => 'exe',
            'install_command' => $file,
            'install_silent_args' => $silent,
            'install_working_dir' => '.',
            'install_timeout_sec' => '900',
            'uninstall_command' => $uninstallCmd,
            'uninstall_silent_args' => $uninstallSilent,
            'uninstall_timeout_sec' => '600',
        ];
    }

    private function unwrapPathQuotes(string $path): string
    {
        $path = trim($path);
        while (strlen($path) >= 2) {
            $a = $path[0];
            $b = $path[strlen($path) - 1];
            if (($a === '"' && $b === '"') || ($a === "'" && $b === "'")) {
                $path = trim(substr($path, 1, -1));
                continue;
            }
            break;
        }

        return $path;
    }
}
