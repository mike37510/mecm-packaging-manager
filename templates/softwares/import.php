<?php

declare(strict_types=1);

use App\Support\View;

/** @var \App\Security\Csrf $csrf */
/** @var array<string, mixed> $old */
$old = \App\Support\Session::pullFlash('old', []);
if (!is_array($old)) {
    $old = [];
}
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= View::e(__('software.import_catalog_title')) ?></h1>
        <p class="text-muted mb-0"><?= View::e(__('software.import_catalog_lead')) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="/softwares"><?= View::e(__('common.back')) ?></a>
</div>

<div class="panel">
    <form method="post" action="/softwares/import" enctype="multipart/form-data" class="vstack gap-3">
        <?= $csrf->field() ?>

        <div>
            <label class="form-label" for="catalog"><?= View::e(__('software.import_catalog_file')) ?></label>
            <input class="form-control" type="file" name="catalog" id="catalog" accept="application/json,.json">
            <div class="form-text"><?= View::e(__('software.import_catalog_file_help')) ?></div>
        </div>

        <div>
            <label class="form-label" for="json"><?= View::e(__('software.import_catalog_json')) ?></label>
            <textarea class="form-control font-monospace" name="json" id="json" rows="14"
                      placeholder="{ &quot;format&quot;: &quot;sccm-pf-software-export&quot;, … }"
            ><?= View::e((string) ($old['json'] ?? '')) ?></textarea>
        </div>

        <div>
            <label class="form-label" for="name"><?= View::e(__('software.import_catalog_name')) ?></label>
            <input class="form-control" type="text" name="name" id="name"
                   value="<?= View::e((string) ($old['name'] ?? '')) ?>"
                   placeholder="<?= View::e(__('software.import_catalog_name_ph')) ?>">
            <div class="form-text"><?= View::e(__('software.import_catalog_name_help')) ?></div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= View::e(__('software.import_catalog_submit')) ?></button>
            <a class="btn btn-outline-secondary" href="/softwares"><?= View::e(__('common.cancel')) ?></a>
        </div>
    </form>
</div>
