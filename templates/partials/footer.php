<?php

use App\Support\View;

/** @var string $appName */
/** @var string $appVersion */
?>
<footer class="app-footer">
    <div class="app-footer-inner">
        <span class="app-footer-brand"><?= View::e($appName) ?></span>
        <div class="app-footer-links">
            <a class="app-footer-link" href="/docs"><?= View::e(__('footer.docs')) ?></a>
            <button type="button"
                    class="app-footer-version"
                    id="changelog-open"
                    data-bs-toggle="modal"
                    data-bs-target="#changelog-modal"
                    title="<?= View::e(__('footer.changelog_open_title')) ?>">
                v<?= View::e($appVersion) ?>
                <span class="app-footer-version-hint"><?= View::e(__('footer.changelog_label')) ?></span>
            </button>
        </div>
    </div>
</footer>

<div class="modal fade" id="changelog-modal" tabindex="-1" aria-labelledby="changelog-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content changelog-modal">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="changelog-modal-title"><?= View::e(__('footer.changelog_title')) ?></h2>
                    <p class="text-muted small mb-0"><?= View::e(__('footer.changelog_hint')) ?></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= View::e(__('footer.close')) ?>"></button>
            </div>
            <div class="modal-body p-0">
                <div class="changelog-layout">
                    <aside class="changelog-nav" id="changelog-nav" aria-label="<?= View::e(__('footer.changelog_versions')) ?>">
                        <div class="changelog-nav-loading text-muted small p-3"><?= View::e(__('footer.changelog_loading')) ?></div>
                    </aside>
                    <div class="changelog-reader" id="changelog-reader">
                        <div class="changelog-reader-placeholder text-muted p-4">
                            <?= View::e(__('footer.changelog_pick')) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
