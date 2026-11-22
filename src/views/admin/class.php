<?php $base = '/admin/classes/' . rawurlencode($classSlug); ?>
<div class="page-header">
    <div>
        <h1>Topics</h1>
        <p class="page-sub"><?= View::e($classTitle) ?></p>
    </div>
</div>

<div class="panel">
    <form method="post" action="<?= $base ?>/language" class="toolbar" style="margin:0">
        <?= Csrf::field() ?>
        <label for="class-lang" style="margin:0">Class page &amp; exam language</label>
        <select name="lang" id="class-lang" onchange="this.form.submit()" style="width:auto;margin:0">
            <?php foreach (I18n::available() as $language): ?>
                <option value="<?= View::e($language['code']) ?>" <?= $language['code'] === Storage::getClassLang($classSlug) ? 'selected' : '' ?>><?= View::e($language['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="small">Set</button></noscript>
    </form>
</div>

<form method="post" action="<?= $base ?>/topics" class="toolbar">
    <?= Csrf::field() ?>
    <input type="text" name="title" placeholder="New topic name" required aria-label="New topic name" style="margin:0;width:280px">
    <button type="submit">Add topic</button>
</form>

<?php if (empty($topics)): ?>
    <p class="empty">No topics yet. Add one above.</p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($topics as $topic): ?>
            <?php $topicUrl = $base . '/topics/' . rawurlencode($topic['slug']); ?>
            <li>
                <a href="<?= $topicUrl ?>"><?= View::e($topic['title']) ?></a>
                <span class="actions">
                    <a href="<?= $topicUrl ?>">Open</a>
                    <details class="menu">
                        <summary aria-label="Topic actions"><?= AdminNav::icon('more_vert') ?></summary>
                        <div class="menu-panel">
                            <form method="post" action="<?= $topicUrl ?>/rename">
                                <?= Csrf::field() ?>
                                <input type="text" name="title" value="<?= View::e($topic['title']) ?>" required aria-label="Topic name">
                                <button type="submit" class="small">Rename</button>
                            </form>
                            <hr>
                            <form method="post" action="<?= $topicUrl ?>/delete" onsubmit="return confirm('Delete this topic and everything in it?');">
                                <?= Csrf::field() ?>
                                <button type="submit" class="danger small">Delete topic</button>
                            </form>
                        </div>
                    </details>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
