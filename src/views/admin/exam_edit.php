<?php $base = '/admin/classes/' . rawurlencode($classSlug); $examBase = $base . '/exams/' . rawurlencode($exam['id']); ?>
<p class="breadcrumbs">
    <a href="/admin"><?= View::tr('Admin') ?></a> &rsaquo;
    <a href="<?= $base ?>"><?= View::e($classTitle) ?></a> &rsaquo;
    <a href="<?= $base ?>/exams"><?= View::tr('Exams') ?></a>
</p>
<h1><?= View::tr('Edit exam') ?></h1>
<p><a href="<?= $examBase ?>/submissions" class="button-secondary"><?= View::tr('Submissions') ?></a></p>

<form method="post" action="<?= $examBase ?>/save" id="exam-form">
    <?= Csrf::field() ?>
    <label><?= View::tr('Title') ?>
        <input type="text" name="title" value="<?= View::e($exam['title']) ?>" required>
    </label>
    <label><?= View::tr('Lesson') ?>
        <select name="lesson" required>
            <?php $cur = ($exam['topic'] ?? '') . '/' . ($exam['material'] ?? ''); $found = false; ?>
            <?php foreach ($lessons as $l): $sel = $l['value'] === $cur; $found = $found || $sel; ?>
                <option value="<?= View::e($l['value']) ?>" <?= $sel ? 'selected' : '' ?>><?= View::e($l['topic'] . ' › ' . $l['title']) ?></option>
            <?php endforeach; ?>
            <?php if (!$found): ?><option value="" selected disabled><?= View::tr('— choose a lesson —') ?></option><?php endif; ?>
        </select>
    </label>
    <label><?= View::tr('Exam code') ?>
        <input type="text" name="code" value="<?= View::e($exam['code'] ?? '') ?>" minlength="4" maxlength="40" required autocomplete="off">
    </label>
    <label class="check">
        <input type="checkbox" name="generate" value="1">
        <?= View::tr('Generate a new random code when saving') ?>
    </label>
    <label><?= View::tr('Exam content') ?>
        <select name="content_id" <?= ($submissionCount > 0 && !empty($exam['contentId'])) ? 'disabled' : '' ?>>
            <option value=""><?= View::tr('— none —') ?></option>
            <?php foreach ($contents as $c): ?>
                <option value="<?= View::e($c['id']) ?>" <?= ($exam['contentId'] ?? null) === $c['id'] ? 'selected' : '' ?>><?= View::e($c['title']) ?> <?= View::tr('({n} questions)', ['n' => count($c['questions'])]) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if ($submissionCount > 0 && !empty($exam['contentId'])): ?>
        <input type="hidden" name="content_id" value="<?= View::e($exam['contentId']) ?>">
        <p class="muted"><?= View::tr('The content is locked because this exam already has {n} submission(s).', ['n' => (int)$submissionCount]) ?></p>
    <?php endif; ?>
    <?php if (!empty($exam['contentId'])): ?>
        <p class="muted"><?= View::tr('{n} questions', ['n' => count($exam['questions'])]) ?> &middot; <a href="/admin/exam-contents/<?= rawurlencode($exam['contentId']) ?>"><?= View::tr('Edit this content in the library') ?></a> <?= View::tr('(changes apply to every exam using it)') ?></p>
    <?php else: ?>
        <p class="muted"><?= View::tr('No content selected.') ?> <a href="/admin/exam-contents"><?= View::tr('Open the library to create one.') ?></a></p>
    <?php endif; ?>
    <label class="check">
        <input type="checkbox" name="published" value="1" <?= !empty($exam['published']) ? 'checked' : '' ?>>
        <?= View::tr('Published (students can open it with the exam code)') ?>
    </label>

    <fieldset class="settings">
        <legend><?= View::tr('Anti-cheating') ?></legend>
        <?php $st = Exams::settingsOf($exam); ?>
        <label class="check">
            <input type="checkbox" name="monitor" value="1" <?= $st['monitor'] ? 'checked' : '' ?>>
            <?= View::tr('Log leaving the page (tab switch, new window), closing the browser and leaving fullscreen. Students are told this is recorded.') ?>
        </label>
        <label class="check">
            <input type="checkbox" name="fullscreen" value="1" <?= $st['fullscreen'] ? 'checked' : '' ?>>
            <?= View::tr('Start the exam in fullscreen and require it until the exam is submitted') ?>
        </label>
        <label><?= View::tr('Time limit in minutes (0 = no limit)') ?>
            <input type="number" name="time_limit" min="0" max="1440" step="1" value="<?= (int)$st['timeLimitMin'] ?>">
        </label>
        <p class="muted"><?= View::tr('A countdown is shown and the answers are submitted automatically at zero. The server also checks the time: a submission arriving more than a minute late is kept but flagged.') ?></p>
    </fieldset>

    <div class="form-actions">
        <button type="submit"><?= View::tr('Save exam') ?></button>
        <a href="<?= $base ?>/exams" class="button-secondary"><?= View::tr('Back') ?></a>
    </div>
</form>

