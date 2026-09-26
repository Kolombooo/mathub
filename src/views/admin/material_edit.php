<p class="breadcrumbs">
    <a href="/admin">Admin</a> &rsaquo;
    <a href="/admin/classes/<?= rawurlencode($classSlug) ?>"><?= View::e($classTitle) ?></a> &rsaquo;
    <a href="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>"><?= View::e($topicTitle) ?></a>
</p>
<h1><?= $materialSlug ? 'Edit material' : 'New material' ?></h1>

<form method="post" action="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/materials/save" id="material-form">
    <?= Csrf::field() ?>
    <?php if ($materialSlug): ?>
        <input type="hidden" name="material_slug" value="<?= View::e($materialSlug) ?>">
    <?php endif; ?>
    <label>Title
        <input type="text" name="title" id="material-title" value="<?= View::e($materialTitle) ?>" required>
    </label>
    <div class="editor-toolbar-extra">
        <button type="button" id="attach-file-btn" class="button-secondary">📎 Attach PDF / file</button>
    </div>
    <div id="editor"></div>
    <textarea name="body" id="material-body" hidden><?= View::e($body) ?></textarea>
    <div class="form-actions">
        <button type="submit">Save material</button>
        <a href="/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>" class="button-secondary">Cancel</a>
    </div>
</form>

<link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css">
<script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
<script>
    window.MATHUB_UPLOAD_URL = "/admin/classes/<?= rawurlencode($classSlug) ?>/topics/<?= rawurlencode($topicSlug) ?>/uploads";
    window.MATHUB_CSRF = "<?= Csrf::token() ?>";
</script>
<script src="/assets/admin.js"></script>
