<h1>Teacher login</h1>
<?php if (!empty($error)): ?>
    <div class="flash flash-error"><?= View::e($error) ?></div>
<?php endif; ?>
<form method="post" action="/admin/login" class="form-narrow">
    <?= Csrf::field() ?>
    <label>Username
        <input type="text" name="username" required autofocus>
    </label>
    <label>Password
        <input type="password" name="password" required>
    </label>
    <button type="submit">Log in</button>
</form>
