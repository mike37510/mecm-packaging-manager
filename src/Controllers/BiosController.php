<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\ModuleAccess;

use App\Services\BiosDellConfigService;
use App\Services\BiosHpConfigService;
use App\Services\BiosLenovoConfigService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * BIOS management hubs (HP / Dell / Lenovo) and config editors.
 */
final class BiosController extends BaseController
{
    private const MANUFACTURERS = ['hp', 'dell', 'lenovo'];

    /** @var array<string, string> */
    private const HP_SCRIPTS = [
        'Apply-HPBiosConfig.ps1' => 'apply',
        'Clear-HPBiosSetupPassword.ps1' => 'clear_pwd',
        'Get-HPBiosAllSettings.ps1' => 'get_all',
    ];

    /** @var array<string, string> */
    private const DELL_SCRIPTS = [
        'Apply-DellBiosConfig.ps1' => 'apply',
        'Clear-DellBiosSetupPassword.ps1' => 'clear_pwd',
        'Get-DellBiosAllSettings.ps1' => 'get_all',
    ];

    /** @var array<string, string> */
    private const LENOVO_SCRIPTS = [
        'Apply-LenovoBiosConfig.ps1' => 'apply',
        'Clear-LenovoBiosSupervisorPassword.ps1' => 'clear_pwd',
        'Get-LenovoBiosAllSettings.ps1' => 'get_all',
    ];

