#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI : crée les dossiers runtime et fixe les droits (chown inclus si root).
 *
 * Usage :
 *   sudo php bin/fix-permissions.php
 *   sudo php bin/fix-permissions.php --data-root=/var/lib/sccm-packaging-factory --migrate
 *
 * Interface admin : /settings#permissions
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "bin/fix-permissions.php : CLI uniquement (UI admin : /settings#permissions).\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use App\Services\InstallCheckService;
use App\Support\Container;

$opts = getopt('', ['web-user::', 'web-group::', 'data-root::', 'migrate', 'help']);
if (isset($opts['help'])) {
    echo <<<HELP
MECM Packaging Manager — droits (CLI)

  sudo php bin/fix-permissions.php [options]

Options :
  --web-user=NAME     Utilisateur Apache (défaut : www-data)
  --web-group=NAME    Groupe Apache (défaut : www-data)
  --data-root=PATH    Ignoré (données = storage/ ; option conservée pour compat CLI)
  --migrate           Lance aussi bin/migrate.php
  --help              Cette aide

UI admin : /settings#permissions

HELP;
    exit(0);
}

/** @var Container $container */
$container = require $root . '/config/bootstrap.php';

/** @var InstallCheckService $install */
$install = $container->get(InstallCheckService::class);

$webUser = (string) ($opts['web-user'] ?? getenv('WEB_USER') ?: 'www-data');
$webGroup = (string) ($opts['web-group'] ?? getenv('WEB_GROUP') ?: 'www-data');
$dataRoot = isset($opts['data-root']) ? (string) $opts['data-root'] : $install->defaultDataRoot();
if ($dataRoot !== '' && $dataRoot[0] !== '/') {
    $dataRoot = $root . '/' . ltrim($dataRoot, './');
}
$doMigrate = array_key_exists('migrate', $opts);
$isRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;

echo "=== MECM Packaging Manager — droits (CLI) ===\n";
echo "Root     : {$root}\n";
echo "Web      : {$webUser}:{$webGroup}\n";
echo "DataRoot : {$dataRoot}\n";
echo 'UID      : ' . ($isRoot ? 'root' : 'non-root') . "\n\n";

$result = $install->repair($dataRoot, true, $webUser, $webGroup, null, 'cli');
foreach ($result['actions'] as $action) {
    $tag = strtoupper((string) ($action['status'] ?? 'ok'));
    echo "[{$tag}] " . ($action['message'] ?? '') . "\n";
}

if ($doMigrate) {
    echo "\n--- migrate.php ---\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/bin/migrate.php'), $code);
    if ($code !== 0) {
        fwrite(STDERR, "[ERR] migrate exit={$code}\n");
        exit(1);
    }
}

$report = $result['report'];
$sum = $report['summary'] ?? [];
echo "\nDiagnostic : OK=" . ($sum['ok'] ?? 0)
    . ' WARN=' . ($sum['warn'] ?? 0)
    . ' FAIL=' . ($sum['fail'] ?? 0) . "\n";

if (!empty($result['needs_sudo']) && !$isRoot) {
    echo "Conseil : sudo php bin/fix-permissions.php --migrate\n";
}

$permFail = false;
foreach ($report['checks'] ?? [] as $check) {
    if (!is_array($check) || ($check['status'] ?? '') !== 'fail') {
        continue;
    }
    $id = (string) ($check['id'] ?? '');
    if (str_starts_with($id, 'dir_') || str_starts_with($id, 'secret_')) {
        $permFail = true;
        break;
    }
}

if ($permFail || ($result['needs_sudo'] && !$isRoot)) {
    exit(1);
}

echo "Prêt. Docker : docker compose up -d --build\n";
echo "UI admin : /settings#permissions\n";
exit(0);
