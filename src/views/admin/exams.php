<?php $base = '/admin/classes/' . rawurlencode($classSlug); ?>
<p class="breadcrumbs">
    <a href="/admin">Admin</a> &rsaquo;
    <a href="<?= $base ?>"><?= View::e($classTitle) ?></a>
</p>
<h1><?= View::e($classTitle) ?> &middot; Exams</h1>
<p class="empty">An exam belongs to one lesson, has its own code and uses one exam content from your <a href="/admin/exam-contents">library</a>. Students enter the code on the home page or on the lesson page.</p>

<h2>New exam</h2>
<?php if (empty($lessons)): ?>
    <p class="empty">Create a lesson (material) in a topic first.</p>
<?php else: ?>
<form method="post" action="<?= $base ?>/exams" class="form-narrow">
    <?= Csrf::field() ?>
    <label>Title
        <input type="text" name="title" required>
    </label>
    <label>Lesson
        <select name="lesson" required>
            <?php foreach ($lessons as $l): ?>
                <option value="<?= View::e($l['value']) ?>" <?= $l['value'] === $preselect ? 'selected' : '' ?>><?= View::e($l['topic'] . ' › ' . $l['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Exam content
        <select name="content_id">
            <option value="">— choose later —</option>
            <?php foreach ($contents as $c): ?>
                <option value="<?= View::e($c['id']) ?>"><?= View::e($c['title']) ?> (<?= count($c['questions']) ?> questions)</option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Add exam</button>
</form>
<?php endif; ?>

<h2>Exams</h2>
<?php if (empty($exams)): ?>
    <p class="empty">No exams yet.</p>
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
                    <span class="badge <?= !empty($exam['published']) ? 'badge-ok' : '' ?>"><?= !empty($exam['published']) ? 'Published' : 'Draft' ?></span>
                    <span class="muted">code <strong><?= View::e($exam['code'] ?? '–') ?></strong> &middot; lesson: <?= $lessonTitle !== null ? View::e($lessonTitle) : '–' ?> &middot; content: <?= View::e($exam['contentTitle'] ?? '–') ?></span>
                </span>
                <span class="actions">
                    <a href="<?= $examBase ?>/submissions">Submissions</a>
                    <form method="post" action="<?= $examBase ?>/delete" class="inline-form" onsubmit="return confirm('Delete this exam and all submissions? The exam content stays in your library.');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="danger">Delete</button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
