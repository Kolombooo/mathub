<?php $base = '/admin/classes/' . rawurlencode($classSlug); $examBase = $base . '/exams/' . rawurlencode($exam['id']); ?>
<p class="breadcrumbs">
    <a href="/admin">Admin</a> &rsaquo;
    <a href="<?= $base ?>"><?= View::e($classTitle) ?></a> &rsaquo;
    <a href="<?= $base ?>/exams">Exams</a>
</p>
<h1>Edit exam</h1>
<p><a href="<?= $examBase ?>/submissions" class="button-secondary">Submissions</a></p>

<form method="post" action="<?= $examBase ?>/save" id="exam-form">
    <?= Csrf::field() ?>
    <label>Title
        <input type="text" name="title" value="<?= View::e($exam['title']) ?>" required>
    </label>
    <label>Lesson
        <select name="lesson" required>
            <?php $cur = ($exam['topic'] ?? '') . '/' . ($exam['material'] ?? ''); $found = false; ?>
            <?php foreach ($lessons as $l): $sel = $l['value'] === $cur; $found = $found || $sel; ?>
                <option value="<?= View::e($l['value']) ?>" <?= $sel ? 'selected' : '' ?>><?= View::e($l['topic'] . ' › ' . $l['title']) ?></option>
            <?php endforeach; ?>
            <?php if (!$found): ?><option value="" selected disabled>— choose a lesson —</option><?php endif; ?>
        </select>
    </label>
    <label>Exam code
        <input type="text" name="code" value="<?= View::e($exam['code'] ?? '') ?>" minlength="4" maxlength="40" required autocomplete="off">
    </label>
    <label class="check">
        <input type="checkbox" name="generate" value="1">
        Generate a new random code when saving
    </label>
    <label>Exam content
        <select name="content_id" <?= ($submissionCount > 0 && !empty($exam['contentId'])) ? 'disabled' : '' ?>>
            <option value="">— none —</option>
            <?php foreach ($contents as $c): ?>
                <option value="<?= View::e($c['id']) ?>" <?= ($exam['contentId'] ?? null) === $c['id'] ? 'selected' : '' ?>><?= View::e($c['title']) ?> (<?= count($c['questions']) ?> questions)</option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if ($submissionCount > 0 && !empty($exam['contentId'])): ?>
        <input type="hidden" name="content_id" value="<?= View::e($exam['contentId']) ?>">
        <p class="muted">The content is locked because this exam already has <?= (int)$submissionCount ?> submission(s).</p>
    <?php endif; ?>
    <?php if (!empty($exam['contentId'])): ?>
        <p class="muted"><?= count($exam['questions']) ?> questions &middot; <a href="/admin/exam-contents/<?= rawurlencode($exam['contentId']) ?>">Edit this content in the library</a> (changes apply to every exam using it)</p>
    <?php else: ?>
        <p class="muted">No content selected. <a href="/admin/exam-contents">Open the library</a> to create one.</p>
    <?php endif; ?>
    <label class="check">
        <input type="checkbox" name="published" value="1" <?= !empty($exam['published']) ? 'checked' : '' ?>>
        Published (students can open it with the exam code)
    </label>

    <fieldset class="settings">
        <legend>Anti-cheating</legend>
        <?php $st = Exams::settingsOf($exam); ?>
        <label class="check">
            <input type="checkbox" name="monitor" value="1" <?= $st['monitor'] ? 'checked' : '' ?>>
            Log leaving the page (tab switch, new window), closing the browser and leaving fullscreen. Students are told this is recorded.
        </label>
        <label class="check">
            <input type="checkbox" name="fullscreen" value="1" <?= $st['fullscreen'] ? 'checked' : '' ?>>
            Start the exam in fullscreen and require it until the exam is submitted
        </label>
    </fieldset>

    <div class="form-actions">
        <button type="submit">Save exam</button>
        <a href="<?= $base ?>/exams" class="button-secondary">Back</a>
    </div>
</form>

