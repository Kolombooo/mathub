<?php
declare(strict_types=1);

session_start();

require dirname(__DIR__) . '/vendor/autoload.php';

View::init(dirname(__DIR__) . '/src/views');
Auth::ensureUserExists();

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function requireCsrf(): void
{
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        echo 'Invalid CSRF token.';
        exit;
    }
}

function notFound(): void
{
    http_response_code(404);
    View::render('404', ['title' => 'Not found']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
if ($path === '') {
    $path = '/';
}
$segments = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
$decoded = array_map('rawurldecode', $segments);

// ---------------------------------------------------------------------
// Public routes
// ---------------------------------------------------------------------

if ($method === 'GET' && $path === '/') {
    $lang = I18n::defaultLang();
    View::render('home', ['title' => I18n::t($lang, 'breadcrumb.classes'), 'lang' => $lang, 'classes' => Storage::listClasses()]);
    exit;
}

if ($method === 'GET' && count($decoded) === 2 && $decoded[0] === 'class') {
    $classSlug = $decoded[1];
    $classTitle = Storage::getClassTitle($classSlug);
    if ($classTitle === null) {
        notFound();
    }
    View::render('class', [
        'title' => $classTitle,
        'lang' => I18n::defaultLang(),
        'classSlug' => $classSlug,
        'classTitle' => $classTitle,
        'topics' => Storage::listTopics($classSlug),
    ]);
    exit;
}

if ($method === 'GET' && count($decoded) === 3 && $decoded[0] === 'class') {
    [, $classSlug, $topicSlug] = $decoded;
    $classTitle = Storage::getClassTitle($classSlug);
    $topicTitle = Storage::getTopicTitle($classSlug, $topicSlug);
    if ($classTitle === null || $topicTitle === null) {
        notFound();
    }
    View::render('topic', [
        'title' => $topicTitle,
        'lang' => Storage::getTopicLang($classSlug, $topicSlug),
        'classSlug' => $classSlug,
        'classTitle' => $classTitle,
        'topicSlug' => $topicSlug,
        'topicTitle' => $topicTitle,
        'materials' => Storage::listMaterials($classSlug, $topicSlug),
    ]);
    exit;
}

if ($method === 'GET' && count($decoded) === 4 && $decoded[0] === 'class') {
    [, $classSlug, $topicSlug, $materialSlug] = $decoded;
    $classTitle = Storage::getClassTitle($classSlug);
    $topicTitle = Storage::getTopicTitle($classSlug, $topicSlug);
    $content = Storage::getMaterialContent($classSlug, $topicSlug, $materialSlug);
    if ($classTitle === null || $topicTitle === null || $content === null) {
        notFound();
    }
    $html = Markdown::toHtml($content);
    View::render('material', [
        'title' => Storage::getMaterialTitle($classSlug, $topicSlug, $materialSlug),
        'bodyClass' => 'page-material',
        'lang' => Storage::getTopicLang($classSlug, $topicSlug),
        'classSlug' => $classSlug,
        'classTitle' => $classTitle,
        'topicSlug' => $topicSlug,
        'topicTitle' => $topicTitle,
        'html' => $html,
        'toc' => Markdown::extractHeadings($html),
    ]);
    exit;
}

if ($method === 'GET' && count($decoded) === 4 && $decoded[0] === 'media') {
    [, $classSlug, $topicSlug, $filename] = $decoded;
    $path = Storage::mediaPath($classSlug, $topicSlug, $filename);
    if ($path === null) {
        notFound();
    }
    $mime = mime_content_type($path) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=86400');
    readfile($path);
    exit;
}

// ---------------------------------------------------------------------
// Admin: auth
// ---------------------------------------------------------------------

if ($path === '/admin/login') {
    if ($method === 'GET') {
        if (Auth::isLoggedIn()) {
            redirect('/admin');
        }
        View::render('admin/login', ['title' => 'Teacher login']);
        exit;
    }
    if ($method === 'POST') {
        requireCsrf();
        if (Auth::attempt((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
            redirect('/admin');
        }
        View::render('admin/login', ['title' => 'Teacher login', 'error' => 'Invalid username or password.']);
        exit;
    }
}

if ($path === '/admin/logout' && $method === 'POST') {
    requireCsrf();
    Auth::logout();
    redirect('/');
}

// Everything else under /admin requires login.
if ($decoded !== [] && $decoded[0] === 'admin' && $path !== '/admin/login') {
    Auth::requireLogin();
}

if ($path === '/admin/account') {
    if ($method === 'GET') {
        View::render('admin/account', ['title' => 'Account']);
        exit;
    }
}

if ($path === '/admin/account/password' && $method === 'POST') {
    requireCsrf();
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    if (strlen($new) < 8) {
        View::render('admin/account', ['title' => 'Account', 'error' => 'New password must be at least 8 characters.']);
        exit;
    }
    if (!Auth::changePassword($current, $new)) {
        View::render('admin/account', ['title' => 'Account', 'error' => 'Current password is incorrect.']);
        exit;
    }
    flash('success', 'Password changed.');
    redirect('/admin/account');
}

// ---------------------------------------------------------------------
// Admin: dashboard / classes
// ---------------------------------------------------------------------

if ($path === '/admin' && $method === 'GET') {
    View::render('admin/dashboard', ['title' => 'Admin', 'classes' => Storage::listClasses()]);
    exit;
}

if ($path === '/admin/classes' && $method === 'POST') {
    requireCsrf();
    try {
        Storage::createClass((string)($_POST['title'] ?? ''));
        flash('success', 'Class created.');
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin');
}

if (count($decoded) === 3 && $decoded[0] === 'admin' && $decoded[1] === 'classes' && $method === 'GET') {
    $classSlug = $decoded[2];
    $classTitle = Storage::getClassTitle($classSlug);
    if ($classTitle === null) {
        notFound();
    }
    View::render('admin/class', [
        'title' => $classTitle,
        'classSlug' => $classSlug,
        'classTitle' => $classTitle,
        'topics' => Storage::listTopics($classSlug),
    ]);
    exit;
}

if (count($decoded) === 4 && $decoded[0] === 'admin' && $decoded[1] === 'classes' && $decoded[3] === 'rename' && $method === 'POST') {
    requireCsrf();
    $classSlug = $decoded[2];
    try {
        Storage::renameClass($classSlug, (string)($_POST['title'] ?? ''));
        flash('success', 'Class renamed.');
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin');
}

if (count($decoded) === 4 && $decoded[0] === 'admin' && $decoded[1] === 'classes' && $decoded[3] === 'delete' && $method === 'POST') {
    requireCsrf();
    Storage::deleteClass($decoded[2]);
    flash('success', 'Class deleted.');
    redirect('/admin');
}

// ---------------------------------------------------------------------
// Admin: topics
// ---------------------------------------------------------------------

if (count($decoded) === 4 && $decoded[0] === 'admin' && $decoded[1] === 'classes' && $decoded[3] === 'topics' && $method === 'POST') {
    requireCsrf();
    $classSlug = $decoded[2];
    try {
        Storage::createTopic($classSlug, (string)($_POST['title'] ?? ''));
        flash('success', 'Topic created.');
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin/classes/' . rawurlencode($classSlug));
}

if (count($decoded) === 5 && $decoded[0] === 'admin' && $decoded[1] === 'classes' && $decoded[3] === 'topics' && $method === 'GET') {
    [, , $classSlug, , $topicSlug] = $decoded;
    $classTitle = Storage::getClassTitle($classSlug);
    $topicTitle = Storage::getTopicTitle($classSlug, $topicSlug);
    if ($classTitle === null || $topicTitle === null) {
        notFound();
    }
    View::render('admin/topic', [
        'title' => $topicTitle,
        'classSlug' => $classSlug,
        'classTitle' => $classTitle,
        'topicSlug' => $topicSlug,
        'topicTitle' => $topicTitle,
        'topicLang' => Storage::getTopicLang($classSlug, $topicSlug),
        'languages' => I18n::available(),
        'materials' => Storage::listMaterials($classSlug, $topicSlug),
        'uploads' => Storage::listUploads($classSlug, $topicSlug),
    ]);
    exit;
}

if (count($decoded) === 6 && $decoded[0] === 'admin' && $decoded[1] === 'classes' && $decoded[3] === 'topics' && $decoded[5] === 'rename' && $method === 'POST') {
    requireCsrf();
    [, , $classSlug, , $topicSlug] = $decoded;
    try {
        Storage::renameTopic($classSlug, $topicSlug, (string)($_POST['title'] ?? ''));
        flash('success', 'Topic renamed.');
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin/classes/' . rawurlencode($classSlug));
}

if (count($decoded) === 6 && $decoded[0] === 'admin' && $decoded[1] === 'classes' && $decoded[3] === 'topics' && $decoded[5] === 'language' && $method === 'POST') {
    requireCsrf();
    [, , $classSlug, , $topicSlug] = $decoded;
    try {
        Storage::setTopicLang($classSlug, $topicSlug, (string)($_POST['lang'] ?? ''));
        flash('success', 'Language updated.');
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
    }
    redirect('/admin/classes/' . rawurlencode($classSlug) . '/topics/' . rawurlencode($topicSlug));
}

if (count($decoded) === 6 && $decoded[0] === 'admin' && $decoded[1] === 'classes' && $decoded[3] === 'topics' && $decoded[5] === 'delete' && $method === 'POST') {
    requireCsrf();
    [, , $classSlug, , $topicSlug] = $decoded;
    Storage::deleteTopic($classSlug, $topicSlug);
    flash('success', 'Topic deleted.');
    redirect('/admin/classes/' . rawurlencode($classSlug));
}

// ---------------------------------------------------------------------
// Admin: materials
// ---------------------------------------------------------------------

if (count($decoded) === 7 && $decoded[5] === 'materials' && $decoded[6] === 'new' && $method === 'GET') {
    [, , $classSlug, , $topicSlug] = $decoded;
    $classTitle = Storage::getClassTitle($classSlug);
    $topicTitle = Storage::getTopicTitle($classSlug, $topicSlug);
    if ($classTitle === null || $topicTitle === null) {
        notFound();
    }
    View::render('admin/material_edit', [
        'title' => 'New material',
        'classSlug' => $classSlug,
        'classTitle' => $classTitle,
        'topicSlug' => $topicSlug,
        'topicTitle' => $topicTitle,
        'materialSlug' => null,
        'materialTitle' => '',
        'body' => '',
    ]);
    exit;
}

if (count($decoded) === 8 && $decoded[5] === 'materials' && $decoded[7] === 'edit' && $method === 'GET') {
    [, , $classSlug, , $topicSlug, , $materialSlug] = $decoded;
    $classTitle = Storage::getClassTitle($classSlug);
    $topicTitle = Storage::getTopicTitle($classSlug, $topicSlug);
    $content = Storage::getMaterialContent($classSlug, $topicSlug, $materialSlug);
    if ($classTitle === null || $topicTitle === null || $content === null) {
        notFound();
    }
    $materialTitle = Storage::getMaterialTitle($classSlug, $topicSlug, $materialSlug);
    $body = preg_replace('/^#\s+.*\r?\n\r?\n?/', '', $content, 1);
    View::render('admin/material_edit', [
        'title' => 'Edit material',
        'classSlug' => $classSlug,
        'classTitle' => $classTitle,
        'topicSlug' => $topicSlug,
        'topicTitle' => $topicTitle,
        'materialSlug' => $materialSlug,
        'materialTitle' => $materialTitle,
        'body' => $body,
    ]);
    exit;
}

if (count($decoded) === 7 && $decoded[5] === 'materials' && $decoded[6] === 'save' && $method === 'POST') {
    requireCsrf();
    [, , $classSlug, , $topicSlug] = $decoded;
    $materialSlug = $_POST['material_slug'] ?? null;
    try {
        $slug = Storage::saveMaterial($classSlug, $topicSlug, $materialSlug ?: null, (string)($_POST['title'] ?? ''), (string)($_POST['body'] ?? ''));
        flash('success', 'Material saved.');
        redirect('/admin/classes/' . rawurlencode($classSlug) . '/topics/' . rawurlencode($topicSlug) . '/materials/' . rawurlencode($slug) . '/edit');
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
        redirect('/admin/classes/' . rawurlencode($classSlug) . '/topics/' . rawurlencode($topicSlug));
    }
}

if (count($decoded) === 8 && $decoded[5] === 'materials' && $decoded[7] === 'delete' && $method === 'POST') {
    requireCsrf();
    [, , $classSlug, , $topicSlug, , $materialSlug] = $decoded;
    Storage::deleteMaterial($classSlug, $topicSlug, $materialSlug);
    flash('success', 'Material deleted.');
    redirect('/admin/classes/' . rawurlencode($classSlug) . '/topics/' . rawurlencode($topicSlug));
}

if (count($decoded) === 8 && $decoded[5] === 'materials' && $decoded[7] === 'move' && $method === 'POST') {
    requireCsrf();
    [, , $classSlug, , $topicSlug, , $materialSlug] = $decoded;
    $materials = array_column(Storage::listMaterials($classSlug, $topicSlug), 'slug');
    $index = array_search($materialSlug, $materials, true);
    if ($index !== false) {
        $direction = $_POST['direction'] ?? '';
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapWith >= 0 && $swapWith < count($materials)) {
            [$materials[$index], $materials[$swapWith]] = [$materials[$swapWith], $materials[$index]];
            Storage::reorderMaterials($classSlug, $topicSlug, $materials);
        }
    }
    redirect('/admin/classes/' . rawurlencode($classSlug) . '/topics/' . rawurlencode($topicSlug));
}

// ---------------------------------------------------------------------
// Admin: uploads
// ---------------------------------------------------------------------

if (count($decoded) === 6 && $decoded[5] === 'uploads' && $method === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
    }
    [, , $classSlug, , $topicSlug] = $decoded;
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'error' => 'No file uploaded.'], 400);
    }
    try {
        $result = Storage::saveUpload($classSlug, $topicSlug, $_FILES['file']['tmp_name'], $_FILES['file']['name']);
        jsonResponse(['ok' => true] + $result);
    } catch (StorageException $e) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

if (count($decoded) === 8 && $decoded[5] === 'uploads' && $decoded[7] === 'delete' && $method === 'POST') {
    requireCsrf();
    [, , $classSlug, , $topicSlug, , $filename] = $decoded;
    Storage::deleteUpload($classSlug, $topicSlug, $filename);
    flash('success', 'File deleted.');
    redirect('/admin/classes/' . rawurlencode($classSlug) . '/topics/' . rawurlencode($topicSlug));
}

notFound();
