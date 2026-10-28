<?php

use App\Support\View;

/**
 * @var string $optionsJsV
 * @var string $helpJsV
 * @var string $editorJsV
 * @var list<array<string, mixed>> $dellVersions
 * @var bool $canWrite
 * @var \App\Security\Csrf $csrf
 */
$versionsBack = '/bios/dell/cfg-editor';
$versions = $dellVersions ?? [];
$downloadBase = '/bios/dell/cfg-versions';
$i18nPrefix = 'dell';

$backHref = '/bios/dell';
$title = __('bios.dell_editor_title');
$lead = __('bios.dell_editor_lead');
$noteLabel = __('bios.dell_save_note');
$notePlaceholder = __('bios.dell_save_note_ph');
$saveLabel = __('bios.dell_editor_save');
$savingLabel = __('bios.dell_saving');
$needSelection = __('bios.dell_save_empty');
$saveUrl = '/bios/dell/cfg-versions';
$readonlyRoleMsg = __('bios.dell_save_readonly_role');
$filterLabel = __('bios.dell_editor_filter');
$filterPlaceholder = __('bios.dell_editor_filter_ph');
$loadingLabel = __('bios.dell_editor_loading');
$helpClose = __('bios.dell_editor_help_close');
$optionsScript = '/assets/js/bios/dell-cctk-options.js';
$helpScript = '/assets/js/bios/dell-cctk-help.js';
$editorScript = '/assets/js/bios/dell-cctk-editor.js';
$editorI18n = [
    'defaultDesc' => __('bios.dell_editor_default_desc'),
    'valueLabel' => __('bios.dell_editor_value'),
    'valuePlaceholder' => __('bios.dell_editor_value_ph'),
    'stats' => __('bios.dell_editor_stats'),
    'errMissing' => __('bios.dell_editor_err_missing'),
    'cfgSectionTitle' => __('bios.dell_editor_cfg_section_title'),
    'cfgSectionLead' => __('bios.dell_editor_cfg_section_lead'),
    'helpBtn' => __('bios.dell_editor_help_btn'),
    'helpValues' => __('bios.dell_editor_help_values'),
    'helpClose' => __('bios.dell_editor_help_close'),
    'redirect' => '/bios/dell/cfg-editor',
    'format' => 'cctk',
];

require __DIR__ . '/partials/cfg_editor.php';
