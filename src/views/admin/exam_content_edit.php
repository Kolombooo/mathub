<?php $cb = '/admin/exam-contents/' . rawurlencode($content['id']); ?>
<p class="breadcrumbs">
    <a href="/admin"><?= View::tr('Admin') ?></a> &rsaquo;
    <a href="/admin/exam-contents"><?= View::tr('Exam content library') ?></a>
</p>
<h1><?= View::tr('Edit exam content') ?>
    <span class="badge <?= $content['kind'] === 'code' ? 'badge-blue' : '' ?>"><?= View::tr(['written' => 'Written', 'code' => 'Code', 'upload' => 'Upload'][$content['kind']]) ?></span>
</h1>

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

    <?php $isCode = $content['kind'] === 'code'; ?>
    <h2><?= View::tr($isCode ? 'Assignments' : 'Questions') ?></h2>
    <?php if ($isCode): ?>
        <p class="empty"><?= View::tr('Each assignment has a description written in Markdown (tab "Zadání"), a language and optional starting code (tab "Kód"). Code runs in the student\'s browser; there are no automatic tests, so you score the submitted code by hand. Students never see scores.') ?></p>
    <?php elseif ($content['kind'] === 'upload'): ?>
        <p class="empty"><?= View::tr('Each question asks the student to upload a file. Uploads are scored by hand.') ?></p>
    <?php else: ?>
        <p class="empty"><?= View::tr('Correct answers are optional. Questions without one (and long text, file upload and drawing) are scored by hand. Students never see scores.') ?></p>
    <?php endif; ?>
    <div id="questions"></div>
    <div class="builder-add">
        <?php if ($isCode || $content['kind'] === 'upload'): ?>
            <input type="hidden" id="add-type" value="<?= $isCode ? 'code' : 'upload' ?>">
            <button type="button" id="add-question"><?= View::tr($isCode ? '+ Add assignment' : '+ Add question') ?></button>
        <?php else: ?>
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
        <?php endif; ?>
    </div>

    <input type="hidden" name="questions" id="questions-json">
</form>

<hr class="editor-divider">

<div class="editor-toolbar" role="toolbar" aria-label="<?= View::tr('Edit exam content') ?>">
    <button type="submit" form="exam-form" class="icon-btn primary" title="<?= View::tr('Save exam content') ?>" aria-label="<?= View::tr('Save exam content') ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
    </button>
    <a href="/admin/exam-contents" class="icon-btn" title="<?= View::tr('Back') ?>" aria-label="<?= View::tr('Back') ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    </a>
    <span class="toolbar-spacer"></span>
    <button type="button" class="icon-btn" id="copy-open" title="<?= View::tr('Duplicate') ?>" aria-label="<?= View::tr('Duplicate') ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
    </button>
    <form method="post" action="<?= $cb ?>/delete" class="inline-form" onsubmit="return confirm(<?= View::jsq('Delete this exam content?') ?>);">
        <?= Csrf::field() ?>
        <button type="submit" class="icon-btn danger" <?= $usedBy ? 'disabled title="' . View::tr('Used by an exam') . '"' : 'title="' . View::tr('Delete') . '"' ?> aria-label="<?= View::tr('Delete') ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        </button>
    </form>
</div>

<dialog id="copy-dialog" class="copy-dialog">
    <form method="post" action="<?= $cb ?>/duplicate">
        <?= Csrf::field() ?>
        <label><?= View::tr('Name of the copy') ?>
            <input type="text" name="title" value="<?= View::e($content['title'] . ' (copy)') ?>" maxlength="200" required>
        </label>
        <div class="modal-actions">
            <button type="button" class="button-secondary" id="copy-cancel"><?= View::tr('Cancel') ?></button>
            <button type="submit"><?= View::tr('Create copy') ?></button>
        </div>
    </form>
</dialog>
<script>
(function () {
    var d = document.getElementById('copy-dialog');
    document.getElementById('copy-open').addEventListener('click', function () {
        d.showModal();
        var i = d.querySelector('input[name=title]');
        i.focus(); i.select();
    });
    document.getElementById('copy-cancel').addEventListener('click', function () { d.close(); });
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
})();
</script>

<script type="application/json" id="exam-data"><?= json_encode($content['questions'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="application/json" id="builder-i18n"><?= json_encode(I18n::group(I18n::defaultLang(), 'builder.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php if ($content['kind'] === 'code'): ?>
<script src="<?= View::e(View::asset('/assets/vendor/codeeditor.min.js')) ?>"></script>
<?php endif; ?>
<script src="<?= View::e(View::asset('/assets/exam_builder.js')) ?>"></script>
