<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SccmIconNormalizer;
use PHPUnit\Framework\TestCase;

final class SccmIconNormalizerTest extends TestCase
{
    public function testNormalizesNonSquareLogoToTransparent512Png(): void
    {
        if (!is_executable('/usr/bin/magick') && trim((string) shell_exec('command -v magick')) === '') {
            $this->markTestSkipped('ImageMagick magick binary not available');
        }

        $dir = sys_get_temp_dir() . '/sccm-icon-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $src = $dir . '/logo.png';
        $dst = $dir . '/out.png';

        exec(sprintf(
            'magick -size 320x180 xc:#2266aa %s 2>&1',
            escapeshellarg($src)
        ), $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));

        $result = (new SccmIconNormalizer())->normalizeToPng($src, $dst);
        $this->assertTrue($result['ok'], (string) $result['error']);
        $this->assertFileExists($dst);

        $info = getimagesize($dst);
        $this->assertNotFalse($info);
        $this->assertSame(512, $info[0]);
        $this->assertSame(512, $info[1]);
        $this->assertSame('image/png', $info['mime']);

        @unlink($src);
        @unlink($dst);
        @rmdir($dir);
    }

    public function testShrinksOversizedLogoWithoutCroppingCanvas(): void
    {
        if (!is_executable('/usr/bin/magick') && trim((string) shell_exec('command -v magick')) === '') {
            $this->markTestSkipped('ImageMagick magick binary not available');
        }

        $dir = sys_get_temp_dir() . '/sccm-icon-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $src = $dir . '/big.png';
        $dst = $dir . '/out.png';

        exec(sprintf('magick -size 900x400 xc:red %s', escapeshellarg($src)), $out, $code);
        $this->assertSame(0, $code);

        $result = (new SccmIconNormalizer())->normalizeToPng($src, $dst);
        $this->assertTrue($result['ok'], (string) $result['error']);

        $info = getimagesize($dst);
        $this->assertSame(512, $info[0]);
        $this->assertSame(512, $info[1]);

        @unlink($src);
        @unlink($dst);
        @rmdir($dir);
    }
}
