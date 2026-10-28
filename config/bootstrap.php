<?php

declare(strict_types=1);

/**
 * Application bootstrap: autoload, config, container, session.
 */

use App\Domain\Procedure\StepPayloadValidator;
use App\Repositories\AuditLogRepository;
use App\Repositories\BiosDellConfigRepository;
use App\Repositories\BiosHpConfigRepository;
use App\Repositories\BiosLenovoConfigRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\DeployProfileRepository;
use App\Repositories\DriverRepository;
use App\Repositories\DriverSyncRepository;
use App\Repositories\PackageExportRepository;
use App\Repositories\ProcedureRepository;
use App\Repositories\SoftwareRepository;
use App\Repositories\SyncRepository;
use App\Repositories\UserRepository;
use App\Repositories\VmValidationRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\BiosDellConfigService;
use App\Services\BiosHpConfigService;
use App\Services\BiosLenovoConfigService;
use App\Services\BiosPackageBuilder;
use App\Services\BiosSettingsService;
use App\Services\ChangelogService;
use App\Services\DashboardService;
use App\Services\DeployService;
use App\Services\DellDriverPackResolver;
use App\Services\HpDriverPackResolver;
use App\Services\DriverPackageService;
use App\Services\DriverService;
use App\Services\DriverSyncService;
use App\Services\GithubReleaseDetector;
use App\Services\InstallCheckService;
use App\Services\MailConfigService;
use App\Services\MailService;
use App\Services\MecmConfigService;
use App\Services\PackageExportService;
use App\Services\RenewalReminderService;
use App\Services\DriverPipelineJobService;
use App\Services\PipelineJobService;
use App\Services\ProcedureService;
use App\Services\SccmIconNormalizer;
use App\Services\SoftwareCatalogService;
use App\Services\SoftwareService;
use App\Services\SyncService;
use App\Services\VirusTotalConfigService;
use App\Services\VirusTotalService;
use App\Services\VmConfigService;
use App\Services\VmValidationService;
use App\Services\SiteConfigService;
use App\Support\Container;
use App\Support\I18n;
use App\Support\JsonStore;
use App\Support\Session;
use App\Support\View;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';
require_once $root . '/src/Support/helpers.php';

$appConfig = require $root . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

Session::prepareRuntimeTemp($root);

if (PHP_SAPI !== 'cli') {
    Session::start($appConfig['session_name']);
} else {
    // CLI workers: no HTTP session cookie
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $_SESSION = [];
    }
}

$authConfig = require $root . '/config/auth.php';
$vmConfig = require $root . '/config/test_vm.php';
$jsonStore = new JsonStore($appConfig['data_path']);

$container = new Container();

$container->set('config.app', static fn () => $appConfig);
$container->set('config.auth', static fn () => $authConfig);
$container->set('config.test_vm', static fn () => $vmConfig);
$container->set(JsonStore::class, static fn () => $jsonStore);

$container->set(Csrf::class, static fn () => new Csrf());
$container->set(UserRepository::class, static fn (Container $c) => new UserRepository($c->get(JsonStore::class)));
$container->set(SoftwareRepository::class, static fn (Container $c) => new SoftwareRepository($c->get(JsonStore::class)));
$container->set(CategoryRepository::class, static fn (Container $c) => new CategoryRepository($c->get(JsonStore::class)));
$container->set(DriverRepository::class, static fn (Container $c) => new DriverRepository($c->get(JsonStore::class)));
$container->set(BiosSettingsService::class, static function (Container $c) use ($appConfig): BiosSettingsService {
    return new BiosSettingsService(
        $appConfig['data_path'],
        $c->get(AuditLogRepository::class),
    );
});
$container->set(BiosPackageBuilder::class, static function (Container $c) use ($root): BiosPackageBuilder {
    return new BiosPackageBuilder(
        $root . '/resources/bios',
        $root . '/storage/cache/tmp',
        $c->get(BiosSettingsService::class),
    );
});
$container->set(BiosHpConfigRepository::class, static fn (Container $c) => new BiosHpConfigRepository($c->get(JsonStore::class)));
$container->set(BiosHpConfigService::class, static function (Container $c) use ($root): BiosHpConfigService {
    return new BiosHpConfigService(
        $c->get(BiosHpConfigRepository::class),
        $c->get(AuditLogRepository::class),
        $c->get(BiosPackageBuilder::class),
        $root . '/storage/bios/hp',
    );
});
$container->set(BiosDellConfigRepository::class, static fn (Container $c) => new BiosDellConfigRepository($c->get(JsonStore::class)));
$container->set(BiosDellConfigService::class, static function (Container $c) use ($root): BiosDellConfigService {
    return new BiosDellConfigService(
        $c->get(BiosDellConfigRepository::class),
        $c->get(AuditLogRepository::class),
        $c->get(BiosPackageBuilder::class),
        $root . '/storage/bios/dell',
    );
});
$container->set(BiosLenovoConfigRepository::class, static fn (Container $c) => new BiosLenovoConfigRepository($c->get(JsonStore::class)));
$container->set(BiosLenovoConfigService::class, static function (Container $c) use ($root): BiosLenovoConfigService {
    return new BiosLenovoConfigService(
        $c->get(BiosLenovoConfigRepository::class),
        $c->get(AuditLogRepository::class),
        $c->get(BiosPackageBuilder::class),
        $root . '/storage/bios/lenovo',
    );
});
$container->set(DriverSyncRepository::class, static fn (Container $c) => new DriverSyncRepository($c->get(JsonStore::class)));
$container->set(ProcedureRepository::class, static fn (Container $c) => new ProcedureRepository($c->get(JsonStore::class)));
$container->set(AuditLogRepository::class, static fn (Container $c) => new AuditLogRepository($c->get(JsonStore::class)));

