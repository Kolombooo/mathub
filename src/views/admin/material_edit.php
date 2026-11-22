<?php $topicUrl = '/admin/classes/' . rawurlencode($classSlug) . '/topics/' . rawurlencode($topicSlug); ?>
<form method="post" action="<?= $topicUrl ?>/materials/save" id="material-form" class="doc-form">
    <?= Csrf::field() ?>
    <?php if ($materialSlug): ?>
        <input type="hidden" name="material_slug" value="<?= View::e($materialSlug) ?>">
    <?php endif; ?>
    <header class="doc-header">
        <a class="doc-back" href="<?= $topicUrl ?>" title="Back to <?= View::e($topicTitle) ?>" aria-label="Back">&#8592;</a>
        <div class="doc-heading">
            <input type="text" name="title" id="material-title" class="doc-title" value="<?= View::e($materialTitle) ?>" placeholder="Untitled material" aria-label="Title" required>
            <div class="doc-crumbs"><?= View::e($classTitle) ?> &rsaquo; <?= View::e($topicTitle) ?></div>
        </div>
        <button type="button" id="attach-file-btn" class="button-secondary">📎 Attach file</button>
        <a href="<?= $topicUrl ?>" class="button-secondary">Cancel</a>
        <button type="submit" class="doc-save">Save</button>
    </header>
    <div id="editor"></div>
    <textarea name="body" id="material-body" hidden><?= View::e($body) ?></textarea>
</form>

<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css">
<script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
<script>
    window.MATHUB_UPLOAD_URL = "/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/uploads";
    window.MATHUB_CSRF = "<?= Csrf::token() ?>";
</script>
<script src="<?= View::e(View::asset('/assets/admin.js')) ?>"></script>
