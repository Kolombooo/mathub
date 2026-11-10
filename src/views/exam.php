<p class="breadcrumbs">
    <a href="/"><?= View::e(I18n::t($lang, 'breadcrumb.classes')) ?></a> &rsaquo;
    <a href="/class/<?= rawurlencode($classSlug) ?>?tab=exams"><?= View::e($classTitle) ?> &middot; <?= View::e(I18n::t($lang, 'exam.breadcrumb')) ?></a>
</p>
<h1><?= View::e($exam['title']) ?></h1>

<div id="exam-app"
     data-start-url="/exams/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($exam['id']) ?>/start"
     data-submit-url="/exams/<?= rawurlencode($classSlug) ?>/<?= rawurlencode($exam['id']) ?>/submit/"
     data-storage-key="mathub-exam-<?= View::e($classSlug) ?>-<?= View::e($exam['id']) ?>"
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
        <div class="flash flash-error" id="exam-start-error" hidden></div>
        <button type="submit"><?= View::e(I18n::t($lang, 'exam.start')) ?></button>
    </form>

    <div id="exam-main" hidden></div>
</div>

<script type="application/json" id="exam-i18n"><?= json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= View::e(View::asset('/assets/vendor/fabric.min.js')) ?>"></script>
<script src="<?= View::e(View::asset('/assets/exam.js')) ?>"></script>
<script src="<?= View::e(View::asset('/assets/exam_guard.js')) ?>"></script>
