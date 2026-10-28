<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PeVersionReader;
use PHPUnit\Framework\TestCase;

final class PeVersionReaderTest extends TestCase
{
    public function testReadsUtf16ProductVersionFromEvergreenExeStub(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pever');
        $blob = 'MZ' . str_repeat("\0", 62);
        foreach (str_split('ProductVersion') as $c) {
            $blob .= $c . "\0";
        }
        $blob .= "\0\0";
        foreach (str_split('9.4.0.129') as $c) {
            $blob .= $c . "\0";
        }
        $blob .= "\0\0";
        file_put_contents($tmp, $blob);

        $this->assertSame('9.4.0.129', PeVersionReader::productVersion($tmp));
        @unlink($tmp);
    }

    public function testReadsOnlyOfficeHeaderFixtureWhenPresent(): void
    {
        $path = '/tmp/onlyoffice-head.exe';
        if (!is_readable($path)) {
            $this->markTestSkipped('OnlyOffice EXE header fixture not present');
        }

        $this->assertSame('9.4.0.129', PeVersionReader::productVersion($path));
    }
}
