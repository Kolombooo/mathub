<h1><?= View::e(I18n::t($lang, 'breadcrumb.classes')) ?></h1>
<?php if (empty($classes)): ?>
    <p class="empty"><?= View::e(I18n::t($lang, 'home.empty')) ?></p>
<?php else: ?>
    <ul class="card-list">
        <?php foreach ($classes as $class): ?>
            <li class="card">
                <a href="/class/<?= rawurlencode($class['slug']) ?>"><?= View::e($class['title']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
