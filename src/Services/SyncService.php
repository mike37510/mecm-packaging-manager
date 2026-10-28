<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Software;
use App\Repositories\AuditLogRepository;
use App\Repositories\SoftwareRepository;
use App\Repositories\SyncRepository;
use App\Support\InstallerBinaryValidator;
use App\Support\MsiMetadataReader;
use App\Support\PeVersionReader;

/**
 * Downloads the official installer and compares SHA256 with the last sync.
 */
final class SyncService
{
    public function __construct(
        private readonly SoftwareRepository $softwares,
        private readonly SyncRepository $syncs,
        private readonly AuditLogRepository $auditLogs,
        private readonly string $packagesPath,
        private readonly GithubReleaseDetector $github = new GithubReleaseDetector(),
    ) {
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   status?: string,
     *   message?: string,
     *   sha256?: string,
     *   previous_sha256?: ?string,
     *   file_name?: string,
     *   file_size?: int,
     *   update_available?: bool,
     *   remote_version?: string
     * }
     */
    public function check(int $softwareId, ?int $userId, ?string $ip): array
    {
        $software = $this->softwares->findById($softwareId);
        if ($software === null) {
            return ['ok' => false, 'error' => 'Logiciel introuvable.'];
        }

        // Installateur déposé manuellement : priorité absolue (pas de re-téléchargement URL).
        if ($software->hasManualInstaller()) {
            return $this->syncFromLocalUpload($software, $userId, $ip);
        }

        $resolved = $this->resolveDownloadSource($software);
        if (!$resolved['ok']) {
            return ['ok' => false, 'error' => $resolved['error'] ?? 'Source de téléchargement introuvable.'];
        }

        $url = (string) $resolved['url'];
        $remoteVersion = $resolved['version'] ?? null;
        $preferredName = $resolved['file_name'] ?? null;
        $detectedInstallerType = $resolved['installer_type'] ?? null;

        // Si un MSI officiel a été résolu, aligner la fiche (règle produit)
        if (is_string($detectedInstallerType)
            && $detectedInstallerType !== ''
            && $detectedInstallerType !== $software->installerType
            && in_array($detectedInstallerType, ['msi', 'msix'], true)
        ) {
            $this->softwares->updateInstallerType($softwareId, $detectedInstallerType);
        } elseif (is_string($detectedInstallerType)
            && $detectedInstallerType !== ''
            && $detectedInstallerType !== $software->installerType
            && $software->installerType === 'other'
        ) {
            $this->softwares->updateInstallerType($softwareId, $detectedInstallerType);
        } elseif (is_string($preferredName) && $preferredName !== '') {
            $fromName = $this->github->installerTypeFromAssetName($preferredName);
            if ($fromName === 'msi' || $fromName === 'msix') {
                if ($fromName !== $software->installerType) {
                    $this->softwares->updateInstallerType($softwareId, $fromName);
                }
                $detectedInstallerType = $fromName;
            }
        }

        $dir = rtrim($this->packagesPath, '/') . '/' . $softwareId;
        if (!$this->ensureWritableDirectory($dir)) {
            return [
                'ok' => false,
                'error' => 'Dossier packages non accessible en écriture (permissions) : '
                    . 'storage/packages/' . $softwareId
                    . '. Lancez : sudo php bin/fix-permissions.php',
            ];
        }

        $tmp = $dir . '/download.tmp';
        @unlink($tmp);
        $download = $this->downloadTo($url, $tmp);

        if (!$download['ok']) {
            @unlink($tmp);
            $this->syncs->create([
                'software_id' => $softwareId,
                'source_url' => $url,
                'file_name' => 'unknown',
                'local_path' => null,
                'sha256' => str_repeat('0', 64),
                'file_size' => 0,
                'previous_sha256' => $software->lastSyncSha256 ?? null,
                'status' => 'error',
                'message' => $download['error'] ?? 'Téléchargement échoué',
            ]);
            $this->syncs->updateSoftwareSyncState($softwareId, 'error', null, $software->pendingInstallerPath ?? null);

            return ['ok' => false, 'error' => $download['error'] ?? 'Téléchargement échoué'];
        }

        $extracted = $this->maybeExtractInstallerFromArchive(
            $tmp,
            $software,
            is_string($preferredName) ? $preferredName : null,
            $download['content_type'] ?? null,
            $download['final_url'] ?? $url
        );
        if (!$extracted['ok']) {
            @unlink($tmp);
            $this->syncs->create([
                'software_id' => $softwareId,
                'source_url' => $url,
                'file_name' => 'unknown',
                'local_path' => null,
                'sha256' => str_repeat('0', 64),
                'file_size' => 0,
                'previous_sha256' => $software->lastSyncSha256 ?? null,
                'status' => 'error',
                'message' => $extracted['error'] ?? 'Archive invalide',
            ]);
            $this->syncs->updateSoftwareSyncState($softwareId, 'error', null, $software->pendingInstallerPath ?? null);

            return ['ok' => false, 'error' => $extracted['error'] ?? 'Archive invalide'];
        }
        if (!empty($extracted['file_name'])) {
            $preferredName = (string) $extracted['file_name'];
        }

        $valid = InstallerBinaryValidator::validate($tmp, $software->installerType, $download['content_type'] ?? null);
        if (!$valid['ok']) {
            @unlink($tmp);
            $this->syncs->create([
                'software_id' => $softwareId,
                'source_url' => $url,
                'file_name' => $preferredName ?: 'unknown',
                'local_path' => null,
                'sha256' => str_repeat('0', 64),
                'file_size' => 0,
                'previous_sha256' => $software->lastSyncSha256 ?? null,
                'status' => 'error',
                'message' => $valid['error'] ?? 'Fichier téléchargé invalide',
            ]);
            $this->syncs->updateSoftwareSyncState($softwareId, 'error', null, null);

            return ['ok' => false, 'error' => $valid['error'] ?? 'Fichier téléchargé invalide'];
        }

        $sha256 = hash_file('sha256', $tmp);
        if ($sha256 === false) {
            @unlink($tmp);

            return ['ok' => false, 'error' => 'Impossible de calculer le SHA256.'];
        }

        $fileName = $preferredName
            ?: $this->resolveFileName($software, $url, $download['content_type'] ?? null, $download['final_url'] ?? $url);
        $finalPath = $dir . '/' . $fileName;

        // Si GitHub n'a pas fourni de version, tenter le nom de fichier / URL finale (CDN versionné).
        if ($remoteVersion === null || trim((string) $remoteVersion) === '') {
            $finalUrl = (string) ($download['final_url'] ?? $url);
            $remoteVersion = $this->guessVersionFromName($fileName)
                ?? $this->guessVersionFromName($finalUrl)
                ?? $this->guessVersionFromName(basename((string) (parse_url($finalUrl, PHP_URL_PATH) ?: '')));
        }
        // URL evergreen (Chrome MSI, OnlyOffice EXE, etc.) : version lue dans le binaire.
        if ($remoteVersion === null || trim((string) $remoteVersion) === '') {
            $fromBinary = MsiMetadataReader::productVersion($tmp)
                ?? PeVersionReader::productVersion($tmp);
            if ($fromBinary !== null) {
                $remoteVersion = $fromBinary;
            }
        }
        // Ne pas écraser une vraie version déjà connue avec rien ; remplacer les placeholders.
        if (($remoteVersion === null || trim((string) $remoteVersion) === '')
            && !$this->isPlaceholderVersion($software->currentVersion)
        ) {
            $remoteVersion = $software->currentVersion;
        }

        $previous = $software->lastPackagedSha256;
        $same = $previous !== null && hash_equals($previous, $sha256);

        $versionHint = $remoteVersion ? (' (release ' . $remoteVersion . ')') : '';

        if ($same) {
            @unlink($tmp);
            // Keep installer path so ZIP can still be generated
            $keepPath = $software->pendingInstallerPath;
            if (($keepPath === null || $keepPath === '') && is_dir($dir)) {
                foreach (scandir($dir) ?: [] as $f) {
                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    if (in_array($ext, ['exe', 'msi', 'msix', 'zip'], true)) {
                        $keepPath = $softwareId . '/' . $f;
                        break;
                    }
                }
            }
            $this->syncs->create([
                'software_id' => $softwareId,
                'source_url' => $url,
                'file_name' => $fileName,
                'local_path' => $keepPath,
                'sha256' => $sha256,
                'file_size' => (int) ($download['size'] ?? 0),
                'previous_sha256' => $previous,
                'status' => 'up_to_date',
                'message' => 'Même fichier que le dernier package généré (SHA256 identique)' . $versionHint . '.',
            ]);
            $this->syncs->updateSoftwareSyncState($softwareId, 'up_to_date', $sha256, $keepPath, $remoteVersion);
            $this->softwares->patch($softwareId, ['installer_source' => 'sync']);
            $this->auditLogs->log($userId, 'software.sync_up_to_date', 'software', $softwareId, [
                'sha256' => $sha256,
                'remote_version' => $remoteVersion,
            ], $ip);

            return [
                'ok' => true,
                'status' => 'up_to_date',
                'message' => 'Aucune nouvelle version' . $versionHint . ' — fichier identique. ZIP toujours régénérable.',
                'sha256' => $sha256,
                'previous_sha256' => $previous,
                'file_name' => $fileName,
                'file_size' => (int) ($download['size'] ?? 0),
                'update_available' => false,
                'remote_version' => $remoteVersion,
            ];
        }

        // If we already have this pending file (re-sync before packaging), keep update_available
        if ($software->lastSyncSha256 !== null && hash_equals($software->lastSyncSha256, $sha256)
            && $software->isUpdateAvailable() && $software->pendingInstallerPath) {
            @unlink($tmp);
            $this->syncs->create([
                'software_id' => $softwareId,
                'source_url' => $url,
                'file_name' => $fileName,
                'local_path' => $software->pendingInstallerPath,
                'sha256' => $sha256,
                'file_size' => (int) ($download['size'] ?? 0),
                'previous_sha256' => $previous,
                'status' => 'update_available',
                'message' => 'Fichier déjà synchronisé, en attente de génération du ZIP' . $versionHint . '.',
            ]);
            $this->syncs->updateSoftwareSyncState(
                $softwareId,
                'update_available',
                $sha256,
                $software->pendingInstallerPath,
                $remoteVersion
            );

            return [
                'ok' => true,
                'status' => 'update_available',
                'message' => 'Mise à jour toujours en attente' . $versionHint . ' — vous pouvez générer le ZIP.',
                'sha256' => $sha256,
                'previous_sha256' => $previous,
                'file_name' => $fileName,
                'file_size' => (int) ($download['size'] ?? 0),
                'update_available' => true,
                'remote_version' => $remoteVersion,
            ];
        }

        // New or first sync → keep file as pending installer
        if (is_file($finalPath) && realpath($finalPath) !== realpath($tmp)) {
            @unlink($finalPath);
        }
        if (!@rename($tmp, $finalPath)) {
            if (!@copy($tmp, $finalPath)) {
                @unlink($tmp);

                return ['ok' => false, 'error' => 'Impossible d\'enregistrer le fichier téléchargé.'];
            }
            @unlink($tmp);
        }
        \App\Support\WebFs::reclaimForWeb($finalPath);
        \App\Support\WebFs::reclaimForWeb(dirname($finalPath));

        $relative = $softwareId . '/' . $fileName;
        $message = $previous === null
            ? 'Première synchro : fichier téléchargé' . $versionHint . ', prêt pour génération du ZIP.'
            : 'Nouvelle version détectée' . $versionHint . ' (SHA256 différent).';

        $this->syncs->create([
            'software_id' => $softwareId,
            'source_url' => $url,
            'file_name' => $fileName,
            'local_path' => $relative,
            'sha256' => $sha256,
            'file_size' => (int) filesize($finalPath),
            'previous_sha256' => $previous,
            'status' => 'update_available',
            'message' => $message,
        ]);
        $this->syncs->updateSoftwareSyncState($softwareId, 'update_available', $sha256, $relative, $remoteVersion);
        $this->softwares->patch($softwareId, ['installer_source' => 'sync']);
        $this->auditLogs->log($userId, 'software.sync_update_available', 'software', $softwareId, [
            'sha256' => $sha256,
            'previous' => $previous,
            'file' => $fileName,
            'remote_version' => $remoteVersion,
        ], $ip);

        return [
            'ok' => true,
            'status' => 'update_available',
            'message' => $message,
            'sha256' => $sha256,
            'previous_sha256' => $previous,
            'file_name' => $fileName,
            'file_size' => (int) filesize($finalPath),
            'update_available' => true,
            'remote_version' => $remoteVersion,
        ];
    }

