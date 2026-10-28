<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PackageExport;
use App\Repositories\AuditLogRepository;
use App\Repositories\PackageExportRepository;
use App\Repositories\SoftwareRepository;
use App\Repositories\VmValidationRepository;
use App\Support\SshRemote;

/**
 * Push a generated MECM ZIP to the Windows lab VM via SSH.
 * Flow: install → détection (témoin) → uninstall → cleanup témoin (retestable).
 */
final class VmValidationService
{
    /** @var array<string, mixed> */
    private array $vmConfig;

    public function __construct(
        private readonly SoftwareRepository $softwares,
        private readonly PackageExportRepository $exports,
        private readonly PackageExportService $exporter,
        private readonly VmValidationRepository $validations,
        private readonly AuditLogRepository $auditLogs,
        private readonly VmConfigService $vmConfigs,
    ) {
        $this->vmConfig = $this->vmConfigs->emptyConfig();
    }

    public function isConfigured(?int $userId = null): bool
    {
        $this->bindUser($userId);

        return !empty($this->vmConfig['enabled'])
            && trim((string) ($this->vmConfig['host'] ?? '')) !== ''
            && trim((string) ($this->vmConfig['username'] ?? '')) !== ''
            && trim((string) ($this->vmConfig['password'] ?? '')) !== '';
    }

    /**
     * @return array{ok: bool, error?: string, stdout?: string}
     */
    public function ping(?int $userId = null): array
    {
        if (!$this->isConfigured($userId)) {
            return ['ok' => false, 'error' => 'VM de test non configurée. Définissez-la dans Mon compte → VM de test.'];
        }

        $ssh = $this->client();
        $result = $ssh->run(
            'powershell -NoProfile -Command "Write-Output (\'PONG \' + $env:COMPUTERNAME)"',
            30
        );

        if (!$result['ok']) {
            return [
                'ok' => false,
                'error' => $result['error'] ?? ('SSH échoué : ' . ($result['stderr'] ?: 'code ' . $result['exit_code'])),
                'stdout' => $result['stdout'],
            ];
        }

        return ['ok' => true, 'stdout' => $result['stdout']];
    }

