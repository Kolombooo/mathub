<p class="breadcrumbs"><a href="/"><?= View::e(I18n::t($lang, 'breadcrumb.classes')) ?></a></p>
<h1><?= View::e($classTitle) ?></h1>
<?php if (empty($topics)): ?>
    <p class="empty"><?= View::e(I18n::t($lang, 'class.empty')) ?></p>
<?php else: ?>
    <ul class="card-list">
        <?php foreach ($topics as $topic): ?>
            <li class="card">
                <a href="/class/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($topic['slug']) ?>"><?= View::e($topic['title']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
