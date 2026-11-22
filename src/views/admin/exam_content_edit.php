<?php $cb = '/admin/exam-contents/' . rawurlencode($content['id']); ?>
<p class="breadcrumbs">
    <a href="/admin"><?= View::tr('Admin') ?></a> &rsaquo;
    <a href="/admin/exam-contents"><?= View::tr('Exam content library') ?></a>
</p>
<h1><?= View::tr('Edit exam content') ?></h1>

<?php if ($usedBy): ?>
    <p class="notice"><?= View::tr('Used by:') ?>
        <?php foreach ($usedBy as $i => $e): ?>
            <?= $i ? ', ' : '' ?><a href="/admin/classes/<?= rawurlencode($e['classSlug']) ?>/exams/<?= rawurlencode($e['id']) ?>"><?= View::e($e['title']) ?></a>
        <?php endforeach; ?>.
        <?= View::tr('Changes apply to all of them. Removing or retyping a question that already has student answers makes those answers unmatched, so prefer adding questions or fixing wording once students have submitted.') ?>
    </p>
<?php endif; ?>

<form method="post" action="<?= $cb ?>/save" id="exam-form">
    <?= Csrf::field() ?>
    <label><?= View::tr('Title') ?>
        <input type="text" name="title" value="<?= View::e($content['title']) ?>" required>
    </label>

    <h2><?= View::tr('Questions') ?></h2>
    <p class="empty"><?= View::tr('Correct answers are optional. Questions without one (and long text, file upload and drawing) are scored by hand. Students never see scores.') ?></p>
    <div id="questions"></div>
    <div class="builder-add">
        <select id="add-type">
            <option value="radio"><?= View::tr('Radio (one choice)') ?></option>
            <option value="checkbox"><?= View::tr('Checkbox (multiple choice)') ?></option>
            <option value="select"><?= View::tr('Select (dropdown)') ?></option>
            <option value="short_text"><?= View::tr('Short text') ?></option>
            <option value="number"><?= View::tr('Number') ?></option>
            <option value="long_text"><?= View::tr('Long text') ?></option>
            <option value="match"><?= View::tr('Connect A to B') ?></option>
            <option value="upload"><?= View::tr('Upload file') ?></option>
            <option value="draw"><?= View::tr('Draw') ?></option>
        </select>
        <button type="button" id="add-question"><?= View::tr('+ Add question') ?></button>
    </div>

    <input type="hidden" name="questions" id="questions-json">
    <div class="form-actions">
        <button type="submit"><?= View::tr('Save exam content') ?></button>
        <a href="/admin/exam-contents" class="button-secondary"><?= View::tr('Back') ?></a>
    </div>
</form>

<div class="form-actions">
    <form method="post" action="<?= $cb ?>/duplicate" class="inline-form">
        <?= Csrf::field() ?>
        <button type="submit" class="button-secondary"><?= View::tr('Duplicate') ?></button>
    </form>
    <form method="post" action="<?= $cb ?>/delete" class="inline-form" onsubmit="return confirm(<?= View::jsq('Delete this exam content?') ?>);">
        <?= Csrf::field() ?>
        <button type="submit" class="danger" <?= $usedBy ? 'disabled title="' . View::tr('Used by an exam') . '"' : '' ?>><?= View::tr('Delete') ?></button>
    </form>
</div>

<script type="application/json" id="exam-data"><?= json_encode($content['questions'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="application/json" id="builder-i18n"><?= json_encode(I18n::group(I18n::defaultLang(), 'builder.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= View::e(View::asset('/assets/exam_builder.js')) ?>"></script>
