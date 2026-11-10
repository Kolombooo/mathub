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
    <label class="check">
        <input type="checkbox" name="published" value="1" <?= !empty($exam['published']) ? 'checked' : '' ?>>
        Published (visible to students who know the class code)
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
        <button type="submit">Save exam</button>
        <a href="<?= $base ?>/exams" class="button-secondary">Back</a>
    </div>
</form>

<script type="application/json" id="exam-data"><?= json_encode($exam['questions'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= View::e(View::asset('/assets/exam_builder.js')) ?>"></script>
