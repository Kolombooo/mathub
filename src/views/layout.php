<!DOCTYPE html>
<html lang="<?= View::e($lang ?? I18n::defaultLang()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($title) ? View::e($title) . ' · MatHub' : 'MatHub' ?></title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="<?= View::e($bodyClass ?? '') ?>">
<header class="site-header">
    <a class="brand" href="/">MatHub</a>
    <nav>
        <?php if (Auth::isLoggedIn()): ?>
            <a href="/admin">Admin</a>
            <a href="/admin/account">Account</a>
            <form method="post" action="/admin/logout" class="inline-form">
                <?= Csrf::field() ?>
                <button type="submit" class="link-button">Log out</button>
            </form>
        <?php else: ?>
            <a href="/admin/login">Teacher login</a>
        <?php endif; ?>
    </nav>
</header>
<main class="site-main">
<?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash flash-<?= View::e($_SESSION['flash']['type']) ?>"><?= View::e($_SESSION['flash']['message']) ?></div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
<?= $content ?>
</main>
<footer class="site-footer">
    <span>MatHub &mdash; Material Hub</span>
</footer>
</body>
</html>
