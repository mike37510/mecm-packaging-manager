<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DriverRepository;

/**
 * Async driver pipeline (URL probe → VirusTotal → ZIP).
 * Same job files as software; kind=driver so workers stay isolated.
 * Packs are never downloaded into PHP — VirusTotal scans the URL (and SHA if known).
 */
final class DriverPipelineJobService
{
    public function __construct(
        private readonly string $jobsPath,
        private readonly string $projectRoot,
        private readonly DriverSyncService $sync,
        private readonly DriverPackageService $packages,
        private readonly VirusTotalService $virusTotal,
        private readonly DriverRepository $drivers,
    ) {
    }

    /**
     * @return array{ok: bool, error?: string, job_id?: string, status_url?: string}
     */
    public function start(int $driverId, ?int $userId, bool $vtEnabled = false): array
    {
        if (!$this->ensureJobsDir()) {
            return ['ok' => false, 'error' => 'Dossier jobs inaccessible en écriture.'];
        }

        $jobId = bin2hex(random_bytes(8));
        $now = date('c');
        $job = [
            'id' => $jobId,
            'kind' => 'driver',
            'driver_id' => $driverId,
            'user_id' => $userId,
            'vt_enabled' => $vtEnabled,
            'status' => 'queued',
            'export_id' => null,
            'steps' => [
                'sync' => ['state' => 'pending', 'message' => 'En attente…'],
                'vt' => [
                    'state' => $vtEnabled ? 'pending' : 'skip',
                    'message' => $vtEnabled ? 'En attente…' : 'VirusTotal désactivé',
                ],
                'zip' => ['state' => 'pending', 'message' => 'En attente…'],
            ],
            'log' => '',
            'error' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'finished_at' => null,
        ];

        if (!$this->writeJob($job)) {
            return ['ok' => false, 'error' => 'Impossible de créer le job pipeline.'];
        }

        $spawn = $this->spawnWorker($jobId);
        if (!$spawn['ok']) {
            $job['status'] = 'error';
            $job['error'] = $spawn['error'] ?? 'Impossible de démarrer le worker.';
            $job['finished_at'] = date('c');
            $this->writeJob($job);

            return ['ok' => false, 'error' => $job['error']];
        }

        return [
            'ok' => true,
            'job_id' => $jobId,
            'status_url' => '/drivers/' . $driverId . '/pipeline/' . $jobId,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $jobId, ?int $driverId = null): ?array
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            return null;
        }
        if (($job['kind'] ?? '') !== 'driver') {
            return null;
        }
        if ($driverId !== null && (int) ($job['driver_id'] ?? 0) !== $driverId) {
            return null;
        }

