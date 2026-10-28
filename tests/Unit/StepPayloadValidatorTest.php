<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Procedure\StepPayloadValidator;
use App\Domain\Procedure\StepType;
use PHPUnit\Framework\TestCase;

final class StepPayloadValidatorTest extends TestCase
{
    private StepPayloadValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new StepPayloadValidator();
    }

    public function testInstallCommandRequiresCommand(): void
    {
        $result = $this->validator->validate(StepType::InstallCommand, [
            'silent_args' => '/S',
        ]);

        self::assertFalse($result['ok']);
        self::assertNotEmpty($result['errors']);
    }

    public function testInstallCommandValidPayload(): void
    {
        $result = $this->validator->validate(StepType::InstallCommand, [
            'command' => 'setup.exe',
            'silent_args' => '/quiet',
            'working_dir' => 'C:\\Temp',
            'timeout_sec' => '600',
        ]);

        self::assertTrue($result['ok']);
        self::assertSame('setup.exe', $result['payload']['command']);
        self::assertSame('/quiet', $result['payload']['silent_args']);
        self::assertSame(600, $result['payload']['timeout_sec']);
    }

    public function testUninstallCommandStripsWrappingQuotes(): void
    {
        $result = $this->validator->validate(StepType::UninstallCommand, [
            'command' => '"%ProgramFiles%\\Microsoft VS Code\\unins000.exe"',
            'silent_args' => '/VERYSILENT /NORESTART',
            'timeout_sec' => '600',
        ]);

        self::assertTrue($result['ok']);
        self::assertSame('%ProgramFiles%\\Microsoft VS Code\\unins000.exe', $result['payload']['command']);
    }

    public function testPowershellRequiresBodyOrPath(): void
    {
        $result = $this->validator->validate(StepType::Powershell, [
            'run_as' => 'system',
        ]);

        self::assertFalse($result['ok']);
    }

    public function testRegistryValidatesOptions(): void
    {
        $result = $this->validator->validate(StepType::Registry, [
            'hive' => 'INVALID',
            'key' => 'Software\\Foo',
            'action' => 'set',
        ]);

        self::assertFalse($result['ok']);
    }

    public function testCopyFileCheckbox(): void
    {
        $result = $this->validator->validate(StepType::CopyFile, [
            'source' => 'a.txt',
            'destination' => 'b.txt',
            'overwrite' => '1',
        ]);

        self::assertTrue($result['ok']);
        self::assertTrue($result['payload']['overwrite']);
    }
}
