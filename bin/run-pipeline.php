<?php

declare(strict_types=1);

/**
 * CLI worker: run an async packaging pipeline job.
 *
 * Usage:
 *   php bin/run-pipeline.php <jobId>
 *   php bin/run-pipeline.php <jobId> --vm-finish
 *   php bin/run-pipeline.php <jobId> --vt-continue
 */

use App\Services\PipelineJobService;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$jobId = $argv[1] ?? '';
$mode = $argv[2] ?? '';
if ($jobId === '' || !preg_match('/^[a-f0-9]{16}$/', $jobId)) {
    fwrite(STDERR, "Usage: php bin/run-pipeline.php <jobId> [--vm-finish|--vm-abort|--vt-continue]\n");
    exit(1);
}

/** @var \App\Support\Container $container */
$container = require $root . '/config/bootstrap.php';

/** @var PipelineJobService $pipeline */
$pipeline = $container->get(PipelineJobService::class);

if ($mode === '--vm-finish') {
    $pipeline->finishVm($jobId);
} elseif ($mode === '--vm-abort') {
    $pipeline->abortVm($jobId);
} elseif ($mode === '--vt-continue') {
    $pipeline->continueAfterVt($jobId);
} else {
    $pipeline->run($jobId);
}

exit(0);
