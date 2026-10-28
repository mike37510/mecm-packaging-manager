<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\ModuleAccess;

use App\Services\DeployService;
use App\Services\PackageExportService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * Assistant Déployer (métadonnées MECM → deploy.json).
 */
final class DeployController extends BaseController
{
    public function update(
        Request $request,
        DeployService $deploy,
        PackageExportService $exports,
        string $id,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_SOFTWARES, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $softwareId = (int) $id;
        $result = $deploy->save($softwareId, $request->all(), $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', implode(' ', $result['errors'] ?? [__('flash.generic_error')]));
            Response::redirect('/softwares/' . $softwareId . '?tab=deploy');
        }

        $zipResult = $exports->export($softwareId, $this->auth->id(), $request->ip());
        if ($zipResult['ok']) {
            $filename = (string) ($zipResult['filename'] ?? 'package.zip');
            Session::flash('success', __('flash.deploy_saved_zip', ['filename' => $filename]));
            Response::redirect('/softwares/' . $softwareId . '?tab=sync');
        }

        Session::flash('success', __('flash.deploy_saved_no_zip', [
            'error' => (string) ($zipResult['error'] ?? __('flash.export_failed')),
        ]));
        Response::redirect('/softwares/' . $softwareId . '?tab=deploy');
    }
}
