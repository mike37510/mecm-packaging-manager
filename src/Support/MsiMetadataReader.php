<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Extrait ProductVersion / ProductCode d’un .msi (table Property).
 *
 * Utile pour les URL evergreen (Chrome Enterprise, Webex…) dont le nom de fichier
 * ne contient pas de numéro de version.
 */
final class MsiMetadataReader
{
    /**
     * @return array{ProductVersion?: string, ProductCode?: string, ProductName?: string}
     */
    public static function read(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }
        $head = @file_get_contents($path, false, null, 0, 8);
        if (!is_string($head) || !str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            return [];
        }

        try {
            $ole = new OleCompoundFile($path);
        } catch (\Throwable) {
            return [];
        }

        try {
            $streams = $ole->readMsiStreams([
                'Table._StringData',
                'Table._StringPool',
                'Table.Property',
            ]);
        } catch (\Throwable) {
            $ole->close();

            return [];
        }
        $ole->close();

        $data = $streams['Table._StringData'] ?? null;
        $pool = $streams['Table._StringPool'] ?? null;
        $property = $streams['Table.Property'] ?? null;
        if (!is_string($data) || !is_string($pool) || !is_string($property)) {
            return [];
        }

        $strings = self::decodeStringPool($data, $pool);
        if ($strings === []) {
            return [];
        }

        $idSize = self::u16($pool, 2) === 0x8000 ? 3 : 2;
        $props = self::decodePropertyTable($property, $strings, $idSize);
        $out = [];
        foreach (['ProductVersion', 'ProductCode', 'ProductName'] as $key) {
            if (isset($props[$key]) && trim($props[$key]) !== '') {
                $out[$key] = trim($props[$key]);
            }
        }

        return $out;
    }

    public static function productVersion(string $path): ?string
    {
        $v = self::read($path)['ProductVersion'] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        if (preg_match('/^0+(\.0+)*$/', $v) === 1) {
            return null;
        }

        return $v;
    }

    /**
     * Décodage des noms de flux MSI (encodage 6 bits, préfixe Table.).
     */
    public static function decodeStreamName(string $msiName): string
    {
        $out = '';
        $chars = preg_split('//u', $msiName, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) {
            return $msiName;
        }
        foreach ($chars as $ch) {
            $x = mb_ord($ch, 'UTF-8');
            if ($x >= 0x3800 && $x < 0x4800) {
                $x -= 0x3800;
                $out .= self::decodeRune($x & 0x3F) . self::decodeRune($x >> 6);
            } elseif ($x >= 0x4800 && $x < 0x4840) {
                $out .= self::decodeRune($x - 0x4800);
            } elseif ($x === 0x4840) {
                $out .= 'Table.';
            } else {
                $out .= $ch;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function decodeStringPool(string $stringData, string $stringPool): array
    {
        if (strlen($stringPool) < 4) {
            return [];
        }
        $offset = 4;
        $dataOff = 0;
        $dataLen = strlen($stringData);
        $strings = [];
        $poolLen = strlen($stringPool);

        while ($offset + 4 <= $poolLen) {
            $size = self::u16($stringPool, $offset);
            $refs = self::u16($stringPool, $offset + 2);
            $offset += 4;
            $len = $size;
            if ($size === 0 && $refs !== 0 && $offset + 4 <= $poolLen) {
                $len = self::u32($stringPool, $offset);
                $offset += 4;
            }
            if ($len < 0 || $dataOff + $len > $dataLen) {
                break;
            }
            $strings[] = $len === 0 ? '' : substr($stringData, $dataOff, $len);
            $dataOff += $len;
        }

        return $strings;
    }

    /**
     * Table Property : 2 colonnes string, stockage colonne-major.
     *
     * @param list<string> $strings
     * @return array<string, string>
     */
    public static function decodePropertyTable(string $propertyStream, array $strings, int $idSize = 2): array
    {
        $rowSize = $idSize * 2;
        if ($rowSize < 2 || strlen($propertyStream) < $rowSize) {
            return [];
        }
        $rowCount = intdiv(strlen($propertyStream), $rowSize);
        $keys = self::readColumnMajorIds($propertyStream, 0, $rowCount, $idSize);
        $vals = self::readColumnMajorIds($propertyStream, $idSize, $rowCount, $idSize);
        $out = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $k = self::stringAt($strings, $keys[$i] ?? 0);
            $v = self::stringAt($strings, $vals[$i] ?? 0);
            if ($k !== '') {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    private static function decodeRune(int $x): string
    {
        if ($x < 10) {
            return chr($x + 48);
        }
        if ($x < 36) {
            return chr($x - 10 + 65);
        }
        if ($x < 62) {
            return chr($x - 36 + 97);
        }
        if ($x === 62) {
            return '.';
        }

        return '_';
    }

    /**
     * @return list<int>
     */
    private static function readColumnMajorIds(string $stream, int $colOffset, int $rowCount, int $idSize): array
    {
        $out = [];
        $byteBase = $colOffset * $rowCount;
        for ($i = 0; $i < $rowCount; $i++) {
            $off = $byteBase + $i * $idSize;
            $out[] = $idSize === 3 ? self::u24($stream, $off) : self::u16($stream, $off);
        }

        return $out;
    }

    /**
     * @param list<string> $strings
     */
    private static function stringAt(array $strings, int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        $idx = $id - 1;

        return $strings[$idx] ?? '';
    }

    private static function u16(string $bin, int $off): int
    {
        if (strlen($bin) < $off + 2) {
            return 0;
        }
        $v = unpack('v', substr($bin, $off, 2));

        return is_array($v) ? (int) $v[1] : 0;
    }

    private static function u24(string $bin, int $off): int
    {
        if (strlen($bin) < $off + 3) {
            return 0;
        }

        return ord($bin[$off]) | (ord($bin[$off + 1]) << 8) | (ord($bin[$off + 2]) << 16);
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
