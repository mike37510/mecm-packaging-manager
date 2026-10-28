<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Thin SSH/SCP wrapper for password auth via sshpass (Linux → Windows OpenSSH).
 */
final class SshRemote
{
    /**
     * @param array{
     *   host: string,
     *   port: int,
     *   username: string,
     *   password: string,
     *   timeout_seconds?: int,
     *   sshpass_bin?: string,
     *   ssh_bin?: string,
     *   scp_bin?: string
     * } $config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function host(): string
    {
        return $this->config['host'];
    }

    public function port(): int
    {
        return (int) $this->config['port'];
    }

    public function username(): string
    {
        return $this->config['username'];
    }

    /**
     * @param callable|null $onChunk Optional callback(string $stdoutChunk): void for live output
     * @param callable|null $onTick Optional callback(int $elapsedSeconds): void while process runs
     * @return array{ok: bool, exit_code: int, stdout: string, stderr: string, error?: string}
     */
    public function run(string $remoteCommand, ?int $timeout = null, ?callable $onChunk = null, ?callable $onTick = null): array
    {
        $timeout ??= (int) ($this->config['timeout_seconds'] ?? 120);
        $ssh = $this->config['ssh_bin'] ?? 'ssh';
        $sshpass = $this->config['sshpass_bin'] ?? 'sshpass';

        $cmd = array_merge(
            [$sshpass, '-e', $ssh, '-p', (string) $this->port()],
            $this->sshOptions(),
            [$this->target(), $remoteCommand],
        );

        return $this->exec($cmd, $timeout, $onChunk, $onTick);
    }

    /**
     * Upload a local file to a remote Windows path (e.g. C:\SCCM_Lab\file.zip).
     *
     * @return array{ok: bool, exit_code: int, stdout: string, stderr: string, error?: string}
     */
    public function upload(string $localPath, string $remoteWindowsPath, ?int $timeout = null): array
    {
        if (!is_readable($localPath)) {
            return [
                'ok' => false,
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => '',
                'error' => 'Fichier local illisible : ' . $localPath,
            ];
        }

        $timeout ??= (int) ($this->config['timeout_seconds'] ?? 300);
        $scp = $this->config['scp_bin'] ?? 'scp';
        $sshpass = $this->config['sshpass_bin'] ?? 'sshpass';
        $remote = $this->toScpPath($remoteWindowsPath);

        $cmd = array_merge(
            [$sshpass, '-e', $scp, '-P', (string) $this->port()],
            $this->sshOptions(),
            [$localPath, $this->target() . ':' . $remote],
        );

        return $this->exec($cmd, $timeout);
    }

    /**
     * OpenSSH options shared by ssh + scp.
     * UserKnownHostsFile must be explicit: OpenSSH uses passwd home (/var/www for www-data),
     * which is not writable and otherwise floods stderr with Permission denied.
     *
     * @return list<string>
     */
    private function sshOptions(): array
    {
        $knownHosts = $this->writableKnownHostsFile();

        return [
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'UserKnownHostsFile=' . $knownHosts,
            '-o', 'GlobalKnownHostsFile=/dev/null',
            '-o', 'PreferredAuthentications=password',
            '-o', 'PubkeyAuthentication=no',
            '-o', 'ConnectTimeout=15',
        ];
    }

    private function writableKnownHostsFile(): string
    {
        $dir = sys_get_temp_dir() . '/sccm_pf_ssh';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return '/dev/null';
        }

        return $dir . '/known_hosts';
    }

    public function target(): string
    {
        return $this->username() . '@' . $this->host();
    }

    /**
     * Convert C:\foo\bar to /C:/foo/bar for OpenSSH scp on Windows.
     */
    public function toScpPath(string $windowsPath): string
    {
        $p = str_replace('\\', '/', $windowsPath);
        if (preg_match('#^([A-Za-z]):/(.*)$#', $p, $m)) {
            return '/' . strtoupper($m[1]) . ':/' . $m[2];
        }

        return $p;
    }

    /**
     * @param list<string> $cmd
     * @param callable|null $onChunk Optional callback(string $stdoutChunk): void for live output
     * @param callable|null $onTick Optional callback(int $elapsedSeconds): void while process runs
     * @return array{ok: bool, exit_code: int, stdout: string, stderr: string, error?: string}
     */
    private function exec(array $cmd, int $timeout, ?callable $onChunk = null, ?callable $onTick = null): array
    {
        if (($this->config['password'] ?? '') === '') {
            return [
                'ok' => false,
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => '',
                'error' => 'Mot de passe VM manquant (TEST_VM_PASSWORD ou config/test_vm.local.php).',
            ];
        }

        $bin = $cmd[0] ?? '';
        if ($bin !== '' && !$this->commandExists($bin)) {
            return [
                'ok' => false,
                'exit_code' => 127,
                'stdout' => '',
                'stderr' => '',
                'error' => "Binaire introuvable : {$bin} (installer openssh-client + sshpass dans l'image/conteneur).",
            ];
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Prefer a writable HOME so OpenSSH can create known_hosts (www-data → /var/www is often RO).
        $home = getenv('HOME') ?: '/tmp';
        if ($home === '' || !is_dir($home) || !is_writable($home)) {
            $home = '/tmp';
        }

        $env = [];
        foreach (array_merge($_ENV ?? [], [
            'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME' => $home,
            'LANG' => getenv('LANG') ?: 'C.UTF-8',
            'SSHPASS' => (string) $this->config['password'],
        ]) as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $env[$key] = (string) $value;
            }
        }

        $proc = @proc_open($cmd, $descriptors, $pipes, null, $env);
        if (!is_resource($proc)) {
            return [
                'ok' => false,
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => '',
                'error' => 'Impossible de démarrer le processus SSH (' . ($bin !== '' ? $bin : 'bin') . ').',
            ];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = time();
        $lastTick = 0;
        $status = ['running' => true];

        $emit = static function (string $chunk) use (&$stdout, $onChunk): void {
            if ($chunk === '') {
                return;
            }
            $stdout .= $chunk;
            if ($onChunk !== null) {
                $onChunk($chunk);
            }
        };

        while ($status['running']) {
            $emit((string) stream_get_contents($pipes[1]));
            $stderr .= (string) stream_get_contents($pipes[2]);
            $elapsed = time() - $start;
            if ($onTick !== null && $elapsed > 0 && $elapsed !== $lastTick) {
                $onTick($elapsed);
                $lastTick = $elapsed;
            }
            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
            if ($elapsed > $timeout) {
                proc_terminate($proc, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);

                return [
                    'ok' => false,
                    'exit_code' => 124,
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'error' => "Timeout SSH après {$timeout}s.",
                ];
            }
            usleep(50_000);
        }

        $emit((string) stream_get_contents($pipes[1]));
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        $code = is_int($status['exitcode'] ?? null) && $status['exitcode'] >= 0
            ? (int) $status['exitcode']
            : (int) $exit;

        return [
            'ok' => $code === 0,
            'exit_code' => $code,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
        ];
    }

    private function commandExists(string $bin): bool
    {
        if ($bin === '') {
            return false;
        }
        if (str_contains($bin, '/') || str_contains($bin, '\\')) {
            return is_executable($bin);
        }

        $paths = explode(PATH_SEPARATOR, (string) (getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'));
        foreach ($paths as $dir) {
            $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $bin;
            if (is_executable($candidate)) {
                return true;
            }
        }

        return false;
    }
}
