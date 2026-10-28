<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;

/**
 * Diagnose / repair runtime directories and permissions (CLI + admin UI).
 */
final class InstallCheckService
{
    public function __construct(
        private readonly string $appRoot,
        private readonly AuditLogRepository $auditLogs,
    ) {
    }

    public function defaultDataRoot(): string
    {
        $env = getenv('HOST_DATA_ROOT');
        if (is_string($env) && trim($env) !== '') {
            $path = trim($env);
            if ($path[0] !== '/') {
                return $this->appRoot . '/' . ltrim($path, './');
            }

            return $path;
        }

        return '';
    }

    /**
     * Dossiers réellement utilisés par l’appli (pas le bind Compose hôte).
     *
     * @return list<string>
     */
    public function runtimeDirs(?string $dataRoot = null): array
    {
        unset($dataRoot);

        return [
            $this->appRoot . '/storage',
            $this->appRoot . '/storage/data',
            $this->appRoot . '/storage/packages',
            $this->appRoot . '/storage/exports',
            $this->appRoot . '/storage/backups',
            $this->appRoot . '/storage/logs',
            $this->appRoot . '/storage/cache',
            $this->appRoot . '/storage/cache/sessions',
            $this->appRoot . '/storage/cache/tmp',
            $this->appRoot . '/storage/jobs',
            $this->appRoot . '/storage/bios',
            $this->appRoot . '/storage/bios/hp',
            $this->appRoot . '/storage/bios/dell',
            $this->appRoot . '/storage/bios/lenovo',
            $this->appRoot . '/public/assets/uploads',
            $this->appRoot . '/public/assets/uploads/icons',
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   php_user: string,
     *   is_root: bool,
     *   data_root: string,
     *   checks: list<array{id: string, label: string, status: string, detail: string}>,
     *   summary: array{ok: int, warn: int, fail: int}
     * }
     */
    public function diagnose(?string $dataRoot = null): array
    {
        unset($dataRoot);

        $phpUser = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
            ? ((posix_getpwuid(posix_geteuid())['name'] ?? null) ?: get_current_user())
            : get_current_user();
        $isRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;

        $checks = [];

        foreach ([
            'php' => 'PHP ≥ 8.2',
            'zip' => 'Extension zip',
            'gd' => 'Extension gd',
            'intl' => 'Extension intl',
            'mbstring' => 'Extension mbstring',
        ] as $id => $label) {
            if ($id === 'php') {
                $ok = version_compare(PHP_VERSION, '8.2.0', '>=');
                $checks[] = [
                    'id' => 'ext_php',
                    'label' => $label,
                    'status' => $ok ? 'ok' : 'fail',
                    'detail' => 'PHP ' . PHP_VERSION,
                ];
                continue;
            }
            $ok = extension_loaded($id);
            $checks[] = [
                'id' => 'ext_' . $id,
                'label' => $label,
                'status' => $ok ? 'ok' : 'fail',
                'detail' => $ok ? 'chargée' : 'manquante',
            ];
        }

        foreach ([
            'magick' => 'ImageMagick (magick)',
            'rsvg-convert' => 'librsvg (rsvg-convert)',
            'ssh' => 'OpenSSH client (ssh)',
            'sshpass' => 'sshpass (tests VM)',
        ] as $bin => $label) {
            $path = $this->which($bin);
            // Debian Bookworm : paquet imagemagick = IM6 → binaire « convert » (pas « magick »).
            if ($path === null && $bin === 'magick') {
                $path = $this->which('convert');
                if ($path !== null) {
                    $path .= ' (convert / ImageMagick 6)';
                }
            }
            $checks[] = [
                'id' => 'bin_' . $bin,
                'label' => $label,
                'status' => $path !== null ? 'ok' : 'warn',
                'detail' => $path ?? 'introuvable dans PATH',
            ];
        }

        foreach ($this->runtimeDirs() as $dir) {
            $rel = $this->rel($dir);
            if (!is_dir($dir)) {
                $checks[] = [
                    'id' => 'dir_' . md5($dir),
                    'label' => 'Dossier ' . $rel,
                    'status' => 'fail',
                    'detail' => 'absent',
                ];
                continue;
            }
            $readable = is_readable($dir);
            $writable = is_writable($dir);
            if ($readable && $writable) {
                $status = 'ok';
                $detail = 'lisible + écriture OK';
            } elseif ($readable) {
                $status = 'fail';
                $detail = 'lisible mais pas d’écriture (www-data)';
            } else {
                $status = 'fail';
                $detail = 'non lisible / droits insuffisants';
            }
            $checks[] = [
                'id' => 'dir_' . md5($dir),
                'label' => 'Dossier ' . $rel,
                'status' => $status,
                'detail' => $detail . $this->ownerHint($dir),
            ];
        }

        $secrets = glob($this->appRoot . '/config/*.local.php') ?: [];
        if ($secrets === []) {
            $checks[] = [
                'id' => 'secrets_none',
                'label' => 'Secrets config/*.local.php',
                'status' => 'warn',
                'detail' => 'aucun fichier (OK si secrets via env / UI)',
            ];
        }
        foreach ($secrets as $secret) {
            $readable = is_readable($secret);
            $checks[] = [
                'id' => 'secret_' . md5($secret),
                'label' => 'Secret ' . basename($secret),
                'status' => $readable ? 'ok' : 'fail',
                'detail' => $readable
                    ? 'lisible' . $this->ownerHint($secret)
                    : 'Apache ne peut pas lire (chmod 640 + groupe www-data)',
            ];
        }

        $summary = ['ok' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($checks as $c) {
            $summary[$c['status']] = ($summary[$c['status']] ?? 0) + 1;
        }

        return [
            'ok' => ($summary['fail'] ?? 0) === 0,
            'php_user' => (string) $phpUser,
            'is_root' => $isRoot,
            'data_root' => '',
            'checks' => $checks,
            'summary' => [
                'ok' => (int) ($summary['ok'] ?? 0),
                'warn' => (int) ($summary['warn'] ?? 0),
                'fail' => (int) ($summary['fail'] ?? 0),
            ],
        ];
    }

    /**
     * Create missing dirs and chmod what the current process can change.
     *
     * @return array{
     *   ok: bool,
     *   actions: list<array{status: string, message: string}>,
     *   needs_sudo: bool,
     *   report: array<string, mixed>
     * }
     */
    public function repair(
        ?string $dataRoot = null,
        bool $tryChown = false,
        string $webUser = 'www-data',
        string $webGroup = 'www-data',
        ?int $userId = null,
        ?string $ip = null,
    ): array {
        $dataRoot ??= $this->defaultDataRoot();
        unset($dataRoot);
        $actions = [];
        $needsSudo = false;
        $isRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;

        foreach ($this->runtimeDirs() as $dir) {
            if (is_dir($dir)) {
                $actions[] = ['status' => 'ok', 'message' => 'Existe : ' . $this->rel($dir)];
            } else {
                if (@mkdir($dir, 0775, true) || is_dir($dir)) {
                    $actions[] = ['status' => 'ok', 'message' => 'Créé : ' . $this->rel($dir)];
                } else {
                    $actions[] = ['status' => 'fail', 'message' => 'Création impossible : ' . $this->rel($dir)];
                    $needsSudo = true;
                }
            }

            foreach ([$dir . '/.gitkeep'] as $keep) {
                if (!file_exists($keep) && is_dir(dirname($keep))) {
                    @touch($keep);
                }
            }
        }

        $trees = [
            $this->appRoot . '/storage',
            $this->appRoot . '/public/assets/uploads',
        ];

        foreach ($trees as $tree) {
            if (!is_dir($tree)) {
                continue;
            }
            if ($tryChown && $isRoot) {
                // Recursive chown is more reliable than per-node chown on some FS layouts
                $cmd = 'chown -R '
                    . escapeshellarg($webUser . ':' . $webGroup) . ' '
                    . escapeshellarg($tree) . ' 2>/dev/null';
                exec($cmd, $out, $code);
                $actions[] = [
                    'status' => $code === 0 ? 'ok' : 'warn',
                    'message' => $code === 0
                        ? "chown -R {$webUser}:{$webGroup} : " . $this->rel($tree)
                        : 'chown -R échoué : ' . $this->rel($tree),
                ];
                if ($code !== 0) {
                    $needsSudo = true;
                }
            }
            $chmodOk = 0;
            $chmodFail = 0;
            foreach ($this->listPaths($tree) as $path) {
                $mode = is_dir($path) ? 02775 : 0664;
                if (@chmod($path, $mode)) {
                    $chmodOk++;
                } else {
                    $chmodFail++;
                    $needsSudo = true;
                }
            }
            $actions[] = [
                'status' => $chmodFail > 0 ? 'warn' : 'ok',
                'message' => sprintf(
                    'chmod sur %s : %d OK, %d échec(s)',
                    $this->rel($tree),
                    $chmodOk,
                    $chmodFail
                ),
            ];
        }

        if (!$tryChown || !$isRoot) {
            $permReport = $this->diagnose();
            $permNeedsSudo = false;
            foreach ($permReport['checks'] as $check) {
                if (($check['status'] ?? '') !== 'fail') {
                    continue;
                }
                $id = (string) ($check['id'] ?? '');
                if (str_starts_with($id, 'dir_') || str_starts_with($id, 'secret_')) {
                    $permNeedsSudo = true;
                    break;
                }
            }
            if ($permNeedsSudo) {
                $needsSudo = true;
                $actions[] = [
                    'status' => 'warn',
                    'message' => 'Droits insuffisants pour Apache : lancer `sudo php bin/fix-permissions.php` sur le serveur.',
                ];
            }
        }

        $secrets = glob($this->appRoot . '/config/*.local.php') ?: [];
        foreach ($secrets as $secret) {
            if ($tryChown && $isRoot) {
                $this->chownPath($secret, 'root', $webGroup)
                    || $this->chownPath($secret, $webUser, $webGroup);
            }
            if (@chmod($secret, 0640)) {
                $actions[] = ['status' => 'ok', 'message' => 'chmod 640 : ' . basename($secret)];
            } elseif (is_readable($secret)) {
                $actions[] = [
                    'status' => 'ok',
                    'message' => 'Secret lisible (chmod déjà OK ou réservé root) : ' . basename($secret),
                ];
            } else {
                $actions[] = ['status' => 'fail', 'message' => 'Secret illisible : ' . basename($secret)];
                $needsSudo = true;
            }
        }

        // Deduplicate noisy repeated lines if any
        if (count($actions) > 120) {
            $actions = array_slice($actions, 0, 120);
            $actions[] = ['status' => 'warn', 'message' => 'Journal tronqué…'];
        }

        $report = $this->diagnose();
        $permOk = !$this->hasPermissionFailures($report) && !$this->hasFailAction($actions);
        $ok = $permOk;

        $this->auditLogs->log($userId, 'install.repair', 'settings', null, [
            'ok' => $ok,
            'needs_sudo' => $needsSudo,
            'actions_count' => count($actions),
            'summary' => $report['summary'],
        ], $ip);

        return [
            'ok' => $ok,
            'actions' => $actions,
            'needs_sudo' => $needsSudo,
            'report' => $report,
        ];
    }

    private function hasFailAction(array $actions): bool
    {
        foreach ($actions as $a) {
            if (($a['status'] ?? '') === 'fail') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function hasPermissionFailures(array $report): bool
    {
        foreach ($report['checks'] ?? [] as $check) {
            if (!is_array($check) || ($check['status'] ?? '') !== 'fail') {
                continue;
            }
            $id = (string) ($check['id'] ?? '');
            if (str_starts_with($id, 'dir_') || str_starts_with($id, 'secret_')) {
                return true;
            }
        }

        return false;
    }

    private function which(string $bin): ?string
    {
        $out = [];
        $code = 0;
        @exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null', $out, $code);
        if ($code === 0 && isset($out[0]) && is_string($out[0]) && $out[0] !== '') {
            return $out[0];
        }

        foreach (['/usr/bin/', '/usr/local/bin/'] as $dir) {
            $path = $dir . $bin;
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function rel(string $path): string
    {
        if (str_starts_with($path, $this->appRoot . '/')) {
            return substr($path, strlen($this->appRoot) + 1);
        }

        return $path;
    }

    private function ownerHint(string $path): string
    {
        if (!function_exists('posix_getpwuid') || !function_exists('fileowner')) {
            return '';
        }
        $uid = @fileowner($path);
        $gid = @filegroup($path);
        if ($uid === false || $gid === false) {
            return '';
        }
        $user = posix_getpwuid($uid)['name'] ?? (string) $uid;
        $group = function_exists('posix_getgrgid')
            ? ((posix_getgrgid($gid)['name'] ?? null) ?: (string) $gid)
            : (string) $gid;

        return " ({$user}:{$group})";
    }

    /**
     * @return list<string>
     */
    private function listPaths(string $dir): array
    {
        $out = [$dir];
        if (!is_dir($dir)) {
            return $out;
        }
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                $out[] = $file->getPathname();
            }
        } catch (\Throwable) {
            // ignore traversal errors
        }

        return $out;
    }

    private function chownPath(string $path, string $user, string $group): bool
    {
        if (!function_exists('posix_getpwnam') || !function_exists('posix_getgrnam')) {
            return false;
        }
        $uid = posix_getpwnam($user)['uid'] ?? null;
        $gid = posix_getgrnam($group)['gid'] ?? null;
        if ($uid === null || $gid === null) {
            return false;
        }

        return @chown($path, $uid) && @chgrp($path, $gid);
    }
}
