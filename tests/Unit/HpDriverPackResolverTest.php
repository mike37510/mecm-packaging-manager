<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\HpDriverPackResolver;
use PHPUnit\Framework\TestCase;

final class HpDriverPackResolverTest extends TestCase
{
    private function resolver(): HpDriverPackResolver
    {
        return new HpDriverPackResolver();
    }

    private function fixtureHtml(): string
    {
        $path = dirname(__DIR__) . '/fixtures/hp-driverpack-matrix-sample.html';
        $html = file_get_contents($path);
        $this->assertNotFalse($html);

        return $html;
    }

    public function testDetectsMatrixAndSoftpaqUrls(): void
    {
        $this->assertTrue(HpDriverPackResolver::isCatalogUrl(
            'https://ftp.hp.com/pub/caps-softpaq/cmit/HP_Driverpack_Matrix_x64.html'
        ));
        $this->assertTrue(HpDriverPackResolver::isCatalogUrl(
            'https://ftp.hp.com/pub/caps-softpaq/cmit/HP_Driverpack_Matrix_x86.html?os=win11-24h2'
        ));
        $this->assertFalse(HpDriverPackResolver::isCatalogUrl(
            'https://ftp.hp.com/pub/softpaq/sp173001-173500/sp173107.exe'
        ));
        $this->assertTrue(HpDriverPackResolver::isDirectPackUrl(
            'https://ftp.hp.com/pub/softpaq/sp173001-173500/sp173107.exe'
        ));
        $this->assertFalse(HpDriverPackResolver::isCatalogUrl('https://www.dell.com/support/kbdoc/en-us/000223166'));
    }

    public function testOsSlug(): void
    {
        $r = $this->resolver();
        $this->assertSame('win11-25h2', $r->osSlug('Windows 11 64-bit, 25H2'));
        $this->assertSame('win11-24h2', $r->osSlug('Windows 11 64-bit, 24H2'));
        $this->assertSame('win11-iot-24h2-ltsc', $r->osSlug('Windows 11 IoT Enterprise 24H2 LTSC, 64-bit'));
        $this->assertSame('win10-22h2', $r->osSlug('Windows 10 64-bit, 22H2'));
        $this->assertSame('win10-iot-2021-ltsc', $r->osSlug('Windows 10 IoT Enterprise 2021 LTSC, 64-bit'));
    }

    public function testParses840G8Win11_24H2(): void
    {
        $picked = $this->resolver()->resolveFromHtml($this->fixtureHtml(), 'EliteBook 840 G8', 'win11-24h2');
        $this->assertTrue($picked['ok'] ?? false, (string) ($picked['error'] ?? 'no error'));
        $this->assertSame('sp142670.exe', $picked['file_name']);
        $this->assertSame('SP142670', $picked['softpaq']);
        $this->assertSame('3.00 A 1', $picked['pack_version']);
        $this->assertSame('3.00 A 1 (SP142670)', $picked['version']);
        $this->assertSame('win11-24h2', $picked['os_slug']);
        $this->assertStringContainsString('sp142670.exe', (string) $picked['download_url']);
        $this->assertStringContainsString('840 G8', (string) $picked['model']);
    }

    public function testAutoPicksNewestAvailableOs(): void
    {
        $picked = $this->resolver()->resolveFromHtml($this->fixtureHtml(), 'HP EliteBook 840 G8 Notebook PC', '');
        $this->assertTrue($picked['ok'] ?? false, (string) ($picked['error'] ?? 'no error'));
        $this->assertSame('win11-24h2', $picked['os_slug']);
        $this->assertSame('SP142670', $picked['softpaq']);
    }

    public function testAutoSkipsWindows10Columns(): void
    {
        $html = <<<'HTML'
<div id="tableDiv"><table>
<tr><th></th><th>Windows 10 64-bit, 22H2</th><th>Windows 11 64-bit, 24H2</th></tr>
<tr><td><p>HP EliteBook 840 G8 Notebook PC</p></td>
<td><a href="https://ftp.hp.com/pub/softpaq/sp144501-145000/sp144988.exe" title="Version: 8.00 A 1 Release Date: 02/24/2023">sp144988.exe</a></td>
<td><a href="https://ftp.hp.com/pub/softpaq/sp142501-143000/sp142670.exe" title="Version: 3.00 A 1 Release Date: 09/27/2022">sp142670.exe</a></td>
</tr>
</table></div>
HTML;
        $picked = $this->resolver()->resolveFromHtml($html, 'EliteBook 840 G8', '');
        $this->assertTrue($picked['ok'] ?? false, (string) ($picked['error'] ?? 'no error'));
        $this->assertSame('win11-24h2', $picked['os_slug']);
        $this->assertSame('SP142670', $picked['softpaq']);
    }

    public function testG2aFamilyOn25H2(): void
    {
        $picked = $this->resolver()->resolveFromHtml($this->fixtureHtml(), 'EliteBook 6 G2a', 'win11-25h2');
        $this->assertTrue($picked['ok'] ?? false, (string) ($picked['error'] ?? 'no error'));
        $this->assertSame('SP173107', $picked['softpaq']);
        $this->assertSame('2.00 A 1 (SP173107)', $picked['version']);
        $this->assertSame('05/15/2026', $picked['release_date']);
    }

    public function testAmbiguousFamiliesNeedFullName(): void
    {
        $html = <<<'HTML'
<div id="tableDiv"><table>
<tr><th></th><th>Windows 11 64-bit, 25H2</th></tr>
<tr><td><p>HP EliteBook 6 G2a 14 inch Notebook AI PC</p></td>
<td><a href="https://ftp.hp.com/pub/softpaq/sp173001-173500/sp173107.exe" title="Version: 2.00 A 1 Release Date: 05/15/2026">sp173107.exe</a></td></tr>
<tr><td><p>HP EliteBook 6 G2a 14 inch Notebook Next Gen AI PC</p></td>
<td><a href="https://ftp.hp.com/pub/softpaq/sp173001-173500/sp173464.exe" title="Version: 2.00 A 1 Release Date: 06/03/2026">sp173464.exe</a></td></tr>
<tr><td><p>HP EliteBook 8 G2a 16 inch Notebook Next Gen AI PC</p></td>
<td><a href="https://ftp.hp.com/pub/softpaq/sp173501-174000/sp173804.exe" title="Version: 1.00 A 1 Release Date: 06/01/2026">sp173804.exe</a></td></tr>
</table></div>
HTML;
        $ambiguous = $this->resolver()->resolveFromHtml($html, 'EliteBook 6 G2a', '');
        $this->assertFalse($ambiguous['ok'] ?? true);
        $this->assertNotSame('', (string) ($ambiguous['error'] ?? ''));

        $picked = $this->resolver()->resolveFromHtml($html, 'EliteBook 6 G2a Next Gen', '');
        $this->assertTrue($picked['ok'] ?? false, (string) ($picked['error'] ?? 'no error'));
        $this->assertSame('SP173464', $picked['softpaq']);
    }

    public function testMissingOsColumnFailsWithHint(): void
    {
        $picked = $this->resolver()->resolveFromHtml($this->fixtureHtml(), 'EliteBook 840 G8', 'win11-25h2');
        $this->assertFalse($picked['ok'] ?? true);
        $this->assertArrayHasKey('error', $picked);
        $this->assertNotSame('', (string) $picked['error']);
    }

    public function testUnknownModelFails(): void
    {
        $picked = $this->resolver()->resolveFromHtml($this->fixtureHtml(), 'ThinkPad T14', '');
        $this->assertFalse($picked['ok'] ?? true);
    }
}
