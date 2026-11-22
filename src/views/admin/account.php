<div class="page-header">
    <div>
        <h1><?= View::tr('Account') ?></h1>
        <p class="page-sub"><?= View::tr('Change your teacher password.') ?></p>
    </div>
</div>
<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= View::e($error) ?></div>
<?php endif; ?>
<form method="post" action="/admin/account/password" class="panel" style="max-width:420px">
    <?= Csrf::field() ?>
    <label><?= View::tr('Current password') ?>
        <input type="password" name="current_password" required>
    </label>
    <label><?= View::tr('New password') ?>
        <input type="password" name="new_password" required minlength="8">
    </label>
    <button type="submit"><?= View::tr('Change password') ?></button>
</form>
