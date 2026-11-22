<?php $base = '/admin/classes/' . rawurlencode($classSlug); ?>
<div class="page-header">
    <div>
        <h1><?= View::tr('Exams') ?></h1>
        <p class="page-sub"><?= View::e($classTitle) ?></p>
    </div>
</div>
<p class="empty"><?= View::tr('An exam belongs to one lesson, has its own code and uses one exam content from your library. Students enter the code on the home page or on the lesson page.') ?> <a href="/admin/exam-contents"><?= View::tr('Exam content library') ?></a></p>

<div class="section-title"><h2><?= View::tr('New exam') ?></h2></div>
<?php if (empty($lessons)): ?>
    <p class="empty"><?= View::tr('Create a lesson (material) in a topic first.') ?></p>
<?php else: ?>
<form method="post" action="<?= $base ?>/exams" class="panel form-narrow" style="max-width:480px">
    <?= Csrf::field() ?>
    <label><?= View::tr('Title') ?>
        <input type="text" name="title" required>
    </label>
    <label><?= View::tr('Lesson') ?>
        <select name="lesson" required>
            <?php foreach ($lessons as $l): ?>
                <option value="<?= View::e($l['value']) ?>" <?= $l['value'] === $preselect ? 'selected' : '' ?>><?= View::e($l['topic'] . ' › ' . $l['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><?= View::tr('Exam content') ?>
        <select name="content_id">
            <option value=""><?= View::tr('— choose later —') ?></option>
            <?php foreach ($contents as $c): ?>
                <option value="<?= View::e($c['id']) ?>"><?= View::e($c['title']) ?> <?= View::tr('({n} questions)', ['n' => count($c['questions'])]) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit"><?= View::tr('Add exam') ?></button>
</form>
<?php endif; ?>

<div class="section-title"><h2><?= View::tr('All exams') ?></h2></div>
<?php if (empty($exams)): ?>
    <p class="empty"><?= View::tr('No exams yet.') ?></p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($exams as $exam): ?>
            <?php
            $examBase = $base . '/exams/' . rawurlencode($exam['id']);
            $lessonTitle = (!empty($exam['topic']) && !empty($exam['material']) && Storage::materialExists($classSlug, $exam['topic'], $exam['material']))
                ? Storage::getMaterialTitle($classSlug, $exam['topic'], $exam['material']) : null;
            ?>
            <li>
                <span>
                    <a href="<?= $examBase ?>"><?= View::e($exam['title']) ?></a>
                    <span class="badge <?= !empty($exam['published']) ? 'badge-ok' : '' ?>"><?= !empty($exam['published']) ? View::tr('Published') : View::tr('Draft') ?></span>
                    <span class="item-meta"><?= View::tr('code') ?> <strong><?= View::e($exam['code'] ?? '–') ?></strong> &middot; <?= View::tr('lesson:') ?> <?= $lessonTitle !== null ? View::e($lessonTitle) : '–' ?> &middot; <?= View::tr('content:') ?> <?= View::e($exam['contentTitle'] ?? '–') ?></span>
                </span>
                <span class="actions">
                    <a href="<?= $examBase ?>/submissions" class="button-secondary small"><?= View::tr('Submissions') ?></a>
                    <form method="post" action="<?= $examBase ?>/delete" class="inline-form" onsubmit="return confirm(<?= View::jsq('Delete this exam and all submissions? The exam content stays in your library.') ?>);">
                        <?= Csrf::field() ?>
                        <button type="submit" class="danger small"><?= View::tr('Delete') ?></button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