$container->set(SiteConfigService::class, static function (Container $c) use ($appConfig): SiteConfigService {
    return new SiteConfigService(
        $appConfig['data_path'],
        $c->get(AuditLogRepository::class),
    );
});

$container->set(I18n::class, static function (Container $c) use ($root): I18n {
    return new I18n(
        $root . '/lang',
        $c->get(SiteConfigService::class)->locale(),
    );
});

// Force I18n singleton early so __() works in templates/domain labels.
$container->get(I18n::class);

$container->set(View::class, static function (Container $c) use ($root, $appConfig): View {
    $i18n = $c->get(I18n::class);
    $view = new View($root . '/templates');
    $view->share('appName', $appConfig['name']);
    $view->share('appVersion', $appConfig['version']);
    $view->share('uploadUrl', $appConfig['upload_url']);
    $view->share('locale', $i18n->locale());
    $view->share('jsI18n', $i18n->jsDictionary());

    return $view;
});

$container->set(SyncRepository::class, static function (Container $c): SyncRepository {
    return new SyncRepository(
        $c->get(JsonStore::class),
        $c->get(SoftwareRepository::class),
    );
});
$container->set(PackageExportRepository::class, static fn (Container $c) => new PackageExportRepository($c->get(JsonStore::class)));
$container->set(DeployProfileRepository::class, static fn (Container $c) => new DeployProfileRepository($c->get(JsonStore::class)));
$container->set(VmValidationRepository::class, static fn (Container $c) => new VmValidationRepository($c->get(JsonStore::class)));

// Keep admin credentials from config/auth.php in sync with users.json
$container->get(UserRepository::class)->syncAdminFromConfig($authConfig['admin']);

$container->set(Auth::class, static fn (Container $c) => new Auth($c->get(UserRepository::class)));
$container->set(StepPayloadValidator::class, static fn () => new StepPayloadValidator());

$container->set(SccmIconNormalizer::class, static fn () => new SccmIconNormalizer());
$container->set(SoftwareService::class, static function (Container $c) use ($appConfig): SoftwareService {
    return new SoftwareService(
        $c->get(SoftwareRepository::class),
        $c->get(ProcedureRepository::class),
        $c->get(AuditLogRepository::class),
        $appConfig['upload_path'],
        $appConfig['packages_path'],
        $c->get(SccmIconNormalizer::class),
        $c->get(CategoryRepository::class),
    );
});

// Seed categories.json from existing software labels if empty.
$container->get(CategoryRepository::class)->seedIfEmpty(
    $container->get(SoftwareRepository::class)->distinctCategories()
);

$container->set(SoftwareCatalogService::class, static function (Container $c): SoftwareCatalogService {
    return new SoftwareCatalogService(
        $c->get(SoftwareService::class),
        $c->get(ProcedureService::class),
        $c->get(StepPayloadValidator::class),
        $c->get(AuditLogRepository::class),
        $c->get(CategoryRepository::class),
    );
});

$container->set(ProcedureService::class, static function (Container $c): ProcedureService {
    return new ProcedureService(
        $c->get(ProcedureRepository::class),
        $c->get(SoftwareRepository::class),
        $c->get(StepPayloadValidator::class),
        $c->get(AuditLogRepository::class),
    );
});

$container->set(DeployService::class, static function (Container $c): DeployService {
    return new DeployService(
        $c->get(SoftwareRepository::class),
        $c->get(ProcedureRepository::class),
        $c->get(DeployProfileRepository::class),
        $c->get(AuditLogRepository::class),
        $c->get(MecmConfigService::class),
    );
});

$container->set(MecmConfigService::class, static function (Container $c) use ($appConfig): MecmConfigService {
    return new MecmConfigService(
        $appConfig['data_path'],
        $c->get(AuditLogRepository::class),
    );
});

$container->set(DriverService::class, static function (Container $c): DriverService {
    return new DriverService(
        $c->get(DriverRepository::class),
        $c->get(AuditLogRepository::class),
    );
});

