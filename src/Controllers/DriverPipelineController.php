<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\ModuleAccess;

use App\Services\DriverPipelineJobService;
use App\Services\VirusTotalService;
use App\Support\Request;
use App\Support\Response;

/**
 * Async driver pipeline API (URL check → VirusTotal → ZIP).
 */
final class DriverPipelineController extends BaseController
{
    public function start(
        Request $request,
        DriverPipelineJobService $pipeline,
        VirusTotalService $virusTotal,
        string $id,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $vtEnabled = $virusTotal->isEnabled();
        $forceVt = $request->input('vt');
        if ($forceVt === '0' || $forceVt === 'false') {
            $vtEnabled = false;
        }

        $result = $pipeline->start((int) $id, $this->auth->id(), $vtEnabled);
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
            'vt_enabled' => $vtEnabled,
            'message' => 'Pipeline démarré en arrière-plan.',
        ]);
    }

    public function status(
        Request $request,
        DriverPipelineJobService $pipeline,
        string $id,
        string $jobId,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_READ);

        $job = $pipeline->get($jobId, (int) $id);
        if ($job === null) {
            Response::json(['ok' => false, 'error' => 'Job introuvable.'], 404);
            return;
        }

        $status = (string) ($job['status'] ?? 'unknown');
        $done = in_array($status, ['done', 'error'], true);
        $awaitingUser = $status === 'awaiting_user';
        $awaitingKind = (string) ($job['awaiting_kind'] ?? ($awaitingUser ? 'vt' : ''));

        Response::json([
            'ok' => true,
            'job_id' => $job['id'] ?? $jobId,
            'driver_id' => (int) ($job['driver_id'] ?? $id),
            'status' => $status,
            'done' => $done,
            'failed' => $status === 'error',
            'awaiting_user' => $awaitingUser,
            'awaiting_kind' => $awaitingKind !== '' ? $awaitingKind : null,
            'vt_review' => $job['vt_review'] ?? null,
            'export_id' => $job['export_id'] ?? null,
            'steps' => $job['steps'] ?? [],
            'log' => $job['log'] ?? '',
            'error' => $job['error'] ?? null,
            'updated_at' => $job['updated_at'] ?? null,
            'finished_at' => $job['finished_at'] ?? null,
        ]);
    }

    public function vtConfirm(
        Request $request,
        DriverPipelineJobService $pipeline,
        string $id,
        string $jobId,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_WRITE);
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
