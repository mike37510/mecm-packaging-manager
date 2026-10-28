<?php

use App\Support\View;
use App\Support\Session;

/** @var \App\Models\Software|null $software */
/** @var list<string> $categories */
/** @var list<string> $detectionMethods */
/** @var list<string> $architectures */
/** @var list<string> $installerTypes */
/** @var \App\Security\Csrf $csrf */
/** @var string $uploadUrl */

$categories = $categories ?? [];
$old = Session::pullFlash('old', []);
if (!is_array($old)) {
    $old = [];
}

$val = static function (string $key, mixed $default = '') use ($software, $old): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($software === null) {
        return (string) $default;
    }

    return match ($key) {
        'name' => $software->name,
        'publisher' => $software->publisher,
        'category' => $software->category,
        'description' => (string) ($software->description ?? ''),
        'current_version' => $software->currentVersion,
        'last_packaged_at' => $software->lastPackagedAt
            ? (new DateTimeImmutable($software->lastPackagedAt))->format('Y-m-d')
            : '',
        'update_frequency_days' => (string) $software->updateFrequencyDays,
        'download_url' => (string) ($software->downloadUrl ?? ''),
        'version_check_url' => (string) ($software->versionCheckUrl ?? ''),
        'documentation_url' => (string) ($software->documentationUrl ?? ''),
        'detection_method' => $software->detectionMethod,
        'architecture' => $software->architecture,
        'installer_type' => $software->installerType,
        default => (string) $default,
    };
};

$isEdit = $software !== null;
$action = $isEdit ? '/softwares/' . $software->id : '/softwares';
$detectionLabels = [
    'fixed_url' => __('software.detection_fixed_url'),
    'github' => __('software.detection_github'),
];
$selectedCategory = $val('category');
?>
<div class="mb-4">
    <h1 class="h3 mb-1"><?= View::e($isEdit ? __('software.edit_title') : __('software.create_title')) ?></h1>
    <p class="text-muted mb-0"><?= View::e(__('software.form_lead')) ?></p>
</div>

<form method="post" action="<?= View::e($action) ?>" enctype="multipart/form-data" class="panel"
      data-loading="<?= View::e(__('software.form_saving')) ?>">
    <?= $csrf->field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name"><?= View::e(__('software.name')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <input class="form-control" id="name" name="name" required value="<?= View::e($val('name')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="publisher"><?= View::e(__('software.publisher')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <input class="form-control" id="publisher" name="publisher" required value="<?= View::e($val('publisher')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="category"><?= View::e(__('software.category')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <select class="form-select" id="category" name="category" required>
                <option value=""><?= View::e(__('software.category_choose')) ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= View::e($cat) ?>" <?= $selectedCategory === $cat ? 'selected' : '' ?>><?= View::e($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                <?= View::e(__('software.category_help')) ?>
                <?php if (!empty($currentUser) && $currentUser->role->canManageUsers()): ?>
                    <a href="/settings#categories"><?= View::e(__('software.category_manage_link')) ?></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="current_version"><?= View::e(__('software.current_version')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <input class="form-control" id="current_version" name="current_version" required value="<?= View::e($val('current_version', '1.0.0')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="update_frequency_days"><?= View::e(__('software.update_frequency_days')) ?></label>
            <input class="form-control" type="number" min="1" id="update_frequency_days" name="update_frequency_days" value="<?= View::e($val('update_frequency_days', '1')) ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="description"><?= View::e(__('software.description')) ?></label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= View::e($val('description')) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="documentation_url"><?= View::e(__('software.documentation_url')) ?></label>
            <input class="form-control" type="url" id="documentation_url" name="documentation_url"
                   value="<?= View::e($val('documentation_url')) ?>"
                   placeholder="https://…">
            <div class="form-text"><?= View::e(__('software.documentation_url_help')) ?></div>
        </div>
        <?php if ($isEdit): ?>
        <div class="col-md-4">
            <label class="form-label" for="installer_type"><?= View::e(__('software.installer_type')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <select class="form-select" id="installer_type" name="installer_type" required>
                <?php foreach ($installerTypes as $t): ?>
                    <option value="<?= View::e($t) ?>" <?= $val('installer_type', 'exe') === $t ? 'selected' : '' ?>><?= View::e(strtoupper($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="architecture"><?= View::e(__('software.architecture')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <select class="form-select" id="architecture" name="architecture" required>
                <?php foreach ($architectures as $a): ?>
                    <option value="<?= View::e($a) ?>" <?= $val('architecture', 'x64') === $a ? 'selected' : '' ?>><?= View::e($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="detection_method"><?= View::e(__('software.detection_method')) ?> <?= View::e(__('common.required_mark')) ?></label>
            <select class="form-select" id="detection_method" name="detection_method" required>
                <?php foreach ($detectionMethods as $m):
                    $label = $detectionLabels[$m] ?? $m;
                ?>
                    <option value="<?= View::e($m) ?>" <?= $val('detection_method', 'fixed_url') === $m ? 'selected' : '' ?>><?= View::e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                <?= View::e(__('software.detection_help')) ?>
                <a href="/docs#detection"><?= View::e(__('software.detection_docs_link')) ?></a>
            </div>
        </div>
        <div class="col-md-6" data-url-for="fixed_url">
            <label class="form-label" for="download_url"><?= View::e(__('software.download_url')) ?></label>
            <input class="form-control" type="url" id="download_url" name="download_url" value="<?= View::e($val('download_url')) ?>">
            <div class="form-text"><?= View::e(__('software.download_url_help')) ?></div>
        </div>
        <div class="col-md-6" data-url-for="github">
            <label class="form-label" for="version_check_url"><?= View::e(__('software.version_check_url')) ?></label>
            <input class="form-control" type="url" id="version_check_url" name="version_check_url" value="<?= View::e($val('version_check_url')) ?>"
                   placeholder="https://github.com/owner/repo/releases">
            <div class="form-text"><?= View::e(__('software.version_check_url_help')) ?></div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="last_packaged_at"><?= View::e(__('software.last_packaged_at')) ?></label>
            <input class="form-control" type="date" id="last_packaged_at" name="last_packaged_at" value="<?= View::e($val('last_packaged_at')) ?>">
        </div>
        <div class="col-md-8">
            <label class="form-label" for="icon"><?= View::e(__('software.icon')) ?></label>
            <input class="form-control" type="file" id="icon" name="icon"
                   accept="image/png,image/jpeg,image/gif,image/webp,image/x-icon,.ico,.png,.jpg,.jpeg">
            <div class="form-text"><?= View::e(__('software.icon_help')) ?></div>
            <?php if ($isEdit && $software->iconPath): ?>
                <div class="mt-2 d-flex align-items-center gap-2">
                    <img class="software-icon" style="width:64px;height:64px" src="<?= View::e($uploadUrl . '/' . $software->iconPath) ?>" alt="<?= View::e(__('software.icon_alt')) ?>">
                    <span class="small text-muted"><?= View::e(__('software.icon_current')) ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= View::e($isEdit ? __('common.save') : __('common.create')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= $isEdit ? '/softwares/' . $software->id : '/softwares' ?>"><?= View::e(__('common.cancel')) ?></a>
    </div>
</form>
