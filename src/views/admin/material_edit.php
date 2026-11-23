<?php $topicUrl = '/admin/classes/' . rawurlencode($classSlug) . '/topics/' . rawurlencode($topicSlug); ?>
<form method="post" action="<?= $topicUrl ?>/materials/save" id="material-form" class="doc-form">
    <?= Csrf::field() ?>
    <?php if ($materialSlug): ?>
        <input type="hidden" name="material_slug" value="<?= View::e($materialSlug) ?>">
    <?php endif; ?>
    <header class="doc-header">
        <a class="doc-back" href="<?= $topicUrl ?>" title="<?= View::tr('Back to {name}', ['name' => $topicTitle]) ?>" aria-label="<?= View::tr('Back') ?>"><?= AdminNav::icon('arrow_back') ?></a>
        <div class="doc-heading">
            <input type="text" name="title" id="material-title" class="doc-title" value="<?= View::e($materialTitle) ?>" placeholder="<?= View::tr('Untitled material') ?>" aria-label="<?= View::tr('Title') ?>" required>
            <div class="doc-crumbs"><?= View::e($classTitle) ?> &rsaquo; <?= View::e($topicTitle) ?></div>
        </div>
        <div class="doc-actions">
            <button type="button" id="attach-file-btn" class="button-secondary"><?= AdminNav::icon('attach_file') ?><span><?= View::tr('Attach file') ?></span></button>
            <a href="<?= $topicUrl ?>" class="button-secondary"><?= View::tr('Cancel') ?></a>
            <button type="submit" class="doc-save"><?= View::tr('Save') ?></button>
        </div>
    </header>
    <p id="editor-fallback-note" class="doc-notice" role="status" hidden><?= View::tr('The visual editor could not be loaded, so you are editing the Markdown text directly. Saving works as usual.') ?></p>
    <div id="editor"></div>
    <textarea name="body" id="material-body" class="doc-fallback-text" aria-label="<?= View::tr('Material text (Markdown)') ?>" hidden><?= View::e($body) ?></textarea>
</form>

<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css">
<script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
<?php if (I18n::defaultLang() === 'cs'): ?>
<script src="https://uicdn.toast.com/editor/latest/i18n/cs-cz.js"></script>
<?php endif; ?>
<script>
    window.MATHUB_UPLOAD_URL = "/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/uploads";
    window.MATHUB_EDITOR_LANG = <?= json_encode(I18n::defaultLang() === 'cs' ? 'cs-CZ' : 'en-US') ?>;
    window.MATHUB_CSRF = "<?= Csrf::token() ?>";
</script>
<script type="application/json" id="admin-i18n"><?= json_encode(I18n::group(I18n::defaultLang(), 'admin_js.'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= View::e(View::asset('/assets/admin.js')) ?>"></script>
