<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\AuditLogController;
use App\Controllers\ChangelogController;
use App\Controllers\DashboardController;
use App\Controllers\DeployController;
use App\Controllers\PackageExportController;
use App\Controllers\PipelineController;
use App\Controllers\ProcedureController;
use App\Controllers\SettingsController;
use App\Controllers\SoftwareController;
use App\Controllers\SyncController;
use App\Controllers\UserController;
use App\Controllers\CategoryController;
use App\Controllers\VmValidationController;
use App\Controllers\DocsController;
use App\Controllers\BiosController;
use App\Controllers\DriverController;
use App\Controllers\DriverPipelineController;
use App\Controllers\WelcomeController;
use App\Support\Dispatcher;
use App\Support\Request;
use App\Support\Response;
use App\Support\Router;

/**
 * @var \App\Support\Container $container
 */
$container = require dirname(__DIR__) . '/config/bootstrap.php';

$router = new Router();

$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/account/password', [AccountController::class, 'passwordForm']);
$router->post('/account/password', [AccountController::class, 'changePassword']);
$router->get('/account/vm', [AccountController::class, 'vmForm']);
$router->post('/account/vm', [AccountController::class, 'updateVm']);
$router->post('/account/vm/ping', [AccountController::class, 'pingVm']);

$router->get('/welcome', [WelcomeController::class, 'index']);
$router->get('/welcome/{lang}', [WelcomeController::class, 'index']);
$router->get('/', [DashboardController::class, 'index']);
$router->get('/docs', [DocsController::class, 'index']);

$router->get('/logs', [AuditLogController::class, 'index']);

$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings/virustotal', [SettingsController::class, 'updateVirusTotal']);
$router->post('/settings/virustotal/ping', [SettingsController::class, 'pingVirusTotal']);
$router->post('/settings/mail', [SettingsController::class, 'updateMail']);
$router->post('/settings/mail/test', [SettingsController::class, 'testMail']);
$router->post('/settings/mail/renewals', [SettingsController::class, 'runRenewalMails']);
$router->post('/settings/mecm', [SettingsController::class, 'updateMecm']);
$router->post('/settings/bios', [SettingsController::class, 'updateBios']);
$router->post('/settings/site', [SettingsController::class, 'updateSite']);
$router->post('/settings/permissions/run', [SettingsController::class, 'runPermissions']);

$router->get('/install', static function (Request $request): void {
    $params = ['section' => 'permissions'];
    $dataRoot = trim((string) $request->query('data_root', ''));
    if ($dataRoot !== '') {
        $params['data_root'] = $dataRoot;
    }
    Response::redirect('/settings?' . http_build_query($params) . '#permissions');
});
$router->post('/install/run', [SettingsController::class, 'runPermissions']);

$router->get('/softwares', [SoftwareController::class, 'index']);
$router->get('/softwares/create', [SoftwareController::class, 'createForm']);
$router->get('/softwares/import', [SoftwareController::class, 'importCatalogForm']);
$router->post('/softwares/import', [SoftwareController::class, 'importCatalog']);
$router->post('/softwares', [SoftwareController::class, 'store']);
$router->get('/softwares/{id}/export-catalog', [SoftwareController::class, 'exportCatalog']);
$router->get('/softwares/{id}', [SoftwareController::class, 'show']);
$router->get('/softwares/{id}/edit', [SoftwareController::class, 'editForm']);
$router->post('/softwares/{id}', [SoftwareController::class, 'update']);
$router->post('/softwares/{id}/delete', [SoftwareController::class, 'destroy']);
$router->post('/softwares/{id}/icon', [SoftwareController::class, 'uploadIcon']);
$router->post('/softwares/{id}/installer', [SoftwareController::class, 'uploadInstaller']);
$router->post('/softwares/{id}/installer/prefer-url', [SoftwareController::class, 'preferUrlSync']);
$router->post('/softwares/{id}/sync', [SyncController::class, 'check']);
$router->post('/softwares/{id}/syncs/{syncId}/delete', [SyncController::class, 'deleteHistory']);
$router->get('/softwares/{id}/export-zip', [PackageExportController::class, 'download']);
$router->post('/softwares/{id}/export-zip', [PackageExportController::class, 'generate']);
$router->get('/softwares/{id}/exports/{exportId}/download', [PackageExportController::class, 'downloadHistory']);
$router->post('/softwares/{id}/exports/{exportId}/delete', [PackageExportController::class, 'deleteHistory']);
$router->post('/softwares/{id}/exports/{exportId}/test-vm', [VmValidationController::class, 'run']);
$router->post('/softwares/{id}/vm-runs/{runId}/delete', [VmValidationController::class, 'deleteHistory']);
$router->post('/softwares/{id}/pipeline', [PipelineController::class, 'start']);
$router->get('/softwares/{id}/pipeline/{jobId}', [PipelineController::class, 'status']);
$router->post('/softwares/{id}/pipeline/{jobId}/vm-confirm', [PipelineController::class, 'vmConfirm']);
$router->post('/softwares/{id}/pipeline/{jobId}/vt-confirm', [PipelineController::class, 'vtConfirm']);

