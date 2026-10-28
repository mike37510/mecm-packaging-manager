<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lecteur OLE Compound File (CFB) — format des .msi.
 *
 * Ne charge pas le fichier entier : secteurs et flux à la demande (Chrome ~160 Mo).
 */
final class OleCompoundFile
{
    private const SIG = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    private const ENDOFCHAIN = 0xFFFFFFFE;
    private const MAX_SPECIAL = 0xFFFFFFFA;

    /** @var resource */
    private $fh;

    private int $sectorSize;

    private int $miniSectorSize;

    private int $miniCutoff;

    private int $majorVersion;

    /** @var list<int> */
    private array $fat = [];

    /** @var list<int> */
    private array $miniFat = [];

    private string $miniStream = '';

    /**
     * @var list<array{name: string, type: int, start: int, size: int}>
     */
    private array $entries = [];

    public function __construct(string $path)
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Impossible d’ouvrir le fichier OLE.');
        }
        $this->fh = $fh;

        try {
            $this->parseHeaderAndDirectory();
        } catch (\Throwable $e) {
            $this->close();
            throw $e;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function parseHeaderAndDirectory(): void
    {
        $header = $this->readBytes(0, 512);
        if (!str_starts_with($header, self::SIG)) {
            throw new \RuntimeException('Signature OLE absente.');
        }

        $this->majorVersion = $this->u16($header, 0x1A);
        $sectorShift = $this->u16($header, 0x1E);
        $miniShift = $this->u16($header, 0x20);
        if ($sectorShift < 9 || $sectorShift > 16 || $miniShift < 2 || $miniShift > 12) {
            throw new \RuntimeException('En-tête OLE invalide.');
        }
        $this->sectorSize = 1 << $sectorShift;
        $this->miniSectorSize = 1 << $miniShift;
        $this->miniCutoff = $this->u32($header, 0x38);
        $dirStart = $this->u32($header, 0x30);
        $miniFatStart = $this->u32($header, 0x3C);
        $firstDifat = $this->u32($header, 0x44);
        $difatCount = $this->u32($header, 0x48);

        $difat = [];
        for ($i = 0; $i < 109; $i++) {
            $s = $this->u32($header, 0x4C + $i * 4);
            if ($s < self::MAX_SPECIAL) {
                $difat[] = $s;
            }
        }

        $difatSect = $firstDifat;
        $guard = 0;
        while ($difatSect < self::MAX_SPECIAL && $guard < $difatCount + 2 && $guard++ < 10_000) {
            $sec = $this->readSector($difatSect);
            $n = intdiv($this->sectorSize, 4);
            for ($i = 0; $i < $n - 1; $i++) {
                $s = $this->u32($sec, $i * 4);
                if ($s < self::MAX_SPECIAL) {
                    $difat[] = $s;
                }
            }
            $difatSect = $this->u32($sec, ($n - 1) * 4);
        }

        foreach ($difat as $fatSect) {
            $sec = $this->readSector($fatSect);
            $n = intdiv($this->sectorSize, 4);
            for ($i = 0; $i < $n; $i++) {
                $this->fat[] = $this->u32($sec, $i * 4);
            }
        }

        $dirData = $this->readFatChain($dirStart, 8_000_000);
        $count = intdiv(strlen($dirData), 128);
        for ($i = 0; $i < $count; $i++) {
            $raw = substr($dirData, $i * 128, 128);
            $type = ord($raw[0x42]);
            if ($type === 0) {
                continue;
            }
            $nameBytes = $this->u16($raw, 0x40);
            $this->entries[] = [
                'name' => $this->utf16leName(substr($raw, 0, 64), $nameBytes),
                'type' => $type,
                'start' => $this->u32($raw, 0x74),
                'size' => $this->majorVersion >= 4
                    ? $this->u32($raw, 0x78) + ($this->u32($raw, 0x7C) << 32)
                    : $this->u32($raw, 0x78),
            ];
        }

        $root = $this->entries[0] ?? null;
        if ($root !== null && $root['type'] === 5 && $root['size'] > 0) {
            $this->miniStream = $this->readFatChain($root['start'], min($root['size'], 32_000_000));
            if (strlen($this->miniStream) > $root['size']) {
                $this->miniStream = substr($this->miniStream, 0, $root['size']);
            }
        }

        if ($miniFatStart < self::MAX_SPECIAL) {
            $miniFatData = $this->readFatChain($miniFatStart, 4_000_000);
            $n = intdiv(strlen($miniFatData), 4);
            for ($i = 0; $i < $n; $i++) {
                $this->miniFat[] = $this->u32($miniFatData, $i * 4);
            }
        }
    }

    public function close(): void
    {
        if (is_resource($this->fh)) {
            fclose($this->fh);
        }
    }

    /**
     * @return array<string, string> nom MSI décodé → contenu du flux
     */
    public function readMsiStreams(array $logicalNames): array
    {
        $want = [];
        foreach ($logicalNames as $n) {
            $want[$n] = true;
        }
        $out = [];
        foreach ($this->entries as $entry) {
            if ($entry['type'] !== 2) {
                continue;
            }
            $decoded = MsiMetadataReader::decodeStreamName($entry['name']);
            if (!isset($want[$decoded])) {
                continue;
            }
            $out[$decoded] = $this->readEntry($entry);
        }

        return $out;
    }

    /**
     * @param array{name: string, type: int, start: int, size: int} $entry
     */
    private function readEntry(array $entry): string
    {
        $size = $entry['size'];
        if ($size <= 0) {
            return '';
        }
        if ($size < $this->miniCutoff && $this->miniStream !== '') {
            return $this->readMiniChain($entry['start'], $size);
        }

        $data = $this->readFatChain($entry['start'], min($size, 32_000_000));

        return strlen($data) > $size ? substr($data, 0, $size) : $data;
    }

    private function readFatChain(int $start, int $maxBytes): string
    {
        $out = '';
        $sect = $start;
        $guard = 0;
        $maxSectors = intdiv($maxBytes, $this->sectorSize) + 8;
        while ($sect < self::MAX_SPECIAL && strlen($out) < $maxBytes && $guard++ < $maxSectors) {
            $out .= $this->readSector($sect);
            $sect = $this->fat[$sect] ?? self::ENDOFCHAIN;
        }

        return $out;
    }

    private function readMiniChain(int $start, int $size): string
    {
        $out = '';
        $sect = $start;
        $guard = 0;
        $maxSectors = intdiv($size, $this->miniSectorSize) + 8;
        while ($sect < self::MAX_SPECIAL && strlen($out) < $size && $guard++ < $maxSectors) {
            $off = $sect * $this->miniSectorSize;
            $out .= substr($this->miniStream, $off, $this->miniSectorSize);
            $sect = $this->miniFat[$sect] ?? self::ENDOFCHAIN;
        }

        return strlen($out) > $size ? substr($out, 0, $size) : $out;
    }

    private function readSector(int $index): string
    {
        return $this->readBytes(($index + 1) * $this->sectorSize, $this->sectorSize);
    }

    private function readBytes(int $offset, int $length): string
    {
        if (fseek($this->fh, $offset) !== 0) {
            throw new \RuntimeException('Lecture OLE hors limites.');
        }
        $data = fread($this->fh, $length);
        if (!is_string($data)) {
            throw new \RuntimeException('Lecture OLE échouée.');
        }

        return str_pad($data, $length, "\0");
    }

    private function utf16leName(string $raw, int $nameBytes): string
    {
        $len = min($nameBytes, strlen($raw));
        if ($len >= 2) {
            $len -= 2;
        }
        $out = '';
        for ($i = 0; $i + 1 < $len; $i += 2) {
            $cp = $this->u16($raw, $i);
            if ($cp === 0) {
                break;
            }
            $out .= mb_chr($cp, 'UTF-8');
        }

        return $out;
    }

    private function u16(string $bin, int $off): int
    {
        if (strlen($bin) < $off + 2) {
            return 0;
        }
        $v = unpack('v', substr($bin, $off, 2));

        return is_array($v) ? (int) $v[1] : 0;
    }

    private function u32(string $bin, int $off): int
    {
        if (strlen($bin) < $off + 4) {
            return 0;
        }
        $v = unpack('V', substr($bin, $off, 4));

        return is_array($v) ? (int) $v[1] : 0;
    }
}
