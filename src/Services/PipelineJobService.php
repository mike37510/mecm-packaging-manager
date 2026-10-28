<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SoftwareRepository;

/**
 * Async software pipeline (sync → VirusTotal → ZIP → optional VM test).
 * Jobs run in a detached CLI process so reverse proxies (OpenResty) do not 504.
 */
final class PipelineJobService
{
    public function __construct(
        private readonly string $jobsPath,
        private readonly string $projectRoot,
        private readonly SyncService $sync,
        private readonly PackageExportService $exporter,
        private readonly VmValidationService $vmValidator,
        private readonly VirusTotalService $virusTotal,
        private readonly SoftwareRepository $softwares,
    ) {
    }

    /**
     * @return array{ok: bool, error?: string, job_id?: string, status_url?: string}
     */
    public function start(int $softwareId, ?int $userId, bool $vmEnabled, bool $vtEnabled = false): array
    {
        if (!$this->ensureJobsDir()) {
            return ['ok' => false, 'error' => 'Dossier jobs inaccessible en écriture.'];
        }

        $jobId = bin2hex(random_bytes(8));
        $now = date('c');
        $job = [
            'id' => $jobId,
            'kind' => 'software',
            'software_id' => $softwareId,
            'user_id' => $userId,
            'vm_enabled' => $vmEnabled,
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
                'vm' => [
                    'state' => $vmEnabled ? 'pending' : 'skip',
                    'message' => $vmEnabled ? 'En attente…' : 'VM non configurée',
                ],
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
            'status_url' => '/softwares/' . $softwareId . '/pipeline/' . $jobId,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $jobId, ?int $softwareId = null): ?array
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            return null;
        }
        if ($softwareId !== null && (int) ($job['software_id'] ?? 0) !== $softwareId) {
            return null;
        }

