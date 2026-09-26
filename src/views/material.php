<p class="breadcrumbs">
    <a href="/"><?= View::e(I18n::t($lang, 'breadcrumb.classes')) ?></a> &rsaquo;
    <a href="/class/<?= rawurlencode($classSlug) ?>"><?= View::e($classTitle) ?></a> &rsaquo;
    <a href="/class/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($topicSlug) ?>"><?= View::e($topicTitle) ?></a>
</p>
<article class="material">
<?= $html ?>
</article>
