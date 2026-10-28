<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\ModuleAccess;

use App\Repositories\DriverSyncRepository;
use App\Services\DriverPackageService;
use App\Services\DriverService;
use App\Services\DriverSyncService;
use App\Services\VirusTotalService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * Driver catalog, hash sync, and MECM deploy ZIP.
 */
final class DriverController extends BaseController
{
    public function index(Request $request, DriverService $service): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_READ);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'manufacturer' => trim((string) $request->query('manufacturer', '')),
            'sync_status' => trim((string) $request->query('sync_status', '')),
        ];

        $this->render('drivers/index', [
            'drivers' => $service->search(array_filter($filters)),
            'filters' => $filters,
            'manufacturers' => DriverService::MANUFACTURERS,
        ]);
    }

    public function createForm(Request $request): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_WRITE);
        $this->render('drivers/form', [
            'driver' => null,
            'manufacturers' => DriverService::MANUFACTURERS,
            'architectures' => DriverService::ARCHITECTURES,
        ]);
    }

    public function store(Request $request, DriverService $service): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $service->create($request->all(), $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', implode(' ', $result['errors'] ?? []));
            Session::flash('old', $request->all());
            Response::redirect('/drivers/create');
        }

        Session::flash('success', __('flash.driver_created'));
        Response::redirect('/drivers/' . $result['id']);
    }

    public function show(
        Request $request,
        DriverService $service,
        DriverSyncRepository $syncs,
        VirusTotalService $virusTotal,
        string $id,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_READ);
        $driver = $service->find((int) $id);
        if ($driver === null) {
            Response::abort(404, __('flash.driver_not_found'));
        }

        $this->render('drivers/show', [
            'driver' => $driver,
            'syncHistory' => $syncs->historyForDriver($driver->id),
            'vtEnabled' => $virusTotal->isEnabled(),
        ]);
    }

    public function editForm(Request $request, DriverService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_WRITE);
        $driver = $service->find((int) $id);
        if ($driver === null) {
            Response::abort(404, __('flash.driver_not_found'));
        }

        $this->render('drivers/form', [
            'driver' => $driver,
            'manufacturers' => DriverService::choicesFor($driver),
            'architectures' => DriverService::ARCHITECTURES,
        ]);
    }

    public function update(Request $request, DriverService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $service->update((int) $id, $request->all(), $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', implode(' ', $result['errors'] ?? []));
            Session::flash('old', $request->all());
            Response::redirect('/drivers/' . $id . '/edit');
        }

        Session::flash('success', __('flash.driver_updated'));
        Response::redirect('/drivers/' . $id);
    }

    public function destroy(Request $request, DriverService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $service->delete((int) $id, $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? __('flash.delete_failed'));
            Response::redirect('/drivers');
        }

        Session::flash('success', __('flash.driver_deleted'));
        Response::redirect('/drivers');
    }

    public function sync(Request $request, DriverSyncService $sync, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $sync->check((int) $id, $this->auth->id(), $request->ip());
        $wantsJson = $request->isAjax()
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

        if (!$result['ok']) {
            if ($wantsJson) {
                Response::json([
                    'ok' => false,
                    'error' => $result['error'] ?? __('flash.driver_sync_failed'),
                    'driver_id' => (int) $id,
                    'status' => 'error',
                ], 422);

                return;
            }
            Session::flash('error', $result['error'] ?? __('flash.driver_sync_failed'));
            Response::redirect('/drivers/' . $id);
        }

        if ($wantsJson) {
            $status = (string) ($result['status'] ?? 'never');
            Response::json([
                'ok' => true,
                'driver_id' => (int) $id,
                'status' => $status,
                'message' => $result['message'] ?? '',
                'sha256' => $result['sha256'] ?? null,
                'changed' => $result['changed'] ?? false,
                'update_available' => $status === 'update_available',
                'current_version' => $result['version'] ?? null,
                'version' => $result['version'] ?? null,
            ]);

            return;
        }

        Session::flash('success', $result['message'] ?? __('flash.driver_sync_ok'));
        Response::redirect('/drivers/' . $id);
    }

    public function generateZip(Request $request, DriverPackageService $packages, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $packages->generate((int) $id, $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? __('flash.export_failed'));
            Response::redirect('/drivers/' . $id);
        }

        Session::flash('success', __('flash.driver_zip_generated', [
            'filename' => (string) ($result['filename'] ?? ''),
        ]));
        Response::redirect('/drivers/' . $id);
    }

    public function downloadZip(Request $request, DriverService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_DRIVERS, ModuleAccess::LEVEL_READ);
        $driver = $service->find((int) $id);
        if ($driver === null || !$driver->hasExport()) {
            Response::abort(404, __('flash.zip_not_found'));
        }

        $filename = $driver->lastExportFilename ?: basename((string) $driver->lastExportPath);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . (string) filesize((string) $driver->lastExportPath));
        readfile((string) $driver->lastExportPath);
        exit;
    }
}
