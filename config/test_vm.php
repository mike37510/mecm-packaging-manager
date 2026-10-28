<?php

declare(strict_types=1);

/**
 * Windows lab VM for remote package validation (SSH / OpenSSH).
 *
 * Secrets : variables d'env TEST_VM_* ou fichier config/test_vm.local.php (gitignored).
 * Défauts lab : 192.168.1.1 / admin / admin — à adapter avant prod.
 *
 * @return array{
 *   enabled: bool,
 *   host: string,
 *   port: int,
 *   username: string,
 *   password: string,
 *   remote_dir: string,
 *   timeout_seconds: int,
 *   sshpass_bin: string,
 *   ssh_bin: string,
 *   scp_bin: string
 * }
 */
$defaults = [
    'enabled' => true,
    'host' => (string) (getenv('TEST_VM_HOST') ?: '192.168.1.1'),
    'port' => (int) (getenv('TEST_VM_PORT') ?: 22),
    'username' => (string) (getenv('TEST_VM_USER') ?: 'admin'),
    'password' => (string) (getenv('TEST_VM_PASSWORD') ?: 'admin'),
    'remote_dir' => (string) (getenv('TEST_VM_REMOTE_DIR') ?: 'C:\\SCCM_Lab'),
    'timeout_seconds' => (int) (getenv('TEST_VM_TIMEOUT') ?: 900),
    'sshpass_bin' => (string) (getenv('SSHPASS_BIN') ?: 'sshpass'),
    'ssh_bin' => (string) (getenv('SSH_BIN') ?: 'ssh'),
    'scp_bin' => (string) (getenv('SCP_BIN') ?: 'scp'),
];

$local = __DIR__ . '/test_vm.local.php';
if (is_readable($local)) {
    /** @var array<string, mixed> $override */
    $override = require $local;
    $defaults = array_merge($defaults, $override);
}

// La config UI est désormais par utilisateur (storage/data/vm_configs.json),
// gérée par VmConfigService — plus de surcharge globale ici.

return $defaults;
