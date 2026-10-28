<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Repositories\SoftwareRepository;
use App\Services\BiosSettingsService;
use App\Services\InstallCheckService;
use App\Services\MailConfigService;
use App\Services\MailService;
use App\Services\MecmConfigService;
use App\Services\RenewalReminderService;
use App\Services\SiteConfigService;
use App\Services\VirusTotalConfigService;
use App\Services\VirusTotalService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

/**
 * Admin settings (MECM defaults + site UI + categories). Lab VM is per-user under /account/vm.
 */
final class SettingsController extends BaseController
{
    public function index(
        Request $request,
        VirusTotalConfigService $virusTotalConfig,
        MailConfigService $mailConfig,
        MecmConfigService $mecmConfig,
        BiosSettingsService $biosSettings,
        InstallCheckService $installCheck,
        SiteConfigService $siteConfig,
        CategoryRepository $categories,
        SoftwareRepository $softwares,
    ): void {
        $this->auth->requireRole(\App\Security\Role::Admin);

        $usage = [];
        foreach ($softwares->all() as $sw) {
            $cat = $sw->category;
            if ($cat === '') {
                continue;
            }
            $usage[$cat] = ($usage[$cat] ?? 0) + 1;
        }

        $this->render('settings/index', [
            'vt' => $virusTotalConfig->get(),
            'vtKeySet' => $virusTotalConfig->apiKeyIsSet(),
            'vtConfigured' => $virusTotalConfig->isEnabled(),
            'mail' => $mailConfig->get(),
            'mailPasswordSet' => $mailConfig->passwordIsSet(),
            'mailConfigured' => $mailConfig->isEnabled(),
            'mecm' => $mecmConfig->get(),
            'bios' => $biosSettings->get(),
            'siteLocale' => $siteConfig->locale(),
            'permissionsReport' => $installCheck->diagnose(),
            'permissionsDataRoot' => '',
            'permissionsActions' => Session::get('_install_actions'),
            'permissionsNeedsSudo' => (bool) Session::get('_install_needs_sudo'),
            'categories' => $categories->all(),
            'categoryUsage' => $usage,
        ]);
        Session::forget('_install_actions');
        Session::forget('_install_needs_sudo');
    }

