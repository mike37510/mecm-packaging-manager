<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ChangelogService;
use PHPUnit\Framework\TestCase;

final class ChangelogServiceTest extends TestCase
{
    public function testParsesVersionSections(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cl');
        file_put_contents($tmp, <<<'MD'
# Changelog

Intro text.

## [Unreleased]

### Planned
- Foo

## [1.0.0] - 2026-08-07

### Added
- Bar
MD);

        $service = new ChangelogService($tmp, '1.0.0');
        $doc = $service->getDocument();

        $this->assertSame('1.0.0', $doc['version']);
        $this->assertCount(2, $doc['releases']);
        $this->assertSame('Unreleased', $doc['releases'][0]['version']);
        $this->assertSame('1.0.0', $doc['releases'][1]['version']);
        $this->assertSame('2026-08-07', $doc['releases'][1]['date']);
        $this->assertStringContainsString('### Added', $doc['releases'][1]['markdown']);

        @unlink($tmp);
    }
}
