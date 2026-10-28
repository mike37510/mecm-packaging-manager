<?php

use App\Support\View;

/**
 * @var list<string> $manufacturers
 * @var list<string> $available
 */
$available = $available ?? ['hp'];
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">
            <?= View::e(__('bios.list_title')) ?>
            <span class="badge text-bg-warning text-dark align-middle ms-1"><?= View::e(__('bios.list_beta')) ?></span>
        </h1>
        <p class="text-muted mb-0"><?= View::e(__('bios.list_lead')) ?></p>
    </div>
</div>

<div class="bios-mfr-grid">
    <?php foreach ($manufacturers as $code): ?>
        <?php
        $isAvailable = in_array($code, $available, true);
        $href = '/bios/' . $code;
        ?>
        <a href="<?= View::e($href) ?>" class="bios-mfr-card panel text-decoration-none<?= $isAvailable ? '' : ' bios-mfr-card-soon' ?>">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h2 class="h5 mb-1 text-body"><?= View::e(__('bios.mfr_' . $code)) ?></h2>
                <?php if ($isAvailable): ?>
                    <span class="badge text-bg-success"><?= View::e(__('bios.status_available')) ?></span>
                <?php else: ?>
                    <span class="badge text-bg-secondary"><?= View::e(__('bios.status_soon')) ?></span>
                <?php endif; ?>
            </div>
            <p class="text-muted mb-0 small"><?= View::e(__('bios.mfr_' . $code . '_lead')) ?></p>
        </a>
    <?php endforeach; ?>
</div>
