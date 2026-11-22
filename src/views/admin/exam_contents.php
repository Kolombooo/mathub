<p class="breadcrumbs"><a href="/admin">Admin</a></p>
<h1>Exam content library</h1>
<p class="empty">An exam content is a series of questions. Build it once here, then pick it when you create an exam in a lesson. Each exam uses exactly one content; one content can be used by several exams.</p>

<form method="post" action="/admin/exam-contents" class="inline-form form-add">
    <?= Csrf::field() ?>
    <input type="text" name="title" placeholder="New exam content title" required>
    <button type="submit">Add exam content</button>
</form>

<?php if (empty($contents)): ?>
    <p class="empty">No exam content yet.</p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($contents as $c): ?>
            <li>
                <span>
                    <a href="/admin/exam-contents/<?= rawurlencode($c['id']) ?>"><?= View::e($c['title']) ?></a>
                    <span class="muted"><?= count($c['questions']) ?> questions &middot; <?= round(Exams::contentMaxScore($c), 2) ?> pts &middot; used by <?= (int)($usage[$c['id']] ?? 0) ?> exam(s)</span>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