        return $job;
    }

    public function run(string $jobId): void
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            fwrite(STDERR, "Job introuvable: {$jobId}\n");
            return;
        }
        if (($job['kind'] ?? '') !== 'driver') {
            return;
        }
        if ((string) ($job['status'] ?? '') !== 'queued') {
            return;
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        $driverId = (int) ($job['driver_id'] ?? 0);
        $userId = isset($job['user_id']) ? (int) $job['user_id'] : null;
        $vtEnabled = !empty($job['vt_enabled']);

        $this->patch($jobId, [
            'status' => 'running',
            'steps' => [
                'sync' => ['state' => 'running', 'message' => 'Vérification URL / empreinte…'],
                'vt' => [
                    'state' => $vtEnabled ? 'pending' : 'skip',
                    'message' => $vtEnabled ? 'En attente…' : 'VirusTotal désactivé',
                ],
                'zip' => ['state' => 'pending', 'message' => 'En attente…'],
            ],
        ]);

        $sync = $this->sync->check($driverId, $userId, 'pipeline');
        if (empty($sync['ok'])) {
            $msg = (string) ($sync['error'] ?? 'Vérification URL échouée');
            $this->fail($jobId, 'sync', $msg);
            return;
        }

        $this->patch($jobId, [
            'steps.sync' => [
                'state' => 'ok',
                'message' => (string) ($sync['message'] ?? $sync['status'] ?? 'Vérif OK'),
            ],
            'log_append' => 'SYNC OK — ' . (string) ($sync['message'] ?? ''),
        ]);

        if ($vtEnabled) {
            $this->patch($jobId, [
                'steps.vt' => ['state' => 'running', 'message' => 'Analyse URL VirusTotal…'],
            ]);
            $driver = $this->drivers->findById($driverId);
            $scanUrl = trim((string) ($driver?->downloadUrl ?? ''));
            $sha = $this->usableSha256(
                isset($sync['sha256']) ? (string) $sync['sha256'] : ($driver?->lastSyncSha256)
            );
            $vt = $this->virusTotal->scanInstallerUrl($scanUrl, $sha);
            if (empty($vt['ok'])) {
                $msg = (string) ($vt['error'] ?? 'Contrôle VirusTotal échoué');
                $extra = isset($vt['permalink']) ? ('Rapport : ' . $vt['permalink']) : '';
                $this->fail($jobId, 'vt', $msg, $extra);
                return;
            }
            if (!empty($vt['needs_review'])) {
                $this->pauseForVirusTotalReview($jobId, $vt);
                return;
            }
            $this->patch($jobId, [
                'steps.vt' => [
                    'state' => 'ok',
                    'message' => (string) ($vt['message'] ?? 'VirusTotal OK'),
                ],
                'log_append' => trim((string) ($vt['message'] ?? 'VT OK')
                    . (isset($vt['permalink']) ? "\n" . $vt['permalink'] : '')),
            ]);
        } else {
            $this->patch($jobId, [
                'steps.vt' => ['state' => 'skip', 'message' => 'VirusTotal désactivé'],
            ]);
        }

        $this->runZip($jobId);
    }

    public function continueAfterVt(string $jobId): void
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            fwrite(STDERR, "Job introuvable: {$jobId}\n");
            return;
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        $this->patch($jobId, [
            'status' => 'running',
            'awaiting_kind' => null,
            'steps.vt' => [
                'state' => 'ok',
                'message' => 'VirusTotal : poursuivi par l’opérateur (faux positif probable).',
            ],
            'log_line' => '[vt] Opérateur : continuer malgré les détections.',
        ]);

        $this->runZip($jobId);
    }

    /**
     * @param array<string, mixed> $vt
     */
    private function pauseForVirusTotalReview(string $jobId, array $vt): void
    {
        $summary = (string) ($vt['message'] ?? 'Détections VirusTotal');
        $engines = is_array($vt['engines'] ?? null) ? $vt['engines'] : [];
        $engineLines = [];
        foreach ($engines as $row) {
            if (!is_array($row)) {
                continue;
            }
            $engineLines[] = trim(
                (string) ($row['engine'] ?? '')
                . ' — '
                . (string) ($row['result'] ?? $row['category'] ?? '')
            );
        }

        $this->patch($jobId, [
            'status' => 'awaiting_user',
            'awaiting_kind' => 'vt',
            'vt_review' => [
                'malicious' => (int) ($vt['malicious'] ?? 0),
                'suspicious' => (int) ($vt['suspicious'] ?? 0),
                'url_malicious' => (int) ($vt['url_malicious'] ?? 0),
                'file_malicious' => (int) ($vt['file_malicious'] ?? 0),
                'hint' => (string) ($vt['hint'] ?? 'unclear'),
                'permalink' => (string) ($vt['permalink'] ?? ''),
                'summary' => $summary,
                'engines' => array_values($engines),
            ],
            'steps.vt' => [
                'state' => 'awaiting',
                'message' => $summary,
            ],
            'log_append' => $summary
                . ($engineLines !== [] ? "\nMoteurs : " . implode('; ', $engineLines) : '')
                . (isset($vt['permalink']) ? "\n" . $vt['permalink'] : ''),
        ]);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function confirmVt(string $jobId, ?int $driverId, string $action, ?int $userId): array
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            return ['ok' => false, 'error' => 'Job introuvable.'];
        }
        if (($job['kind'] ?? '') !== 'driver') {
            return ['ok' => false, 'error' => 'Job / pilote incohérents.'];
        }
        if ($driverId !== null && (int) ($job['driver_id'] ?? 0) !== $driverId) {
            return ['ok' => false, 'error' => 'Job / pilote incohérents.'];
        }
        if ((string) ($job['status'] ?? '') !== 'awaiting_user'
            || (string) ($job['awaiting_kind'] ?? '') !== 'vt'
        ) {
            return ['ok' => false, 'error' => 'Ce pipeline n’attend pas de décision VirusTotal.'];
        }

        $action = strtolower(trim($action));
        if (!in_array($action, ['continue', 'abort'], true)) {
            return ['ok' => false, 'error' => 'Action invalide (continue|abort).'];
        }

        if ($action === 'abort') {
            $this->fail(
                $jobId,
                'vt',
                'Pipeline arrêté : l’opérateur a refusé de continuer malgré VirusTotal.',
                'Décision opérateur : abort'
            );

            return ['ok' => true];
        }

        $this->patch($jobId, [
            'status' => 'running',
            'awaiting_kind' => null,
            'steps.vt' => [
                'state' => 'running',
                'message' => 'Reprise après décision opérateur…',
            ],
            'log_line' => '[vt] Opérateur : continuer (faux positif probable).',
        ]);

        $spawn = $this->spawnWorker($jobId, '--vt-continue');
        if (!$spawn['ok']) {
            $this->fail($jobId, 'vt', $spawn['error'] ?? 'Impossible de reprendre après VirusTotal.');

            return ['ok' => false, 'error' => $spawn['error'] ?? 'Worker impossible.'];
        }

        return ['ok' => true];
    }

    private function runZip(string $jobId): void
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            return;
        }

        $driverId = (int) ($job['driver_id'] ?? 0);
        $userId = isset($job['user_id']) ? (int) $job['user_id'] : null;

        $this->patch($jobId, [
            'steps.zip' => ['state' => 'running', 'message' => 'Génération du ZIP MECM…'],
        ]);

        $zip = $this->packages->generate($driverId, $userId, 'pipeline');
        if (empty($zip['ok'])) {
            $this->fail($jobId, 'zip', (string) ($zip['error'] ?? 'Génération ZIP échouée'));
            return;
        }

        $filename = (string) ($zip['filename'] ?? '');
        $this->patch($jobId, [
            'status' => 'done',
            'finished_at' => date('c'),
            'export_id' => $driverId,
            'export_filename' => $filename !== '' ? $filename : null,
            'steps.zip' => [
                'state' => 'ok',
                'message' => $filename !== '' ? ('ZIP : ' . $filename) : 'ZIP généré',
            ],
            'log_append' => 'ZIP OK' . ($filename !== '' ? ' — ' . $filename : ''),
        ]);
    }

    private function usableSha256(mixed $sha): ?string
    {
        if (!is_string($sha) || preg_match('/^[a-f0-9]{64}$/i', $sha) !== 1) {
            return null;
        }
        if (strtolower($sha) === str_repeat('0', 64)) {
            return null;
        }

        return strtolower($sha);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function patch(string $jobId, array $fields): void
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            return;
        }

        if (isset($fields['log_append'])) {
            $extra = trim((string) $fields['log_append']);
            unset($fields['log_append']);
            if ($extra !== '') {
                $current = trim((string) ($job['log'] ?? ''));
                $job['log'] = $current === '' ? $extra : $current . "\n\n" . $extra;
            }
        }

        if (isset($fields['log_line'])) {
            $extra = rtrim((string) $fields['log_line'], "\r\n");
            unset($fields['log_line']);
            if ($extra !== '') {
                $current = (string) ($job['log'] ?? '');
                $job['log'] = $current === '' ? $extra : rtrim($current, "\r\n") . "\n" . $extra;
            }
        }

        foreach ($fields as $key => $value) {
            if (str_starts_with($key, 'steps.') && is_array($value)) {
                $step = substr($key, 6);
                $job['steps'][$step] = $value;
                continue;
            }
            $job[$key] = $value;
        }

        $job['updated_at'] = date('c');
        $this->writeJob($job);
    }

    private function fail(string $jobId, string $failedStep, string $message, string $logExtra = ''): void
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            return;
        }

        $steps = is_array($job['steps'] ?? null) ? $job['steps'] : [];
        $order = ['sync', 'vt', 'zip'];
        $seenFail = false;
        foreach ($order as $step) {
            if ($step === $failedStep) {
                $steps[$step] = ['state' => 'fail', 'message' => $message];
                $seenFail = true;
                continue;
            }
            if (!$seenFail) {
                continue;
            }
            if ($step === 'vt' && empty($job['vt_enabled'])) {
                $steps[$step] = ['state' => 'skip', 'message' => 'VirusTotal désactivé'];
                continue;
            }
            $steps[$step] = ['state' => 'skip', 'message' => 'Annulé'];
        }

        $log = (string) ($job['log'] ?? '');
        $block = strtoupper($failedStep) . ' FAIL — ' . $message;
        if ($logExtra !== '') {
            $block .= "\n" . $logExtra;
        }
        $job['log'] = trim($log . ($log !== '' ? "\n\n" : '') . $block);
        $job['steps'] = $steps;
        $job['status'] = 'error';
        $job['error'] = $message;
        $job['finished_at'] = date('c');
        $job['updated_at'] = date('c');
        $this->writeJob($job);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private function spawnWorker(string $jobId, string $extraArg = ''): array
    {
        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $script = rtrim($this->projectRoot, '/') . '/bin/run-driver-pipeline.php';
        if (!is_readable($script)) {
            return ['ok' => false, 'error' => 'Script worker introuvable.'];
        }

        $logFile = rtrim($this->jobsPath, '/') . '/' . $jobId . '.worker.log';
        $extra = $extraArg !== '' ? ' ' . escapeshellarg($extraArg) : '';
        $cmd = sprintf(
            'nohup %s %s %s%s >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($script),
            escapeshellarg($jobId),
            $extra,
            escapeshellarg($logFile)
        );

        $output = [];
        $exit = 0;
        @exec($cmd, $output, $exit);

        if ($exit !== 0) {
            return ['ok' => false, 'error' => 'Échec du lancement du worker (exit ' . $exit . ').'];
        }

        return ['ok' => true];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJob(string $jobId): ?array
    {
        if (!preg_match('/^[a-f0-9]{16}$/', $jobId)) {
            return null;
        }
        $path = $this->jobPath($jobId);
        if (!is_readable($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $job
     */
    private function writeJob(array $job): bool
    {
        $id = (string) ($job['id'] ?? '');
        if ($id === '') {
            return false;
        }
        $path = $this->jobPath($id);
        $json = json_encode(
            $job,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            return false;
        }

        return file_put_contents($path, $json . "\n", LOCK_EX) !== false;
    }

    private function jobPath(string $jobId): string
    {
        return rtrim($this->jobsPath, '/') . '/' . $jobId . '.json';
    }

    private function ensureJobsDir(): bool
    {
        $dir = rtrim($this->jobsPath, '/');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        return is_writable($dir);
    }
}
