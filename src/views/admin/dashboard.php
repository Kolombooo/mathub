<div class="page-header">
    <div>
        <h1><?= View::tr('Classes') ?></h1>
        <p class="page-sub"><?= View::tr('Select a class to manage its topics, materials and exams.') ?></p>
    </div>
</div>

<form method="post" action="/admin/classes" class="toolbar">
    <?= Csrf::field() ?>
    <input type="text" name="title" placeholder="<?= View::tr('New class name') ?>" required aria-label="<?= View::tr('New class name') ?>" style="margin:0;width:280px">
    <button type="submit"><?= View::tr('Add class') ?></button>
</form>

<?php if (empty($classes)): ?>
    <p class="empty"><?= View::tr('No classes yet. Add one above.') ?></p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($classes as $class): ?>
            <?php $classUrl = '/admin/classes/' . rawurlencode($class['slug']); ?>
            <li>
                <a href="<?= $classUrl ?>"><?= View::e($class['title']) ?></a>
                <span class="actions">
                    <a href="<?= $classUrl ?>/exams"><?= View::tr('Exams') ?></a>
                    <a href="<?= $classUrl ?>"><?= View::tr('Topics') ?></a>
                    <details class="menu">
                        <summary aria-label="<?= View::tr('Class actions') ?>"><?= AdminNav::icon('more_vert') ?></summary>
                        <div class="menu-panel">
                            <form method="post" action="<?= $classUrl ?>/rename">
                                <?= Csrf::field() ?>
                                <input type="text" name="title" value="<?= View::e($class['title']) ?>" required aria-label="<?= View::tr('Class name') ?>">
                                <button type="submit" class="small"><?= View::tr('Rename') ?></button>
                            </form>
                            <hr>
                            <form method="post" action="<?= $classUrl ?>/delete" onsubmit="return confirm(<?= View::jsq('Delete this class and everything in it?') ?>);">
                                <?= Csrf::field() ?>
                                <button type="submit" class="danger small"><?= View::tr('Delete class') ?></button>
                            </form>
                        </div>
                    </details>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