$router->post('/softwares/{id}/procedure', [ProcedureController::class, 'updateMeta']);
$router->post('/softwares/{id}/procedure/wizard', [ProcedureController::class, 'applyWizard']);
$router->post('/softwares/{id}/procedure/wizard/probe', [ProcedureController::class, 'probeInstaller']);
$router->post('/softwares/{id}/procedure/steps', [ProcedureController::class, 'addStep']);
$router->post('/softwares/{id}/procedure/steps/{stepId}', [ProcedureController::class, 'updateStep']);
$router->post('/softwares/{id}/procedure/steps/{stepId}/delete', [ProcedureController::class, 'deleteStep']);
$router->post('/softwares/{id}/procedure/reorder', [ProcedureController::class, 'reorder']);
$router->post('/softwares/{id}/deploy', [DeployController::class, 'update']);

$router->get('/drivers', [DriverController::class, 'index']);
$router->get('/drivers/create', [DriverController::class, 'createForm']);
$router->post('/drivers', [DriverController::class, 'store']);
$router->get('/drivers/{id}', [DriverController::class, 'show']);
$router->get('/drivers/{id}/edit', [DriverController::class, 'editForm']);
$router->post('/drivers/{id}', [DriverController::class, 'update']);
$router->post('/drivers/{id}/delete', [DriverController::class, 'destroy']);
$router->post('/drivers/{id}/sync', [DriverController::class, 'sync']);
$router->post('/drivers/{id}/export-zip', [DriverController::class, 'generateZip']);
$router->get('/drivers/{id}/export-zip', [DriverController::class, 'downloadZip']);
$router->post('/drivers/{id}/pipeline', [DriverPipelineController::class, 'start']);
$router->get('/drivers/{id}/pipeline/{jobId}', [DriverPipelineController::class, 'status']);
$router->post('/drivers/{id}/pipeline/{jobId}/vt-confirm', [DriverPipelineController::class, 'vtConfirm']);

$router->get('/bios', [BiosController::class, 'index']);
$router->get('/bios/hp/cfg-editor', [BiosController::class, 'hpCfgEditor']);
$router->post('/bios/hp/cfg-versions', [BiosController::class, 'saveHpConfig']);
$router->get('/bios/hp/cfg-versions/{id}/download', [BiosController::class, 'downloadHpConfig']);
$router->post('/bios/hp/cfg-versions/{id}/delete', [BiosController::class, 'deleteHpConfig']);
$router->get('/bios/hp/scripts/{file}', [BiosController::class, 'downloadHpScript']);
$router->get('/bios/dell/cfg-editor', [BiosController::class, 'dellCfgEditor']);
$router->post('/bios/dell/cfg-versions', [BiosController::class, 'saveDellConfig']);
$router->get('/bios/dell/cfg-versions/{id}/download', [BiosController::class, 'downloadDellConfig']);
$router->post('/bios/dell/cfg-versions/{id}/delete', [BiosController::class, 'deleteDellConfig']);
$router->get('/bios/dell/scripts/{file}', [BiosController::class, 'downloadDellScript']);
$router->get('/bios/lenovo/cfg-editor', [BiosController::class, 'lenovoCfgEditor']);
$router->post('/bios/lenovo/cfg-versions', [BiosController::class, 'saveLenovoConfig']);
$router->get('/bios/lenovo/cfg-versions/{id}/download', [BiosController::class, 'downloadLenovoConfig']);
$router->post('/bios/lenovo/cfg-versions/{id}/delete', [BiosController::class, 'deleteLenovoConfig']);
$router->get('/bios/lenovo/scripts/{file}', [BiosController::class, 'downloadLenovoScript']);
$router->get('/bios/{code}', [BiosController::class, 'manufacturer']);

$router->get('/api/changelog', [ChangelogController::class, 'api']);
$router->get('/api/step-schema/{type}', [ProcedureController::class, 'stepSchema']);

$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->post('/users/{id}/delete', [UserController::class, 'destroy']);
$router->post('/users/{id}', [UserController::class, 'update']);

$router->get('/categories', static function (): void {
    Response::redirect('/settings#categories');
});
$router->post('/settings/categories', [CategoryController::class, 'store']);
$router->post('/settings/categories/{id}/delete', [CategoryController::class, 'destroy']);
$router->post('/settings/categories/{id}', [CategoryController::class, 'update']);

$request = Request::fromGlobals();
$match = $router->match($request->method(), $request->path());

if ($match === null) {
    Response::abort(404, 'Page introuvable');
}

$dispatcher = new Dispatcher($container);

try {
    $dispatcher->dispatch($match['handler'], $match['params'], $request);
} catch (Throwable $e) {
    $debug = (getenv('APP_DEBUG') ?: '0') === '1';
    if ($debug) {
        Response::html('<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>', 500);
    } else {
        error_log((string) $e);
        Response::abort(500, 'Erreur interne');
    }
}