    /**
     * Utilise l'installateur uploadé manuellement (aucune requête HTTP).
     *
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   status?: string,
     *   message?: string,
     *   sha256?: string,
     *   previous_sha256?: ?string,
     *   file_name?: string,
     *   file_size?: int,
     *   update_available?: bool,
     *   remote_version?: string
     * }
     */
    private function syncFromLocalUpload(Software $software, ?int $userId, ?string $ip): array
    {
        $abs = $this->pendingInstallerAbsolutePath($software);
        if ($abs === null || !is_readable($abs)) {
            $this->softwares->patch($software->id, ['installer_source' => 'sync']);

            return [
                'ok' => false,
                'error' => 'Installateur manuel introuvable sur le disque. Rechargez le fichier ou repassez en sync URL.',
            ];
        }

        $check = InstallerBinaryValidator::validate($abs, $software->installerType);
        if (!$check['ok']) {
            return ['ok' => false, 'error' => $check['error'] ?? 'Installateur manuel invalide.'];
        }

        $sha256 = hash_file('sha256', $abs);
        if ($sha256 === false) {
            return ['ok' => false, 'error' => 'Impossible de calculer le SHA256.'];
        }

        $fileName = basename($abs);
        $relative = $software->pendingInstallerPath ?? ($software->id . '/' . $fileName);
        $remoteVersion = $this->guessVersionFromName($fileName);
        if ($remoteVersion === null) {
            $fromBinary = MsiMetadataReader::productVersion($abs)
                ?? PeVersionReader::productVersion($abs);
            if ($fromBinary !== null) {
                $remoteVersion = $fromBinary;
            }
        }
        if ($remoteVersion === null && !$this->isPlaceholderVersion($software->currentVersion)) {
            $remoteVersion = $software->currentVersion;
        }
        $versionHint = $remoteVersion ? (' (release ' . $remoteVersion . ')') : '';
        $size = (int) filesize($abs);
        $previous = $software->lastPackagedSha256;
        $same = $previous !== null && hash_equals($previous, $sha256);

        $status = $same ? 'up_to_date' : 'update_available';
        $message = $same
            ? 'Installateur manuel — fichier identique au dernier ZIP' . $versionHint . '.'
            : 'Installateur manuel utilisé' . $versionHint . ' — prêt pour génération du ZIP.';

        $this->syncs->create([
            'software_id' => $software->id,
            'source_url' => 'upload://manual',
            'file_name' => $fileName,
            'local_path' => $relative,
            'sha256' => $sha256,
            'file_size' => $size,
            'previous_sha256' => $previous,
            'status' => $status,
            'message' => $message,
        ]);
        $this->syncs->updateSoftwareSyncState(
            $software->id,
            $status,
            $sha256,
            $relative,
            $remoteVersion
        );
        $this->softwares->patch($software->id, ['installer_source' => 'upload']);
        $this->auditLogs->log($userId, 'software.sync_manual_installer', 'software', $software->id, [
            'sha256' => $sha256,
            'file' => $fileName,
            'remote_version' => $remoteVersion,
        ], $ip);

        return [
            'ok' => true,
            'status' => $status,
            'message' => $message,
            'sha256' => $sha256,
            'previous_sha256' => $previous,
            'file_name' => $fileName,
            'file_size' => $size,
            'update_available' => !$same,
            'remote_version' => $remoteVersion ?? '',
        ];
    }