    public function index(Request $request): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_READ);
        $this->render('bios/index', [
            'pageTitle' => __('bios.list_title'),
            'manufacturers' => self::MANUFACTURERS,
            'available' => ['hp', 'dell', 'lenovo'],
        ]);
    }

    public function manufacturer(
        Request $request,
        BiosHpConfigService $hpConfigs,
        BiosDellConfigService $dellConfigs,
        BiosLenovoConfigService $lenovoConfigs,
        string $code,
    ): void {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_READ);
        $code = strtolower($code);
        if (!in_array($code, self::MANUFACTURERS, true)) {
            Response::abort(404, __('bios.not_found'));
        }

        // Scripts PowerShell are shipped inside each version ZIP (see SCRIPTS.txt), not listed on the hub.
        $this->render('bios/' . $code, [
            'pageTitle' => __('bios.mfr_' . $code . '_title'),
            'manufacturer' => $code,
            'hpVersions' => $code === 'hp' ? $hpConfigs->listVersions() : [],
            'dellVersions' => $code === 'dell' ? $dellConfigs->listVersions() : [],
            'lenovoVersions' => $code === 'lenovo' ? $lenovoConfigs->listVersions() : [],
        ]);
    }

    public function hpCfgEditor(Request $request, BiosHpConfigService $hpConfigs): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_READ);
        $base = dirname(__DIR__, 2) . '/public/assets/js/bios';
        $user = $this->auth->user();

        $this->render('bios/hp_cfg_editor', [
            'pageTitle' => __('bios.hp_editor_title'),
            'optionsJsV' => (string) @filemtime($base . '/hp-bcu-options.js'),
            'helpJsV' => (string) @filemtime($base . '/hp-bcu-help.js'),
            'editorJsV' => (string) @filemtime($base . '/hp-bcu-editor.js'),
            'hpVersions' => $hpConfigs->listVersions(),
            'canWrite' => $user !== null && $user->canWriteModule(ModuleAccess::MODULE_BIOS),
        ]);
    }

    public function dellCfgEditor(Request $request, BiosDellConfigService $dellConfigs): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_READ);
        $base = dirname(__DIR__, 2) . '/public/assets/js/bios';
        $user = $this->auth->user();

        $this->render('bios/dell_cfg_editor', [
            'pageTitle' => __('bios.dell_editor_title'),
            'optionsJsV' => (string) @filemtime($base . '/dell-cctk-options.js'),
            'helpJsV' => (string) @filemtime($base . '/dell-cctk-help.js'),
            'editorJsV' => (string) @filemtime($base . '/dell-cctk-editor.js'),
            'dellVersions' => $dellConfigs->listVersions(),
            'canWrite' => $user !== null && $user->canWriteModule(ModuleAccess::MODULE_BIOS),
        ]);
    }

    public function lenovoCfgEditor(Request $request, BiosLenovoConfigService $lenovoConfigs): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_READ);
        $base = dirname(__DIR__, 2) . '/public/assets/js/bios';
        $user = $this->auth->user();

        $this->render('bios/lenovo_cfg_editor', [
            'pageTitle' => __('bios.lenovo_editor_title'),
            'optionsJsV' => (string) @filemtime($base . '/lenovo-tbct-options.js'),
            'helpJsV' => (string) @filemtime($base . '/lenovo-tbct-help.js'),
            'editorJsV' => (string) @filemtime($base . '/lenovo-tbct-editor.js'),
            'lenovoVersions' => $lenovoConfigs->listVersions(),
            'canWrite' => $user !== null && $user->canWriteModule(ModuleAccess::MODULE_BIOS),
        ]);
    }

    public function saveHpConfig(Request $request, BiosHpConfigService $hpConfigs): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $content = (string) $request->input('content', '');
        $note = $this->trimNote((string) $request->input('note', ''));
        $user = $this->auth->user();
        $result = $hpConfigs->save(
            $content,
            $note !== '' ? $note : null,
            $user?->id,
            $user?->displayName,
            $request->ip(),
        );

        if (!$result['ok']) {
            Response::json(['ok' => false, 'error' => $this->hpSaveError($result['error'] ?? '')], 422);
        }

        Response::json([
            'ok' => true,
            'id' => $result['id'],
            'version' => $result['version'],
            'filename' => $result['filename'],
            'message' => __('bios.hp_save_ok', ['file' => $result['filename']]),
            'redirect' => '/bios/hp/cfg-editor',
        ]);
    }

    public function saveDellConfig(Request $request, BiosDellConfigService $dellConfigs): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $content = $this->biosPostedContent($request);
        $note = $this->trimNote((string) $request->input('note', ''));
        $user = $this->auth->user();
        $result = $dellConfigs->save(
            $content,
            $note !== '' ? $note : null,
            $user?->id,
            $user?->displayName,
            $request->ip(),
        );

        if (!$result['ok']) {
            Response::json(['ok' => false, 'error' => $this->dellSaveError($result['error'] ?? '')], 422);
        }

        Response::json([
            'ok' => true,
            'id' => $result['id'],
            'version' => $result['version'],
            'filename' => $result['filename'],
            'message' => __('bios.dell_save_ok', ['file' => $result['filename']]),
            'redirect' => '/bios/dell/cfg-editor',
        ]);
    }

    public function saveLenovoConfig(Request $request, BiosLenovoConfigService $lenovoConfigs): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);

        $content = (string) $request->input('content', '');
        $note = $this->trimNote((string) $request->input('note', ''));
        $user = $this->auth->user();
        $result = $lenovoConfigs->save(
            $content,
            $note !== '' ? $note : null,
            $user?->id,
            $user?->displayName,
            $request->ip(),
        );

        if (!$result['ok']) {
            Response::json(['ok' => false, 'error' => $this->lenovoSaveError($result['error'] ?? '')], 422);
        }

        Response::json([
            'ok' => true,
            'id' => $result['id'],
            'version' => $result['version'],
            'filename' => $result['filename'],
            'message' => __('bios.lenovo_save_ok', ['file' => $result['filename']]),
            'redirect' => '/bios/lenovo/cfg-editor',
        ]);
    }

    public function downloadHpConfig(Request $request, BiosHpConfigService $hpConfigs, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_READ);
        $result = $hpConfigs->buildPackageZip((int) $id);
        if (!$result['ok']) {
            Response::abort(404, __('bios.hp_version_not_found'));
        }
        $this->sendTempFile($result['path'], $result['filename'], 'application/zip');
    }

    public function downloadDellConfig(Request $request, BiosDellConfigService $dellConfigs, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_READ);
        $result = $dellConfigs->buildPackageZip((int) $id);
        if (!$result['ok']) {
            Response::abort(404, __('bios.dell_version_not_found'));
        }
        $this->sendTempFile($result['path'], $result['filename'], 'application/zip');
    }

    public function downloadLenovoConfig(Request $request, BiosLenovoConfigService $lenovoConfigs, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_READ);
        $result = $lenovoConfigs->buildPackageZip((int) $id);
        if (!$result['ok']) {
            Response::abort(404, __('bios.lenovo_version_not_found'));
        }
        $this->sendTempFile($result['path'], $result['filename'], 'application/zip');
    }

    public function deleteHpConfig(Request $request, BiosHpConfigService $hpConfigs, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);
        $result = $hpConfigs->delete((int) $id, $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', __('bios.hp_version_not_found'));
            Response::redirect('/bios/hp/cfg-editor');
        }
        Session::flash('success', __('bios.hp_delete_ok'));
        Response::redirect($this->safeBiosBack($request, '/bios/hp/cfg-editor'));
    }

    public function deleteDellConfig(Request $request, BiosDellConfigService $dellConfigs, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);
        $result = $dellConfigs->delete((int) $id, $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', __('bios.dell_version_not_found'));
            Response::redirect('/bios/dell/cfg-editor');
        }
        Session::flash('success', __('bios.dell_delete_ok'));
        Response::redirect($this->safeBiosBack($request, '/bios/dell/cfg-editor'));
    }

    public function deleteLenovoConfig(Request $request, BiosLenovoConfigService $lenovoConfigs, string $id): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_WRITE);
        $this->requireCsrf($request);
        $result = $lenovoConfigs->delete((int) $id, $this->auth->id(), $request->ip());
        if (!$result['ok']) {
            Session::flash('error', __('bios.lenovo_version_not_found'));
            Response::redirect('/bios/lenovo/cfg-editor');
        }
        Session::flash('success', __('bios.lenovo_delete_ok'));
        Response::redirect($this->safeBiosBack($request, '/bios/lenovo/cfg-editor'));
    }

    public function downloadHpScript(Request $request, string $file): void
    {
        $this->downloadScript($file, self::HP_SCRIPTS, 'hp');
    }

    public function downloadDellScript(Request $request, string $file): void
    {
        $this->downloadScript($file, self::DELL_SCRIPTS, 'dell');
    }

    public function downloadLenovoScript(Request $request, string $file): void
    {
        $this->downloadScript($file, self::LENOVO_SCRIPTS, 'lenovo');
    }

    /**
     * @param array<string, string> $allowed
     */
    private function downloadScript(string $file, array $allowed, string $vendor): void
    {
        $this->auth->requireModule(ModuleAccess::MODULE_BIOS, ModuleAccess::LEVEL_READ);
        $base = basename($file);
        if (!isset($allowed[$base])) {
            Response::abort(404, __('bios.script_not_found'));
        }
        $path = dirname(__DIR__, 2) . '/resources/bios/' . $vendor . '/' . $base;
        if (!is_file($path)) {
            Response::abort(404, __('bios.script_not_found'));
        }
        $this->sendFile($path, $base, 'application/octet-stream');
    }

    private function sendFile(string $path, string $filename, string $contentType): never
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function sendTempFile(string $path, string $filename, string $contentType): never
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        try {
            readfile($path);
        } finally {
            @unlink($path);
        }
        exit;
    }

    /**
     * Read BIOS editor payload. Prefers content_b64 (avoids HTTP filters that
     * blank form fields containing INI section headers like "[cctk]").
     */
    private function biosPostedContent(Request $request): string
    {
        $b64 = trim((string) $request->input('content_b64', ''));
        if ($b64 !== '') {
            $decoded = base64_decode($b64, true);
            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return (string) $request->input('content', '');
    }

    private function trimNote(string $note): string
    {
        $note = trim($note);
        if (mb_strlen($note) > 200) {
            return mb_substr($note, 0, 200);
        }

        return $note;
    }

    private function safeBiosBack(Request $request, string $default): string
    {
        $back = (string) ($request->input('back') ?: $default);
        if (!str_starts_with($back, '/bios')) {
            return $default;
        }

        return $back;
    }

    private function hpSaveError(string $code): string
    {
        return match ($code) {
            'empty_selection' => __('bios.hp_save_empty'),
            'write_failed' => __('bios.hp_save_write_failed'),
            default => __('bios.hp_save_invalid'),
        };
    }

    private function dellSaveError(string $code): string
    {
        return match ($code) {
            'empty_selection' => __('bios.dell_save_empty'),
            'write_failed' => __('bios.dell_save_write_failed'),
            default => __('bios.dell_save_invalid'),
        };
    }

    private function lenovoSaveError(string $code): string
    {
        return match ($code) {
            'empty_selection' => __('bios.lenovo_save_empty'),
            'write_failed' => __('bios.lenovo_save_write_failed'),
            default => __('bios.lenovo_save_invalid'),
        };
    }
}
