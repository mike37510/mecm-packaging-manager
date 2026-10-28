<?php

use App\Support\View;
use App\Support\Session;
use App\Services\HpDriverPackResolver;

/** @var \App\Models\Driver|null $driver */
/** @var list<string> $manufacturers */
/** @var list<string> $architectures */
/** @var \App\Security\Csrf $csrf */

$old = Session::pullFlash('old', []);
if (!is_array($old)) {
    $old = [];
}

$val = static function (string $key, mixed $default = '') use ($driver, $old): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($driver === null) {
        return (string) $default;
    }

    return match ($key) {
        'name' => $driver->name,
        'manufacturer' => $driver->manufacturer,
        'model' => $driver->model,
        'version' => $driver->version,
        'architecture' => $driver->architecture,
        'os_target' => $driver->osTarget,
        'download_url' => $driver->catalogUrl ?: $driver->downloadUrl,
        'description' => (string) ($driver->description ?? ''),
        'expected_sha256' => (string) ($driver->expectedSha256 ?? ''),
        default => (string) $default,
    };
};

$isEdit = $driver !== null;
$action = $isEdit ? '/drivers/' . $driver->id : '/drivers';
$currentMfr = $val('manufacturer', 'dell');
$hpMatrixX64 = HpDriverPackResolver::MATRIX_X64;
$hpMatrixX86 = HpDriverPackResolver::MATRIX_X86;
?>
<div class="mb-4">
    <h1 class="h3 mb-1"><?= View::e($isEdit ? __('driver.edit_title') : __('driver.create_title')) ?></h1>
    <p class="text-muted mb-0"><?= View::e(__('driver.form_lead')) ?></p>
</div>

<form method="post" action="<?= View::e($action) ?>" class="panel" id="driver-form"
      data-loading="<?= View::e(__('driver.form_saving')) ?>"
      data-hp-matrix-x64="<?= View::e($hpMatrixX64) ?>"
      data-hp-matrix-x86="<?= View::e($hpMatrixX86) ?>">
    <?= $csrf->field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name"><?= View::e(__('driver.name')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <input class="form-control" id="name" name="name" required value="<?= View::e($val('name')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="manufacturer"><?= View::e(__('driver.manufacturer')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <select class="form-select" id="manufacturer" name="manufacturer" required>
                <?php foreach ($manufacturers as $code): ?>
                    <option value="<?= View::e($code) ?>" <?= $currentMfr === $code ? 'selected' : '' ?>>
                        <?php
                        $mfrLabel = __('driver.mfr_' . $code);
                        echo View::e($mfrLabel !== 'driver.mfr_' . $code ? $mfrLabel : $code);
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="architecture"><?= View::e(__('driver.architecture')) ?></label>
            <select class="form-select" id="architecture" name="architecture">
                <?php foreach ($architectures as $arch): ?>
                    <option value="<?= View::e($arch) ?>" <?= $val('architecture', 'x64') === $arch ? 'selected' : '' ?>>
                        <?= View::e($arch) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="model"><?= View::e(__('driver.model')) ?></label>
            <input class="form-control" id="model" name="model" value="<?= View::e($val('model')) ?>"
                   placeholder="<?= View::e(__('driver.model_placeholder')) ?>">
            <div class="form-text" data-mfr-help="dell" <?= $currentMfr === 'hp' ? 'hidden' : '' ?>><?= View::e(__('driver.model_help')) ?></div>
            <div class="form-text" data-mfr-help="hp" <?= $currentMfr === 'hp' ? '' : 'hidden' ?>><?= View::e(__('driver.model_help_hp')) ?></div>
        </div>
        <div class="col-md-6" id="hp-os-wrap" <?= $currentMfr === 'hp' ? '' : 'hidden' ?>>
            <label class="form-label" for="os_target"><?= View::e(__('driver.os_target')) ?></label>
            <select class="form-select" id="os_target" name="os_target">
                <?php foreach (HpDriverPackResolver::osChoicesFor($val('os_target')) as $slug => $label): ?>
                    <option value="<?= View::e($slug) ?>" <?= $val('os_target') === $slug ? 'selected' : '' ?>>
                        <?= View::e($slug === '' ? __('driver.os_auto') : $label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text"><?= View::e(__('driver.os_target_help')) ?></div>
        </div>
        <div class="col-12">
            <label class="form-label" for="download_url"><?= View::e(__('driver.download_url')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <input class="form-control" id="download_url" name="download_url" type="url" required
                   value="<?= View::e($val('download_url')) ?>"
                   placeholder="https://www.dell.com/support/kbdoc/fr-fr/000223166/…">
            <div class="form-text" data-mfr-help="dell" <?= $currentMfr === 'hp' ? 'hidden' : '' ?>><?= View::e(__('driver.download_url_help')) ?></div>
            <div class="form-text" data-mfr-help="hp" <?= $currentMfr === 'hp' ? '' : 'hidden' ?>><?= View::e(__('driver.download_url_help_hp')) ?></div>
        </div>
        <div class="col-12">
            <label class="form-label" for="expected_sha256"><?= View::e(__('driver.expected_sha256')) ?></label>
            <input class="form-control font-monospace" id="expected_sha256" name="expected_sha256"
                   value="<?= View::e($val('expected_sha256')) ?>"
                   maxlength="64" pattern="[A-Fa-f0-9]{64}"
                   placeholder="<?= View::e(__('driver.expected_sha256_placeholder')) ?>"
                   autocomplete="off">
            <div class="form-text"><?= View::e(__('driver.expected_sha256_help')) ?></div>
        </div>
        <div class="col-12">
            <label class="form-label" for="description"><?= View::e(__('driver.description')) ?></label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= View::e($val('description')) ?></textarea>
        </div>
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= View::e(__('common.save')) ?></button>
            <a href="<?= View::e($isEdit ? '/drivers/' . $driver->id : '/drivers') ?>" class="btn btn-outline-secondary"><?= View::e(__('common.cancel')) ?></a>
        </div>
    </div>
</form>