    /**
     * Choisit l'URL réelle à télécharger selon le mode de détection.
     *
     * @return array{ok: bool, error?: string, url?: string, version?: ?string, file_name?: ?string, installer_type?: ?string}
     */
    private function resolveDownloadSource(Software $software): array
    {
        $method = $software->detectionMethod;
        $checkUrl = trim((string) ($software->versionCheckUrl ?? ''));
        $downloadUrl = trim((string) ($software->downloadUrl ?? ''));
        $looksGithub = $this->github->parseGithubRepo($checkUrl) !== null
            || $this->github->parseGithubRepo($downloadUrl) !== null;

        $useGithub = $method === 'github' || $looksGithub;

        if ($useGithub) {
            $gh = $this->github->resolve($software);
            if (!$gh['ok']) {
                return ['ok' => false, 'error' => $gh['error'] ?? 'Détection GitHub échouée.'];
            }

            return [
                'ok' => true,
                'url' => $gh['download_url'],
                'version' => $gh['version'] ?? null,
                'file_name' => $gh['asset_name'] ?? null,
                'installer_type' => $gh['installer_type'] ?? null,
            ];
        }

        if ($downloadUrl === '') {
            return ['ok' => false, 'error' => 'URL de téléchargement manquante sur la fiche logiciel.'];
        }

        // URL directe : déduire le type depuis l'extension / mots-clés (MSI prioritaire)
        $installerType = null;
        $lower = strtolower($downloadUrl);
        if (str_contains($lower, '.msi') || str_contains($lower, 'msi-latest') || str_contains($lower, 'msi_')) {
            $installerType = 'msi';
        } elseif (str_ends_with(parse_url($downloadUrl, PHP_URL_PATH) ?: '', '.exe') || str_contains($lower, '.exe')) {
            $installerType = 'exe';
        }

        $pathName = basename((string) (parse_url($downloadUrl, PHP_URL_PATH) ?: ''));
        $finalUrl = $this->probeFinalUrl($downloadUrl) ?? $downloadUrl;
        $guessed = $this->guessVersionFromName(rawurldecode($pathName))
            ?? $this->guessVersionFromName($downloadUrl)
            ?? $this->guessVersionFromName($finalUrl);

        return [
            'ok' => true,
            'url' => $downloadUrl,
            'version' => $guessed,
            'file_name' => null,
            'installer_type' => $installerType,
        ];
    }

