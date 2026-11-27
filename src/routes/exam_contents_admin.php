<?php
// Teacher's exam content library: /admin/exam-contents. Included from
// public/index.php after the /admin login guard.

if (count($decoded) < 2 || $decoded[0] !== 'admin' || $decoded[1] !== 'exam-contents') {
    return;
}

if (count($decoded) === 2 && $method === 'GET') {
    $usage = [];
    foreach (Exams::allExams() as $e) {
        if (!empty($e['contentId'])) {
            $usage[$e['contentId']] = ($usage[$e['contentId']] ?? 0) + 1;
        }
    }
    View::render('admin/exam_contents', ['title' => I18n::ui('Exam content library'), 'contents' => Exams::listContents(), 'usage' => $usage]);
    exit;
}

if (count($decoded) === 2 && $method === 'POST') {
    requireCsrf();
    try {
        $id = Exams::createContent((string)($_POST['title'] ?? ''), [], (string)($_POST['kind'] ?? 'written'));
        redirect('/admin/exam-contents/' . rawurlencode($id));
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
        redirect('/admin/exam-contents');
    }
}

$contentId = $decoded[2] ?? '';
$content = $contentId === '' ? null : Exams::getContent($contentId);
if ($content === null) {
    notFound();
}
$contentBase = '/admin/exam-contents/' . rawurlencode($contentId);

if (count($decoded) === 3 && $method === 'GET') {
    View::render('admin/exam_content_edit', [
        'title' => $content['title'],
        'content' => $content,
        'usedBy' => Exams::contentUsage($contentId),
        'types' => Exams::typesForKind($content['kind']),
    ]);
    exit;
}

if (count($decoded) === 4 && $decoded[3] === 'save' && $method === 'POST') {
    requireCsrf();
    $questions = json_decode((string)($_POST['questions'] ?? '[]'), true);
    try {
        Exams::saveContent($contentId, (string)($_POST['title'] ?? ''), is_array($questions) ? $questions : []);
        flash('success', I18n::ui('Exam content saved.'));
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
    }
    redirect($contentBase);
}

if (count($decoded) === 4 && $decoded[3] === 'duplicate' && $method === 'POST') {
    requireCsrf();
    $newTitle = trim((string)($_POST['title'] ?? ''));
    if ($newTitle === '') {
        $newTitle = $content['title'] . ' (copy)';
    }
    try {
        $copy = Exams::createContent(mb_substr($newTitle, 0, 200), $content['questions'], $content['kind']);
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
        redirect($contentBase);
    }
    flash('success', I18n::ui('Exam content duplicated.'));
    redirect('/admin/exam-contents/' . rawurlencode($copy));
}

if (count($decoded) === 4 && $decoded[3] === 'delete' && $method === 'POST') {
    requireCsrf();
    try {
        Exams::deleteContent($contentId);
        flash('success', I18n::ui('Exam content deleted.'));
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
        redirect($contentBase);
    }
    redirect('/admin/exam-contents');
}

notFound();
