<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Normalise une icône uploadée en PNG MECM 512×512 (fond transparent, proportions conservées).
 */
final class SccmIconNormalizer
{
    public const SIZE = 512;

    private readonly string $magickBinary;

    public function __construct(?string $magickBinary = null)
    {
        $this->magickBinary = ($magickBinary !== null && $magickBinary !== '')
            ? $magickBinary
            : self::resolveMagickBinary();
    }

    public static function resolveMagickBinary(): string
    {
        foreach (['/usr/bin/magick', '/usr/local/bin/magick', '/usr/bin/convert'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return 'magick';
    }

    /**
     * Place le logo dans un calque 512×512 transparent sans déformer les proportions.
     * Réduit uniquement si plus grand que le calque ; ne grossit pas un logo plus petit.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function normalizeToPng(string $sourcePath, string $destPngPath): array
    {
        if (!is_readable($sourcePath)) {
            return ['ok' => false, 'error' => 'Fichier source icône illisible.'];
        }

        $size = self::SIZE;
        $cmd = sprintf(
            '%s %s -auto-orient -background none -alpha set -gravity center -resize %s -extent %dx%d PNG32:%s 2>&1',
            escapeshellcmd($this->magickBinary),
            escapeshellarg($sourcePath),
            escapeshellarg($size . 'x' . $size . '>'),
            $size,
            $size,
            escapeshellarg($destPngPath)
        );

        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        if ($code !== 0 || !is_file($destPngPath) || filesize($destPngPath) === 0) {
            $detail = trim(implode("\n", $output));
            return [
                'ok' => false,
                'error' => 'Impossible de normaliser l\'icône en PNG 512×512.'
                    . ($detail !== '' ? ' ' . $detail : ''),
            ];
        }

        @chmod($destPngPath, 0664);

        return ['ok' => true, 'error' => null];
    }
}
