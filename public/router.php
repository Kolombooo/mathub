<?php
// Router used only by PHP's built-in dev server: `php -S localhost:8000 -t public public/router.php`
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false; // serve the requested static file as-is (e.g. /assets/style.css)
}

require __DIR__ . '/index.php';
