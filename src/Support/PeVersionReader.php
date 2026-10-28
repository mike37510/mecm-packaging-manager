<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Extrait ProductVersion / FileVersion d’un .exe (ressource PE VERSIONINFO).
 *
 * Utile pour les installateurs evergreen (OnlyOffice DesktopEditors_x64.exe, PDF24…).
 */
final class PeVersionReader
{
    private const RT_VERSION = 16;

    /**
     * @return array{ProductVersion?: string, FileVersion?: string}
     */
    public static function read(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }

        try {
            $parsed = self::parsePe($fh);
            if ($parsed !== []) {
                return $parsed;
            }

            return self::scanUtf16Versions($fh);
        } catch (\Throwable) {
            return [];
        } finally {
            fclose($fh);
        }
    }

    public static function productVersion(string $path): ?string
    {
        $meta = self::read($path);
        foreach (['ProductVersion', 'FileVersion'] as $key) {
            $v = isset($meta[$key]) ? trim($meta[$key]) : '';
            if ($v !== '' && preg_match('/^0+(\.0+)*$/', $v) !== 1) {
                return $v;
            }
        }

        return null;
    }

    /**
     * @param resource $fh
     * @return array{ProductVersion?: string, FileVersion?: string}
     */
    private static function parsePe($fh): array
    {
        $dos = self::pread($fh, 0, 64);
        if (!str_starts_with($dos, 'MZ')) {
            return [];
        }
        $peOff = self::u32($dos, 0x3C);
        if ($peOff < 64 || $peOff > 16_000_000) {
            return [];
        }
        $pe = self::pread($fh, $peOff, 264);
        if (!str_starts_with($pe, "PE\0\0")) {
            return [];
        }

        $nSections = self::u16($pe, 6);
        $optSize = self::u16($pe, 20);
        $optMagic = self::u16($pe, 24);
        $optOff = 24;
        if ($optSize < 96 || $nSections < 1 || $nSections > 96) {
            return [];
        }

        $ddOff = $optMagic === 0x20B ? 112 : 96;
        if ($optSize < $ddOff + 24) {
            return [];
        }
        $resRva = self::u32($pe, $optOff + $ddOff + 16);
        $resSize = self::u32($pe, $optOff + $ddOff + 20);
        if ($resRva === 0 || $resSize < 16 || $resSize > 8_000_000) {
            return [];
        }

        $sectOff = $peOff + 24 + $optSize;
        $sections = [];
        for ($i = 0; $i < $nSections; $i++) {
            $s = self::pread($fh, $sectOff + $i * 40, 40);
            $sections[] = [
                'va' => self::u32($s, 12),
                'vsize' => self::u32($s, 8),
                'raw' => self::u32($s, 20),
                'rawsize' => self::u32($s, 16),
            ];
        }

        $resFile = self::rvaToOffset($sections, $resRva);
        if ($resFile === null) {
            return [];
        }
        $blob = self::pread($fh, $resFile, $resSize);

        $dataRva = self::findVersionDataRva($blob, 0, 0);
        if ($dataRva === null) {
            return [];
        }
        $dataOff = self::rvaToOffset($sections, $dataRva['rva']);
        if ($dataOff === null) {
            return [];
        }
        $ver = self::pread($fh, $dataOff, min($dataRva['size'], 65_536));

        return self::parseVersionBlock($ver);
    }

    /**
     * @param list<array{va: int, vsize: int, raw: int, rawsize: int}> $sections
     */
    private static function rvaToOffset(array $sections, int $rva): ?int
    {
        foreach ($sections as $s) {
            $span = max($s['vsize'], $s['rawsize']);
            if ($rva >= $s['va'] && $rva < $s['va'] + $span) {
                return $s['raw'] + ($rva - $s['va']);
            }
        }

        return null;
    }

    /**
     * @return array{rva: int, size: int}|null
     */
    private static function findVersionDataRva(string $blob, int $dirOff, int $level): ?array
    {
        if ($level > 3 || $dirOff + 16 > strlen($blob)) {
            return null;
        }
        $named = self::u16($blob, $dirOff + 12);
        $ids = self::u16($blob, $dirOff + 14);
        $count = $named + $ids;
        if ($count < 1 || $count > 4096) {
            return null;
        }

        for ($i = 0; $i < $count; $i++) {
            $ent = $dirOff + 16 + $i * 8;
            if ($ent + 8 > strlen($blob)) {
                break;
            }
            $nameOrId = self::u32($blob, $ent);
            $offset = self::u32($blob, $ent + 4);
            $isName = ($nameOrId & 0x80000000) !== 0;
            $id = $nameOrId & 0x7FFFFFFF;
            $isDir = ($offset & 0x80000000) !== 0;
            $ptr = $offset & 0x7FFFFFFF;

            if ($level === 0 && !$isName && $id !== self::RT_VERSION) {
                continue;
            }
            if ($isDir) {
                $found = self::findVersionDataRva($blob, $ptr, $level + 1);
                if ($found !== null) {
                    return $found;
                }
                continue;
            }
            if ($ptr + 8 > strlen($blob)) {
                continue;
            }

            return [
                'rva' => self::u32($blob, $ptr),
                'size' => self::u32($blob, $ptr + 4),
            ];
        }

        return null;
    }

    /**
     * @return array{ProductVersion?: string, FileVersion?: string}
     */
    private static function parseVersionBlock(string $data): array
    {
        $out = [];
        if (strlen($data) < 40) {
            return $out;
        }

        $sigPos = strpos($data, "\xBD\x04\xEF\xFE");
        if ($sigPos !== false && $sigPos + 28 <= strlen($data)) {
            $fileMs = self::u32($data, $sigPos + 8);
            $fileLs = self::u32($data, $sigPos + 12);
            $prodMs = self::u32($data, $sigPos + 16);
            $prodLs = self::u32($data, $sigPos + 20);
            $prod = self::dotted($prodMs, $prodLs);
            $file = self::dotted($fileMs, $fileLs);
            if ($prod !== '0.0.0.0') {
                $out['ProductVersion'] = $prod;
            }
            if ($file !== '0.0.0.0') {
                $out['FileVersion'] = $file;
            }
        }

        foreach (self::utf16Pairs($data) as $key => $value) {
            if ($key === 'ProductVersion' && $value !== '') {
                $out['ProductVersion'] = $value;
            } elseif ($key === 'FileVersion' && $value !== '' && empty($out['FileVersion'])) {
                $out['FileVersion'] = $value;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function utf16Pairs(string $data): array
    {
        $keys = ['ProductVersion', 'FileVersion'];
        $out = [];
        foreach ($keys as $key) {
            $needle = self::utf16le($key) . "\0\0";
            $pos = 0;
            while (($i = strpos($data, $needle, $pos)) !== false) {
                $val = self::readUtf16z($data, self::skipPad($i + strlen($needle)));
                if ($val !== '') {
                    $out[$key] = $val;
                    break;
                }
                $pos = $i + 2;
            }
        }

        return $out;
    }

    /**
     * @param resource $fh
     * @return array{ProductVersion?: string, FileVersion?: string}
     */
    private static function scanUtf16Versions($fh): array
    {
        $chunk = self::pread($fh, 0, 4_000_000);
        $out = [];
        foreach (['ProductVersion', 'FileVersion'] as $key) {
            $needle = self::utf16le($key) . "\0\0";
            $i = strpos($chunk, $needle);
            if ($i === false) {
                continue;
            }
            $val = self::readUtf16z($chunk, self::skipPad($i + strlen($needle)));
            if ($val !== '') {
                $out[$key] = $val;
            }
        }

        return $out;
    }

    private static function skipPad(int $off): int
    {
        return $off;
    }

    private static function dotted(int $ms, int $ls): string
    {
        return ($ms >> 16) . '.' . ($ms & 0xFFFF) . '.' . ($ls >> 16) . '.' . ($ls & 0xFFFF);
    }

    private static function utf16le(string $ascii): string
    {
        $out = '';
        $len = strlen($ascii);
        for ($i = 0; $i < $len; $i++) {
            $out .= $ascii[$i] . "\0";
        }

        return $out;
    }

    private static function readUtf16z(string $data, int $off): string
    {
        $s = '';
        $len = strlen($data);
        for ($i = $off; $i + 1 < $len; $i += 2) {
            $c = self::u16($data, $i);
            if ($c === 0) {
                break;
            }
            if ($c < 32 || $c > 126) {
                if ($s === '') {
                    continue;
                }
                break;
            }
            $s .= chr($c);
            if (strlen($s) > 64) {
                break;
            }
        }

        return trim($s);
    }

    /**
     * @param resource $fh
     */
    private static function pread($fh, int $offset, int $length): string
    {
        if ($length < 1) {
            return '';
        }
        if (fseek($fh, $offset) !== 0) {
            return '';
        }
        $data = fread($fh, $length);

        return is_string($data) ? $data : '';
    }

    private static function u16(string $bin, int $off): int
    {
        if (strlen($bin) < $off + 2) {
            return 0;
        }
        $v = unpack('v', substr($bin, $off, 2));

        return is_array($v) ? (int) $v[1] : 0;
    }

    private static function u32(string $bin, int $off): int
    {
        if (strlen($bin) < $off + 4) {
            return 0;
        }
        $v = unpack('V', substr($bin, $off, 4));

        return is_array($v) ? (int) $v[1] : 0;
    }
}
