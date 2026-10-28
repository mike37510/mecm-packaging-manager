<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * @return array<string, mixed>
 */
return [
    'name' => 'MECM Packaging Manager',
    'version' => '4.7.0',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => (getenv('APP_DEBUG') ?: '0') === '1',
    'url' => getenv('APP_URL') ?: '',
    'timezone' => 'Europe/Paris',
    'session_name' => 'sccm_pf_session',
    'upload_path' => dirname(__DIR__) . '/public/assets/uploads/icons',
    'upload_url' => '/assets/uploads/icons',
    'export_path' => dirname(__DIR__) . '/storage/exports',
    'packages_path' => dirname(__DIR__) . '/storage/packages',
    'data_path' => dirname(__DIR__) . '/storage/data',
    'jobs_path' => dirname(__DIR__) . '/storage/jobs',
    'changelog_path' => dirname(__DIR__) . '/CHANGELOG.md',
];
