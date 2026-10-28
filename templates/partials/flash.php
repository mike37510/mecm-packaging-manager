<?php

use App\Support\View;

/** @var array<string, mixed> $flash */
?>
<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><?= View::e((string) $flash['success']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-danger"><?= View::e((string) $flash['error']) ?></div>
<?php endif; ?>
