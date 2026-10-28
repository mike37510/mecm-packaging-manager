<?php

use App\Support\View;

/** @var array<string, mixed> $docs */

$modules = is_array($docs['modules'] ?? null) ? $docs['modules'] : [];
$cycle = is_array($docs['cycle'] ?? null) ? $docs['cycle'] : [];
$zipSteps = is_array($docs['zip_steps'] ?? null) ? $docs['zip_steps'] : [];
$stepFormFields = is_array($docs['step_form_fields'] ?? null) ? $docs['step_form_fields'] : [];
$stepTypes = is_array($docs['step_types'] ?? null) ? $docs['step_types'] : [];
$steps = is_array($docs['procedure_steps'] ?? null) ? $docs['procedure_steps'] : [];
$tips = is_array($docs['procedure_tips'] ?? null) ? $docs['procedure_tips'] : [];
?>
<div class="docs-page">
    <header class="docs-hero panel">
        <h1 class="h3 mb-2"><?= View::e((string) $docs['intro_title']) ?></h1>
        <p class="text-muted mb-3"><?= View::e((string) $docs['intro_body']) ?></p>
        <nav class="docs-toc" aria-label="TOC">
            <a href="#modules"><?= View::e((string) $docs['toc_modules']) ?></a>
            <a href="#status"><?= View::e((string) ($docs['toc_status'] ?? 'Sync')) ?></a>
            <a href="#detection"><?= View::e((string) ($docs['toc_detection'] ?? 'Détection')) ?></a>
            <?php if (!empty($docs['toc_drivers'])): ?>
                <a href="#module-drivers"><?= View::e((string) $docs['toc_drivers']) ?></a>
            <?php endif; ?>
            <?php if (!empty($docs['toc_bios'])): ?>
                <a href="#module-bios"><?= View::e((string) $docs['toc_bios']) ?></a>
            <?php endif; ?>
            <a href="#cycle"><?= View::e((string) $docs['toc_cycle']) ?></a>
            <a href="#after-zip"><?= View::e((string) ($docs['toc_zip'] ?? 'ZIP')) ?></a>
            <a href="#step-types"><?= View::e((string) ($docs['toc_step_types'] ?? 'Types')) ?></a>
            <a href="#procedure"><?= View::e((string) $docs['toc_procedure']) ?></a>
        </nav>
    </header>

    <section class="panel docs-section" id="modules">
        <h2 class="h4"><?= View::e((string) $docs['modules_title']) ?></h2>
        <p class="text-muted mb-4"><?= View::e((string) $docs['modules_lead']) ?></p>
        <div class="docs-modules">
            <?php foreach ($modules as $module): ?>
                <?php if (!is_array($module)) {
                    continue;
                } ?>
                <article class="docs-module" id="module-<?= View::e((string) ($module['id'] ?? '')) ?>">
                    <h3 class="h5 mb-1"><?= View::e((string) ($module['name'] ?? '')) ?></h3>
                    <p class="docs-module-summary"><?= View::e((string) ($module['summary'] ?? '')) ?></p>
                    <?php $how = is_array($module['how'] ?? null) ? $module['how'] : []; ?>
                    <?php if ($how !== []): ?>
                        <ol class="docs-how">
                            <?php foreach ($how as $line): ?>
                                <li><?= View::e((string) $line) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <?php
    $statusBlocks = is_array($docs['status_blocks'] ?? null) ? $docs['status_blocks'] : [];
    $statusDots = is_array($docs['status_dots'] ?? null) ? $docs['status_dots'] : [];
    if (!empty($docs['status_title'])):
    ?>
    <section class="panel docs-section" id="status">
        <h2 class="h4"><?= View::e((string) $docs['status_title']) ?></h2>
        <p class="text-muted mb-4"><?= View::e((string) ($docs['status_lead'] ?? '')) ?></p>
        <?php foreach ($statusBlocks as $block): ?>
            <?php if (!is_array($block)) {
                continue;
            } ?>
            <h3 class="h5"><?= View::e((string) ($block['title'] ?? '')) ?></h3>
            <p><?= View::e((string) ($block['body'] ?? '')) ?></p>
        <?php endforeach; ?>
        <?php if ($statusDots !== []): ?>
            <h3 class="h5"><?= View::e((string) ($docs['status_dots_title'] ?? '')) ?></h3>
            <ul class="docs-status-dots">
                <?php foreach ($statusDots as $dot): ?>
                    <?php
                    if (!is_array($dot)) {
                        continue;
                    }
                    $kind = (string) ($dot['dot'] ?? '');
                    $cls = match ($kind) {
                        'ok' => 'sync-dot sync-dot-ok',
                        'ko' => 'sync-dot sync-dot-ko',
                        'err' => 'sync-dot sync-dot-err',
                        default => 'sync-dot sync-dot-muted',
                    };
                    ?>
                    <li>
                        <span class="<?= View::e($cls) ?>" aria-hidden="true"></span>
                        <div>
                            <strong><?= View::e((string) ($dot['label'] ?? '')) ?></strong>
                            <span><?= View::e((string) ($dot['body'] ?? '')) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($docs['status_note'])): ?>
            <p class="docs-zip-note small mt-3 mb-0"><?= View::e((string) $docs['status_note']) ?></p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php
    $detectionModes = is_array($docs['detection_modes'] ?? null) ? $docs['detection_modes'] : [];
    $detectionCommon = is_array($docs['detection_common'] ?? null) ? $docs['detection_common'] : [];
    if (!empty($docs['detection_title'])):
    ?>
    <section class="panel docs-section" id="detection">
        <h2 class="h4"><?= View::e((string) $docs['detection_title']) ?></h2>
        <p class="text-muted mb-4"><?= View::e((string) ($docs['detection_lead'] ?? '')) ?></p>

        <?php if ($detectionCommon !== []): ?>
            <h3 class="h5"><?= View::e((string) ($docs['detection_common_title'] ?? '')) ?></h3>
            <ul class="docs-how mb-4">
                <?php foreach ($detectionCommon as $line): ?>
                    <li><?= View::e((string) $line) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="docs-detection-modes">
            <?php foreach ($detectionModes as $mode): ?>
                <?php if (!is_array($mode)) {
                    continue;
                } ?>
                <article class="docs-detection-mode" id="detection-<?= View::e((string) ($mode['id'] ?? '')) ?>">
                    <h3 class="h5 mb-1"><?= View::e((string) ($mode['name'] ?? '')) ?></h3>
                    <p class="docs-module-summary"><?= View::e((string) ($mode['summary'] ?? '')) ?></p>
                    <?php $how = is_array($mode['how'] ?? null) ? $mode['how'] : []; ?>
                    <?php if ($how !== []): ?>
                        <ol class="docs-how">
                            <?php foreach ($how as $line): ?>
                                <li><?= View::e((string) $line) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                    <?php if (!empty($mode['example'])): ?>
                        <p class="docs-detection-example small mb-0"><strong><?= View::e((string) ($docs['detection_example_label'] ?? 'Exemple')) ?> :</strong> <?= View::e((string) $mode['example']) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($docs['detection_note'])): ?>
            <p class="docs-zip-note small mt-4 mb-0"><?= View::e((string) $docs['detection_note']) ?></p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="panel docs-section" id="cycle">
        <h2 class="h4"><?= View::e((string) $docs['cycle_title']) ?></h2>
        <p class="text-muted mb-3"><?= View::e((string) $docs['cycle_lead']) ?></p>
        <ol class="docs-cycle">
            <?php foreach ($cycle as $item): ?>
                <?php if (!is_array($item)) {
                    continue;
                } ?>
                <li>
                    <strong><?= View::e((string) ($item['title'] ?? '')) ?></strong>
                    <span><?= View::e((string) ($item['body'] ?? '')) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="panel docs-section" id="after-zip">
        <h2 class="h4"><?= View::e((string) ($docs['zip_title'] ?? '')) ?></h2>
        <p class="text-muted mb-3"><?= View::e((string) ($docs['zip_lead'] ?? '')) ?></p>
        <ol class="docs-cycle docs-zip-steps">
            <?php foreach ($zipSteps as $item): ?>
                <?php if (!is_array($item)) {
                    continue;
                } ?>
                <li>
                    <strong><?= View::e((string) ($item['title'] ?? '')) ?></strong>
                    <span><?= View::e((string) ($item['body'] ?? '')) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if (!empty($docs['zip_note'])): ?>
            <p class="docs-zip-note small mt-3 mb-0"><?= View::e((string) $docs['zip_note']) ?></p>
        <?php endif; ?>
        <?php
        $zipDriversSteps = is_array($docs['zip_drivers_steps'] ?? null) ? $docs['zip_drivers_steps'] : [];
        if (!empty($docs['zip_drivers_title']) && $zipDriversSteps !== []):
        ?>
            <h3 class="h5 mt-4"><?= View::e((string) $docs['zip_drivers_title']) ?></h3>
            <?php if (!empty($docs['zip_drivers_lead'])): ?>
                <p class="text-muted"><?= View::e((string) $docs['zip_drivers_lead']) ?></p>
            <?php endif; ?>
            <ol class="docs-cycle docs-zip-steps">
                <?php foreach ($zipDriversSteps as $item): ?>
                    <?php if (!is_array($item)) {
                        continue;
                    } ?>
                    <li>
                        <strong><?= View::e((string) ($item['title'] ?? '')) ?></strong>
                        <span><?= View::e((string) ($item['body'] ?? '')) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section class="panel docs-section" id="step-types">
        <h2 class="h4"><?= View::e((string) ($docs['step_form_title'] ?? '')) ?></h2>
        <p class="text-muted mb-3"><?= View::e((string) ($docs['step_form_lead'] ?? '')) ?></p>
        <dl class="docs-field-list">
            <?php foreach ($stepFormFields as $field): ?>
                <?php if (!is_array($field)) {
                    continue;
                } ?>
                <dt><?= View::e((string) ($field['name'] ?? '')) ?></dt>
                <dd><?= View::e((string) ($field['detail'] ?? '')) ?></dd>
            <?php endforeach; ?>
        </dl>

        <h3 class="h5 mt-4"><?= View::e((string) ($docs['step_types_title'] ?? '')) ?></h3>
        <p class="text-muted mb-3"><?= View::e((string) ($docs['step_types_lead'] ?? '')) ?></p>
        <div class="docs-step-types">
            <?php foreach ($stepTypes as $type): ?>
                <?php if (!is_array($type)) {
                    continue;
                } ?>
                <article class="docs-step-type-item">
                    <h4 class="h6 mb-1">
                        <?= View::e((string) ($type['name'] ?? '')) ?>
                        <code class="docs-step-type"><?= View::e((string) ($type['code'] ?? '')) ?></code>
                    </h4>
                    <p class="mb-1"><?= View::e((string) ($type['summary'] ?? '')) ?></p>
                    <p class="small text-muted mb-0"><strong><?= View::e(__('common.details')) ?>:</strong> <?= View::e((string) ($type['fields'] ?? '')) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel docs-section" id="procedure">
        <h2 class="h4"><?= View::e((string) $docs['procedure_title']) ?></h2>
        <p class="text-muted mb-3"><?= View::e((string) $docs['procedure_lead']) ?></p>

        <?php
        $wizardSteps = is_array($docs['procedure_wizard_steps'] ?? null) ? $docs['procedure_wizard_steps'] : [];
        if (!empty($docs['procedure_wizard_title']) && $wizardSteps !== []):
        ?>
            <div class="docs-procedure-wizard mb-4">
                <h3 class="h5 mb-2"><?= View::e((string) $docs['procedure_wizard_title']) ?></h3>
                <?php if (!empty($docs['procedure_wizard_lead'])): ?>
                    <p class="small text-muted mb-3"><?= View::e((string) $docs['procedure_wizard_lead']) ?></p>
                <?php endif; ?>
                <ol class="docs-cycle">
                    <?php foreach ($wizardSteps as $item): ?>
                        <?php if (!is_array($item)) {
                            continue;
                        } ?>
                        <li>
                            <strong><?= View::e((string) ($item['title'] ?? '')) ?></strong>
                            <span><?= View::e((string) ($item['body'] ?? '')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>

        <div class="docs-procedure-example">
            <h3 class="h5 mb-2"><?= View::e((string) $docs['procedure_example_title']) ?></h3>
            <p class="small text-muted mb-3"><?= View::e((string) $docs['procedure_example_context']) ?></p>

            <ol class="docs-procedure-steps">
                <?php foreach ($steps as $i => $step): ?>
                    <?php if (!is_array($step)) {
                        continue;
                    } ?>
                    <li>
                        <div class="docs-step-head">
                            <span class="docs-step-num"><?= (int) $i + 1 ?></span>
                            <div>
                                <strong><?= View::e((string) ($step['title'] ?? '')) ?></strong>
                                <code class="docs-step-type"><?= View::e((string) ($step['type'] ?? '')) ?></code>
                            </div>
                        </div>
                        <pre class="docs-step-detail"><?= View::e((string) ($step['detail'] ?? '')) ?></pre>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <h3 class="h6 mt-4"><?= View::e((string) $docs['procedure_tips_title']) ?></h3>
        <ul class="docs-tips">
            <?php foreach ($tips as $tip): ?>
                <li><?= View::e((string) $tip) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
