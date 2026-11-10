<?php $base = '/admin/classes/' . rawurlencode($classSlug); ?>
<p class="breadcrumbs">
    <a href="/admin">Admin</a> &rsaquo;
    <a href="<?= $base ?>"><?= View::e($classTitle) ?></a>
</p>
<h1><?= View::e($classTitle) ?> &middot; Exams</h1>

<h2>Class code</h2>
<p class="empty">Students enter this code on the class page (Exams / Testy tab) to see the published exams. Leave empty to lock exams.</p>
<form method="post" action="<?= $base ?>/exams/code" class="inline-form form-add">
    <?= Csrf::field() ?>
    <input type="text" name="code" value="<?= View::e($code) ?>" placeholder="(no code — exams locked)" maxlength="40" autocomplete="off">
    <button type="submit">Save code</button>
    <button type="submit" name="generate" value="1" class="button-secondary">Generate random</button>
</form>

<h2>Exams</h2>
<form method="post" action="<?= $base ?>/exams" class="inline-form form-add">
    <?= Csrf::field() ?>
    <input type="text" name="title" placeholder="New exam title" required>
    <button type="submit">Add exam</button>
</form>

<?php if (empty($exams)): ?>
    <p class="empty">No exams yet.</p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($exams as $exam): ?>
            <?php $examBase = $base . '/exams/' . rawurlencode($exam['id']); ?>
            <li>
                <span>
                    <a href="<?= $examBase ?>"><?= View::e($exam['title']) ?></a>
                    <span class="badge <?= !empty($exam['published']) ? 'badge-ok' : '' ?>"><?= !empty($exam['published']) ? 'Published' : 'Draft' ?></span>
                </span>
                <span class="actions">
                    <a href="<?= $examBase ?>/submissions">Submissions</a>
                    <form method="post" action="<?= $examBase ?>/delete" class="inline-form" onsubmit="return confirm('Delete this exam and all submissions?');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="danger">Delete</button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
