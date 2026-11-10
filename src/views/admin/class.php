<p class="breadcrumbs"><a href="/admin">Admin</a></p>
<h1><?= View::e($classTitle) ?> &middot; Topics</h1>

<p>
    <a href="/admin/classes/<?= rawurlencode($classSlug) ?>/exams" class="button">Exams</a>
</p>
<form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/language" class="inline-form form-add">
    <?= Csrf::field() ?>
    <label for="class-lang" style="display:inline;font-weight:normal;">Class page &amp; exam language:</label>
    <select name="lang" id="class-lang" onchange="this.form.submit()">
        <?php foreach (I18n::available() as $language): ?>
            <option value="<?= View::e($language['code']) ?>" <?= $language['code'] === Storage::getClassLang($classSlug) ? 'selected' : '' ?>><?= View::e($language['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit">Set</button></noscript>
</form>

<form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics" class="inline-form form-add">
    <?= Csrf::field() ?>
    <input type="text" name="title" placeholder="New topic name" required>
    <button type="submit">Add topic</button>
</form>

<?php if (empty($topics)): ?>
    <p class="empty">No topics yet. Add one above.</p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($topics as $topic): ?>
            <li>
                <a href="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topic['slug']) ?>"><?= View::e($topic['title']) ?></a>
                <span class="actions">
                    <form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topic['slug']) ?>/rename" class="inline-form">
                        <?= Csrf::field() ?>
                        <input type="text" name="title" value="<?= View::e($topic['title']) ?>" required>
                        <button type="submit">Rename</button>
                    </form>
                    <form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topic['slug']) ?>/delete" class="inline-form" onsubmit="return confirm('Delete this topic and everything in it?');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="danger">Delete</button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
