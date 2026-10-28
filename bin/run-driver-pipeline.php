<?php

declare(strict_types=1);

/**
 * CLI worker: run an async driver pipeline job.
 *
 * Usage:
 *   php bin/run-driver-pipeline.php <jobId>
 *   php bin/run-driver-pipeline.php <jobId> --vt-continue
 */

use App\Services\DriverPipelineJobService;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$jobId = $argv[1] ?? '';
$mode = $argv[2] ?? '';
if ($jobId === '' || !preg_match('/^[a-f0-9]{16}$/', $jobId)) {
    fwrite(STDERR, "Usage: php bin/run-driver-pipeline.php <jobId> [--vt-continue]\n");
    exit(1);
}

/** @var \App\Support\Container $container */
$container = require $root . '/config/bootstrap.php';

/** @var DriverPipelineJobService $pipeline */
$pipeline = $container->get(DriverPipelineJobService::class);

if ($mode === '--vt-continue') {
    $pipeline->continueAfterVt($jobId);
} else {
    $pipeline->run($jobId);
}

exit(0);
