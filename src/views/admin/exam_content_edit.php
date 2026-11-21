<?php $cb = '/admin/exam-contents/' . rawurlencode($content['id']); ?>
<p class="breadcrumbs">
    <a href="/admin">Admin</a> &rsaquo;
    <a href="/admin/exam-contents">Exam content library</a>
</p>
<h1>Edit exam content</h1>

<?php if ($usedBy): ?>
    <p class="notice">Used by:
        <?php foreach ($usedBy as $i => $e): ?>
            <?= $i ? ', ' : '' ?><a href="/admin/classes/<?= rawurlencode($e['classSlug']) ?>/exams/<?= rawurlencode($e['id']) ?>"><?= View::e($e['title']) ?></a>
        <?php endforeach; ?>.
        Changes apply to all of them. Removing or retyping a question that already has student answers makes those answers unmatched, so prefer adding questions or fixing wording once students have submitted.
    </p>
<?php endif; ?>

<form method="post" action="<?= $cb ?>/save" id="exam-form">
    <?= Csrf::field() ?>
    <label>Title
        <input type="text" name="title" value="<?= View::e($content['title']) ?>" required>
    </label>

    <h2>Questions</h2>
    <p class="empty">Correct answers are optional. Questions without one (and long text, file upload and drawing) are scored by hand. Students never see scores.</p>
    <div id="questions"></div>
    <div class="builder-add">
        <select id="add-type">
            <option value="radio">Radio (one choice)</option>
            <option value="checkbox">Checkbox (multiple choice)</option>
            <option value="select">Select (dropdown)</option>
            <option value="short_text">Short text</option>
            <option value="number">Number</option>
            <option value="long_text">Long text</option>
            <option value="match">Connect A to B</option>
            <option value="upload">Upload file</option>
            <option value="draw">Draw</option>
        </select>
        <button type="button" id="add-question">+ Add question</button>
    </div>

    <input type="hidden" name="questions" id="questions-json">
    <div class="form-actions">
        <button type="submit">Save exam content</button>
        <a href="/admin/exam-contents" class="button-secondary">Back</a>
    </div>
</form>

<div class="form-actions">
    <form method="post" action="<?= $cb ?>/duplicate" class="inline-form">
        <?= Csrf::field() ?>
        <button type="submit" class="button-secondary">Duplicate</button>
    </form>
    <form method="post" action="<?= $cb ?>/delete" class="inline-form" onsubmit="return confirm('Delete this exam content?');">
        <?= Csrf::field() ?>
        <button type="submit" class="danger" <?= $usedBy ? 'disabled title="Used by an exam"' : '' ?>>Delete</button>
    </form>
</div>

<script type="application/json" id="exam-data"><?= json_encode($content['questions'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= View::e(View::asset('/assets/exam_builder.js')) ?>"></script>
