<?php $classUrl = '/class/' . rawurlencode($classSlug); ?>
<p class="breadcrumbs"><a href="/"><?= View::e(I18n::t($lang, 'breadcrumb.classes')) ?></a></p>
<h1><?= View::e($classTitle) ?></h1>

<nav class="tabs" aria-label="<?= View::e($classTitle) ?>">
    <a href="<?= $classUrl ?>" class="<?= $tab === 'topics' ? 'active' : '' ?>"><?= View::e(I18n::t($lang, 'class.tab.topics')) ?></a>
    <a href="<?= $classUrl ?>?tab=exams" class="<?= $tab === 'exams' ? 'active' : '' ?>"><?= View::e(I18n::t($lang, 'class.tab.exams')) ?></a>
</nav>

<?php if ($tab === 'topics'): ?>
    <?php if (empty($topics)): ?>
        <p class="empty"><?= View::e(I18n::t($lang, 'class.empty')) ?></p>
    <?php else: ?>
        <ul class="card-list">
            <?php foreach ($topics as $topic): ?>
                <li class="card">
                    <a href="<?= $classUrl ?>/<?= rawurlencode($topic['slug']) ?>"><?= View::e($topic['title']) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php elseif (!$examsEnabled): ?>
    <p class="empty"><?= View::e(I18n::t($lang, 'exams.disabled')) ?></p>
<?php elseif (!$unlocked): ?>
    <form method="post" action="/exams/<?= rawurlencode($classSlug) ?>/unlock" class="form-narrow code-form">
        <?= Csrf::field() ?>
        <h2><?= View::e(I18n::t($lang, 'exams.code.title')) ?></h2>
        <p class="empty"><?= View::e(I18n::t($lang, 'exams.code.hint')) ?></p>
        <?php if ($codeError): ?>
            <div class="flash flash-error"><?= View::e(I18n::t($lang, 'exams.code.wrong')) ?></div>
        <?php endif; ?>
        <label><?= View::e(I18n::t($lang, 'exams.code.label')) ?>
            <input type="text" name="code" required autofocus autocomplete="off" autocapitalize="characters">
        </label>
        <button type="submit"><?= View::e(I18n::t($lang, 'exams.code.submit')) ?></button>
    </form>
<?php elseif (empty($exams)): ?>
    <p class="empty"><?= View::e(I18n::t($lang, 'exams.empty')) ?></p>
<?php else: ?>
    <ul class="card-list">
        <?php foreach ($exams as $exam): ?>
            <li class="card">
                <a href="/exams/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($exam['id']) ?>"><?= View::e($exam['title']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
