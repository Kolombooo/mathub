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

<?php if (!empty($hasExam) || !empty($codeError)): ?>
<form method="post" action="/exams/enter" class="form-narrow code-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="back" value="<?= View::e('/class/' . rawurlencode($classSlug) . '/' . rawurlencode($topicSlug) . '/' . rawurlencode($materialSlug)) ?>">
    <h2><?= View::e(I18n::t($lang, 'exams.code.title')) ?></h2>
    <p class="empty"><?= View::e(I18n::t($lang, 'exams.code.hint')) ?></p>
    <?php if ($codeError): ?>
        <div class="flash flash-error"><?= View::e(I18n::t($lang, 'exams.code.wrong')) ?></div>
    <?php endif; ?>
    <label><?= View::e(I18n::t($lang, 'exams.code.label')) ?>
        <input type="text" name="code" required autocomplete="off" autocapitalize="characters">
    </label>
    <button type="submit"><?= View::e(I18n::t($lang, 'exams.code.submit')) ?></button>
</form>
<?php endif; ?>
