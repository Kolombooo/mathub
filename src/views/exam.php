<p class="breadcrumbs">
    <a href="/"><?= View::e(I18n::t($lang, 'breadcrumb.classes')) ?></a> &rsaquo;
    <a href="/class/<?= rawurlencode($classSlug) ?>"><?= View::e($classTitle) ?></a>
</p>
<h1><?= View::e($exam['title']) ?></h1>

<div id="exam-app"
     data-start-url="/exams/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($exam['id']) ?>/start"
     data-submit-url="/exams/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($exam['id']) ?>/submit/"
     data-storage-key="mathub-exam-<?= View::e($classSlug) ?>-<?= View::e($exam['id']) ?>"
     data-save-url="/exams/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($exam['id']) ?>/save/"
     data-runner-url="/exams/code-runner?v=<?= (int)@filemtime(dirname(__DIR__) . '/runner/code_runner.html') ?>"
     data-pyodide-base="<?= View::e(Exams::pyodideBase()) ?>"
     data-event-url="/exams/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($exam['id']) ?>/event/"
     data-settings="<?= View::e(json_encode(Exams::settingsOf($exam))) ?>"
     data-csrf="<?= View::e(Csrf::token()) ?>">

    <form id="exam-start" class="form-narrow">
        <p class="empty"><?= View::e(I18n::t($lang, 'exam.intro')) ?></p>
        <label><?= View::e(I18n::t($lang, 'exam.first_name')) ?>
            <input type="text" name="first_name" required maxlength="80" autocomplete="off">
        </label>
        <label><?= View::e(I18n::t($lang, 'exam.last_name')) ?>
            <input type="text" name="last_name" required maxlength="80" autocomplete="off">
        </label>
        <?php $examSettings = Exams::settingsOf($exam); ?>
        <?php if ($examSettings['monitor']): ?>
            <p class="notice"><?= View::e(I18n::t($lang, 'exam.monitor_notice')) ?></p>
        <?php endif; ?>
        <?php if ($examSettings['timeLimitMin'] > 0): ?>
            <p class="notice"><?= View::e(str_replace('{n}', (string)(int)$examSettings['timeLimitMin'], I18n::t($lang, 'exam.time_limit_notice'))) ?></p>
        <?php endif; ?>
        <div class="flash flash-error" id="exam-start-error" hidden></div>
        <button type="submit"><?= View::e(I18n::t($lang, 'exam.start')) ?></button>
    </form>

    <div id="exam-main" hidden></div>
</div>

<script type="application/json" id="exam-i18n"><?= json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php /* The 300 KB drawing library is only needed when the exam has a drawing question. */ ?>
<?php if (array_filter((array)($exam['questions'] ?? []), fn($q) => ($q['type'] ?? '') === 'draw')): ?>
<script src="<?= View::e(View::asset('/assets/vendor/fabric.min.js')) ?>"></script>
<?php endif; ?>
<?php if (($exam['kind'] ?? 'written') === 'essay'): ?>
<script src="<?= View::e(View::asset('/assets/exam_essay.js')) ?>"></script>
<?php endif; ?>
<?php if (($exam['kind'] ?? 'written') === 'code'): ?>
<script src="<?= View::e(View::asset('/assets/vendor/codeeditor.min.js')) ?>"></script>
<script src="<?= View::e(View::asset('/assets/exam_code.js')) ?>"></script>
<?php endif; ?>
<script src="<?= View::e(View::asset('/assets/exam.js')) ?>"></script>
<script src="<?= View::e(View::asset('/assets/exam_guard.js')) ?>"></script>
