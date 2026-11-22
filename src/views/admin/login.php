<div class="login-wrap">
    <h1><?= View::tr('Teacher login') ?></h1>
    <p class="muted"><?= View::tr('Sign in to manage classes, materials and exams.') ?></p>
    <?php if (!empty($error)): ?>
        <div class="flash flash-error"><?= View::e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/admin/login">
        <?= Csrf::field() ?>
        <label><?= View::tr('Username') ?>
            <input type="text" name="username" required autofocus>
        </label>
        <label><?= View::tr('Password') ?>
            <input type="password" name="password" required>
        </label>
        <button type="submit"><?= View::tr('Log in') ?></button>
    </form>
</div>
