<?php

use App\Support\View;

/**
 * @var list<array<string, mixed>> $versions
 * @var bool $canWrite
 * @var string $versionsBack
 * @var string $downloadBase   e.g. /bios/hp/cfg-versions
 * @var string $i18nPrefix     e.g. hp or dell
 */
$canWrite = $canWrite ?? false;
$versionsBack = $versionsBack ?? '/bios';
$downloadBase = rtrim($downloadBase ?? '', '/');
$i18nPrefix = $i18nPrefix ?? 'hp';
$versions = $versions ?? [];
?>
<div class="panel mb-4" id="bios-<?= View::e($i18nPrefix) ?>-versions">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="h5 mb-0"><?= View::e(__('bios.' . $i18nPrefix . '_versions_title')) ?></h2>
        <span class="text-muted small"><?= View::e(__('bios.' . $i18nPrefix . '_versions_lead')) ?></span>
    </div>
    <?php if ($versions === []): ?>
        <p class="text-muted mb-0 small"><?= View::e(__('bios.' . $i18nPrefix . '_versions_empty')) ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th><?= View::e(__('bios.' . $i18nPrefix . '_versions_col_file')) ?></th>
                    <th><?= View::e(__('bios.' . $i18nPrefix . '_versions_col_date')) ?></th>
                    <th><?= View::e(__('bios.' . $i18nPrefix . '_versions_col_options')) ?></th>
                    <th><?= View::e(__('bios.' . $i18nPrefix . '_versions_col_author')) ?></th>
                    <th><?= View::e(__('bios.' . $i18nPrefix . '_versions_col_note')) ?></th>
                    <th class="text-end"><?= View::e(__('bios.' . $i18nPrefix . '_versions_col_actions')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($versions as $row): ?>
                    <?php
                    $id = (int) ($row['id'] ?? 0);
                    $filename = (string) ($row['filename'] ?? '');
                    $packageName = $filename !== ''
                        ? (pathinfo($filename, PATHINFO_FILENAME) . '.zip')
                        : '';
                    $created = (string) ($row['created_at'] ?? '');
                    $opts = (int) ($row['option_count'] ?? 0);
                    $author = (string) ($row['created_by_name'] ?? '');
                    $note = (string) ($row['note'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <code><?= View::e($packageName !== '' ? $packageName : $filename) ?></code>
                            <?php if ($filename !== '' && $packageName !== ''): ?>
                                <div class="text-muted small"><?= View::e($filename) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap small"><?= View::e($created) ?></td>
                        <td><?= $opts ?></td>
                        <td class="small"><?= View::e($author !== '' ? $author : '—') ?></td>
                        <td class="small text-muted"><?= View::e($note !== '' ? $note : '—') ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= View::e($downloadBase) ?>/<?= $id ?>/download">
                                <?= View::e(__('bios.download_package')) ?>
                            </a>
                            <?php if ($canWrite): ?>
                                <form method="post" action="<?= View::e($downloadBase) ?>/<?= $id ?>/delete" class="d-inline"
                                      onsubmit="return confirm(<?= json_encode(__('bios.' . $i18nPrefix . '_delete_confirm', ['file' => $filename]), JSON_UNESCAPED_UNICODE) ?>);">
                                    <?= $csrf->field() ?>
                                    <input type="hidden" name="back" value="<?= View::e($versionsBack) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= View::e(__('common.delete')) ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