        return $job;
    }

    /**
     * Execute a queued job (CLI worker).
     */
    public function run(string $jobId): void
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            fwrite(STDERR, "Job introuvable: {$jobId}\n");
            return;
        }

        if (($job['kind'] ?? 'software') === 'driver') {
            return;
        }

        if ((string) ($job['status'] ?? '') !== 'queued') {
            // Ignore duplicate spawn once the worker has started or finished.
            return;
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        $softwareId = (int) ($job['software_id'] ?? 0);
        $userId = isset($job['user_id']) ? (int) $job['user_id'] : null;
        $vmEnabled = !empty($job['vm_enabled']);
        $vtEnabled = !empty($job['vt_enabled']);

        $this->patch($jobId, [
            'status' => 'running',
            'steps' => [
                'sync' => ['state' => 'running', 'message' => 'Téléchargement / SHA256…'],
                'vt' => [
                    'state' => $vtEnabled ? 'pending' : 'skip',
                    'message' => $vtEnabled ? 'En attente…' : 'VirusTotal désactivé',
                ],
                'zip' => ['state' => 'pending', 'message' => 'En attente…'],
                'vm' => [
                    'state' => $vmEnabled ? 'pending' : 'skip',
                    'message' => $vmEnabled ? 'En attente…' : 'VM non configurée',
                ],
            ],
        ]);

        $sync = $this->sync->check($softwareId, $userId, 'pipeline');
        if (empty($sync['ok'])) {
            $msg = (string) ($sync['error'] ?? 'Synchro échouée');
            $this->fail($jobId, 'sync', $msg, $vmEnabled);
            return;
        }

        $this->patch($jobId, [
            'steps.sync' => [
                'state' => 'ok',
                'message' => (string) ($sync['message'] ?? $sync['status'] ?? 'Synchro OK'),
            ],
            'log_append' => 'SYNC OK',
        ]);

        if ($vtEnabled) {
            $this->patch($jobId, [
                'steps.vt' => ['state' => 'running', 'message' => 'Analyse URL VirusTotal…'],
            ]);
            $software = $this->softwares->findById($softwareId);
            $scanUrl = trim((string) ($software?->downloadUrl ?? ''));
            $sha = isset($sync['sha256']) ? (string) $sync['sha256'] : ($software?->lastSyncSha256);
            $vt = $this->virusTotal->scanInstallerUrl($scanUrl, $sha);
            if (empty($vt['ok'])) {
                $msg = (string) ($vt['error'] ?? 'Contrôle VirusTotal échoué');
                $extra = isset($vt['permalink']) ? ('Rapport : ' . $vt['permalink']) : '';
                $this->fail($jobId, 'vt', $msg, $vmEnabled, skipVm: true, logExtra: $extra);
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

        $this->runZipAndVm($jobId);
    }

    /**
     * Resume pipeline after the operator accepted a VirusTotal warning.
     */
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

        $this->runZipAndVm($jobId);
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
     * Operator decision on a VirusTotal warning.
     *
     * @return array{ok: bool, error?: string}
     */
    public function confirmVt(string $jobId, ?int $softwareId, string $action, ?int $userId): array
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            return ['ok' => false, 'error' => 'Job introuvable.'];
        }
        if ($softwareId !== null && (int) ($job['software_id'] ?? 0) !== $softwareId) {
            return ['ok' => false, 'error' => 'Job / logiciel incohérents.'];
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

        $vmEnabled = !empty($job['vm_enabled']);
        if ($action === 'abort') {
            $this->fail(
                $jobId,
                'vt',
                'Pipeline arrêté : l’opérateur a refusé de continuer malgré VirusTotal.',
                $vmEnabled,
                skipVm: true,
                logExtra: 'Décision opérateur : abort'
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
            $this->fail($jobId, 'vt', $spawn['error'] ?? 'Impossible de reprendre après VirusTotal.', $vmEnabled, skipVm: true);

            return ['ok' => false, 'error' => $spawn['error'] ?? 'Worker impossible.'];
        }

        return ['ok' => true];
    }

    private function runZipAndVm(string $jobId): void
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            return;
        }

        $softwareId = (int) ($job['software_id'] ?? 0);
        $userId = isset($job['user_id']) ? (int) $job['user_id'] : null;
        $vmEnabled = !empty($job['vm_enabled']);

        $this->patch($jobId, [
            'steps.zip' => ['state' => 'running', 'message' => 'Génération du package…'],
        ]);

        $zip = $this->exporter->export($softwareId, $userId, 'pipeline');
        if (empty($zip['ok'])) {
            $msg = (string) ($zip['error'] ?? 'ZIP impossible');
            $this->fail($jobId, 'zip', $msg, $vmEnabled, skipVm: true);
            return;
        }

        $exportId = (int) ($zip['export_id'] ?? 0);
        $this->patch($jobId, [
            'export_id' => $exportId,
            'steps.zip' => [
                'state' => 'ok',
                'message' => 'ZIP : ' . (string) ($zip['filename'] ?? ''),
            ],
            'log_append' => 'ZIP OK — export_id=' . $exportId,
        ]);

        if (!$vmEnabled) {
            $this->patch($jobId, [
                'status' => 'done',
                'finished_at' => date('c'),
                'steps.vm' => ['state' => 'skip', 'message' => 'VM non configurée'],
            ]);
            return;
        }

        if ($exportId <= 0) {
            $this->fail($jobId, 'vm', 'Pas d’export_id pour le test VM', true);
            return;
        }

        $this->patch($jobId, [
            'steps.vm' => [
                'state' => 'running',
                'message' => 'Install → détection → uninstall sur la VM…',
            ],
            'log_append' => '--- TEST VM ---',
        ]);

        $vm = $this->vmValidator->validateExport(
            $softwareId,
            $exportId,
            $userId,
            'pipeline',
            function (string $line, ?string $stepMessage = null) use ($jobId): void {
                $fields = [];
                if ($line !== '') {
                    $fields['log_line'] = $line;
                }
                if ($stepMessage !== null && $stepMessage !== '') {
                    $fields['steps.vm'] = [
                        'state' => 'running',
                        'message' => $stepMessage,
                    ];
                }
                if ($fields !== []) {
                    $this->patch($jobId, $fields);
                }
            }
        );

        if (empty($vm['ok'])) {
            $msg = (string) ($vm['error'] ?? $vm['message'] ?? 'Test VM échoué');
            $log = !empty($vm['streamed']) ? '' : (string) ($vm['log'] ?? '');
            $this->fail($jobId, 'vm', $msg, true, skipVm: false, logExtra: $log);
            return;
        }

        if (!empty($vm['awaiting_confirmation'])) {
            $this->patch($jobId, [
                'status' => 'awaiting_user',
                'awaiting_kind' => 'vm',
                'vm_run_id' => (int) ($vm['run_id'] ?? 0),
                'steps.vm' => [
                    'state' => 'awaiting',
                    'message' => 'Vérifiez l\'appli sur la VM, puis confirmez pour lancer l\'uninstall…',
                ],
                'log_line' => '[vm] En attente de confirmation opérateur (install OK, logiciel laissé sur la VM).',
            ]);

            return;
        }

        $this->patch($jobId, [
            'status' => 'done',
            'finished_at' => date('c'),
            'steps.vm' => [
                'state' => 'ok',
                'message' => (string) ($vm['message'] ?? 'Test VM OK'),
            ],
            'log_line' => empty($vm['streamed'])
                ? (string) ($vm['log'] ?? 'VM OK')
                : ('[vm] ' . (string) ($vm['message'] ?? 'Test VM OK')),
        ]);
    }

    /**
     * Operator confirmed install on VM → run uninstall phase.
     *
     * @return array{ok: bool, error?: string}
     */
    public function confirmVm(string $jobId, ?int $softwareId, string $action, ?int $userId): array
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            return ['ok' => false, 'error' => 'Job introuvable.'];
        }
        if ($softwareId !== null && (int) ($job['software_id'] ?? 0) !== $softwareId) {
            return ['ok' => false, 'error' => 'Job / logiciel incohérents.'];
        }
        if ((string) ($job['status'] ?? '') !== 'awaiting_user') {
            return ['ok' => false, 'error' => 'Ce pipeline n\'attend pas de confirmation VM.'];
        }
        $kind = (string) ($job['awaiting_kind'] ?? 'vm');
        if ($kind !== 'vm') {
            return ['ok' => false, 'error' => 'Ce pipeline n\'attend pas de confirmation VM.'];
        }

        $action = strtolower(trim($action));
        if (!in_array($action, ['continue', 'abort'], true)) {
            return ['ok' => false, 'error' => 'Action invalide (continue|abort).'];
        }

        $this->patch($jobId, [
            'status' => 'running',
            'vm_confirm_action' => $action,
            'steps.vm' => [
                'state' => 'running',
                'message' => $action === 'continue'
                    ? 'Confirmation reçue — uninstall en cours…'
                    : 'Annulation — nettoyage lab…',
            ],
            'log_line' => $action === 'continue'
                ? '[vm] Opérateur : installation validée → uninstall…'
                : '[vm] Opérateur : installation refusée → abort (logiciel conservé)…',
        ]);

        $spawn = $this->spawnWorker($jobId, $action === 'continue' ? '--vm-finish' : '--vm-abort');
        if (!$spawn['ok']) {
            $this->fail($jobId, 'vm', $spawn['error'] ?? 'Impossible de reprendre le worker VM.', true, skipVm: false);

            return ['ok' => false, 'error' => $spawn['error'] ?? 'Worker impossible.'];
        }

        return ['ok' => true];
    }

    /**
     * Continue VM phase after operator confirmation (CLI worker).
     */
    public function finishVm(string $jobId): void
    {
        $this->runVmConfirmPhase($jobId, 'continue');
    }

    /**
     * Abort VM phase after operator rejection (CLI worker).
     */
    public function abortVm(string $jobId): void
    {
        $this->runVmConfirmPhase($jobId, 'abort');
    }

    private function runVmConfirmPhase(string $jobId, string $action): void
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            fwrite(STDERR, "Job introuvable: {$jobId}\n");
            return;
        }

        $runId = (int) ($job['vm_run_id'] ?? 0);
        $userId = isset($job['user_id']) ? (int) $job['user_id'] : null;
        if ($runId <= 0) {
            $this->fail($jobId, 'vm', 'vm_run_id manquant pour finaliser.', true, skipVm: false);
            return;
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        $progress = function (string $line, ?string $stepMessage = null) use ($jobId): void {
            $fields = [];
            if ($line !== '') {
                $fields['log_line'] = $line;
            }
            if ($stepMessage !== null && $stepMessage !== '') {
                $fields['steps.vm'] = ['state' => 'running', 'message' => $stepMessage];
            }
            if ($fields !== []) {
                $this->patch($jobId, $fields);
            }
        };

        $vm = $action === 'continue'
            ? $this->vmValidator->continueAfterConfirmation($runId, $userId, 'pipeline', $progress)
            : $this->vmValidator->abortAfterConfirmation($runId, $userId, 'pipeline', $progress);

        if ($action === 'abort' || !empty($vm['aborted'])) {
            $msg = (string) ($vm['message'] ?? $vm['error'] ?? 'Annulé par l\'opérateur');
            $this->fail($jobId, 'vm', $msg, true, skipVm: false, logExtra: !empty($vm['streamed']) ? '' : (string) ($vm['log'] ?? ''));
            // Mark as aborted rather than generic error if possible
            $this->patch($jobId, [
                'status' => 'error',
                'steps.vm' => [
                    'state' => 'fail',
                    'message' => $msg,
                ],
            ]);

            return;
        }

        if (empty($vm['ok'])) {
            $msg = (string) ($vm['error'] ?? $vm['message'] ?? 'Uninstall VM échoué');
            $this->fail($jobId, 'vm', $msg, true, skipVm: false, logExtra: !empty($vm['streamed']) ? '' : (string) ($vm['log'] ?? ''));
            return;
        }

        $this->patch($jobId, [
            'status' => 'done',
            'finished_at' => date('c'),
            'steps.vm' => [
                'state' => 'ok',
                'message' => (string) ($vm['message'] ?? 'Test VM OK'),
            ],
            'log_line' => '[vm] ' . (string) ($vm['message'] ?? 'Test VM OK'),
        ]);
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

    private function fail(
        string $jobId,
        string $failedStep,
        string $message,
        bool $vmEnabled,
        bool $skipVm = true,
        string $logExtra = '',
    ): void {
        $job = $this->readJob($jobId);
        if ($job === null) {
            return;
        }

        $steps = is_array($job['steps'] ?? null) ? $job['steps'] : [];
        $order = ['sync', 'vt', 'zip', 'vm'];
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
            if ($step === 'vm' && (!$vmEnabled || $skipVm)) {
                $steps[$step] = [
                    'state' => 'skip',
                    'message' => $vmEnabled ? 'Annulé' : 'VM non configurée',
                ];
            } else {
                $steps[$step] = ['state' => 'skip', 'message' => 'Annulé'];
            }
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
        $script = rtrim($this->projectRoot, '/') . '/bin/run-pipeline.php';
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

        // exec of backgrounded command usually returns 0 immediately
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
