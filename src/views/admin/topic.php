<div class="page-header">
    <div>
        <h1><?= View::e($topicTitle) ?></h1>
        <p class="page-sub"><?= View::tr('Materials and files') ?></p>
    </div>
    <div class="page-actions">
        <a href="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/materials/new" class="button"><?= View::tr('+ New material') ?></a>
    </div>
</div>

<form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/language" class="toolbar">
    <?= Csrf::field() ?>
    <label for="topic-lang" style="margin:0"><?= View::tr('Content language') ?></label>
    <select name="lang" id="topic-lang" onchange="this.form.submit()" style="width:auto;margin:0">
        <?php foreach ($languages as $language): ?>
            <option value="<?= View::e($language['code']) ?>" <?= $language['code'] === $topicLang ? 'selected' : '' ?>><?= View::e($language['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit"><?= View::tr('Set') ?></button></noscript>
</form>

<?php if (empty($materials)): ?>
    <p class="empty"><?= View::tr('No materials yet.') ?></p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($materials as $i => $material): ?>
            <li>
                <a href="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/materials/<?= rawurlencode($material['slug']) ?>/edit"><?= View::e($material['title']) ?></a>
                <span class="actions">
                    <form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/materials/<?= rawurlencode($material['slug']) ?>/move" class="inline-form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="button-secondary small" <?= $i === 0 ? 'disabled' : '' ?>>&uarr;</button>
                    </form>
                    <form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/materials/<?= rawurlencode($material['slug']) ?>/move" class="inline-form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="button-secondary small" <?= $i === count($materials) - 1 ? 'disabled' : '' ?>>&darr;</button>
                    </form>
                    <a href="/admin/classes/<?= rawurlencode($classSlug) ?>/exams?lesson=<?= rawurlencode($topicSlug . '/' . $material['slug']) ?>"><?= View::tr('+ Exam') ?></a>
                    <a href="/class/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($topicSlug) ?>/<?= rawurlencode($material['slug']) ?>" target="_blank"><?= View::tr('View') ?></a>
                    <form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/materials/<?= rawurlencode($material['slug']) ?>/delete" class="inline-form" onsubmit="return confirm(<?= View::jsq('Delete this material?') ?>);">
                        <?= Csrf::field() ?>
                        <button type="submit" class="danger small"><?= View::tr('Delete') ?></button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<div class="section-title"><h2><?= View::tr('Files') ?></h2></div>
<form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/uploads" enctype="multipart/form-data" class="toolbar">
    <?= Csrf::field() ?>
    <input type="file" name="file" required style="margin:0;width:auto">
    <button type="submit"><?= View::tr('Upload') ?></button>
</form>

<?php if (empty($uploads)): ?>
    <p class="empty"><?= View::tr('No files uploaded yet.') ?></p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($uploads as $upload): ?>
            <li>
                <a href="<?= View::e($upload['url']) ?>" target="_blank"><?= View::e($upload['filename']) ?></a>
                <span class="actions">
                    <button type="button" class="copy-url button-secondary small" data-url="<?= View::e($upload['url']) ?>"><?= View::tr('Copy URL') ?></button>
                    <form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/uploads/<?= rawurlencode($upload['filename']) ?>/delete" class="inline-form" onsubmit="return confirm(<?= View::jsq('Delete this file?') ?>);">
                        <?= Csrf::field() ?>
                        <button type="submit" class="danger small"><?= View::tr('Delete') ?></button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<script type="application/json" id="admin-i18n"><?= json_encode(I18n::group(I18n::defaultLang(), 'admin_js.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= View::e(View::asset('/assets/admin.js')) ?>"></script>
