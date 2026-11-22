<?php
// Teacher-facing exam routes. Included from public/index.php after the
// /admin login guard, so every route here already requires a logged-in teacher.

if (count($decoded) < 4 || $decoded[0] !== 'admin' || $decoded[1] !== 'classes') {
    return;
}

$classSlug = $decoded[2];
$classTitle = Storage::getClassTitle($classSlug);
$base = '/admin/classes/' . rawurlencode($classSlug);

// Class-level settings (language) -------------------------------------
if ($method === 'POST' && count($decoded) === 4 && $decoded[3] === 'language') {
    requireCsrf();
    try {
        Storage::setClassLang($classSlug, (string)($_POST['lang'] ?? ''));
        flash('success', I18n::ui('Language updated.'));
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
    }
    redirect($base);
}

if ($decoded[3] !== 'exams') {
    return;
}
if ($classTitle === null) {
    notFound();
}

// Exam list + create ----------------------------------------------------
if (count($decoded) === 4 && $method === 'GET') {
    $lessons = [];
    foreach (Storage::listTopics($classSlug) as $t) {
        foreach (Storage::listMaterials($classSlug, $t['slug']) as $m) {
            $lessons[] = ['value' => $t['slug'] . '/' . $m['slug'], 'topic' => $t['title'], 'title' => $m['title']];
        }
    }
    View::render('admin/exams', [
        'title' => I18n::ui('Exams'),
        'classSlug' => $classSlug,
        'classTitle' => $classTitle,
        'exams' => Exams::listExams($classSlug),
        'lessons' => $lessons,
        'contents' => Exams::listContents(),
        'preselect' => (string)($_GET['lesson'] ?? ''),
    ]);
    exit;
}

if (count($decoded) === 4 && $method === 'POST') {
    requireCsrf();
    try {
        $id = Exams::createExam(
            $classSlug,
            (string)($_POST['title'] ?? ''),
            Exams::parseLesson($classSlug, (string)($_POST['lesson'] ?? '')),
            (string)($_POST['content_id'] ?? '')
        );
        redirect($base . '/exams/' . rawurlencode($id));
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
        redirect($base . '/exams');
    }
}

// Everything below is about one exam.
$examId = $decoded[4] ?? '';
$exam = $examId === '' ? null : Exams::getExam($classSlug, $examId);
if ($exam === null) {
    notFound();
}
$examBase = $base . '/exams/' . rawurlencode($examId);

$lessons = [];
foreach (Storage::listTopics($classSlug) as $t) {
    foreach (Storage::listMaterials($classSlug, $t['slug']) as $m) {
        $lessons[] = ['value' => $t['slug'] . '/' . $m['slug'], 'topic' => $t['title'], 'title' => $m['title']];
    }
}

if (count($decoded) === 5 && $method === 'GET') {
    View::render('admin/exam_edit', [
        'title' => $exam['title'],
        'classSlug' => $classSlug,
        'classTitle' => $classTitle,
        'exam' => $exam,
        'lessons' => $lessons,
        'contents' => Exams::listContents(),
        'submissionCount' => Exams::submissionCount($classSlug, $examId),
    ]);
    exit;
}

if (count($decoded) === 6 && $decoded[5] === 'save' && $method === 'POST') {
    requireCsrf();
    try {
        $code = isset($_POST['generate']) ? Exams::generateCode() : (string)($_POST['code'] ?? '');
        Exams::saveExam(
            $classSlug,
            $examId,
            (string)($_POST['title'] ?? ''),
            !empty($_POST['published']),
            ['monitor' => !empty($_POST['monitor']), 'fullscreen' => !empty($_POST['fullscreen']), 'timeLimitMin' => (int)($_POST['time_limit'] ?? 0)],
            Exams::parseLesson($classSlug, (string)($_POST['lesson'] ?? '')),
            (string)($_POST['content_id'] ?? ''),
            $code
        );
        flash('success', I18n::ui('Exam saved.'));
    } catch (StorageException $e) {
        flash('error', $e->getMessage());
    }
    redirect($examBase);
}

