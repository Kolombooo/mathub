<p class="breadcrumbs">
    <a href="/"><?= View::e(I18n::t($lang, 'breadcrumb.classes')) ?></a> &rsaquo;
    <a href="/class/<?= rawurlencode($classSlug) ?>"><?= View::e($classTitle) ?></a>
</p>
<h1><?= View::e($topicTitle) ?></h1>
<?php if (empty($materials)): ?>
    <p class="empty"><?= View::e(I18n::t($lang, 'topic.empty')) ?></p>
<?php else: ?>
    <ul class="card-list">
        <?php foreach ($materials as $material): ?>
            <li class="card">
                <a href="/class/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($topicSlug) ?>/<?= rawurlencode($material['slug']) ?>"><?= View::e($material['title']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
