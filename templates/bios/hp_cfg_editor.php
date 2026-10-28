<?php

use App\Support\View;

/**
 * @var string $optionsJsV
 * @var string $helpJsV
 * @var string $editorJsV
 * @var list<array<string, mixed>> $hpVersions
 * @var bool $canWrite
 * @var \App\Security\Csrf $csrf
 */
$versionsBack = '/bios/hp/cfg-editor';
$versions = $hpVersions ?? [];
$downloadBase = '/bios/hp/cfg-versions';
$i18nPrefix = 'hp';

$backHref = '/bios/hp';
$title = __('bios.hp_editor_title');
$lead = __('bios.hp_editor_lead');
$noteLabel = __('bios.hp_save_note');
$notePlaceholder = __('bios.hp_save_note_ph');
$saveLabel = __('bios.hp_editor_save');
$savingLabel = __('bios.hp_saving');
$needSelection = __('bios.hp_save_empty');
$saveUrl = '/bios/hp/cfg-versions';
$readonlyRoleMsg = __('bios.hp_save_readonly_role');
$filterLabel = __('bios.hp_editor_filter');
$filterPlaceholder = __('bios.hp_editor_filter_ph');
$loadingLabel = __('bios.hp_editor_loading');
$helpClose = __('bios.hp_editor_help_close');
$optionsScript = '/assets/js/bios/hp-bcu-options.js';
$helpScript = '/assets/js/bios/hp-bcu-help.js';
$editorScript = '/assets/js/bios/hp-bcu-editor.js';
$editorI18n = [
    'defaultDesc' => __('bios.hp_editor_default_desc'),
    'valueLabel' => __('bios.hp_editor_value'),
    'valuePlaceholder' => __('bios.hp_editor_value_ph'),
    'stats' => __('bios.hp_editor_stats'),
    'errMissing' => __('bios.hp_editor_err_missing'),
    'cfgSectionTitle' => __('bios.hp_editor_cfg_section_title'),
    'cfgSectionLead' => __('bios.hp_editor_cfg_section_lead'),
    'helpBtn' => __('bios.hp_editor_help_btn'),
    'helpValues' => __('bios.hp_editor_help_values'),
    'helpClose' => __('bios.hp_editor_help_close'),
    'redirect' => '/bios/hp/cfg-editor',
    'format' => 'bcu',
];

require __DIR__ . '/partials/cfg_editor.php';
