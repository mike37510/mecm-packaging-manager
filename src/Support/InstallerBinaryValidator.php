<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validates that a synced file is a real Windows installer (not an HTML page).
 */
final class InstallerBinaryValidator
{
    /**
     * @return array{ok: bool, error?: string}
     */
    public static function validate(string $path, ?string $installerType = null, ?string $contentType = null): array
    {
        if (!is_readable($path)) {
            return ['ok' => false, 'error' => 'Installateur introuvable ou illisible.'];
        }

        if (is_string($contentType) && preg_match('#text/html#i', $contentType) === 1) {
            return [
                'ok' => false,
                'error' => 'Téléchargement HTML au lieu d\'un installateur. Indiquez une URL directe (.exe/.msi) ou un flux Chocolatey (.nupkg).',
            ];
        }

        $size = (int) filesize($path);
        if ($size < 1024) {
            return [
                'ok' => false,
                'error' => 'Fichier trop petit (' . $size . ' octets) — ce n\'est pas un installateur Windows.',
            ];
        }

        $head = @file_get_contents($path, false, null, 0, 16);
        if (!is_string($head) || strlen($head) < 2) {
            return ['ok' => false, 'error' => 'Impossible de lire le fichier installateur.'];
        }

        $isMz = str_starts_with($head, 'MZ');
        $isMsi = str_starts_with($head, "\xD0\xCF\x11\xE0");
        $isZip = str_starts_with($head, "PK\x03\x04");
        $looksText = preg_match('/^\s*</', $head) === 1
            || str_starts_with($head, '<!DOCTYPE')
            || str_starts_with($head, '<html')
            || str_starts_with(strtolower($head), '<!doctype');

        if ($looksText) {
            return [
                'ok' => false,
                'error' => 'Le fichier local est une page HTML, pas un .exe/.msi. Corrigez l\'URL de téléchargement puis resynchronisez — le ZIP ne peut pas être généré.',
            ];
        }

        $type = $installerType ?? 'exe';
        if (in_array($type, ['exe', 'nsis', 'inno', 'other'], true) && !$isMz && !$isMsi) {
            return [
                'ok' => false,
                'error' => 'Le fichier n\'est pas un exécutable Windows (signature MZ absente). ZIP refusé.',
            ];
        }
        if ($type === 'msi' && !$isMsi && !$isMz) {
            return ['ok' => false, 'error' => 'Le fichier n\'est pas un MSI valide. ZIP refusé.'];
        }
        if ($type === 'zip' && !$isZip) {
            return ['ok' => false, 'error' => 'Le fichier n\'est pas une archive ZIP.'];
        }
        if ($type === 'msix' && !$isZip && !$isMz) {
            return ['ok' => false, 'error' => 'Le fichier n\'est pas un MSIX valide.'];
        }

        return ['ok' => true];
    }

    public static function isValid(string $path, ?string $installerType = null): bool
    {
        return self::validate($path, $installerType)['ok'] === true;
    }
}