$container->set(DriverPackageService::class, static function (Container $c) use ($appConfig): DriverPackageService {
    return new DriverPackageService(
        $c->get(DriverRepository::class),
        $c->get(MecmConfigService::class),
        $c->get(AuditLogRepository::class),
        $appConfig['export_path'],
    );
});

$container->set(DellDriverPackResolver::class, static fn () => new DellDriverPackResolver());
$container->set(HpDriverPackResolver::class, static fn () => new HpDriverPackResolver(
    dirname(__DIR__) . '/storage/cache'
));

$container->set(DriverSyncService::class, static function (Container $c): DriverSyncService {
    return new DriverSyncService(
        $c->get(DriverRepository::class),
        $c->get(DriverSyncRepository::class),
        $c->get(AuditLogRepository::class),
        $c->get(DellDriverPackResolver::class),
        $c->get(HpDriverPackResolver::class),
    );
});

$container->set(ChangelogService::class, static function (Container $c) use ($appConfig): ChangelogService {
    return new ChangelogService(
        $appConfig['changelog_path'],
        $appConfig['version'],
    );
});

$container->set(DashboardService::class, static function (Container $c): DashboardService {
    return new DashboardService(
        $c->get(SoftwareRepository::class),
        $c->get(DriverRepository::class),
    );
});

$container->set(GithubReleaseDetector::class, static fn () => new GithubReleaseDetector());
$container->set(SyncService::class, static function (Container $c) use ($appConfig): SyncService {
    return new SyncService(
        $c->get(SoftwareRepository::class),
        $c->get(SyncRepository::class),
        $c->get(AuditLogRepository::class),
        $appConfig['packages_path'],
        $c->get(GithubReleaseDetector::class),
    );
});

$container->set(PackageExportService::class, static function (Container $c) use ($appConfig): PackageExportService {
    return new PackageExportService(
        $c->get(SoftwareRepository::class),
        $c->get(ProcedureRepository::class),
        $c->get(AuditLogRepository::class),
        $c->get(SyncRepository::class),
        $c->get(PackageExportRepository::class),
        $c->get(DeployService::class),
        $appConfig['export_path'],
        $appConfig['upload_path'],
        $appConfig['packages_path'],
    );
});

$container->set(VmConfigService::class, static function (Container $c) use ($appConfig, $vmConfig): VmConfigService {
    return new VmConfigService(
        $appConfig['data_path'],
        $vmConfig,
        $c->get(AuditLogRepository::class),
        $c->get(UserRepository::class),
    );
});

$container->set(VirusTotalConfigService::class, static function (Container $c) use ($appConfig): VirusTotalConfigService {
    return new VirusTotalConfigService(
        $appConfig['data_path'],
        $c->get(AuditLogRepository::class),
    );
});

$container->set(VirusTotalService::class, static function (Container $c): VirusTotalService {
    return new VirusTotalService($c->get(VirusTotalConfigService::class));
});

$container->set(MailConfigService::class, static function (Container $c) use ($appConfig): MailConfigService {
    return new MailConfigService(
        $appConfig['data_path'],
        $c->get(AuditLogRepository::class),
        (string) ($appConfig['url'] ?? ''),
    );
});

$container->set(MailService::class, static function (Container $c): MailService {
    return new MailService($c->get(MailConfigService::class));
});

$container->set(RenewalReminderService::class, static function (Container $c): RenewalReminderService {
    return new RenewalReminderService(
        $c->get(SoftwareRepository::class),
        $c->get(UserRepository::class),
        $c->get(PackageExportRepository::class),
        $c->get(MailConfigService::class),
        $c->get(MailService::class),
        $c->get(AuditLogRepository::class),
    );
});

$container->set(VmValidationService::class, static function (Container $c): VmValidationService {
    return new VmValidationService(
        $c->get(SoftwareRepository::class),
        $c->get(PackageExportRepository::class),
        $c->get(PackageExportService::class),
        $c->get(VmValidationRepository::class),
        $c->get(AuditLogRepository::class),
        $c->get(VmConfigService::class),
    );
});

$container->set(PipelineJobService::class, static function (Container $c) use ($appConfig, $root): PipelineJobService {
    return new PipelineJobService(
        $appConfig['jobs_path'],
        $root,
        $c->get(SyncService::class),
        $c->get(PackageExportService::class),
        $c->get(VmValidationService::class),
        $c->get(VirusTotalService::class),
        $c->get(SoftwareRepository::class),
    );
});

$container->set(DriverPipelineJobService::class, static function (Container $c) use ($appConfig, $root): DriverPipelineJobService {
    return new DriverPipelineJobService(
        $appConfig['jobs_path'],
        $root,
        $c->get(DriverSyncService::class),
        $c->get(DriverPackageService::class),
        $c->get(VirusTotalService::class),
        $c->get(DriverRepository::class),
    );
});

$container->set(InstallCheckService::class, static function (Container $c) use ($root): InstallCheckService {
    return new InstallCheckService(
        $root,
        $c->get(AuditLogRepository::class),
    );
});

return $container;
