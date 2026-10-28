<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Software;
use App\Services\GithubReleaseDetector;
use PHPUnit\Framework\TestCase;

final class GithubReleaseDetectorTest extends TestCase
{
    public function testParsesGithubUrls(): void
    {
        $d = new GithubReleaseDetector();
        $this->assertSame(['ip7z', '7zip'], $d->parseGithubRepo('https://github.com/ip7z/7zip/releases/'));
        $this->assertSame(['ip7z', '7zip'], $d->parseGithubRepo('https://github.com/ip7z/7zip'));
        $this->assertSame(['ip7z', '7zip'], $d->parseGithubRepo('ip7z/7zip'));
        $this->assertSame(['ip7z', '7zip'], $d->parseGithubRepo('https://api.github.com/repos/ip7z/7zip/releases/latest'));
        $this->assertNull($d->parseGithubRepo('https://www.7-zip.org/'));
    }

    public function testPicksX64MsiFor7zip(): void
    {
        $software = new Software(
            id: 1,
            name: '7-Zip',
            publisher: 'Igor Pavlov',
            category: 'Compression',
            description: null,
            iconPath: null,
            currentVersion: '26.00',
            lastPackagedAt: null,
            updateFrequencyDays: 30,
            downloadUrl: null,
            versionCheckUrl: 'https://github.com/ip7z/7zip/releases/',
            detectionMethod: 'github',
            architecture: 'x64',
            installerType: 'msi',
            createdAt: '2026-01-01 00:00:00',
            updatedAt: '2026-01-01 00:00:00',
        );

        $assets = [
            ['name' => '7z2602-arm.exe', 'browser_download_url' => 'https://example/arm.exe'],
            ['name' => '7z2602-linux-x64.tar.xz', 'browser_download_url' => 'https://example/linux.tar.xz'],
            ['name' => '7z2602-src.7z', 'browser_download_url' => 'https://example/src.7z'],
            ['name' => '7z2602.exe', 'browser_download_url' => 'https://example/7z.exe'],
            ['name' => '7z2602-x64.exe', 'browser_download_url' => 'https://example/x64.exe'],
            ['name' => '7z2602-x64.msi', 'browser_download_url' => 'https://example/x64.msi'],
            ['name' => '7z2602.msi', 'browser_download_url' => 'https://example/plain.msi'],
        ];

        $picked = (new GithubReleaseDetector())->pickAsset($assets, $software);
        $this->assertNotNull($picked);
        $this->assertSame('7z2602-x64.msi', $picked['name']);
    }

    public function testPrefersMsiEvenWhenSoftwareMarkedExe(): void
    {
        $software = new Software(
            id: 1,
            name: 'Draw.io',
            publisher: 'JGraph',
            category: 'Diagrammes',
            description: null,
            iconPath: null,
            currentVersion: '29.0.3',
            lastPackagedAt: null,
            updateFrequencyDays: 30,
            downloadUrl: null,
            versionCheckUrl: 'https://github.com/jgraph/drawio-desktop/releases',
            detectionMethod: 'github',
            architecture: 'x64',
            installerType: 'exe',
        );

        $assets = [
            ['name' => 'draw.io-31.1.8-windows-installer.exe', 'browser_download_url' => 'https://example/app.exe'],
            ['name' => 'draw.io-31.1.8.msi', 'browser_download_url' => 'https://example/app.msi'],
        ];

        $picked = (new GithubReleaseDetector())->pickAsset($assets, $software);
        $this->assertNotNull($picked);
        $this->assertSame('draw.io-31.1.8.msi', $picked['name']);
    }

    public function testPrefersBraveStandaloneSetupOverSilentAndStub(): void
    {
        $software = new Software(
            id: 31,
            name: 'Brave Browser',
            publisher: 'Brave Software',
            category: 'Navigateur',
            description: null,
            iconPath: null,
            currentVersion: '1.93.134',
            lastPackagedAt: null,
            updateFrequencyDays: 30,
            downloadUrl: null,
            versionCheckUrl: 'https://github.com/brave/brave-browser/releases',
            detectionMethod: 'github',
            architecture: 'x64',
            installerType: 'exe',
        );

        $assets = [
            ['name' => 'BraveBrowserSetup.exe', 'browser_download_url' => 'https://example/stub.exe', 'size' => 1_200_000],
            ['name' => 'BraveBrowserStandaloneSilentSetup.exe', 'browser_download_url' => 'https://example/silent.exe', 'size' => 159_000_000],
            ['name' => 'BraveBrowserStandaloneSetup.exe', 'browser_download_url' => 'https://example/full.exe', 'size' => 159_000_000],
            ['name' => 'BraveBrowserStandaloneSetup32.exe', 'browser_download_url' => 'https://example/x86.exe', 'size' => 144_000_000],
        ];

        $picked = (new GithubReleaseDetector())->pickAsset($assets, $software);
        $this->assertNotNull($picked);
        $this->assertSame('BraveBrowserStandaloneSetup.exe', $picked['name']);
    }

    public function testNormalizeVersion(): void
    {
        $d = new GithubReleaseDetector();
        $this->assertSame('26.02', $d->normalizeVersion('26.02'));
        $this->assertSame('1.90.128', $d->normalizeVersion('v1.90.128'));
    }
}
