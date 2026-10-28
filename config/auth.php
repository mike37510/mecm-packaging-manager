<?php

declare(strict_types=1);

/**
 * Seed du compte admin par défaut (créé seulement s’il n’existe pas).
 * Le mot de passe se change ensuite dans l’UI (Utilisateurs) — ce fichier n’écrase plus le hash.
 *
 * Mot de passe seed : UNIQUEMENT via ADMIN_PASSWORD_HASH (bcrypt) — ne pas committer
 * de hash d’instance de production. Pour la démo publique, voir .env.example /
 * docker-compose.yml (hash correspondant à admin@mecmpackagingmanager.com).
 *
 * Générer un hash : php -r "echo password_hash('MonMotDePasse', PASSWORD_DEFAULT), PHP_EOL;"
 *
 * Surcharges env : ADMIN_EMAIL, ADMIN_PASSWORD_HASH, ADMIN_DISPLAY_NAME
 *
 * @return array{
 *   admin: array{email: string, password_hash: string, display_name: string}
 * }
 */
$demoHash = '$2y$10$qJhPG5rRNt1vsGw4Wr0BtOggKz1jFtPS6tdxTxVOHlGHhIeYgUMBS'; // public demo only

return [
    'admin' => [
        'email' => (string) (getenv('ADMIN_EMAIL') ?: 'admin@mecmpackagingmanager.com'),
        // Hash bcrypt public (démo) — surcharger avec ADMIN_PASSWORD_HASH en prod
        'password_hash' => (string) (getenv('ADMIN_PASSWORD_HASH') ?: $demoHash),
        'display_name' => (string) (getenv('ADMIN_DISPLAY_NAME') ?: 'admin'),
    ],
];
