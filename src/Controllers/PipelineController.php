<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\ModuleAccess;

use App\Services\PipelineJobService;
use App\Services\VirusTotalService;
use App\Services\VmValidationService;
use App\Support\Request;
use App\Support\Response;

/**
 * Async pipeline API (avoids reverse-proxy 504 on long sync / VM tests).
 */
final class PipelineController extends BaseController
{
    public function start(
        Request $request,
        PipelineJobService $pipeline,
        VmValidationService $vm,
        VirusTotalService $virusTotal,
        string $id,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $vmEnabled = $vm->isConfigured($this->auth->id());
        $force = $request->input('vm');
        if ($force === '0' || $force === 'false') {
            $vmEnabled = false;
        }

        $vtEnabled = $virusTotal->isEnabled();
        $forceVt = $request->input('vt');
        if ($forceVt === '0' || $forceVt === 'false') {
            $vtEnabled = false;
        }

        $result = $pipeline->start((int) $id, $this->auth->id(), $vmEnabled, $vtEnabled);
        if (empty($result['ok'])) {
            Response::json([
                'ok' => false,
                'error' => $result['error'] ?? 'Impossible de démarrer le pipeline.',
            ], 500);
            return;
        }

        Response::json([
            'ok' => true,
            'job_id' => $result['job_id'],
            'status_url' => $result['status_url'],
            'vm_enabled' => $vmEnabled,
            'vt_enabled' => $vtEnabled,
            'message' => 'Pipeline démarré en arrière-plan.',
        ]);
    }

    public function status(
        Request $request,
        PipelineJobService $pipeline,
        string $id,
        string $jobId,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_READ);

        $job = $pipeline->get($jobId, (int) $id);
        if ($job === null) {
            Response::json(['ok' => false, 'error' => 'Job introuvable.'], 404);
            return;
        }

        $status = (string) ($job['status'] ?? 'unknown');
        $done = in_array($status, ['done', 'error'], true);
        $awaitingUser = $status === 'awaiting_user';
        $awaitingKind = (string) ($job['awaiting_kind'] ?? ($awaitingUser ? 'vm' : ''));

        Response::json([
            'ok' => true,
            'job_id' => $job['id'] ?? $jobId,
            'software_id' => (int) ($job['software_id'] ?? $id),
            'status' => $status,
            'done' => $done,
            'failed' => $status === 'error',
            'awaiting_user' => $awaitingUser,
            'awaiting_kind' => $awaitingKind !== '' ? $awaitingKind : null,
            'vt_review' => $job['vt_review'] ?? null,
            'vm_run_id' => $job['vm_run_id'] ?? null,
            'export_id' => $job['export_id'] ?? null,
            'steps' => $job['steps'] ?? [],
            'log' => $job['log'] ?? '',
            'error' => $job['error'] ?? null,
            'updated_at' => $job['updated_at'] ?? null,
            'finished_at' => $job['finished_at'] ?? null,
        ]);
    }

    public function vmConfirm(
        Request $request,
        PipelineJobService $pipeline,
        string $id,
        string $jobId,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $action = (string) ($request->input('action') ?? '');
        $result = $pipeline->confirmVm($jobId, (int) $id, $action, $this->auth->id());
        if (empty($result['ok'])) {
            Response::json([
                'ok' => false,
                'error' => $result['error'] ?? 'Confirmation VM impossible.',
            ], 422);
            return;
        }

        Response::json([
            'ok' => true,
            'job_id' => $jobId,
            'action' => $action,
            'message' => $action === 'abort'
                ? 'Annulation lancée.'
                : 'Uninstall lancé après confirmation.',
        ]);
    }

    public function vtConfirm(
        Request $request,
        PipelineJobService $pipeline,
        string $id,
        string $jobId,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $action = (string) ($request->input('action') ?? '');
        $result = $pipeline->confirmVt($jobId, (int) $id, $action, $this->auth->id());
        if (empty($result['ok'])) {
            Response::json([
                'ok' => false,
                'error' => $result['error'] ?? 'Décision VirusTotal impossible.',
            ], 422);
            return;
        }

        Response::json([
            'ok' => true,
            'job_id' => $jobId,
            'action' => $action,
            'message' => $action === 'abort'
                ? 'Pipeline arrêté (VirusTotal).'
                : 'Pipeline repris après VirusTotal.',
        ]);
    }
}