    /**
     * @param callable|null $onProgress Optional callback(string $line, ?string $stepMessage): void
     *                                  for live pipeline log updates
     * @return array{ok: bool, error?: string, run_id?: int, status?: string, message?: string, log?: string, streamed?: bool}
     */
    public function validateExport(int $softwareId, int $exportId, ?int $userId, ?string $ip, ?callable $onProgress = null): array
    {
        if (!$this->isConfigured($userId)) {
            return ['ok' => false, 'error' => 'VM de test non configurée pour votre compte (Mon compte → VM de test).'];
        }

        $software = $this->softwares->findById($softwareId);
        if ($software === null) {
            return ['ok' => false, 'error' => 'Logiciel introuvable.'];
        }

        $export = $this->exports->findById($exportId);
        if ($export === null || $export->softwareId !== $softwareId) {
            return ['ok' => false, 'error' => 'ZIP historique introuvable.'];
        }

        $zipPath = $this->exporter->absolutePathForExport($export);
        if ($zipPath === null || !is_readable($zipPath)) {
            return ['ok' => false, 'error' => 'Fichier ZIP manquant sur le disque.'];
        }

        @set_time_limit(0);
        $ssh = $this->client();
        $remoteRoot = rtrim((string) $this->vmConfig['remote_dir'], '\\/');
        $jobName = 'job_' . date('Ymd_His') . '_' . $exportId;
        $jobDir = $remoteRoot . '\\jobs\\' . $jobName;
        $remoteZip = $remoteRoot . '\\incoming\\' . $jobName . '.zip';

        $runId = $this->validations->create([
            'software_id' => $softwareId,
            'export_id' => $exportId,
            'version' => $export->version,
            'filename' => $export->filename,
            'host' => $ssh->host(),
            'status' => 'running',
            'message' => 'Transfert du ZIP…',
            'install_exit' => null,
            'uninstall_exit' => null,
            'detection_after_install' => null,
            'detection_after_uninstall' => null,
            'witness_path' => null,
            'log' => '',
            'created_by' => $userId,
        ]);

        $log = [];
        $streamed = false;
        $emit = function (string $line, ?string $stepMessage = null) use (&$log, &$streamed, $onProgress): void {
            $line = $this->sanitizeLogLine($line);
            if ($line === '') {
                if ($stepMessage !== null && $onProgress !== null) {
                    $onProgress('', $stepMessage);
                    $streamed = true;
                }

                return;
            }
            $log[] = $line;
            if ($onProgress !== null) {
                $onProgress($line, $stepMessage);
                $streamed = true;
            }
        };

        $emit(
            '[' . date('H:i:s') . "] Cible {$ssh->username()}@{$ssh->host()}:{$ssh->port()}",
            'Connexion SSH…'
        );
        $emit('[' . date('H:i:s') . "] ZIP local : {$zipPath}");
        $emit('[' . date('H:i:s') . '] Préparation dossiers VM…', 'Préparation dossiers VM…');

        $prep = $ssh->run($this->psScript(
            "New-Item -ItemType Directory -Force -Path (Join-Path '{$this->ps($remoteRoot)}' 'incoming') | Out-Null; "
            . "New-Item -ItemType Directory -Force -Path (Join-Path '{$this->ps($remoteRoot)}' 'jobs') | Out-Null; "
            . "Write-Output 'prep-ok'"
        ), 60);
        $prepDetail = trim((string) (($prep['stdout'] ?? '') !== '' ? $prep['stdout'] : ($prep['stderr'] ?? '')));
        if (($prep['error'] ?? '') !== '') {
            $prepDetail = trim($prepDetail . ($prepDetail !== '' ? ' — ' : '') . (string) $prep['error']);
        }
        $emit('[prep] ' . ($prepDetail !== '' ? $prepDetail : 'échec sans détail'));
        if (!$prep['ok']) {
            $msg = 'Préparation dossiers VM impossible.';
            if (($prep['error'] ?? '') !== '') {
                $msg .= ' ' . (string) $prep['error'];
            } elseif ($prepDetail !== '') {
                $msg .= ' ' . $prepDetail;
            }

            return $this->fail($runId, $log, $msg, $userId, $softwareId, $export, $ip, streamed: $streamed);
        }

        $zipBytes = @filesize($zipPath);
        $zipSize = is_int($zipBytes) && $zipBytes > 0
            ? sprintf('%.1f Mo', $zipBytes / 1048576)
            : '?';
        $emit('[' . date('H:i:s') . "] Upload ZIP ({$zipSize})…", "Upload ZIP ({$zipSize})…");
        $up = $ssh->upload($zipPath, $remoteZip);
        $emit('[' . date('H:i:s') . '] Upload exit=' . $up['exit_code']);
        if ($up['stdout'] !== '') {
            $emit($this->sanitizeLogLine($up['stdout']));
        }
        if ($up['stderr'] !== '') {
            foreach ($this->usefulStderrLines($up['stderr']) as $errLine) {
                $emit($errLine);
            }
        }
        if (!$up['ok']) {
            return $this->fail(
                $runId,
                $log,
                $up['error'] ?? 'Échec transfert SCP du ZIP.',
                $userId,
                $softwareId,
                $export,
                $ip,
                streamed: $streamed
            );
        }

        $this->validations->update($runId, [
            'status' => 'running',
            'message' => 'Installation / détection (pause confirmation)…',
            'log' => implode("\n", $log),
        ]);

        $timeout = (int) ($this->vmConfig['timeout_seconds'] ?? 900);
        $remoteRunner = $remoteRoot . '\\incoming\\' . $jobName . '_runner.ps1';
        $tmp = tempnam(sys_get_temp_dir(), 'vmrun_');
        if ($tmp === false) {
            return $this->fail($runId, $log, 'Impossible de créer le script runner local.', $userId, $softwareId, $export, $ip, streamed: $streamed);
        }
        $localRunner = $tmp . '.ps1';
        @rename($tmp, $localRunner);

        try {
            file_put_contents($localRunner, $this->buildRemoteJobScript());
            $emit('[' . date('H:i:s') . '] Upload script runner…', 'Upload script runner…');
            $upScript = $ssh->upload($localRunner, $remoteRunner, 60);
            $emit('[' . date('H:i:s') . '] Upload runner exit=' . $upScript['exit_code']);
            if (!$upScript['ok']) {
                return $this->fail(
                    $runId,
                    $log,
                    $upScript['error'] ?? 'Échec transfert du script runner.',
                    $userId,
                    $softwareId,
                    $export,
                    $ip,
                    streamed: $streamed
                );
            }

            $emit('[' . date('H:i:s') . '] Lancement job distant (phase Install)…', 'Installation sur la VM…');
            $emit('--- stdout distant ---');

            // Shell distant Windows = souvent PowerShell → passer via cmd /c pour éviter le double parsing
            $cmd = sprintf(
                'cmd /c powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -OutputFormat Text -File %s -Phase Install -Zip %s -Job %s',
                $this->toWinFwd($remoteRunner),
                $this->toWinFwd($remoteZip),
                $this->toWinFwd($jobDir)
            );

            $exec = $this->runRemoteStreaming($ssh, $cmd, $timeout, $emit);
        } finally {
            @unlink($localRunner);
        }

        $emit('[' . date('H:i:s') . '] Job remote exit=' . $exec['exit_code']);
        if ($exec['stderr'] !== '') {
            foreach ($this->usefulStderrLines($exec['stderr']) as $errLine) {
                $emit($errLine);
            }
        }

        $parsed = $this->parseJobResult($exec['stdout']);
        $installExit = $parsed['install_exit'] ?? null;
        $detectionAfterInstall = $parsed['detection_after_install'] ?? null;
        $witnessPath = $parsed['witness_path'] ?? null;
        $awaiting = (($parsed['awaiting_confirmation'] ?? false) === true)
            && (($parsed['ok'] ?? false) === true)
            && $detectionAfterInstall === true;

        if (!$awaiting) {
            return $this->fail(
                $runId,
                $log,
                ($parsed['message'] ?? '') !== '' && ($parsed['message'] ?? '') !== 'Réponse VM illisible (pas de marqueur __VM_RESULT__).'
                    ? (string) $parsed['message']
                    : ($exec['error'] ?? ('Job distant échoué (code ' . $exec['exit_code'] . ').')),
                $userId,
                $softwareId,
                $export,
                $ip,
                $installExit,
                null,
                $detectionAfterInstall,
                null,
                $witnessPath,
                streamed: $streamed
            );
        }

        $message = (string) (($parsed['message'] ?? '') !== ''
            ? $parsed['message']
            : 'Install + détection OK — en attente de confirmation opérateur');

        $this->validations->update($runId, [
            'status' => 'awaiting_confirmation',
            'message' => $message,
            'install_exit' => $installExit,
            'uninstall_exit' => null,
            'detection_after_install' => $detectionAfterInstall,
            'detection_after_uninstall' => null,
            'witness_path' => $witnessPath,
            'remote_job_dir' => $jobDir,
            'remote_zip' => $remoteZip,
            'remote_runner' => $remoteRunner,
            'log' => implode("\n", $log),
            'finished_at' => null,
        ]);

        $this->auditLogs->log($userId, 'vm.validation.awaiting_confirmation', 'software', $softwareId, [
            'export_id' => $exportId,
            'run_id' => $runId,
            'version' => $export->version,
            'install_exit' => $installExit,
            'witness_path' => $witnessPath,
        ], $ip);

        $emit('[vm] ' . $message, 'En attente de votre confirmation…');

        return [
            'ok' => true,
            'awaiting_confirmation' => true,
            'run_id' => $runId,
            'status' => 'awaiting_confirmation',
            'message' => $message,
            'log' => implode("\n", $log),
            'error' => null,
            'streamed' => $streamed,
            'remote_job_dir' => $jobDir,
            'remote_zip' => $remoteZip,
        ];
    }

