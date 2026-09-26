<h1>Account</h1>
<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= View::e($error) ?></div>
<?php endif; ?>
<form method="post" action="/admin/account/password" class="form-narrow">
    <?= Csrf::field() ?>
    <label>Current password
        <input type="password" name="current_password" required>
    </label>
    <label>New password
        <input type="password" name="new_password" required minlength="8">
    </label>
    <button type="submit">Change password</button>
</form>
