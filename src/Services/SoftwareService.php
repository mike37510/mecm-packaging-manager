<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Software;
use App\Repositories\AuditLogRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ProcedureRepository;
use App\Repositories\SoftwareRepository;

/**
 * Software catalog business operations.
 */
final class SoftwareService
{
    public const DETECTION_METHODS = ['fixed_url', 'github'];
    public const ARCHITECTURES = ['x86', 'x64', 'arm', 'multi'];
    public const INSTALLER_TYPES = ['exe', 'msi', 'msix', 'zip', 'inno', 'nsis', 'other'];

    public function __construct(
        private readonly SoftwareRepository $softwares,
        private readonly ProcedureRepository $procedures,
        private readonly AuditLogRepository $auditLogs,
        private readonly string $uploadPath,
        private readonly string $packagesPath,
        private readonly SccmIconNormalizer $iconNormalizer = new SccmIconNormalizer(),
        private readonly ?CategoryRepository $categories = null,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, errors: list<string>, id?: int}
     */
    public function create(array $input, ?array $file, ?int $userId, ?string $ip): array
    {
        $validated = $this->validateInput($input);
        if (!$validated['ok']) {
            return $validated;
        }

        $data = $validated['data'];
        $icon = $this->storeIcon($file);
        if ($icon['error'] !== null) {
            return ['ok' => false, 'errors' => [$icon['error']]];
        }
        $data['icon_path'] = $icon['path'];

        $id = $this->softwares->create($data);
        $this->procedures->getOrCreateActive($id);

        $this->auditLogs->log($userId, 'software.created', 'software', $id, ['name' => $data['name']], $ip);

        return ['ok' => true, 'errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, errors: list<string>}
     */
    public function update(int $id, array $input, ?array $file, ?int $userId, ?string $ip): array
    {
        $software = $this->softwares->findById($id);
        if ($software === null) {
            return ['ok' => false, 'errors' => ['Logiciel introuvable.']];
        }

        $validated = $this->validateInput($input);
        if (!$validated['ok']) {
            return $validated;
        }

        $data = $validated['data'];
        $data['icon_path'] = $software->iconPath;

        $icon = $this->storeIcon($file);
        if ($icon['error'] !== null) {
            return ['ok' => false, 'errors' => [$icon['error']]];
        }
        if ($icon['path'] !== null) {
            if ($software->iconPath) {
                $old = rtrim($this->uploadPath, '/') . '/' . $software->iconPath;
                if (is_file($old)) {
                    @unlink($old);
                }
            }
            $data['icon_path'] = $icon['path'];
        }

        $this->softwares->update($id, $data);
        $this->auditLogs->log($userId, 'software.updated', 'software', $id, ['name' => $data['name']], $ip);

        return ['ok' => true, 'errors' => []];
    }

    /**
     * Met à jour la fiche technique depuis l’assistant procédure (type, archi, URL, icône…).
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, errors: list<string>}
     */
    public function updateWizardFields(int $id, array $input, ?array $file, ?int $userId, ?string $ip): array
    {
        $software = $this->softwares->findById($id);
        if ($software === null) {
            return ['ok' => false, 'errors' => ['Logiciel introuvable.']];
        }

        $merged = [
            'name' => $software->name,
            'publisher' => $software->publisher,
            'category' => $software->category,
            'description' => $software->description ?? '',
            'current_version' => $software->currentVersion,
            'last_packaged_at' => $input['last_packaged_at'] ?? (
                $software->lastPackagedAt
                    ? (new \DateTimeImmutable($software->lastPackagedAt))->format('Y-m-d')
                    : ''
            ),
            'update_frequency_days' => $software->updateFrequencyDays,
            'download_url' => $input['download_url'] ?? $software->downloadUrl ?? '',
            'version_check_url' => $input['version_check_url'] ?? $software->versionCheckUrl ?? '',
            'detection_method' => $input['detection_method'] ?? $software->detectionMethod,
            'architecture' => $input['architecture'] ?? $software->architecture,
            'installer_type' => $input['installer_type'] ?? $software->installerType,
        ];

        $validated = $this->validateInput($merged);
        if (!$validated['ok']) {
            return $validated;
        }

        $data = $validated['data'];
        $data['icon_path'] = $software->iconPath;

        $icon = $this->storeIcon($file);
        if ($icon['error'] !== null) {
            return ['ok' => false, 'errors' => [$icon['error']]];
        }
        if ($icon['path'] !== null) {
            if ($software->iconPath) {
                $old = rtrim($this->uploadPath, '/') . '/' . $software->iconPath;
                if (is_file($old)) {
                    @unlink($old);
                }
            }
            $data['icon_path'] = $icon['path'];
        }

        $this->softwares->update($id, $data);
        $this->auditLogs->log($userId, 'software.wizard_fields_updated', 'software', $id, [
            'installer_type' => $data['installer_type'],
            'architecture' => $data['architecture'],
            'detection_method' => $data['detection_method'],
        ], $ip);

        return ['ok' => true, 'errors' => []];
    }

    public function delete(int $id, ?int $userId, ?string $ip): bool
    {
        $software = $this->softwares->findById($id);
        if ($software === null) {
            return false;
        }

        $this->softwares->softDelete($id);
        $this->auditLogs->log($userId, 'software.deleted', 'software', $id, ['name' => $software->name], $ip);

        return true;
    }

    public function find(int $id): ?Software
    {
        return $this->softwares->findById($id);
    }

    /**
     * @param array{q?: string, category?: string, publisher?: string, installer_type?: string} $filters
     * @return list<Software>
     */
    public function search(array $filters): array
    {
        return $this->softwares->search($filters);
    }

    /**
     * @return array{ok: bool, errors: list<string>, data?: array<string, mixed>}
     */
    private function validateInput(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $publisher = trim((string) ($input['publisher'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $currentVersion = trim((string) ($input['current_version'] ?? ''));

        if ($name === '') {
            $errors[] = 'Le nom est obligatoire.';
        }
        if ($publisher === '') {
            $errors[] = 'L\'éditeur est obligatoire.';
        }
        if ($category === '') {
            $errors[] = 'La catégorie est obligatoire.';
        } elseif ($this->categories !== null && !$this->categories->exists($category)) {
            $errors[] = 'Catégorie inconnue. Choisissez une catégorie dans la liste (Configuration → Catégories).';
        }
        if ($currentVersion === '') {
            $errors[] = 'La version est obligatoire.';
        }

        $detection = (string) ($input['detection_method'] ?? 'fixed_url');
        $arch = (string) ($input['architecture'] ?? 'x64');
        $installer = (string) ($input['installer_type'] ?? 'exe');
        $freq = (int) ($input['update_frequency_days'] ?? 1);

        if (!in_array($detection, self::DETECTION_METHODS, true)) {
            $errors[] = 'Méthode de détection invalide.';
        }
        if (!in_array($arch, self::ARCHITECTURES, true)) {
            $errors[] = 'Architecture invalide.';
        }
        if (!in_array($installer, self::INSTALLER_TYPES, true)) {
            $errors[] = 'Type d\'installateur invalide.';
        }
        if ($freq < 1 || $freq > 3650) {
            $errors[] = 'Fréquence de mise à jour invalide.';
        }

        $documentationUrl = trim((string) ($input['documentation_url'] ?? ''));
        if ($documentationUrl !== '' && filter_var($documentationUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'URL de documentation invalide.';
        }

        $lastPackaged = trim((string) ($input['last_packaged_at'] ?? ''));
        if ($lastPackaged !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $lastPackaged)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $lastPackaged);
            if ($dt === false) {
                $errors[] = 'Date de dernier packaging invalide.';
            } else {
                $lastPackaged = $dt->format('Y-m-d H:i:s');
            }
        } else {
            $lastPackaged = null;
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        return [
            'ok' => true,
            'errors' => [],
            'data' => [
                'name' => $name,
                'publisher' => $publisher,
                'category' => $category,
                'description' => trim((string) ($input['description'] ?? '')) ?: null,
                'current_version' => $currentVersion,
                'last_packaged_at' => $lastPackaged,
                'update_frequency_days' => $freq,
                'download_url' => trim((string) ($input['download_url'] ?? '')) ?: null,
                'version_check_url' => trim((string) ($input['version_check_url'] ?? '')) ?: null,
                'documentation_url' => $documentationUrl !== '' ? $documentationUrl : null,
                'detection_method' => $detection,
                'architecture' => $arch,
                'installer_type' => $installer,
            ],
        ];
    }

    /**
     * Upload or replace MECM icon. Normalised to 512×512 PNG. Keeps existing if no file sent.
     *
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, errors: list<string>}
     */
    public function updateIcon(int $id, ?array $file, ?int $userId, ?string $ip): array
    {
        $software = $this->softwares->findById($id);
        if ($software === null) {
            return ['ok' => false, 'errors' => ['Logiciel introuvable.']];
        }

        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'errors' => ['Sélectionnez un fichier icône.']];
        }

        $icon = $this->storeIcon($file);
        if ($icon['error'] !== null) {
            return ['ok' => false, 'errors' => [$icon['error']]];
        }

        // Remove previous icon file if replaced
        if ($software->iconPath && $icon['path'] !== null) {
            $old = rtrim($this->uploadPath, '/') . '/' . $software->iconPath;
            if (is_file($old)) {
                @unlink($old);
            }
        }

        $this->softwares->update($id, [
            'name' => $software->name,
            'publisher' => $software->publisher,
            'category' => $software->category,
            'description' => $software->description,
            'icon_path' => $icon['path'],
            'current_version' => $software->currentVersion,
            'last_packaged_at' => $software->lastPackagedAt,
            'update_frequency_days' => $software->updateFrequencyDays,
            'download_url' => $software->downloadUrl,
            'version_check_url' => $software->versionCheckUrl,
            'detection_method' => $software->detectionMethod,
            'architecture' => $software->architecture,
            'installer_type' => $software->installerType,
        ]);

        $this->auditLogs->log($userId, 'software.icon_updated', 'software', $id, [
            'icon' => $icon['path'],
        ], $ip);

        return ['ok' => true, 'errors' => []];
    }

    /**
     * Dépôt manuel d'un installateur (.exe / .msi / …). Prioritaire sur l'URL de sync.
     *
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, errors: list<string>, path?: string, size?: int}
     */
    public function uploadInstaller(int $id, ?array $file, ?int $userId, ?string $ip): array
    {
        $software = $this->softwares->findById($id);
        if ($software === null) {
            return ['ok' => false, 'errors' => ['Logiciel introuvable.']];
        }

        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'errors' => ['Sélectionnez un fichier installateur (.exe / .msi).']];
        }

        $err = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'errors' => [$this->uploadErrorMessage($err)]];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'errors' => ['Upload invalide (fichier temporaire manquant).']];
        }

        $original = (string) ($file['name'] ?? 'installer.bin');
        $safe = $this->sanitizeInstallerFilename($original);
        $ext = strtolower(pathinfo($safe, PATHINFO_EXTENSION));
        if (!in_array($ext, ['exe', 'msi', 'msix', 'zip'], true)) {
            return ['ok' => false, 'errors' => ['Extension refusée. Autorisées : .exe, .msi, .msix, .zip.']];
        }

        $typeHint = match ($ext) {
            'msi' => 'msi',
            'msix' => 'msix',
            'zip' => 'zip',
            default => 'exe',
        };
        $check = \App\Support\InstallerBinaryValidator::validate($tmp, $typeHint);
        if (!$check['ok']) {
            return ['ok' => false, 'errors' => [$check['error'] ?? 'Fichier installateur invalide.']];
        }

        $dir = rtrim($this->packagesPath, '/') . '/' . $id;
        if (!\App\Support\WebFs::ensureWritableDirectory($dir)) {
            return ['ok' => false, 'errors' => ['Dossier packages inaccessible en écriture.']];
        }

        $dest = $dir . '/' . $safe;
        if (is_file($dest)) {
            @unlink($dest);
        }
        if (!@move_uploaded_file($tmp, $dest)) {
            if (!@rename($tmp, $dest) && !@copy($tmp, $dest)) {
                return ['ok' => false, 'errors' => ['Impossible d\'enregistrer l\'installateur sur le disque.']];
            }
            @unlink($tmp);
        }
        @chmod($dest, 0664);
        \App\Support\WebFs::reclaimForWeb($dest);
        \App\Support\WebFs::reclaimForWeb($dir);

        $sha = hash_file('sha256', $dest);
        if ($sha === false) {
            @unlink($dest);

            return ['ok' => false, 'errors' => ['Impossible de calculer le SHA256.']];
        }

        $relative = $id . '/' . $safe;
        $size = (int) filesize($dest);
        $guessed = null;
        if (preg_match('/(?:^|[-_ .\/\\\\])v?(\d+\.\d+\.\d+(?:\.\d+)?)(?:[-_ .\/\\\\]|$)/i', $safe, $m)) {
            $guessed = $m[1];
        } elseif (preg_match('/(?:^|[-_ .\/\\\\])v?(\d+\.\d+)(?:[-_ .\/\\\\]|$)/i', $safe, $m)) {
            $guessed = $m[1];
        }

        $patch = [
            'pending_installer_path' => $relative,
            'installer_source' => 'upload',
            'last_sync_sha256' => $sha,
            'last_sync_at' => date('Y-m-d H:i:s'),
            'sync_status' => 'update_available',
        ];
        if ($guessed !== null && $guessed !== '') {
            $patch['current_version'] = $guessed;
        }

        if ($ext === 'msi') {
            $this->softwares->updateInstallerType($id, 'msi');
        } elseif ($ext === 'msix') {
            $this->softwares->updateInstallerType($id, 'msix');
        } elseif ($ext === 'exe' && $software->isMsiFamily()) {
            $this->softwares->updateInstallerType($id, 'exe');
        }

        $this->softwares->patch($id, $patch);
        $this->auditLogs->log($userId, 'software.installer_uploaded', 'software', $id, [
            'file' => $safe,
            'size' => $size,
            'sha256' => $sha,
        ], $ip);

        return ['ok' => true, 'errors' => [], 'path' => $relative, 'size' => $size];
    }

    /**
     * Repasse en mode sync URL et supprime l'installateur déposé manuellement (libération disque).
     *
     * @return array{ok: bool, errors: list<string>, deleted_bytes?: int}
     */
    public function preferUrlSync(int $id, ?int $userId, ?string $ip): array
    {
        $software = $this->softwares->findById($id);
        if ($software === null) {
            return ['ok' => false, 'errors' => ['Logiciel introuvable.']];
        }

        $deletedBytes = 0;
        $deletedFile = null;
        if ($software->installerSource === 'upload'
            && $software->pendingInstallerPath !== null
            && $software->pendingInstallerPath !== ''
        ) {
            $abs = $this->packagesPath . '/' . ltrim(str_replace('\\', '/', $software->pendingInstallerPath), '/');
            $realPackages = realpath($this->packagesPath);
            $realFile = is_file($abs) ? realpath($abs) : false;
            if ($realPackages !== false
                && $realFile !== false
                && str_starts_with($realFile, $realPackages . DIRECTORY_SEPARATOR)
            ) {
                $deletedBytes = (int) filesize($realFile);
                $deletedFile = basename($realFile);
                @unlink($realFile);
            }
        }

        $this->softwares->patch($id, [
            'installer_source' => 'sync',
            'pending_installer_path' => null,
            'sync_status' => 'never',
            'last_sync_sha256' => null,
        ]);
        $this->auditLogs->log($userId, 'software.installer_prefer_url', 'software', $id, [
            'deleted_file' => $deletedFile,
            'deleted_bytes' => $deletedBytes,
        ], $ip);

        return ['ok' => true, 'errors' => [], 'deleted_bytes' => $deletedBytes];
    }

    private function sanitizeInstallerFilename(string $name): string
    {
        $base = basename(str_replace(["\0", '\\'], '', $name));
        $base = preg_replace('/[^\w.\-]+/', '_', $base) ?? 'installer.bin';
        $base = trim($base, '._');

        return $base !== '' ? $base : 'installer.bin';
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux pour la config PHP (limite prévue : 1 Go).',
            UPLOAD_ERR_PARTIAL => 'Téléversement interrompu (fichier partiel).',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire PHP manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Écriture disque impossible (espace ou permissions).',
            UPLOAD_ERR_EXTENSION => 'Upload bloqué par une extension PHP.',
            default => 'Erreur d\'upload (code ' . $code . ').',
        };
    }

    /**
     * Stocke l'icône après normalisation PNG 512×512 (calque transparent, proportions inchangées).
     *
     * @param array<string, mixed>|null $file
     * @return array{path: ?string, error: ?string}
     */
    private function storeIcon(?array $file): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'Échec du téléversement de l\'icône.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $allowed = [
            'image/png' => true,
            'image/jpeg' => true,
            'image/gif' => true,
            'image/webp' => true,
            'image/x-icon' => true,
            'image/vnd.microsoft.icon' => true,
        ];

        if (!isset($allowed[$mime])) {
            return ['path' => null, 'error' => 'Format d\'icône non supporté (png, jpg, gif, webp, ico).'];
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['path' => null, 'error' => 'Icône trop volumineuse (max 5 Mo).'];
        }

        $size = @getimagesize($tmp);
        if ($size === false || empty($size[0]) || empty($size[1])) {
            return ['path' => null, 'error' => 'Impossible de lire les dimensions de l\'icône.'];
        }

        $width = (int) $size[0];
        $height = (int) $size[1];
        if ($width < 1 || $height < 1) {
            return ['path' => null, 'error' => 'Dimensions d\'icône invalides.'];
        }

        if (!is_dir($this->uploadPath) && !mkdir($this->uploadPath, 0775, true) && !is_dir($this->uploadPath)) {
            return ['path' => null, 'error' => 'Impossible de créer le dossier d\'upload.'];
        }

        // Always store as normalised MECM PNG (512×512, transparent padding)
        $filename = bin2hex(random_bytes(8)) . '.png';
        $dest = rtrim($this->uploadPath, '/') . '/' . $filename;

        $normalized = $this->iconNormalizer->normalizeToPng($tmp, $dest);
        if (!$normalized['ok']) {
            return ['path' => null, 'error' => $normalized['error']];
        }

        return ['path' => $filename, 'error' => null];
    }
}