    /**
     * After operator confirms the installed app on the VM: uninstall + cleanup.
     *
     * @param callable|null $onProgress callback(string $line, ?string $stepMessage): void
     * @return array{ok: bool, error?: string, run_id?: int, status?: string, message?: string, log?: string, streamed?: bool}
     */
    public function continueAfterConfirmation(int $runId, ?int $userId, ?string $ip, ?callable $onProgress = null): array
    {
        return $this->runPostConfirmPhase($runId, 'Finish', $userId, $ip, $onProgress);
    }

    /**
     * Operator rejects install: cleanup lab artifacts, leave software installed.
     *
     * @param callable|null $onProgress callback(string $line, ?string $stepMessage): void
     * @return array{ok: bool, error?: string, run_id?: int, status?: string, message?: string, log?: string, streamed?: bool}
     */
    public function abortAfterConfirmation(int $runId, ?int $userId, ?string $ip, ?callable $onProgress = null): array
    {
        return $this->runPostConfirmPhase($runId, 'Abort', $userId, $ip, $onProgress);
    }

    /**
     * @param callable|null $onProgress
     * @return array{ok: bool, error?: string, run_id?: int, status?: string, message?: string, log?: string, streamed?: bool}
     */
    private function runPostConfirmPhase(
        int $runId,
        string $phase,
        ?int $userId,
        ?string $ip,
        ?callable $onProgress,
    ): array {
        $run = $this->validations->findById($runId);
        if ($run === null) {
            return ['ok' => false, 'error' => 'Validation VM introuvable.'];
        }
        if (($run['status'] ?? '') !== 'awaiting_confirmation') {
            return ['ok' => false, 'error' => 'Cette validation n\'est pas en attente de confirmation.'];
        }

        $configUserId = isset($run['created_by']) && $run['created_by'] !== null && $run['created_by'] !== ''
            ? (int) $run['created_by']
            : $userId;
        if (!$this->isConfigured($configUserId)) {
            return ['ok' => false, 'error' => 'VM de test non configurée pour le compte ayant lancé le test.'];
        }

        $jobDir = (string) ($run['remote_job_dir'] ?? '');
        $remoteZip = (string) ($run['remote_zip'] ?? '');
        if ($jobDir === '' || $remoteZip === '') {
            return ['ok' => false, 'error' => 'Contexte distant manquant (job/zip) pour finaliser.'];
        }

        $softwareId = (int) ($run['software_id'] ?? 0);
        $exportId = (int) ($run['export_id'] ?? 0);
        $export = $this->exports->findById($exportId);

        @set_time_limit(0);
        $ssh = $this->client();
        $timeout = (int) ($this->vmConfig['timeout_seconds'] ?? 900);
        $log = [];
        $streamed = false;
        $emit = function (string $line, ?string $stepMessage = null) use (&$log, &$streamed, $onProgress): void {
            $line = $this->sanitizeLogLine($line);
            if ($line === '') {
                if ($stepMessage !== null && $onProgress !== null) {
                    $onProgress('', $stepMessage);
                    $streamed = true;
                }

                return;
            }
            $log[] = $line;
            if ($onProgress !== null) {
                $onProgress($line, $stepMessage);
                $streamed = true;
            }
        };

        $existingLog = trim((string) ($run['log'] ?? ''));
        if ($existingLog !== '') {
            $log[] = $existingLog;
            $log[] = '';
        }

        $label = $phase === 'Finish' ? 'uninstall + cleanup' : 'abort (logiciel conservé)';
        $emit('[' . date('H:i:s') . "] Phase {$phase} — {$label}", $phase === 'Finish' ? 'Désinstallation…' : 'Annulation…');

        $remoteRunner = (string) ($run['remote_runner'] ?? '');
        if ($remoteRunner === '') {
            $remoteRoot = rtrim((string) $this->vmConfig['remote_dir'], '\\/');
            $remoteRunner = $remoteRoot . '\\incoming\\confirm_' . $runId . '_runner.ps1';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'vmrun_');
        if ($tmp === false) {
            return ['ok' => false, 'error' => 'Impossible de créer le script runner local.'];
        }
        $localRunner = $tmp . '.ps1';
        @rename($tmp, $localRunner);

        try {
            file_put_contents($localRunner, $this->buildRemoteJobScript());
            $upScript = $ssh->upload($localRunner, $remoteRunner, 60);
            $emit('[' . date('H:i:s') . '] Upload runner exit=' . $upScript['exit_code']);
            if (!$upScript['ok']) {
                return [
                    'ok' => false,
                    'error' => $upScript['error'] ?? 'Échec transfert du script runner.',
                    'run_id' => $runId,
                    'log' => implode("\n", $log),
                    'streamed' => $streamed,
                ];
            }

            $cmd = sprintf(
                'cmd /c powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -OutputFormat Text -File %s -Phase %s -Zip %s -Job %s',
                $this->toWinFwd($remoteRunner),
                $phase,
                $this->toWinFwd($remoteZip),
                $this->toWinFwd($jobDir)
            );
            $exec = $this->runRemoteStreaming($ssh, $cmd, $timeout, $emit);
        } finally {
            @unlink($localRunner);
        }

        $emit('[' . date('H:i:s') . "] Phase {$phase} exit=" . $exec['exit_code']);
        if ($exec['stderr'] !== '') {
            foreach ($this->usefulStderrLines($exec['stderr']) as $errLine) {
                $emit($errLine);
            }
        }

        $parsed = $this->parseJobResult($exec['stdout']);
        $uninstallExit = $parsed['uninstall_exit'] ?? null;
        $detectionAfterUninstall = $parsed['detection_after_uninstall'] ?? null;
        $witnessPath = $parsed['witness_path'] ?? ($run['witness_path'] ?? null);
        $fullLog = implode("\n", $log);

        if ($phase === 'Abort') {
            $message = (string) (($parsed['message'] ?? '') !== ''
                ? $parsed['message']
                : 'Annulé par l\'opérateur — logiciel laissé installé sur la VM');
            $this->validations->update($runId, [
                'status' => 'aborted',
                'message' => $message,
                'detection_after_uninstall' => null,
                'cleanup_ok' => $parsed['cleanup_ok'] ?? null,
                'log' => $fullLog,
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
            $this->auditLogs->log($userId, 'vm.validation.aborted', 'software', $softwareId, [
                'export_id' => $exportId,
                'run_id' => $runId,
            ], $ip);

            return [
                'ok' => false,
                'aborted' => true,
                'run_id' => $runId,
                'status' => 'aborted',
                'message' => $message,
                'error' => $message,
                'log' => $fullLog,
                'streamed' => $streamed,
            ];
        }

        $remoteOk = (($parsed['ok'] ?? false) === true)
            && $detectionAfterUninstall === false;

        if (!$remoteOk) {
            $error = (string) (($parsed['message'] ?? '') !== ''
                ? $parsed['message']
                : ($exec['error'] ?? 'Échec uninstall / cleanup.'));
            $this->validations->update($runId, [
                'status' => 'failed',
                'message' => $error,
                'uninstall_exit' => $uninstallExit,
                'detection_after_uninstall' => $detectionAfterUninstall,
                'witness_path' => $witnessPath,
                'log' => $fullLog,
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
            $this->auditLogs->log($userId, 'vm.validation.failed', 'software', $softwareId, [
                'export_id' => $exportId,
                'run_id' => $runId,
                'error' => $error,
                'phase' => 'Finish',
            ], $ip);

            return [
                'ok' => false,
                'error' => $error,
                'run_id' => $runId,
                'status' => 'failed',
                'message' => $error,
                'log' => $fullLog,
                'streamed' => $streamed,
            ];
        }

        $message = (string) (($parsed['message'] ?? '') !== ''
            ? $parsed['message']
            : 'Install + confirmation + uninstall + cleanup OK');
        $this->validations->update($runId, [
            'status' => 'passed',
            'message' => $message,
            'uninstall_exit' => $uninstallExit,
            'detection_after_uninstall' => $detectionAfterUninstall,
            'witness_path' => $witnessPath,
            'log' => $fullLog,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
        $this->auditLogs->log($userId, 'vm.validation.passed', 'software', $softwareId, [
            'export_id' => $exportId,
            'run_id' => $runId,
            'version' => $export?->version,
            'uninstall_exit' => $uninstallExit,
            'witness_path' => $witnessPath,
        ], $ip);

        return [
            'ok' => true,
            'run_id' => $runId,
            'status' => 'passed',
            'message' => $message,
            'log' => $fullLog,
            'error' => null,
            'streamed' => $streamed,
        ];
    }

    /**
     * @param callable(string, ?string): void $emit
     * @return array{ok: bool, exit_code: int, stdout: string, stderr: string, error?: string}
     */
    private function runRemoteStreaming(SshRemote $ssh, string $cmd, int $timeout, callable $emit): array
    {
        $lineBuf = '';
        $lastOutputAt = time();
        $lastHeartbeatElapsed = 0;
        $onChunk = function (string $chunk) use (&$lineBuf, &$lastOutputAt, $emit): void {
            $lineBuf .= $chunk;
            $lineBuf = str_replace(["\r\n", "\r"], "\n", $lineBuf);
            while (($pos = strpos($lineBuf, "\n")) !== false) {
                $line = substr($lineBuf, 0, $pos);
                $lineBuf = substr($lineBuf, $pos + 1);
                $clean = $this->sanitizeLogLine($line);
                if ($clean === '' || str_starts_with($clean, '#< CLIXML') || str_starts_with($clean, '<Objs ')) {
                    continue;
                }
                if (str_starts_with($clean, '__VM_RESULT__')) {
                    $emit('[vm] résultat JSON reçu');
                    $lastOutputAt = time();
                    continue;
                }
                $emit($clean, $this->stepMessageFromRemoteLine($clean));
                $lastOutputAt = time();
            }
        };
        $onTick = function (int $elapsed) use (&$lastOutputAt, &$lastHeartbeatElapsed, $emit): void {
            if ((time() - $lastOutputAt) < 15) {
                return;
            }
            if (($elapsed - $lastHeartbeatElapsed) < 15) {
                return;
            }
            $lastHeartbeatElapsed = $elapsed;
            $emit(
                '[' . date('H:i:s') . "] … toujours en cours ({$elapsed}s)",
                "Job distant en cours ({$elapsed}s)…"
            );
        };

        $exec = $ssh->run($cmd, $timeout, $onChunk, $onTick);
        if ($lineBuf !== '') {
            $clean = $this->sanitizeLogLine($lineBuf);
            if ($clean !== '' && !str_starts_with($clean, '__VM_RESULT__') && !str_starts_with($clean, '#< CLIXML')) {
                $emit($clean, $this->stepMessageFromRemoteLine($clean));
            }
        }

        return $exec;
    }

    private function bindUser(?int $userId): void
    {
        $this->vmConfig = $this->vmConfigs->getForUser($userId);
    }

    private function client(): SshRemote
    {
        return new SshRemote($this->vmConfig);
    }

    private function ps(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function psSingleQuotedCmd(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /** C:\foo\bar → C:/foo/bar (compatible -File via OpenSSH). */
    private function toWinFwd(string $windowsPath): string
    {
        return str_replace('\\', '/', $windowsPath);
    }

    private function psScript(string $powershellBody): string
    {
        $encoded = base64_encode(mb_convert_encoding($powershellBody, 'UTF-16LE', 'UTF-8'));

        return 'powershell -NoProfile -ExecutionPolicy Bypass -OutputFormat Text -EncodedCommand ' . $encoded;
    }

    private function buildRemoteJobScript(): string
    {
        $path = dirname(__DIR__, 2) . '/resources/vm/Validate-Package.ps1';
        $contents = @file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            throw new \RuntimeException('Script VM manquant : resources/vm/Validate-Package.ps1');
        }

        return $contents;
    }

    /**
     * @return array{
     *   ok?: bool,
     *   awaiting_confirmation?: bool,
     *   aborted?: bool,
     *   cleanup_ok?: bool|null,
     *   install_exit?: int|null,
     *   uninstall_exit?: int|null,
     *   detection_after_install?: bool|null,
     *   detection_after_uninstall?: bool|null,
     *   witness_path?: string|null,
     *   message?: string
     * }
     */
    private function parseJobResult(string $stdout): array
    {
        $fallback = $this->inferResultFromStdout($stdout);

        $json = $this->extractVmResultJson($stdout);
        if ($json === null) {
            return $fallback ?? ['ok' => false, 'message' => 'Réponse VM illisible (pas de marqueur __VM_RESULT__).'];
        }

        $json = $this->sanitizeLogLine($json);
        if ($json === '' || $json[0] !== '{') {
            return $fallback ?? ['ok' => false, 'message' => 'JSON résultat VM invalide.'];
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Dernier recours : JSON parfois tronqué / encoding Windows — utiliser les marqueurs texte.
            return $fallback ?? ['ok' => false, 'message' => 'JSON résultat VM invalide.'];
        }

        $boolOrNull = static function (mixed $v): ?bool {
            if ($v === null) {
                return null;
            }

            return (bool) $v;
        };

        $parsed = [
            'ok' => (bool) ($data['ok'] ?? false),
            'awaiting_confirmation' => (bool) ($data['awaiting_confirmation'] ?? false),
            'aborted' => (bool) ($data['aborted'] ?? false),
            'cleanup_ok' => array_key_exists('cleanup_ok', $data) ? (bool) $data['cleanup_ok'] : null,
            'install_exit' => array_key_exists('install_exit', $data) && $data['install_exit'] !== null
                ? (int) $data['install_exit'] : null,
            'uninstall_exit' => array_key_exists('uninstall_exit', $data) && $data['uninstall_exit'] !== null
                ? (int) $data['uninstall_exit'] : null,
            'detection_after_install' => $boolOrNull($data['detection_after_install'] ?? null),
            'detection_after_uninstall' => $boolOrNull($data['detection_after_uninstall'] ?? null),
            'witness_path' => isset($data['witness_path']) && $data['witness_path'] !== ''
                ? (string) $data['witness_path'] : null,
            'message' => (string) ($data['message'] ?? ''),
        ];

        // Si le JSON dit ok=false mais les marqueurs montrent une pause confirmation, préférer le fallback.
        if (
            empty($parsed['awaiting_confirmation'])
            && is_array($fallback)
            && !empty($fallback['awaiting_confirmation'])
        ) {
            return $fallback;
        }

        return $parsed;
    }

    private function extractVmResultJson(string $stdout): ?string
    {
        $pos = strrpos($stdout, '__VM_RESULT__');
        if ($pos === false) {
            return null;
        }
        $tail = substr($stdout, $pos + strlen('__VM_RESULT__'));
        $tail = preg_replace('/#< CLIXML.*/s', '', $tail) ?? $tail;
        $tail = trim($tail);
        $start = strpos($tail, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($tail);
        for ($i = $start; $i < $len; $i++) {
            $ch = $tail[$i];
            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($ch === '"') {
                $inString = true;
                continue;
            }
            if ($ch === '{') {
                $depth++;
                continue;
            }
            if ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($tail, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function inferResultFromStdout(string $stdout): ?array
    {
        $witness = null;
        if (preg_match('/^WITNESS_PATH=(.+)$/m', $stdout, $m)) {
            $witness = trim($m[1]);
        }

        $installExit = null;
        if (preg_match('/^INSTALL_EXIT=(-?\d+)\s*$/m', $stdout, $m)) {
            $installExit = (int) $m[1];
        }
        $uninstallExit = null;
        if (preg_match('/^UNINSTALL_EXIT=(-?\d+)\s*$/m', $stdout, $m)) {
            $uninstallExit = (int) $m[1];
        }

        $detectionAfterInstall = null;
        if (preg_match('/^DETECTION_AFTER_INSTALL=(True|False)\s*$/mi', $stdout, $m)) {
            $detectionAfterInstall = strcasecmp($m[1], 'True') === 0;
        }
        $detectionAfterUninstall = null;
        if (preg_match('/^DETECTION_AFTER_UNINSTALL_PRESENT=(True|False)\s*$/mi', $stdout, $m)) {
            $detectionAfterUninstall = strcasecmp($m[1], 'True') === 0;
        }

        if (str_contains($stdout, '=== PAUSE CONFIRMATION OPERATEUR ===')
            && $detectionAfterInstall === true
            && ($installExit === 0 || $installExit === 3010)
        ) {
            return [
                'ok' => true,
                'awaiting_confirmation' => true,
                'aborted' => false,
                'install_exit' => $installExit,
                'uninstall_exit' => null,
                'detection_after_install' => true,
                'detection_after_uninstall' => null,
                'witness_path' => $witness,
                'message' => 'Install + detection OK - en attente de confirmation operateur avant uninstall',
            ];
        }

        if (str_contains($stdout, 'ABORT_OK=True')) {
            return [
                'ok' => false,
                'awaiting_confirmation' => false,
                'aborted' => true,
                'install_exit' => $installExit,
                'uninstall_exit' => $uninstallExit,
                'detection_after_install' => $detectionAfterInstall,
                'detection_after_uninstall' => $detectionAfterUninstall,
                'witness_path' => $witness,
                'message' => 'Annule par operateur - logiciel laisse installe sur la VM',
            ];
        }

        return null;
    }

    /**
     * @param list<string> $log
     * @return array{ok: false, error: string, run_id: int, status: string, log: string, streamed: bool}
     */
    private function fail(
        int $runId,
        array $log,
        string $error,
        ?int $userId,
        int $softwareId,
        PackageExport $export,
        ?string $ip,
        ?int $installExit = null,
        ?int $uninstallExit = null,
        ?bool $detectionAfterInstall = null,
        ?bool $detectionAfterUninstall = null,
        ?string $witnessPath = null,
        bool $streamed = false,
    ): array {
        $this->validations->update($runId, [
            'status' => 'failed',
            'message' => $error,
            'install_exit' => $installExit,
            'uninstall_exit' => $uninstallExit,
            'detection_after_install' => $detectionAfterInstall,
            'detection_after_uninstall' => $detectionAfterUninstall,
            'witness_path' => $witnessPath,
            'log' => implode("\n", $log),
            'finished_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogs->log($userId, 'vm.validation.failed', 'software', $softwareId, [
            'export_id' => $export->id,
            'run_id' => $runId,
            'error' => $error,
        ], $ip);

        return [
            'ok' => false,
            'error' => $error,
            'run_id' => $runId,
            'status' => 'failed',
            'log' => implode("\n", $log),
            'streamed' => $streamed,
        ];
    }

    private function sanitizeLogLine(string $line): string
    {
        $line = str_replace("\0", '', $line);
        $line = trim($line);
        if ($line === '') {
            return '';
        }
        if (!mb_check_encoding($line, 'UTF-8')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $line);
            if (!is_string($converted) || $converted === '') {
                $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $line);
            }
            $line = is_string($converted) ? trim($converted) : '';
        }

        return $line;
    }

    /**
     * @return list<string>
     */
    private function usefulStderrLines(string $stderr): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\n|\r/', $stderr) ?: [] as $line) {
            $clean = $this->sanitizeLogLine((string) $line);
            if ($clean === '') {
                continue;
            }
            if (str_starts_with($clean, '#< CLIXML') || str_starts_with($clean, '<Objs ') || str_contains($clean, 'xmlns="http://schemas.microsoft.com/powershell')) {
                continue;
            }
            if (str_contains($clean, 'Permanently added') && str_contains($clean, 'known hosts')) {
                continue;
            }
            $lines[] = '[stderr] ' . $clean;
        }

        return $lines;
    }

    private function stepMessageFromRemoteLine(string $line): ?string
    {
        $upper = strtoupper($line);
        if (str_contains($upper, 'PAUSE CONFIRMATION') || str_contains($upper, 'EN ATTENTE DE CONFIRMATION')) {
            return 'Vérifiez l\'installation sur la VM…';
        }
        if (str_contains($upper, '=== PRECLEAN')) {
            return 'Preclean témoin…';
        }
        if (str_contains($upper, '=== INSTALL')) {
            return 'Installation en cours…';
        }
        if (str_contains($upper, 'INSTALL_EXIT=')) {
            return 'Installation terminée (code ' . trim(substr($line, strpos($line, '=') + 1)) . ')';
        }
        if (str_contains($upper, 'DETECTION APRES INSTALL') || str_contains($upper, 'DETECTION_AFTER_INSTALL')) {
            return 'Détection post-install…';
        }
        if (str_contains($upper, '=== UNINSTALL')) {
            return 'Désinstallation en cours…';
        }
        if (str_contains($upper, 'UNINSTALL_EXIT=')) {
            return 'Désinstallation terminée (code ' . trim(substr($line, strpos($line, '=') + 1)) . ')';
        }
        if (str_contains($upper, 'DETECTION APRES UNINSTALL') || str_contains($upper, 'DETECTION_AFTER_UNINSTALL')) {
            return 'Détection post-uninstall…';
        }
        if (str_contains($upper, 'FINAL CLEANUP') || str_contains($upper, 'CLEANUP JOB')) {
            return 'Cleanup distant…';
        }
        if (preg_match('/Lancement de l.installation|Installation MSI|Code de sortie|Code sortie/i', $line) === 1) {
            return mb_strlen($line) > 90 ? (mb_substr($line, 0, 87) . '…') : $line;
        }

        return null;
    }
}
