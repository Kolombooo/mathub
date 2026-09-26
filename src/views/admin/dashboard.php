<h1>Admin &middot; Classes</h1>

<form method="post" action="/admin/classes" class="inline-form form-add">
    <?= Csrf::field() ?>
    <input type="text" name="title" placeholder="New class name" required>
    <button type="submit">Add class</button>
</form>

<?php if (empty($classes)): ?>
    <p class="empty">No classes yet. Add one above.</p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($classes as $class): ?>
            <li>
                <a href="/admin/classes/<?= rawurlencode($class['slug']) ?>"><?= View::e($class['title']) ?></a>
                <span class="actions">
                    <form method="post" action="/admin/classes/<?= rawurlencode($class['slug']) ?>/rename" class="inline-form">
                        <?= Csrf::field() ?>
                        <input type="text" name="title" value="<?= View::e($class['title']) ?>" required>
                        <button type="submit">Rename</button>
                    </form>
                    <form method="post" action="/admin/classes/<?= rawurlencode($class['slug']) ?>/delete" class="inline-form" onsubmit="return confirm('Delete this class and everything in it?');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="danger">Delete</button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
