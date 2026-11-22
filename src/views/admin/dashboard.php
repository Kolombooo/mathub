<div class="page-header">
    <div>
        <h1>Classes</h1>
        <p class="page-sub">Select a class to manage its topics, materials and exams.</p>
    </div>
</div>

<form method="post" action="/admin/classes" class="toolbar">
    <?= Csrf::field() ?>
    <input type="text" name="title" placeholder="New class name" required aria-label="New class name" style="margin:0;width:280px">
    <button type="submit">Add class</button>
</form>

<?php if (empty($classes)): ?>
    <p class="empty">No classes yet. Add one above.</p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($classes as $class): ?>
            <?php $classUrl = '/admin/classes/' . rawurlencode($class['slug']); ?>
            <li>
                <a href="<?= $classUrl ?>"><?= View::e($class['title']) ?></a>
                <span class="actions">
                    <a href="<?= $classUrl ?>/exams">Exams</a>
                    <a href="<?= $classUrl ?>">Topics</a>
                    <details class="menu">
                        <summary aria-label="Class actions"><?= AdminNav::icon('more_vert') ?></summary>
                        <div class="menu-panel">
                            <form method="post" action="<?= $classUrl ?>/rename">
                                <?= Csrf::field() ?>
                                <input type="text" name="title" value="<?= View::e($class['title']) ?>" required aria-label="Class name">
                                <button type="submit" class="small">Rename</button>
                            </form>
                            <hr>
                            <form method="post" action="<?= $classUrl ?>/delete" onsubmit="return confirm('Delete this class and everything in it?');">
                                <?= Csrf::field() ?>
                                <button type="submit" class="danger small">Delete class</button>
                            </form>
                        </div>
                    </details>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
