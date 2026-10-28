<?php

use App\Support\View;

/** @var string $content */
/** @var string $appName */
/** @var string $appVersion */
/** @var string|null $pageTitle */
/** @var string|null $metaDescription */
/** @var string|null $locale */
/** @var array<string, mixed>|null $t */
/** @var \App\Models\User|null $currentUser */

$appVersion = $appVersion ?? '4.3.0';
$locale = ($locale ?? 'fr') === 'en' ? 'en' : 'fr';
$t = is_array($t ?? null) ? $t : [];
$title = trim(($pageTitle ?? ($locale === 'en' ? 'Overview' : 'Présentation')) . ' — ' . $appName);
$meta = (string) ($metaDescription ?? ($t['meta_description'] ?? 'MECM Packaging Manager'));
$themeLabel = (string) ($t['theme_label'] ?? ($locale === 'en' ? 'Toggle dark mode' : 'Basculer le mode sombre'));
$otherLang = (string) ($t['other_lang'] ?? ($locale === 'en' ? 'Français' : 'English'));
$otherLangHref = (string) ($t['other_lang_href'] ?? ($locale === 'en' ? '/welcome' : '/welcome/en'));
$otherLangCode = (string) ($t['other_lang_code'] ?? ($locale === 'en' ? 'fr' : 'en'));
$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/public/assets/css/welcome.css');
?>
<!DOCTYPE html>
<html lang="<?= View::e($locale) ?>" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= View::e($meta) ?>">
    <title><?= View::e($title) ?></title>
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
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/welcome.css?v=<?= View::e($appVersion . '.' . $cssV) ?>" rel="stylesheet">
</head>
<body class="welcome-body">
<nav class="welcome-top" aria-label="<?= View::e($locale === 'en' ? 'Overview' : 'Présentation') ?>">
    <a class="welcome-lang" href="<?= View::e($otherLangHref) ?>" hreflang="<?= View::e($otherLangCode) ?>" lang="<?= View::e($otherLangCode) ?>"><?= View::e($otherLang) ?></a>
    <button type="button" class="welcome-theme" id="theme-toggle" aria-label="<?= View::e($themeLabel) ?>" title="<?= View::e($themeLabel) ?>">
        <span class="welcome-theme-icon welcome-theme-moon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 14.5A8.5 8.5 0 0 1 9.5 3 7 7 0 1 0 21 14.5z"/></svg>
        </span>
        <span class="welcome-theme-icon welcome-theme-sun" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
        </span>
    </button>
</nav>
<?= $content ?>
<script src="/assets/js/app.js"></script>
<script>
(function () {
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) return;
    var nodes = document.querySelectorAll('.welcome-reveal');
    if (!('IntersectionObserver' in window) || !nodes.length) {
        nodes.forEach(function (n) { n.classList.add('is-in'); });
        return;
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) {
                e.target.classList.add('is-in');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.18 });
    nodes.forEach(function (n) { io.observe(n); });
})();
</script>
</body>
</html>