    public function updateVirusTotal(Request $request, VirusTotalConfigService $virusTotalConfig): void
    {
        $this->auth->requireRole(\App\Security\Role::Admin);
        $this->requireCsrf($request);

        $result = $virusTotalConfig->save([
            'enabled' => $request->input('enabled') ? true : false,
            'api_key' => $request->input('api_key'),
        ], $this->auth->id(), $request->ip());

        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? __('flash.settings_save_failed'));
            Response::redirect('/settings#rubrique-general');
        }

        Session::flash('success', __('flash.settings_vt_saved'));
        Response::redirect('/settings#rubrique-general');
    }

    public function pingVirusTotal(Request $request, VirusTotalService $virusTotal): void
    {
        $this->auth->requireRole(\App\Security\Role::Admin);
        $this->requireCsrf($request);

        $result = $virusTotal->ping();
        if (empty($result['ok'])) {
            Session::flash('error', $result['error'] ?? __('flash.ping_vt_failed'));
            Response::redirect('/settings#rubrique-general');
        }

        Session::flash('success', __('flash.ping_vt_ok', [
            'message' => (string) ($result['message'] ?? 'OK'),
        ]));
        Response::redirect('/settings#rubrique-general');
    }

    public function updateMail(Request $request, MailConfigService $mailConfig): void
    {
        $this->auth->requireRole(\App\Security\Role::Admin);
        $this->requireCsrf($request);

        $result = $mailConfig->save([
            'enabled' => $request->input('enabled') ? true : false,
            'host' => $request->input('host'),
            'port' => $request->input('port'),
            'encryption' => $request->input('encryption'),
            'username' => $request->input('smtp_username', $request->input('username')),
            'password' => $request->input('smtp_password', $request->input('password')),
            'from_email' => $request->input('from_email'),
            'from_name' => $request->input('from_name'),
            'app_url' => $request->input('app_url'),
        ], $this->auth->id(), $request->ip());

        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? __('flash.settings_save_failed'));
            Response::redirect('/settings#rubrique-general');
        }

        Session::flash('success', __('flash.settings_mail_saved'));
        Response::redirect('/settings#rubrique-general');
    }

    public function testMail(Request $request, MailService $mail): void
    {
        $this->auth->requireRole(\App\Security\Role::Admin);
        $this->requireCsrf($request);

        $user = $this->auth->user();
        $to = $user?->email ?? '';
        if ($to === '') {
            Session::flash('error', __('flash.mail_test_no_user'));
            Response::redirect('/settings#rubrique-general');
        }

        $result = $mail->sendTest($to);
        if (empty($result['ok'])) {
            Session::flash('error', $result['error'] ?? __('flash.mail_test_failed'));
            Response::redirect('/settings#rubrique-general');
        }

        Session::flash('success', (string) ($result['message'] ?? __('flash.mail_test_ok', ['email' => $to])));
        Response::redirect('/settings#rubrique-general');
    }

    public function runRenewalMails(Request $request, RenewalReminderService $renewals): void
    {
        $this->auth->requireRole(\App\Security\Role::Admin);
        $this->requireCsrf($request);

        $result = $renewals->run($this->auth->id(), $request->ip());
        if (empty($result['ok'])) {
            Session::flash('error', $result['error'] ?? __('flash.mail_renewals_failed'));
            Response::redirect('/settings#rubrique-general');
        }

        $msg = __('flash.mail_renewals_done', [
            'sent' => (string) $result['sent'],
            'due' => (string) $result['due'],
            'skipped' => (string) $result['skipped'],
        ]);
        if ($result['failures'] !== []) {
            $msg .= ' ' . implode(' ', array_slice($result['failures'], 0, 3));
        }
        Session::flash($result['sent'] > 0 || $result['due'] === 0 ? 'success' : 'error', $msg);
        Response::redirect('/settings#rubrique-general');
    }

    public function updateMecm(Request $request, MecmConfigService $mecmConfig): void
    {
        $this->auth->requireRole(\App\Security\Role::Admin);
        $this->requireCsrf($request);

        $result = $mecmConfig->save([
            'site_code' => $request->input('site_code'),
            'site_server' => $request->input('site_server'),
            'distribution_point' => $request->input('distribution_point'),
            'collection_id' => $request->input('collection_id'),
            'deployment_purpose' => $request->input('deployment_purpose'),
            'driver_source_unc' => $request->input('driver_source_unc'),
            'driver_package_unc' => $request->input('driver_package_unc'),
            'driver_category' => $request->input('driver_category'),
        ], $this->auth->id(), $request->ip());

        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? __('flash.mecm_save_failed'));
            Response::redirect('/settings#rubrique-general');
        }

        Session::flash('success', __('flash.settings_mecm_saved'));
        Response::redirect('/settings#rubrique-general');
    }

    public function updateSite(Request $request, SiteConfigService $siteConfig): void
    {
        $this->auth->requireRole(\App\Security\Role::Admin);
        $this->requireCsrf($request);

        $result = $siteConfig->save([
            'locale' => $request->input('locale'),
        ], $this->auth->id(), $request->ip());

        if (!$result['ok']) {
            Session::flash('error', $result['error'] ?? __('flash.settings_save_failed'));
            Response::redirect('/settings#rubrique-general');
        }

        Session::flash('success', __('flash.settings_site_saved'));
        Response::redirect('/settings#rubrique-general');
    }

    public function updateBios(Request $request, BiosSettingsService $biosSettings): void
    {
        $this->auth->requireRole(\App\Security\Role::Admin);
        $this->requireCsrf($request);

        $result = $biosSettings->save([
            'log_local_path' => $request->input('log_local_path'),
            'log_share_path' => $request->input('log_share_path'),
        ], $this->auth->id(), $request->ip());

        if (!$result['ok']) {
            $err = match ($result['error'] ?? '') {
                'invalid_local' => __('settings.bios_err_local'),
                'invalid_share' => __('settings.bios_err_share'),
                default => __('flash.settings_save_failed'),
            };
            Session::flash('error', $err);
            Response::redirect('/settings#rubrique-bios');
        }

        Session::flash('success', __('flash.settings_bios_saved'));
        Response::redirect('/settings#rubrique-bios');
    }

    public function runPermissions(Request $request, InstallCheckService $installCheck): void
    {
        $this->auth->requireRole(\App\Security\Role::Admin);
        $this->requireCsrf($request);

        $result = $installCheck->repair(
            null,
            false,
            'www-data',
            'www-data',
            $this->auth->id(),
            $request->ip(),
        );

        Session::set('_install_actions', $result['actions']);
        Session::set('_install_needs_sudo', $result['needs_sudo']);

        if ($result['ok']) {
            if (!empty($result['report']['ok'])) {
                Session::flash('success', __('flash.install_ok'));
            } else {
                Session::flash('success', __('flash.install_perms_ok'));
            }
        } elseif ($result['needs_sudo']) {
            Session::flash('error', __('flash.install_needs_sudo'));
        } else {
            Session::flash('error', __('flash.install_partial'));
        }

        Response::redirect('/settings?section=permissions#permissions');
    }
}
