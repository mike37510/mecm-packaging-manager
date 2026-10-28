<?php

use App\Support\View;

/** @var list<\App\Models\Category> $categories */
/** @var array<string, int> $categoryUsage */
/** @var \App\Security\Csrf $csrf */

$categories = $categories ?? [];
$categoryUsage = $categoryUsage ?? [];
?>
<div class="panel mb-4" id="categories">
    <h2 class="h5 mb-1"><?= View::e(__('categories.title')) ?></h2>
    <p class="text-muted small mb-3"><?= View::e(__('categories.lead')) ?></p>

    <form method="post" action="/settings/categories" class="row g-3 mb-4">
        <?= $csrf->field() ?>
        <div class="col-md-6">
            <label class="form-label" for="category_name"><?= View::e(__('categories.name')) ?></label>
            <input class="form-control" name="name" id="category_name" required maxlength="120"
                   placeholder="<?= View::e(__('categories.name_placeholder')) ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit"><?= View::e(__('categories.create')) ?></button>
        </div>
    </form>

    <div class="table-responsive border rounded">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th><?= View::e(__('categories.name')) ?></th>
                <th style="width:140px"><?= View::e(__('categories.used_by')) ?></th>
                <th style="width:220px"><?= View::e(__('common.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($categories === []): ?>
                <tr>
                    <td colspan="3" class="text-muted"><?= View::e(__('categories.empty')) ?></td>
                </tr>
            <?php endif; ?>
            <?php foreach ($categories as $category): ?>
                <?php $count = (int) ($categoryUsage[$category->name] ?? 0); ?>
                <tr>
                    <td>
                        <form id="category-form-<?= (int) $category->id ?>" method="post"
                              action="/settings/categories/<?= (int) $category->id ?>" class="m-0">
                            <?= $csrf->field() ?>
                            <input class="form-control form-control-sm" name="name"
                                   value="<?= View::e($category->name) ?>" required maxlength="120">
                        </form>
                    </td>
                    <td>
                        <?php if ($count > 0): ?>
                            <a href="/softwares?category=<?= rawurlencode($category->name) ?>" class="small">
                                <?= (int) $count ?> <?= View::e(__('categories.softwares')) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" type="submit"
                                form="category-form-<?= (int) $category->id ?>">
                            <?= View::e(__('common.save')) ?>
                        </button>
                        <form method="post" action="/settings/categories/<?= (int) $category->id ?>/delete" class="m-0"
                              onsubmit="return confirm(<?= json_encode(__('categories.confirm_delete', ['name' => $category->name]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                            <?= $csrf->field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit"
                                <?= $count > 0 ? 'disabled title="' . View::e(__('categories.delete_blocked')) . '"' : '' ?>>
                                <?= View::e(__('common.delete')) ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
