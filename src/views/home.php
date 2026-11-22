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

<form method="post" action="/exams/enter" class="form-narrow code-form">
    <?= Csrf::field() ?>
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
