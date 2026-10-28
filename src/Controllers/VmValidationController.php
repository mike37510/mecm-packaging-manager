<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\ModuleAccess;

use App\Repositories\AuditLogRepository;
use App\Repositories\VmValidationRepository;
use App\Services\VmValidationService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * Remote Windows lab validation (SSH).
 */
final class VmValidationController extends BaseController
{
    public function run(
        Request $request,
        VmValidationService $validator,
        string $id,
        string $exportId,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $validator->validateExport(
            (int) $id,
            (int) $exportId,
            $this->auth->id(),
            $request->ip()
        );

        $wantsJson = $request->isAjax() || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

        if (!$result['ok']) {
            if ($wantsJson) {
                Response::json([
                    'ok' => false,
                    'error' => $result['error'] ?? __('flash.vm_failed'),
                    'software_id' => (int) $id,
                    'export_id' => (int) $exportId,
                    'message' => $result['error'] ?? __('flash.vm_failed'),
                    'log' => $result['log'] ?? null,
                    'status' => $result['status'] ?? 'failed',
                ], 422);
                return;
            }
            Session::flash('error', $result['error'] ?? __('flash.vm_failed'));
            if (!empty($result['log'])) {
                Session::flash('vm_log', $result['log']);
            }
            Response::redirect('/softwares/' . $id . '?tab=sync');
        }

        if ($wantsJson) {
            Response::json([
                'ok' => true,
                'awaiting_confirmation' => !empty($result['awaiting_confirmation']),
                'software_id' => (int) $id,
                'export_id' => (int) $exportId,
                'message' => $result['message'] ?? __('flash.vm_ok'),
                'log' => $result['log'] ?? null,
                'status' => $result['status'] ?? 'passed',
                'run_id' => $result['run_id'] ?? null,
            ]);
            return;
        }

        if (!empty($result['awaiting_confirmation'])) {
            Session::flash('success', __('flash.vm_awaiting', [
                'message' => (string) ($result['message'] ?? ''),
            ]));
        } else {
            Session::flash('success', $result['message'] ?? __('flash.vm_ok'));
        }
        if (!empty($result['log'])) {
            Session::flash('vm_log', $result['log']);
        }
        Response::redirect('/softwares/' . $id . '?tab=sync');
    }

    public function deleteHistory(
        Request $request,
        VmValidationRepository $runs,
        AuditLogRepository $auditLogs,
        string $id,
        string $runId,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $softwareId = (int) $id;
        $row = $runs->findById((int) $runId);
        if ($row === null || (int) ($row['software_id'] ?? 0) !== $softwareId) {
            Session::flash('error', __('flash.vm_not_found'));
            Response::redirect('/softwares/' . $softwareId . '?tab=sync');
        }

        $runs->delete((int) $row['id']);
        $auditLogs->log(
            $this->auth->id(),
            'vm.history_deleted',
            'software',
            $softwareId,
            [
                'run_id' => (int) $row['id'],
                'status' => $row['status'] ?? null,
                'version' => $row['version'] ?? null,
            ],
            $request->ip()
        );

        Session::flash('success', __('flash.vm_history_deleted'));
        Response::redirect('/softwares/' . $softwareId . '?tab=sync');
    }
}