if (count($decoded) === 6 && $decoded[5] === 'delete' && $method === 'POST') {
    requireCsrf();
    Exams::deleteExam($classSlug, $examId);
    flash('success', I18n::ui('Exam deleted.'));
    redirect($base . '/exams');
}

if (count($decoded) === 6 && $decoded[5] === 'submissions' && $method === 'GET') {
    View::render('admin/submissions', [
        'title' => $exam['title'] . ' · ' . I18n::ui('Submissions'),
        'classSlug' => $classSlug,
        'classTitle' => $classTitle,
        'exam' => $exam,
        'maxScore' => Exams::maxScore($exam),
        'submissions' => Exams::listSubmissions($classSlug, $examId),
    ]);
    exit;
}

// One submission ---------------------------------------------------------
if (count($decoded) >= 7 && $decoded[5] === 'submissions') {
    $sid = $decoded[6];
    $sub = Exams::getSubmission($classSlug, $examId, $sid);
    if ($sub === null) {
        notFound();
    }
    $subBase = $examBase . '/submissions/' . rawurlencode($sid);

    if (count($decoded) === 7 && $method === 'GET') {
        View::render('admin/submission', [
            'title' => $sub['lastName'] . ' ' . $sub['firstName'],
            'classSlug' => $classSlug,
            'classTitle' => $classTitle,
            'exam' => $exam,
            'sub' => $sub,
            'maxScore' => Exams::maxScore($exam),
        ]);
        exit;
    }

    if (count($decoded) === 8 && $decoded[7] === 'save' && $method === 'POST') {
        requireCsrf();
        $scores = [];
        $given = is_array($_POST['score'] ?? null) ? $_POST['score'] : [];
        foreach ($exam['questions'] as $q) {
            $v = trim((string)($given[$q['id']] ?? ''));
            $v = str_replace(',', '.', $v);
            // Blank = not scored yet. Scores are not capped at the question's
            // points so teachers can award bonus points.
            $scores[$q['id']] = $v !== '' && is_numeric($v) ? round((float)$v, 2) : null;
        }
        $action = (string)($_POST['action'] ?? 'save');
        $comment = mb_substr((string)($_POST['comment'] ?? ''), 0, 5000);
        // Locked read-modify-write: a late beacon/heartbeat cannot be lost or overwrite the marks.
        Exams::mutateSubmission($classSlug, $examId, $sid, function (array $sub) use ($scores, $comment, $action): array {
            $sub['scores'] = $scores;
            $sub['comment'] = $comment;
            if ($sub['status'] !== 'working') {
                if ($action === 'mark') {
                    $sub['status'] = 'marked';
                    $sub['markedAt'] = time();
                } elseif ($action === 'unmark') {
                    $sub['status'] = 'submitted';
                }
            }
            return $sub;
        });
        flash('success', $action === 'mark' ? I18n::ui('Saved and marked.') : I18n::ui('Saved.'));
        redirect($action === 'mark' ? $examBase . '/submissions' : $subBase);
    }

    if (count($decoded) === 8 && $decoded[7] === 'delete' && $method === 'POST') {
        requireCsrf();
        Exams::deleteSubmission($classSlug, $examId, $sid);
        flash('success', I18n::ui('Submission deleted.'));
        redirect($examBase . '/submissions');
    }

    if (count($decoded) === 9 && $decoded[7] === 'files' && $method === 'GET') {
        $file = Exams::submissionFile($classSlug, $examId, $sid, $decoded[8]);
        if ($file === null) {
            notFound();
        }
        $name = basename($decoded[8]);
        // Student uploads are untrusted: never let the browser sniff or render
        // anything but known-safe image types.
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
        if ($file['inline']) {
            header('Content-Type: ' . ($file['ext'] === 'jpg' ? 'image/jpeg' : 'image/' . $file['ext']));
            header('Content-Disposition: inline; filename="' . $name . '"');
        } else {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $name . '"');
        }
        header('Content-Length: ' . filesize($file['path']));
        readfile($file['path']);
        exit;
    }
}
