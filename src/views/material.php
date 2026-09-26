<p class="breadcrumbs">
    <a href="/"><?= View::e(I18n::t($lang, 'breadcrumb.classes')) ?></a> &rsaquo;
    <a href="/class/<?= rawurlencode($classSlug) ?>"><?= View::e($classTitle) ?></a> &rsaquo;
    <a href="/class/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($topicSlug) ?>"><?= View::e($topicTitle) ?></a>
</p>
<div class="material-layout">
    <?php if (!empty($toc)): ?>
        <nav class="material-toc" aria-label="Table of contents">
            <ul>
                <?php foreach ($toc as $heading): ?>
                    <li class="toc-level-<?= (int)$heading['level'] ?>">
                        <a href="#<?= View::e($heading['id']) ?>"><?= View::e($heading['text']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>
    <article class="material">
<?= $html ?>
    </article>
</div>
