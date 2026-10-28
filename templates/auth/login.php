<?php

use App\Support\View;

/** @var \App\Security\Csrf $csrf */
?>
<div class="auth-brand">
    <img src="/assets/brand/logo-on-dark.png" width="748" height="156" alt="<?= View::e($appName) ?>">
</div>
<h1 class="visually-hidden"><?= View::e($appName) ?></h1>
<p class="text-muted mb-4"><?= View::e(__('auth.lead')) ?></p>
<form method="post" action="/login" autocomplete="on">
    <?= $csrf->field() ?>
    <div class="mb-3">
        <label class="form-label" for="email"><?= View::e(__('auth.email')) ?></label>
        <input class="form-control" type="email" name="email" id="email" required autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password"><?= View::e(__('auth.password')) ?></label>
        <input class="form-control" type="password" name="password" id="password" required>
    </div>
    <button class="btn btn-primary w-100" type="submit"><?= View::e(__('auth.submit')) ?></button>
</form>
<p class="text-center text-muted small mt-3 mb-0">
    <a href="/welcome"><?= View::e(__('auth.discover')) ?></a>
    <span aria-hidden="true"> · </span>
    <a href="/welcome/en" hreflang="en" lang="en"><?= View::e(__('auth.discover_en')) ?></a>
    <span aria-hidden="true"> · </span>
    <a href="https://github.com/mike37510/mecm-packaging-manager" target="_blank" rel="noopener noreferrer"><?= View::e(__('auth.github')) ?></a>
</p>
