<?php

use App\Support\View;

/** @var \App\Security\Csrf $csrf */
?>
<div class="mb-4">
    <h1 class="h3 mb-1"><?= View::e(__('account.password_title')) ?></h1>
    <p class="text-muted mb-0"><?= View::e(__('account.password_lead')) ?></p>
</div>

<div class="panel" style="max-width:28rem">
    <form method="post" action="/account/password" autocomplete="off">
        <?= $csrf->field() ?>
        <div class="mb-3">
            <label class="form-label" for="current_password"><?= View::e(__('account.current_password')) ?></label>
            <input class="form-control" type="password" name="current_password" id="current_password"
                   required autocomplete="current-password" autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label" for="new_password"><?= View::e(__('account.new_password')) ?></label>
            <input class="form-control" type="password" name="new_password" id="new_password"
                   required minlength="8" autocomplete="new-password">
            <div class="form-text"><?= View::e(__('account.password_hint')) ?></div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="new_password_confirm"><?= View::e(__('account.confirm_password')) ?></label>
            <input class="form-control" type="password" name="new_password_confirm" id="new_password_confirm"
                   required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary"><?= View::e(__('account.save_password')) ?></button>
    </form>
</div>
