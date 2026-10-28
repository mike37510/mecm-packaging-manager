<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\ModuleAccess;

use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\SoftwareRepository;
use App\Services\PackageExportService;
use App\Services\ProcedureService;
use App\Services\SoftwareService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * Software CRUD and detail tabs.
 */
final class SoftwareController extends BaseController
{
    public function index(Request $request, SoftwareService $service, SoftwareRepository $repo, CategoryRepository $categories): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_READ);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'category' => trim((string) $request->query('category', '')),
            'publisher' => trim((string) $request->query('publisher', '')),
            'installer_type' => trim((string) $request->query('installer_type', '')),
        ];

        $categoryNames = $categories->names();
        foreach ($repo->distinctCategories() as $legacy) {
            if ($legacy !== '' && !in_array($legacy, $categoryNames, true)) {
                $categoryNames[] = $legacy;
            }
        }
        sort($categoryNames, SORT_STRING | SORT_FLAG_CASE);

        $this->render('softwares/index', [
            'softwares' => $service->search(array_filter($filters)),
            'filters' => $filters,
            'categories' => $categoryNames,
            'publishers' => $repo->distinctPublishers(),
            'installerTypes' => SoftwareService::INSTALLER_TYPES,
        ]);
    }

    public function createForm(Request $request, CategoryRepository $categories): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->render('softwares/form', [
            'software' => null,
            'categories' => $categories->names(),
            'detectionMethods' => SoftwareService::DETECTION_METHODS,
            'architectures' => SoftwareService::ARCHITECTURES,
            'installerTypes' => SoftwareService::INSTALLER_TYPES,
        ]);
    }

    public function store(Request $request, SoftwareService $service): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $service->create($request->all(), $request->file('icon'), $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', implode(' ', $result['errors']));
            Session::flash('old', $request->all());
            Response::redirect('/softwares/create');
        }

        Session::flash('success', __('flash.software_created'));
        Response::redirect('/softwares/' . $result['id'] . '?tab=procedure&wizard=1');
    }

    public function show(
        Request $request,
        SoftwareService $service,
        ProcedureService $procedures,
        AuditLogRepository $auditLogs,
        \App\Repositories\SyncRepository $syncs,
        PackageExportService $exports,
        \App\Repositories\VmValidationRepository $vmValidations,
        \App\Services\VmValidationService $vmValidator,
        \App\Services\VirusTotalService $virusTotal,
        \App\Services\DeployService $deploy,
        string $id,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_READ);
        $software = $service->find((int) $id);
        if ($software === null) {
            Response::abort(404, __('flash.software_not_found'));
        }

        $tab = (string) $request->query('tab', 'info');
        if (!in_array($tab, ['info', 'procedure', 'history', 'sync', 'deploy'], true)) {
            $tab = 'info';
        }

        $deployData = $deploy->assistantData($software->id);

        $this->render('softwares/show', [
            'software' => $software,
            'tab' => $tab,
            'procedure' => $procedures->getForSoftware($software->id),
            'history' => $auditLogs->forEntity('software', $software->id),
            'syncHistory' => $syncs->historyForSoftware($software->id),
            'zipHistory' => $exports->history($software->id),
            'canGenerateZip' => $exports->hasInstaller($software),
            'stepTypes' => \App\Domain\Procedure\StepType::cases(),
            'vmValidations' => $vmValidations->forSoftware($software->id),
            'vmTestEnabled' => $vmValidator->isConfigured($this->auth->id()),
            'vtEnabled' => $virusTotal->isEnabled(),
            'deployForm' => $deployData['form'] ?? [],
            'deployProfile' => $deployData['profile'] ?? null,
            'wizardDefaults' => $procedures->wizardDefaults($software->id),
            'detectionMethods' => SoftwareService::DETECTION_METHODS,
            'architectures' => SoftwareService::ARCHITECTURES,
            'installerTypes' => SoftwareService::INSTALLER_TYPES,
        ]);
    }

    public function editForm(Request $request, SoftwareService $service, CategoryRepository $categories, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $software = $service->find((int) $id);
        if ($software === null) {
            Response::abort(404, __('flash.software_not_found'));
        }

        $categoryNames = $categories->names();
        if ($software->category !== '' && !in_array($software->category, $categoryNames, true)) {
            $categoryNames[] = $software->category;
            sort($categoryNames, SORT_STRING | SORT_FLAG_CASE);
        }

        $this->render('softwares/form', [
            'software' => $software,
            'categories' => $categoryNames,
            'detectionMethods' => SoftwareService::DETECTION_METHODS,
            'architectures' => SoftwareService::ARCHITECTURES,
            'installerTypes' => SoftwareService::INSTALLER_TYPES,
        ]);
    }

    public function update(Request $request, SoftwareService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $service->update((int) $id, $request->all(), $request->file('icon'), $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', implode(' ', $result['errors']));
            Response::redirect('/softwares/' . $id . '/edit');
        }

        Session::flash('success', __('flash.software_updated'));
        Response::redirect('/softwares/' . $id);
    }

    public function destroy(Request $request, SoftwareService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        if (!$service->delete((int) $id, $this->auth->id(), $request->ip())) {
            Session::flash('error', __('flash.software_not_found'));
            Response::redirect('/softwares');
        }

        Session::flash('success', __('flash.software_deleted'));
        Response::redirect('/softwares');
    }

    public function uploadIcon(Request $request, SoftwareService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $service->updateIcon((int) $id, $request->file('icon'), $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', implode(' ', $result['errors']));
            Response::redirect('/softwares/' . $id . '?tab=info');
        }

        Session::flash('success', __('flash.icon_updated'));
        Response::redirect('/softwares/' . $id . '?tab=info');
    }

    public function uploadInstaller(Request $request, SoftwareService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        // Gros fichiers : laisser tourner longtemps
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');

        $result = $service->uploadInstaller((int) $id, $request->file('installer'), $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', implode(' ', $result['errors']));
            Response::redirect('/softwares/' . $id . '?tab=sync');
        }

        $sizeMo = isset($result['size']) ? round(((int) $result['size']) / 1048576, 1) : null;
        Session::flash(
            'success',
            __('software.installer_upload_ok')
            . ($sizeMo !== null ? ' (' . $sizeMo . ' Mo)' : '')
        );
        Response::redirect('/softwares/' . $id . '?tab=sync');
    }

    public function preferUrlSync(Request $request, SoftwareService $service, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $service->preferUrlSync((int) $id, $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', implode(' ', $result['errors']));
        } else {
            $msg = __('software.installer_prefer_url_ok');
            $bytes = (int) ($result['deleted_bytes'] ?? 0);
            if ($bytes > 0) {
                $msg .= ' (−' . round($bytes / 1048576, 1) . ' Mo)';
            }
            Session::flash('success', $msg);
        }
        Response::redirect('/softwares/' . $id . '?tab=sync');
    }

    public function exportCatalog(Request $request, \App\Services\SoftwareCatalogService $catalog, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_READ);
        $result = $catalog->export((int) $id);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? __('software.export_catalog_failed'));
            Response::redirect('/softwares/' . $id);
        }

        $json = json_encode(
            $result['payload'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($json === false) {
            Session::flash('error', __('software.export_catalog_failed'));
            Response::redirect('/softwares/' . $id);
        }

        Response::download(
            $json . "\n",
            (string) ($result['filename'] ?? 'software-export.json'),
            'application/json; charset=utf-8'
        );
    }

    public function importCatalogForm(Request $request): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->render('softwares/import', []);
    }

    public function importCatalog(Request $request, \App\Services\SoftwareCatalogService $catalog): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $json = trim((string) $request->input('json', ''));
        $file = $request->file('catalog');
        if ($json === '' && is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) ($file['tmp_name'] ?? '');
            if ($tmp !== '' && is_readable($tmp)) {
                $json = (string) file_get_contents($tmp);
            }
        }
        if ($json === '') {
            Session::flash('error', __('software.import_catalog_missing'));
            Response::redirect('/softwares/import');
        }

        $nameOverride = trim((string) $request->input('name', '')) ?: null;
        $result = $catalog->import($json, $nameOverride, $this->auth->id(), $request->ip());
        if (!($result['ok'] ?? false) || empty($result['id'])) {
            Session::flash('error', implode(' ', $result['errors'] ?? [__('software.import_catalog_failed')]));
            Session::flash('old', ['json' => $json, 'name' => $nameOverride ?? '']);
            Response::redirect('/softwares/import');
        }

        $msg = __('software.import_catalog_ok');
        if (($result['errors'] ?? []) !== []) {
            $msg .= ' ' . __('software.import_catalog_partial') . ' ' . implode(' ', $result['errors']);
            Session::flash('error', $msg);
        } else {
            Session::flash('success', $msg);
        }

        Response::redirect('/softwares/' . $result['id'] . '/edit');
    }
}
