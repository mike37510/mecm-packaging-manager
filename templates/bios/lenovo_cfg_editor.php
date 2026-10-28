<?php

use App\Support\View;

/**
 * @var string $optionsJsV
 * @var string $helpJsV
 * @var string $editorJsV
 * @var list<array<string, mixed>> $lenovoVersions
 * @var bool $canWrite
 * @var \App\Security\Csrf $csrf
 */
$versionsBack = '/bios/lenovo/cfg-editor';
$versions = $lenovoVersions ?? [];
$downloadBase = '/bios/lenovo/cfg-versions';
$i18nPrefix = 'lenovo';

$backHref = '/bios/lenovo';
$title = __('bios.lenovo_editor_title');
$lead = __('bios.lenovo_editor_lead');
$noteLabel = __('bios.lenovo_save_note');
$notePlaceholder = __('bios.lenovo_save_note_ph');
$saveLabel = __('bios.lenovo_editor_save');
$savingLabel = __('bios.lenovo_saving');
$needSelection = __('bios.lenovo_save_empty');
$saveUrl = '/bios/lenovo/cfg-versions';
$readonlyRoleMsg = __('bios.lenovo_save_readonly_role');
$filterLabel = __('bios.lenovo_editor_filter');
$filterPlaceholder = __('bios.lenovo_editor_filter_ph');
$loadingLabel = __('bios.lenovo_editor_loading');
$helpClose = __('bios.lenovo_editor_help_close');
$optionsScript = '/assets/js/bios/lenovo-tbct-options.js';
$helpScript = '/assets/js/bios/lenovo-tbct-help.js';
$editorScript = '/assets/js/bios/lenovo-tbct-editor.js';
$editorI18n = [
    'defaultDesc' => __('bios.lenovo_editor_default_desc'),
    'valueLabel' => __('bios.lenovo_editor_value'),
    'valuePlaceholder' => __('bios.lenovo_editor_value_ph'),
    'stats' => __('bios.lenovo_editor_stats'),
    'errMissing' => __('bios.lenovo_editor_err_missing'),
    'cfgSectionTitle' => __('bios.lenovo_editor_cfg_section_title'),
    'cfgSectionLead' => __('bios.lenovo_editor_cfg_section_lead'),
    'helpBtn' => __('bios.lenovo_editor_help_btn'),
    'helpValues' => __('bios.lenovo_editor_help_values'),
    'helpClose' => __('bios.lenovo_editor_help_close'),
    'redirect' => '/bios/lenovo/cfg-editor',
    'format' => 'tbct',
];

require __DIR__ . '/partials/cfg_editor.php';
