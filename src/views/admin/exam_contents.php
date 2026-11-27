<div class="page-header">
    <div>
        <h1><?= View::tr('Exam content library') ?></h1>
    </div>
</div>
<p class="empty"><?= View::tr('An exam content is a series of questions. Build it once here, then pick it when you create an exam in a lesson. Each exam uses exactly one content; one content can be used by several exams.') ?></p>

<form method="post" action="/admin/exam-contents" class="toolbar">
    <?= Csrf::field() ?>
    <input type="text" name="title" placeholder="<?= View::tr('New exam content title') ?>" required aria-label="<?= View::tr('New exam content title') ?>" style="margin:0;width:280px">
    <select name="kind" aria-label="<?= View::tr('Exam type') ?>" style="margin:0;width:auto">
        <option value="written"><?= View::tr('Written') ?></option>
        <option value="code"><?= View::tr('Code') ?></option>
        <option value="essay"><?= View::tr('Essay') ?></option>
    </select>
    <button type="submit"><?= View::tr('Add exam content') ?></button>
</form>

<?php if (empty($contents)): ?>
    <p class="empty"><?= View::tr('No exam content yet.') ?></p>
<?php else: ?>
    <ul class="manage-list">
        <?php foreach ($contents as $c): ?>
            <li>
                <span>
                    <a href="/admin/exam-contents/<?= rawurlencode($c['id']) ?>"><?= View::e($c['title']) ?></a>
                    <span class="badge <?= $c['kind'] === 'code' ? 'badge-blue' : '' ?>"><?= View::tr(['written' => 'Written', 'code' => 'Code', 'essay' => 'Essay'][$c['kind']]) ?></span>
                    <span class="item-meta"><?= View::tr('{n} questions · {pts} pts · used by {used} exam(s)', ['n' => count($c['questions']), 'pts' => round(Exams::contentMaxScore($c), 2), 'used' => (int)($usage[$c['id']] ?? 0)]) ?></span>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
