<?php

declare(strict_types=1);

/**
 * CLI: send renewal reminder emails for overdue software.
 *
 * Cron example (every day at 08:00):
 *   0 8 * * * php /var/www/html/bin/send-renewal-mails.php
 */

use App\Services\RenewalReminderService;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

/** @var \App\Support\Container $container */
$container = require $root . '/config/bootstrap.php';

/** @var RenewalReminderService $renewals */
$renewals = $container->get(RenewalReminderService::class);
$result = $renewals->run(null, 'cli');

if (empty($result['ok'])) {
    fwrite(STDERR, ($result['error'] ?? 'Mailing failed') . "\n");
    exit(1);
}

echo sprintf(
    "checked=%d due=%d sent=%d skipped=%d\n",
    $result['checked'],
    $result['due'],
    $result['sent'],
    $result['skipped']
);
foreach ($result['failures'] as $line) {
    fwrite(STDERR, $line . "\n");
}

exit($result['failures'] !== [] && $result['sent'] === 0 ? 2 : 0);
