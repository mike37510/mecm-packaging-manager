<?php

declare(strict_types=1);

/**
 * Initialise le stockage JSON et synchronise le compte admin depuis config/auth.php.
 */

use App\Repositories\UserRepository;
use App\Support\JsonStore;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$appConfig = require $root . '/config/app.php';
$authConfig = require $root . '/config/auth.php';

$store = new JsonStore($appConfig['data_path']);
$users = new UserRepository($store);
$users->syncAdminFromConfig($authConfig['admin']);

echo "JSON store ready: {$appConfig['data_path']}\n";
echo 'Admin: ' . $authConfig['admin']['email'] . " (depuis config/auth.php)\n";
echo 'Users count: ' . $users->count() . "\n";