    /**
     * Suit les redirections (HEAD) pour exposer une URL CDN versionnée (Zoom, etc.).
     */
    private function probeFinalUrl(string $url): ?string
    {
        if (!function_exists('curl_init') || str_starts_with($url, 'file://')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_AUTOREFERER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 SCCM-Packaging-Factory/1.1',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FAILONERROR => false,
        ]);
        curl_exec($ch);
        $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($final) || $final === '' || $code >= 400) {
            return null;
        }

        return $final;
    }

    /**
     * Extrait une version depuis un nom de fichier / URL (fallback hors GitHub).
     */
    public function guessVersionFromName(string $name): ?string
    {
        $name = trim(rawurldecode($name));
        if ($name === '') {
            return null;
        }

        // Chemins CDN type …/prod/7.1.5.43453/x64/ZoomInstallerFull.msi
        // → utiliser l’URL/chemin entier, pas seulement le basename.
        $haystack = $name;
        $base = pathinfo($name, PATHINFO_FILENAME);
        if ($base !== '' && $base !== $name && !str_contains($name, '/') && !str_contains($name, '\\')) {
            $haystack = $base;
        }

        // Firefox Setup 140.13.0esr.msi
        if (preg_match('/(\d+\.\d+(?:\.\d+)?)\s*esr/i', $haystack, $m)) {
            return $m[1];
        }
        // OpenJDK … 17.0.20_8 / hotspot_17.0.20
        if (preg_match('/(?:jdk|hotspot|temurin)[^\d]*(\d+\.\d+\.\d+)/i', $haystack, $m)) {
            return $m[1];
        }
        // Adobe AcroRdrDCx642600121662 → 26.001.21662 (pas 64.260.01216)
        if (preg_match('/acro(?:rdr|bat)?(?:dc)?(?:x\d+)?(\d{10})/i', $haystack, $m)) {
            $d = $m[1];

            return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5);
        }
        // 7z2602-x64 → 26.02
        if (preg_match('/(?:^|[^0-9])7z(\d{2})(\d{2})(?:[^0-9]|$)/i', $haystack, $m)) {
            return $m[1] . '.' . $m[2];
        }
        // Séparateurs : - _ . espace / \ (Zoom CDN, GitHub asset paths, etc.)
        if (preg_match('/(?:^|[-_ .\/\\\\])v?(\d+\.\d+\.\d+(?:\.\d+)?)(?:[-_ .\/\\\\]|$)/i', $haystack, $m)) {
            return $m[1];
        }
        if (preg_match('/(?:^|[-_ .\/\\\\])v?(\d+\.\d+)(?:[-_ .\/\\\\]|$)/i', $haystack, $m)) {
            return $m[1];
        }

        return null;
    }

    public function isPlaceholderVersion(string $version): bool
    {
        $v = strtolower(trim($version));

        return $v === ''
            || $v === 'unknown'
            || $v === 'n/a'
            || preg_match('/^0+(\.0+)*$/', $v) === 1;
    }

    /**
     * Absolute path to pending installer, if any.
     */
    public function pendingInstallerAbsolutePath(Software $software): ?string
    {
        $rel = $software->pendingInstallerPath;
        if ($rel === null || $rel === '') {
            return null;
        }
        $full = rtrim($this->packagesPath, '/') . '/' . ltrim($rel, '/');

        return is_readable($full) ? $full : null;
    }

    /**
     * @return array{ok: bool, error?: string, size?: int, content_type?: ?string, final_url?: string}
     */
    private function downloadTo(string $url, string $destination): array
    {
        if (str_starts_with($url, 'file://')) {
            $local = substr($url, 7);
            if (!is_readable($local)) {
                return ['ok' => false, 'error' => 'Fichier local introuvable : ' . $local];
            }
            if (!@copy($local, $destination)) {
                return ['ok' => false, 'error' => 'Copie locale impossible.'];
            }

            return [
                'ok' => true,
                'size' => (int) filesize($destination),
                'content_type' => null,
                'final_url' => $url,
            ];
        }

        if (function_exists('curl_init')) {
            return $this->downloadWithCurl($url, $destination);
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 300,
                'follow_location' => 1,
                'max_redirects' => 10,
                'header' => "User-Agent: SCCM-Packaging-Factory/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            return ['ok' => false, 'error' => 'Échec du téléchargement (stream).'];
        }
        if (file_put_contents($destination, $data) === false) {
            return ['ok' => false, 'error' => 'Écriture locale impossible.'];
        }

        return [
            'ok' => true,
            'size' => strlen($data),
            'content_type' => null,
            'final_url' => $url,
        ];
    }

    /**
     * @return array{ok: bool, error?: string, size?: int, content_type?: ?string, final_url?: string}
     */
    private function downloadWithCurl(string $url, string $destination): array
    {
        $url = $this->normalizeDownloadUrl($url);

        $fp = @fopen($destination, 'wb');
        if ($fp === false) {
            return [
                'ok' => false,
                'error' => 'Impossible d\'écrire le fichier temporaire (permission refusée sur storage/packages).',
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 600,
            // Certains CDN (media.inkscape.org) renvoient 403 si cURL envoie
            // automatiquement le Referer de la page gallery lors du follow-redirect.
            CURLOPT_AUTOREFERER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 SCCM-Packaging-Factory/1.1',
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Accept-Language: en-US,en;q=0.9',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FAILONERROR => false,
        ]);

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null;
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
        $size = (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        curl_close($ch);
        fclose($fp);

        // Retry : 302 gallery → CDN direct (hotlink / rate-limit sur inkscape.org)
        if ($ok !== false && $errno === 0 && $status === 403) {
            @unlink($destination);
            $resolved = $this->resolveRedirectTarget($url);
            if ($resolved === null) {
                $resolved = $this->inkscapeMediaFallback($url);
            }
            if ($resolved !== null && $resolved !== $url) {
                return $this->downloadWithCurl($resolved, $destination);
            }

            return ['ok' => false, 'error' => 'HTTP 403 lors du téléchargement.'];
        }

        if ($ok === false || $errno !== 0) {
            @unlink($destination);

            return ['ok' => false, 'error' => 'cURL : ' . ($error !== '' ? $error : 'échec')];
        }
        if ($status >= 400) {
            @unlink($destination);

            return ['ok' => false, 'error' => 'HTTP ' . $status . ' lors du téléchargement.'];
        }

        clearstatcache(true, $destination);
        $realSize = is_file($destination) ? (int) filesize($destination) : $size;

        return [
            'ok' => true,
            'size' => $realSize,
            'content_type' => is_string($contentType) ? $contentType : null,
            'final_url' => $finalUrl,
        ];
    }

    /**
     * Prefer stable CDN URLs when vendors publish gallery wrappers that 403 scrapers.
     */
    private function normalizeDownloadUrl(string $url): string
    {
        $media = $this->inkscapeMediaFallback($url);

        return $media ?? $url;
    }

    /**
     * inkscape.org/gallery/item/…/file.msi → media.inkscape.org/dl/resources/file/file.msi
     */
    private function inkscapeMediaFallback(string $url): ?string
    {
        if (!preg_match('~^https?://(?:www\.)?inkscape\.org/gallery/item/\d+/([^/?#]+\.(?:msi|exe))~i', $url, $m)) {
            return null;
        }

        return 'https://media.inkscape.org/dl/resources/file/' . rawurlencode(rawurldecode($m[1]));
    }

    /**
     * Resolve a single HTTP redirect target (no download body).
     */
    private function resolveRedirectTarget(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_AUTOREFERER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 SCCM-Packaging-Factory/1.1',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $headers = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($headers) || $status < 300 || $status >= 400) {
            return null;
        }
        if (!preg_match('/^location:\s*(.+)$/mi', $headers, $m)) {
            return null;
        }
        $loc = trim($m[1]);
        if ($loc === '') {
            return null;
        }
        if (str_starts_with($loc, '/')) {
            $parts = parse_url($url);
            $loc = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $loc;
        }

        return $loc;
    }

    private function resolveFileName(Software $software, string $url, ?string $contentType, string $finalUrl): string
    {
        foreach ([$url, $finalUrl] as $candidate) {
            $path = parse_url($candidate, PHP_URL_PATH) ?: '';
            $base = rawurldecode(basename((string) $path));
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            $allowed = ['exe', 'msi', 'msix', 'zip', 'msp'];
            if ($base !== '' && $base !== '/' && in_array($ext, $allowed, true)) {
                return $base;
            }
        }

        // Infer from installer type
        $typeExt = match ($software->installerType) {
            'msi' => 'msi',
            'msix' => 'msix',
            'zip' => 'zip',
            default => 'exe',
        };

        if (is_string($contentType)) {
            if (str_contains($contentType, 'msi')) {
                $typeExt = 'msi';
            } elseif (str_contains($contentType, 'zip')) {
                $typeExt = 'zip';
            }
        }

        $slug = preg_replace('/[^a-zA-Z0-9]+/', '_', $software->name) ?: 'installer';

        return strtolower($slug) . '_installer.' . $typeExt;
    }

    /**
     * Si le téléchargement est un .nupkg/.zip (ex. Chocolatey FileZilla), extraire l'installateur.
     *
     * @return array{ok: bool, error?: string, file_name?: string}
     */
    private function maybeExtractInstallerFromArchive(
        string $tmpPath,
        Software $software,
        ?string $preferredName,
        ?string $contentType,
        string $finalUrl,
    ): array {
        $wantArchive = $software->installerType === 'zip';
        $header = @file_get_contents($tmpPath, false, null, 0, 4);
        $isZip = is_string($header) && str_starts_with($header, "PK\x03\x04");
        if (!$isZip) {
            return ['ok' => true];
        }

        $urlPath = strtolower((string) (parse_url($finalUrl, PHP_URL_PATH) ?: ''));
        $looksNupkg = str_ends_with($urlPath, '.nupkg')
            || str_contains($urlPath, '/package/')
            || str_contains(strtolower((string) $contentType), 'octet-stream')
            || (is_string($preferredName) && str_ends_with(strtolower($preferredName), '.nupkg'));

        // ZIP attendu comme livrable final : ne pas extraire
        if ($wantArchive && !$looksNupkg) {
            return ['ok' => true];
        }

        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'error' => 'Extension PHP zip manquante pour extraire le package.'];
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            return ['ok' => false, 'error' => 'Archive ZIP/nupkg illisible.'];
        }

        $candidates = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }
            $base = basename($name);
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (!in_array($ext, ['exe', 'msi', 'msix'], true)) {
                continue;
            }
            $candidates[] = ['path' => $name, 'base' => $base, 'ext' => $ext];
        }

        if ($candidates === []) {
            $zip->close();

            return ['ok' => false, 'error' => 'Aucun installateur (.exe/.msi) trouvé dans l\'archive téléchargée.'];
        }

        $arch = strtolower($software->architecture);
        usort($candidates, static function (array $a, array $b) use ($arch): int {
            $score = static function (array $c) use ($arch): int {
                $n = strtolower($c['base']);
                $s = 0;
                if ($c['ext'] === 'msi') {
                    $s += 30;
                }
                if ($arch === 'x64' && (str_contains($n, 'win64') || str_contains($n, 'x64') || str_contains($n, '64-bit') || str_contains($n, '64bit'))) {
                    $s += 50;
                }
                if ($arch === 'x86' && (str_contains($n, 'win32') || str_contains($n, 'x86') || str_contains($n, '32-bit'))) {
                    $s += 50;
                }
                if (str_contains($n, 'setup') || str_contains($n, 'installer')) {
                    $s += 10;
                }
                if (str_contains($n, 'portable')) {
                    $s -= 20;
                }

                return $s;
            };

            return $score($b) <=> $score($a);
        });

        $pick = $candidates[0];
        $extracted = $zip->getFromName($pick['path']);
        $zip->close();
        if ($extracted === false || $extracted === '') {
            return ['ok' => false, 'error' => 'Impossible d\'extraire ' . $pick['base'] . ' depuis l\'archive.'];
        }

        if (file_put_contents($tmpPath, $extracted) === false) {
            return ['ok' => false, 'error' => 'Écriture de l\'installateur extrait impossible.'];
        }

        return ['ok' => true, 'file_name' => $pick['base']];
    }

    /**
     * Create package directory if needed and verify write access for the web user.
     */
    private function ensureWritableDirectory(string $dir): bool
    {
        $base = rtrim($this->packagesPath, '/');
        if (!\App\Support\WebFs::ensureWritableDirectory($base)) {
            return false;
        }

        return \App\Support\WebFs::ensureWritableDirectory($dir);
    }
}
