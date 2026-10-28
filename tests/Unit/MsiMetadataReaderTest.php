<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AuditLogRepository;
use App\Repositories\SoftwareRepository;
use App\Repositories\SyncRepository;
use App\Support\JsonStore;
use App\Support\MsiMetadataReader;
use App\Services\SyncService;
use PHPUnit\Framework\TestCase;

final class MsiMetadataReaderTest extends TestCase
{
    public function testChromeEvergreenFilenameHasNoVersion(): void
    {
        $dir = sys_get_temp_dir() . '/msi-sync-' . bin2hex(random_bytes(4));
        $store = new JsonStore($dir);
        $softwares = new SoftwareRepository($store);
        $sync = new SyncService(
            $softwares,
            new SyncRepository($store, $softwares),
            new AuditLogRepository($store),
            $dir,
        );

        $this->assertNull($sync->guessVersionFromName('googlechromestandaloneenterprise64.msi'));
        $this->assertNull($sync->guessVersionFromName(
            'https://dl.google.com/dl/chrome/install/googlechromestandaloneenterprise64.msi'
        ));
        $this->assertNull($sync->guessVersionFromName('DesktopEditors_x64.exe'));
        $this->assertNull($sync->guessVersionFromName(
            'https://download.onlyoffice.com/install/desktop/editors/windows/distrib/onlyoffice/DesktopEditors_x64.exe'
        ));
        $this->assertTrue($sync->isPlaceholderVersion('unknown'));
    }

    public function testDecodesMsiStreamNames(): void
    {
        $encoded = mb_chr(0x4840, 'UTF-8') . self::encodeMsiChars('Property');
        $this->assertSame('Table.Property', MsiMetadataReader::decodeStreamName($encoded));

        $encodedData = mb_chr(0x4840, 'UTF-8') . self::encodeMsiChars('_StringData');
        $this->assertSame('Table._StringData', MsiMetadataReader::decodeStreamName($encodedData));
    }

    public function testDecodesPropertyTableFromStringPool(): void
    {
        $strings = ['ProductVersion', '142.0.7444.176', 'ProductCode', '{ABC}'];
        $data = implode('', $strings);
        $pool = pack('v', 1252) . pack('v', 0);
        foreach ($strings as $s) {
            $pool .= pack('v', strlen($s)) . pack('v', 1);
        }

        $decoded = MsiMetadataReader::decodeStringPool($data, $pool);
        $this->assertSame($strings, $decoded);

        // colonne-major : ids 1,3 puis 2,4
        $property = pack('v*', 1, 3, 2, 4);
        $props = MsiMetadataReader::decodePropertyTable($property, $decoded, 2);
        $this->assertSame('142.0.7444.176', $props['ProductVersion']);
        $this->assertSame('{ABC}', $props['ProductCode']);
    }

    public function testProductVersionReturnsNullForNonMsi(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'notmsi');
        file_put_contents($tmp, 'MZ not an msi');
        $this->assertNull(MsiMetadataReader::productVersion($tmp));
        @unlink($tmp);
    }

    private static function encodeMsiChars(string $ascii): string
    {
        $out = '';
        $len = strlen($ascii);
        $i = 0;
        while ($i < $len) {
            $a = self::encodeRune(ord($ascii[$i]));
            if ($i + 1 < $len) {
                $b = self::encodeRune(ord($ascii[$i + 1]));
                $out .= mb_chr(0x3800 + $a + ($b << 6), 'UTF-8');
                $i += 2;
            } else {
                $out .= mb_chr(0x4800 + $a, 'UTF-8');
                $i++;
            }
        }

        return $out;
    }

    private static function encodeRune(int $ch): int
    {
        if ($ch >= 48 && $ch <= 57) {
            return $ch - 48;
        }
        if ($ch >= 65 && $ch <= 90) {
            return $ch - 65 + 10;
        }
        if ($ch >= 97 && $ch <= 122) {
            return $ch - 97 + 36;
        }
        if ($ch === 46) {
            return 62;
        }

        return 63;
    }
}
