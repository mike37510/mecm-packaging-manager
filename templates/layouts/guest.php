<?php

use App\Support\View;

/** @var string $content */
/** @var string $appName */
/** @var string|null $pageTitle */
/** @var string $locale */
/** @var array<string, string> $jsI18n */
/** @var array<string, mixed> $flash */

$locale = $locale ?? 'fr';
$jsI18n = $jsI18n ?? [];
?>
<!DOCTYPE html>
<html lang="<?= View::e($locale) ?>" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e(($pageTitle ?? __('auth.title')) . ' — ' . $appName) ?></title>
    <?php require dirname(__DIR__) . '/partials/favicon.php'; ?>
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('pf-theme');
                var theme = stored === 'dark' || stored === 'light'
                    ? stored
                    : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-bs-theme', theme);
            } catch (e) {}
        })();
        window.PF_I18N = <?= json_encode($jsI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        window.PF_LOCALE = <?= json_encode($locale) ?>;
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <button type="button" class="theme-toggle theme-toggle-floating" id="theme-toggle" aria-label="<?= View::e(__('nav.theme')) ?>" title="<?= View::e(__('nav.theme_title')) ?>">
        <span class="theme-toggle-icon theme-toggle-icon-moon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 14.5A8.5 8.5 0 0 1 9.5 3 7 7 0 1 0 21 14.5z"/></svg>
        </span>
        <span class="theme-toggle-icon theme-toggle-icon-sun" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
        </span>
    </button>
    <div class="auth-card">
        <?php require __DIR__ . '/../partials/flash.php'; ?>
        <?= $content ?>
    </div>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
