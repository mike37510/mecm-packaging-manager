<?php

use App\Support\View;

/** @var string $appName */
/** @var string $appVersion */
/** @var string $locale */
/** @var array<string, string> $jsI18n */
/** @var \App\Models\User|null $currentUser */
/** @var array<string, mixed> $flash */
/** @var \App\Security\Csrf $csrf */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$appVersion = $appVersion ?? '4.3.0';
$locale = $locale ?? 'fr';
$jsI18n = $jsI18n ?? [];
?>
<!DOCTYPE html>
<html lang="<?= View::e($locale) ?>" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e(isset($pageTitle) && $pageTitle !== '' ? ($pageTitle . ' — ' . $appName) : $appName) ?></title>
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
    <link href="/assets/css/app.css?v=<?= View::e(($appVersion ?? '1') . '.' . (string) @filemtime(dirname(__DIR__, 2) . '/public/assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="app-body">
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="/" aria-label="<?= View::e($appName) ?>">
            <img src="/assets/brand/logo-on-dark.png" width="748" height="156" alt="<?= View::e($appName) ?>">
        </a>
        <nav>
            <a href="/" class="<?= $path === '/' ? 'active' : '' ?>"><?= View::e(__('nav.dashboard')) ?></a>
            <?php if ($currentUser && $currentUser->canSeeModule(\App\Security\ModuleAccess::MODULE_SOFTWARES)): ?>
            <a href="/softwares" class="<?= str_starts_with($path, '/softwares') ? 'active' : '' ?>"><?= View::e(__('nav.softwares')) ?></a>
            <?php endif; ?>
            <?php if ($currentUser && $currentUser->canSeeModule(\App\Security\ModuleAccess::MODULE_DRIVERS)): ?>
            <a href="/drivers" class="<?= str_starts_with($path, '/drivers') ? 'active' : '' ?>">
                <?= View::e(__('nav.drivers')) ?>
                <span class="badge text-bg-warning text-dark ms-1" style="font-size:.65rem;vertical-align:middle"><?= View::e(__('nav.drivers_beta')) ?></span>
            </a>
            <?php endif; ?>
            <?php if ($currentUser && $currentUser->canSeeModule(\App\Security\ModuleAccess::MODULE_BIOS)): ?>
            <a href="/bios" class="<?= str_starts_with($path, '/bios') ? 'active' : '' ?>">
                <?= View::e(__('nav.bios')) ?>
                <span class="badge text-bg-warning text-dark ms-1" style="font-size:.65rem;vertical-align:middle"><?= View::e(__('nav.bios_beta')) ?></span>
            </a>
            <?php endif; ?>
            <?php if ($currentUser && $currentUser->role->canManageUsers()): ?>
                <a href="/logs" class="<?= str_starts_with($path, '/logs') ? 'active' : '' ?>"><?= View::e(__('nav.logs')) ?></a>
                <a href="/settings" class="<?= str_starts_with($path, '/settings') ? 'active' : '' ?>"><?= View::e(__('nav.settings')) ?></a>
                <a href="/users" class="<?= str_starts_with($path, '/users') ? 'active' : '' ?>"><?= View::e(__('nav.users')) ?></a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="main-panel">
        <header class="topbar">
            <div class="topbar-brand">
                <img src="/assets/brand/icon.png" width="32" height="32" alt="">
                <strong><?= View::e($appName) ?></strong>
            </div>
            <div class="topbar-actions">
                <button type="button" class="theme-toggle" id="theme-toggle" aria-label="<?= View::e(__('nav.theme')) ?>" title="<?= View::e(__('nav.theme_title')) ?>">
                    <span class="theme-toggle-icon theme-toggle-icon-moon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 14.5A8.5 8.5 0 0 1 9.5 3 7 7 0 1 0 21 14.5z"/></svg>
                    </span>
                    <span class="theme-toggle-icon theme-toggle-icon-sun" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                    </span>
                </button>
                <?php if ($currentUser): ?>
                <div class="dropdown topbar-user">
                    <button type="button"
                            class="topbar-user-toggle dropdown-toggle<?= str_starts_with($path, '/account') ? ' is-active' : '' ?>"
                            id="topbar-user-menu"
                            data-bs-toggle="dropdown"
                            data-bs-auto-close="true"
                            aria-expanded="false"
                            aria-label="<?= View::e(__('nav.account_menu')) ?>">
                        <span class="topbar-user-text">
                            <span class="topbar-user-name"><?= View::e($currentUser->displayName) ?></span>
                            <span class="topbar-user-role"><?= View::e($currentUser->role->label()) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end topbar-user-menu" aria-labelledby="topbar-user-menu">
                        <li>
                            <a class="dropdown-item<?= $path === '/account/password' ? ' active' : '' ?>" href="/account/password">
                                <?= View::e(__('nav.change_password')) ?>
                            </a>
                        </li>
                        <?php if ($currentUser->canWriteModule(\App\Security\ModuleAccess::MODULE_SOFTWARES)): ?>
                        <li>
                            <a class="dropdown-item<?= str_starts_with($path, '/account/vm') ? ' active' : '' ?>" href="/account/vm">
                                <?= View::e(__('nav.account_vm')) ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="/logout" class="m-0">
                                <?= $csrf->field() ?>
                                <button type="submit" class="dropdown-item topbar-user-logout"><?= View::e(__('nav.logout')) ?></button>
                            </form>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </header>
        <main class="content">
            <?php require __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?>
        </main>
        <?php require __DIR__ . '/../partials/footer.php'; ?>
    </div>
</div>

<div id="app-loading" class="app-loading" hidden aria-live="polite" aria-busy="false">
    <div class="app-loading-card">
        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
        <p class="app-loading-text mb-0" id="app-loading-text"><?= View::e(__('nav.loading')) ?></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script src="/assets/js/app.js?v=<?= View::e(($appVersion ?? '1') . '.' . (string) @filemtime(dirname(__DIR__, 2) . '/public/assets/js/app.js')) ?>"></script>
</body>
</html>
