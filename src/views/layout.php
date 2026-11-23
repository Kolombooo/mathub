<?php
$requestPath = rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
$isAdminShell = AdminNav::isAdminPage($requestPath);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="<?= View::e($lang ?? I18n::defaultLang()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= isset($title) ? View::e($title) . ' · MatHub' : 'MatHub' ?></title>
<link rel="preload" href="/assets/fonts/hanken-grotesk-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= View::e(View::asset('/assets/style.css')) ?>">
</head>
<?php if ($isAdminShell): $nav = AdminNav::build($requestPath); ?>
<body class="admin-body <?= View::e($bodyClass ?? '') ?>">
<input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
<div class="admin-shell">
    <aside class="sidebar" aria-label="<?= View::tr('Teacher navigation') ?>">
        <div class="sidebar-head">
            <a class="brand" href="/admin"><?= View::tr('MatHub') ?></a>
            <label for="nav-toggle" class="sidebar-close" aria-label="<?= View::tr('Close menu') ?>"><?= AdminNav::icon('close') ?></label>
        </div>

        <?php if ($nav['class'] !== null): ?>
            <a class="sidebar-back" href="/admin"><?= AdminNav::icon('arrow_back') ?><span><?= View::tr('All classes') ?></span></a>
            <div class="sidebar-class">
                <span class="sidebar-eyebrow"><?= View::tr('Class') ?></span>
                <strong><?= View::e($nav['class']['title']) ?></strong>
            </div>
        <?php endif; ?>

        <nav class="sidebar-nav">
            <?php foreach ($nav['sections'] as $section): ?>
                <div class="sidebar-section">
                    <?php if ($section['label'] !== null): ?>
                        <span class="sidebar-eyebrow"><?= View::e($section['label']) ?></span>
                    <?php endif; ?>
                    <ul>
                        <?php foreach ($section['items'] as $item): ?>
                            <li>
                                <a class="sidebar-link<?= $item['active'] ? ' is-active' : '' ?>" href="<?= View::e($item['href']) ?>"<?= !empty($item['external']) ? ' target="_blank" rel="noopener"' : '' ?><?= $item['active'] ? ' aria-current="page"' : '' ?>>
                                    <?= AdminNav::icon($item['icon']) ?>
                                    <span><?= View::e($item['label']) ?></span>
                                </a>
                                <?php if (!empty($item['children'])): ?>
                                    <ul class="sidebar-sub">
                                        <?php foreach ($item['children'] as $child): ?>
                                            <li><a class="sidebar-sublink<?= $child['active'] ? ' is-active' : '' ?>" href="<?= View::e($child['href']) ?>"><?= View::e($child['label']) ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <a class="sidebar-link<?= $requestPath === '/admin/account' ? ' is-active' : '' ?>" href="/admin/account"><?= AdminNav::icon('person') ?><span><?= View::tr('Account') ?></span></a>
            <a class="sidebar-link" href="/" target="_blank" rel="noopener"><?= AdminNav::icon('open_in_new') ?><span><?= View::tr('View student site') ?></span></a>
            <form method="post" action="/admin/logout">
                <?= Csrf::field() ?>
                <button type="submit" class="sidebar-link"><?= AdminNav::icon('logout') ?><span><?= View::tr('Log out') ?></span></button>
            </form>
        </div>
    </aside>
    <label for="nav-toggle" class="sidebar-scrim" aria-hidden="true"></label>

    <div class="admin-content">
        <header class="admin-topbar">
            <label for="nav-toggle" class="topbar-menu" aria-label="<?= View::tr('Open menu') ?>"><?= AdminNav::icon('menu') ?></label>
            <a class="brand" href="/admin"><?= View::tr('MatHub') ?></a>
        </header>
        <main class="admin-main">
            <?php if (!empty($flash)): ?>
                <div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
</body>
<?php else: ?>
<body class="<?= View::e($bodyClass ?? '') ?>">
<header class="site-header">
    <a class="brand" href="/"><?= View::tr('MatHub') ?></a>
    <nav>
        <?php if (Auth::isLoggedIn()): ?>
            <a href="/admin"><?= View::tr('Teacher panel') ?></a>
        <?php else: ?>
            <a href="/admin/login"><?= View::tr('Teacher login') ?></a>
        <?php endif; ?>
    </nav>
</header>
<main class="site-main">
<?php if (!empty($flash)): ?>
    <div class="flash flash-<?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>
<?= $content ?>
</main>
<footer class="site-footer">
    <span>MatHub &mdash; <?= View::tr('Material Hub') ?></span>
</footer>
</body>
<?php endif; ?>
</html>
