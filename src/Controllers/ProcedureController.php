<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\ModuleAccess;

use App\Domain\Procedure\StepType;
use App\Services\ProcedureService;
use App\Services\SoftwareService;
use App\Services\SyncService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * Procedure step management endpoints.
 */
final class ProcedureController extends BaseController
{
    public function applyWizard(Request $request, ProcedureService $service, SoftwareService $softwareService, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $meta = $softwareService->updateWizardFields(
            (int) $id,
            $request->all(),
            $request->file('icon'),
            $this->auth->id(),
            $request->ip()
        );
        if (!$meta['ok']) {
            if ($request->isAjax() || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
                Response::json(
                    [
                        'ok' => false,
                        'errors' => $meta['errors'] ?? [],
                        'message' => implode(' ', $meta['errors'] ?? []),
                    ],
                    422
                );

                return;
            }

            Session::flash('error', implode(' ', $meta['errors'] ?? []));
            Response::redirect('/softwares/' . $id . '?tab=procedure');

            return;
        }

        $result = $service->applyWizard((int) $id, $request->all(), $this->auth->id(), $request->ip());

        if ($request->isAjax() || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            Response::json(
                [
                    'ok' => $result['ok'],
                    'errors' => $result['errors'] ?? [],
                    'created' => $result['created'] ?? [],
                    'message' => $result['ok']
                        ? __('flash.procedure_wizard_ok')
                        : implode(' ', $result['errors'] ?? []),
                ],
                $result['ok'] ? 200 : 422
            );

            return;
        }

        Session::flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('flash.procedure_wizard_ok') : implode(' ', $result['errors'])
        );
        Response::redirect('/softwares/' . $id . '?tab=procedure');
    }

    /**
     * Enregistre les URL / type de l’assistant, télécharge l’installateur et renvoie les suggestions.
     */
    public function probeInstaller(
        Request $request,
        SoftwareService $softwareService,
        SyncService $sync,
        ProcedureService $procedures,
        string $id,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $downloadUrl = trim((string) $request->input('download_url', ''));
        $versionCheckUrl = trim((string) $request->input('version_check_url', ''));
        $detection = (string) $request->input('detection_method', 'fixed_url');

        if ($downloadUrl === '' && $versionCheckUrl === '' && $detection !== 'github') {
            Response::json([
                'ok' => false,
                'errors' => [__('procedure.wizard_probe_need_url')],
                'message' => __('procedure.wizard_probe_need_url'),
            ], 422);

            return;
        }
        if ($detection === 'github' && $versionCheckUrl === '' && $downloadUrl === '') {
            Response::json([
                'ok' => false,
                'errors' => [__('procedure.wizard_err_github_url')],
                'message' => __('procedure.wizard_err_github_url'),
            ], 422);

            return;
        }

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $meta = $softwareService->updateWizardFields(
            (int) $id,
            $request->all(),
            null,
            $this->auth->id(),
            $request->ip()
        );
        if (!$meta['ok']) {
            Response::json([
                'ok' => false,
                'errors' => $meta['errors'] ?? [],
                'message' => implode(' ', $meta['errors'] ?? []),
            ], 422);

            return;
        }

        $result = $sync->check((int) $id, $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Response::json([
                'ok' => false,
                'errors' => [$result['error'] ?? __('procedure.wizard_probe_failed')],
                'message' => $result['error'] ?? __('procedure.wizard_probe_failed'),
            ], 422);

            return;
        }

        $defaults = $procedures->wizardDefaults((int) $id);
        $fileName = (string) ($result['file_name'] ?? $defaults['installer_name'] ?? '');
        $installerType = (string) ($defaults['installer_type'] ?? 'exe');

        Response::json([
            'ok' => true,
            'message' => __('procedure.wizard_probe_ok', [
                'file' => $fileName !== '' ? $fileName : '—',
                'type' => strtoupper($installerType),
            ]),
            'sync' => [
                'status' => $result['status'] ?? null,
                'file_name' => $result['file_name'] ?? null,
                'file_size' => $result['file_size'] ?? null,
                'remote_version' => $result['remote_version'] ?? null,
                'sha256' => $result['sha256'] ?? null,
            ],
            'suggestions' => $defaults,
        ]);
    }

    public function updateMeta(Request $request, ProcedureService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $service->updateMeta(
            (int) $id,
            (string) $request->input('name', ''),
            (string) $request->input('notes', ''),
            $this->auth->id(),
            $request->ip()
        );

        Session::flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('flash.procedure_saved') : implode(' ', $result['errors'])
        );
        Response::redirect('/softwares/' . $id . '?tab=procedure');
    }

    public function addStep(Request $request, ProcedureService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $service->addStep((int) $id, $request->all(), $this->auth->id(), $request->ip());
        Session::flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('flash.step_added') : implode(' ', $result['errors'])
        );
        Response::redirect('/softwares/' . $id . '?tab=procedure');
    }

    public function updateStep(Request $request, ProcedureService $service, string $id, string $stepId): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $service->updateStep((int) $id, (int) $stepId, $request->all(), $this->auth->id(), $request->ip());
        Session::flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('flash.step_updated') : implode(' ', $result['errors'])
        );
        Response::redirect('/softwares/' . $id . '?tab=procedure');
    }

    public function deleteStep(Request $request, ProcedureService $service, string $id, string $stepId): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $service->deleteStep((int) $id, (int) $stepId, $this->auth->id(), $request->ip());
        Session::flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('flash.step_deleted') : implode(' ', $result['errors'])
        );
        Response::redirect('/softwares/' . $id . '?tab=procedure');
    }

    public function reorder(Request $request, ProcedureService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $order = $request->input('order', []);
        if (is_string($order)) {
            $decoded = json_decode($order, true);
            $order = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($order)) {
            $order = [];
        }

        $result = $service->reorder((int) $id, $order, $this->auth->id(), $request->ip());

        if ($request->isAjax() || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            Response::json($result, $result['ok'] ? 200 : 422);
            return;
        }

        Session::flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('flash.step_order_updated') : implode(' ', $result['errors'])
        );
        Response::redirect('/softwares/' . $id . '?tab=procedure');
    }

    /**
     * Returns field schema for a step type (AJAX helper for dynamic forms).
     */
    public function stepSchema(Request $request, string $type): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_READ);
        try {
            $stepType = StepType::from($type);
        } catch (\ValueError) {
            Response::json(['ok' => false, 'error' => __('flash.generic_error')], 404);
            return;
        }

        Response::json([
            'ok' => true,
            'type' => $stepType->value,
            'label' => $stepType->label(),
            'fields' => $stepType->fields(),
        ]);
    }
}
