<?php

declare(strict_types=1);

namespace App\Domain\Procedure;

/**
 * Supported procedure step types with structured payloads.
 */
enum StepType: string
{
    case InstallCommand = 'install_command';
    case UninstallCommand = 'uninstall_command';
    case CopyFile = 'copy_file';
    case DeleteFile = 'delete_file';
    case Rename = 'rename';
    case CreateShortcut = 'create_shortcut';
    case Registry = 'registry';
    case Powershell = 'powershell';
    case Note = 'note';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t) => $t->value, self::cases());
    }

    public function label(): string
    {
        return __('step.' . $this->value);
    }

    /**
     * Field definitions for UI forms and validation.
     *
     * @return list<array{name: string, label: string, type: string, required: bool, options?: list<string>}>
     */
    public function fields(): array
    {
        $L = static fn (string $name): string => __('step.' . $name);

        return match ($this) {
            self::InstallCommand => [
                ['name' => 'command', 'label' => $L('command'), 'type' => 'text', 'required' => true],
                ['name' => 'silent_args', 'label' => $L('silent_args'), 'type' => 'text', 'required' => false],
                ['name' => 'working_dir', 'label' => $L('working_dir'), 'type' => 'text', 'required' => false],
                ['name' => 'timeout_sec', 'label' => $L('timeout_sec'), 'type' => 'number', 'required' => false],
                ['name' => 'success_paths', 'label' => $L('success_paths'), 'type' => 'textarea', 'required' => false],
            ],
            self::UninstallCommand => [
                ['name' => 'command', 'label' => $L('command'), 'type' => 'text', 'required' => true],
                ['name' => 'silent_args', 'label' => $L('silent_args'), 'type' => 'text', 'required' => false],
                ['name' => 'product_code', 'label' => $L('product_code'), 'type' => 'text', 'required' => false],
                ['name' => 'timeout_sec', 'label' => $L('timeout_sec'), 'type' => 'number', 'required' => false],
                ['name' => 'pre_kill', 'label' => $L('pre_kill'), 'type' => 'textarea', 'required' => false],
                ['name' => 'success_gone_paths', 'label' => $L('success_gone_paths'), 'type' => 'textarea', 'required' => false],
            ],
            self::CopyFile => [
                ['name' => 'source', 'label' => $L('source'), 'type' => 'text', 'required' => true],
                ['name' => 'destination', 'label' => $L('destination'), 'type' => 'text', 'required' => true],
                ['name' => 'overwrite', 'label' => $L('overwrite'), 'type' => 'checkbox', 'required' => false],
            ],
            self::DeleteFile => [
                ['name' => 'path', 'label' => $L('path'), 'type' => 'text', 'required' => true],
                ['name' => 'recursive', 'label' => $L('recursive'), 'type' => 'checkbox', 'required' => false],
            ],
            self::Rename => [
                ['name' => 'from_path', 'label' => $L('from_path'), 'type' => 'text', 'required' => true],
                ['name' => 'to_path', 'label' => $L('to_path'), 'type' => 'text', 'required' => true],
            ],
            self::CreateShortcut => [
                ['name' => 'target', 'label' => $L('target'), 'type' => 'text', 'required' => true],
                ['name' => 'link_path', 'label' => $L('link_path'), 'type' => 'text', 'required' => true],
                ['name' => 'arguments', 'label' => $L('arguments'), 'type' => 'text', 'required' => false],
                ['name' => 'icon', 'label' => $L('icon'), 'type' => 'text', 'required' => false],
            ],
            self::Registry => [
                ['name' => 'hive', 'label' => $L('hive'), 'type' => 'select', 'required' => true, 'options' => ['HKLM', 'HKCU', 'HKCR', 'HKU']],
                ['name' => 'key', 'label' => $L('key'), 'type' => 'text', 'required' => true],
                ['name' => 'value_name', 'label' => $L('value_name'), 'type' => 'text', 'required' => false],
                ['name' => 'value_type', 'label' => $L('value_type'), 'type' => 'select', 'required' => false, 'options' => ['REG_SZ', 'REG_DWORD', 'REG_EXPAND_SZ', 'REG_MULTI_SZ', 'REG_BINARY']],
                ['name' => 'value_data', 'label' => $L('value_data'), 'type' => 'text', 'required' => false],
                ['name' => 'action', 'label' => $L('action'), 'type' => 'select', 'required' => true, 'options' => ['set', 'delete']],
            ],
            self::Powershell => [
                ['name' => 'script_body', 'label' => $L('script_body'), 'type' => 'textarea', 'required' => false],
                ['name' => 'script_path', 'label' => $L('script_path'), 'type' => 'text', 'required' => false],
                ['name' => 'run_as', 'label' => $L('run_as'), 'type' => 'select', 'required' => false, 'options' => ['system', 'user', 'admin']],
            ],
            self::Note => [
                ['name' => 'text', 'label' => $L('text'), 'type' => 'textarea', 'required' => true],
            ],
        };
    }
}
