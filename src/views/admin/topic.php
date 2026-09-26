<p class="breadcrumbs">
    <a href="/admin">Admin</a> &rsaquo;
    <a href="/admin/classes/<?= rawurlencode($classSlug) ?>"><?= View::e($classTitle) ?></a>
</p>
<h1><?= View::e($topicTitle) ?> &middot; Materials</h1>

<form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/language" class="inline-form form-add">
    <?= Csrf::field() ?>
    <label for="topic-lang" style="display:inline;font-weight:normal;">Content language:</label>
    <select name="lang" id="topic-lang" onchange="this.form.submit()">
        <?php foreach ($languages as $language): ?>
            <option value="<?= View::e($language['code']) ?>" <?= $language['code'] === $topicLang ? 'selected' : '' ?>><?= View::e($language['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit">Set</button></noscript>
</form>

<p><a href="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/materials/new" class="button">+ New material</a></p>

<?php if (empty($materials)): ?>
    <p class="empty">No materials yet.</p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($materials as $i => $material): ?>
            <li>
                <a href="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/materials/<?= rawurlencode($material['slug']) ?>/edit"><?= View::e($material['title']) ?></a>
                <span class="actions">
                    <form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/materials/<?= rawurlencode($material['slug']) ?>/move" class="inline-form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" <?= $i === 0 ? 'disabled' : '' ?>>&uarr;</button>
                    </form>
                    <form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/materials/<?= rawurlencode($material['slug']) ?>/move" class="inline-form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" <?= $i === count($materials) - 1 ? 'disabled' : '' ?>>&darr;</button>
                    </form>
                    <a href="/class/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($topicSlug) ?>/<?= rawurlencode($material['slug']) ?>" target="_blank">View</a>
                    <form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/materials/<?= rawurlencode($material['slug']) ?>/delete" class="inline-form" onsubmit="return confirm('Delete this material?');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="danger">Delete</button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Files</h2>
<form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/uploads" enctype="multipart/form-data" class="inline-form form-add">
    <?= Csrf::field() ?>
    <input type="file" name="file" required>
    <button type="submit">Upload</button>
</form>

<?php if (empty($uploads)): ?>
    <p class="empty">No files uploaded yet.</p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($uploads as $upload): ?>
            <li>
                <a href="<?= View::e($upload['url']) ?>" target="_blank"><?= View::e($upload['filename']) ?></a>
                <span class="actions">
                    <button type="button" class="copy-url" data-url="<?= View::e($upload['url']) ?>">Copy URL</button>
                    <form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/uploads/<?= rawurlencode($upload['filename']) ?>/delete" class="inline-form" onsubmit="return confirm('Delete this file?');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="danger">Delete</button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<script src="<?= View::e(View::asset('/assets/admin.js')) ?>"></script>
