<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PackageExportRepository;
use App\Security\ModuleAccess;
use App\Security\Role;
use App\Services\PackageExportService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * Package ZIP export for manual deployment testing.
 */
final class PackageExportController extends BaseController
{
    public function download(Request $request, PackageExportService $exporter, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_READ);

        $result = $exporter->export((int) $id, $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? __('flash.export_failed'));
            Response::redirect('/softwares/' . $id . '?tab=sync');
        }

        $this->sendZipFile($result['path'] ?? '', $result['filename'] ?? 'package.zip', (int) $id);
    }

    /**
     * Generate ZIP without forcing a browser download (JSON / pipeline).
     */
    public function generate(Request $request, PackageExportService $exporter, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $result = $exporter->export((int) $id, $this->auth->id(), $request->ip());
        $wantsJson = $request->isAjax() || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

        if (!$result['ok']) {
            if ($wantsJson) {
                Response::json([
                    'ok' => false,
                    'error' => $result['error'] ?? __('flash.export_failed'),
                    'software_id' => (int) $id,
                ], 422);
                return;
            }
            Session::flash('error', $result['error'] ?? __('flash.export_failed'));
            Response::redirect('/softwares/' . $id . '?tab=sync');
        }

        $exportId = (int) ($result['export_id'] ?? 0);
        $filename = (string) ($result['filename'] ?? 'package.zip');

        if ($wantsJson) {
            Response::json([
                'ok' => true,
                'software_id' => (int) $id,
                'export_id' => $exportId,
                'filename' => $filename,
                'message' => __('flash.zip_generated', ['filename' => $filename]),
                'download_url' => '/softwares/' . (int) $id . '/exports/' . $exportId . '/download',
            ]);
            return;
        }

        Session::flash('success', __('flash.zip_generated', ['filename' => $filename]));
        Response::redirect('/softwares/' . $id . '?tab=sync');
    }

    /**
     * Download a previously generated ZIP from history.
     */
    public function downloadHistory(
        Request $request,
        PackageExportService $exporter,
        PackageExportRepository $exports,
        string $id,
        string $exportId,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_READ);

        $export = $exports->findById((int) $exportId);
        if ($export === null || $export->softwareId !== (int) $id) {
            Session::flash('error', __('flash.zip_not_found'));
            Response::redirect('/softwares/' . $id . '?tab=sync');
        }

        $path = $exporter->absolutePathForExport($export);
        if ($path === null) {
            Session::flash('error', __('flash.zip_missing_disk'));
            Response::redirect('/softwares/' . $id . '?tab=sync');
        }

        $this->sendZipFile($path, $export->filename, (int) $id);
    }

    /**
     * Delete a ZIP from history (admin only).
     */
    public function deleteHistory(
        Request $request,
        PackageExportService $exporter,
        string $id,
        string $exportId,
    ): void {
        $this->auth->requireRole(Role::Admin);
        $this->requireCsrf($request);

        $result = $exporter->deleteExport((int) $id, (int) $exportId, $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? __('flash.delete_failed'));
            Response::redirect('/softwares/' . $id . '?tab=sync');
        }

        Session::flash('success', __('flash.zip_deleted'));
        Response::redirect('/softwares/' . $id . '?tab=sync');
    }

    private function sendZipFile(string $path, string $filename, int $softwareId): void
    {
        if ($path === '' || !is_readable($path)) {
            Session::flash('error', __('flash.zip_file_not_found'));
            Response::redirect('/softwares/' . $softwareId . '?tab=sync');
        }

        header('Content-Type: application/zip');
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }
}
