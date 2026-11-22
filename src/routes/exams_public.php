<?php
// Student-facing exam routes. Included from public/index.php (shares its scope:
// $method, $decoded, redirect(), jsonResponse(), requireCsrf(), notFound()).
//
// Students have no accounts. Each exam has its own code: entering it unlocks
// that one exam for the PHP session and leads to the name form. A randomly
// generated submission id (created when the student enters their name) is the
// only credential needed to submit.

function examsUnlocked(string $classSlug, string $examId): bool
{
    return !empty($_SESSION['exams_unlocked'][$classSlug . '/' . $examId]);
}

// Enter an exam code (from the home page or a lesson page).
if ($method === 'POST' && $decoded === ['exams', 'enter']) {
    requireCsrf();
    $exam = Exams::findByCode((string)($_POST['code'] ?? ''));
    if ($exam !== null && !empty($exam['published'])) {
        $_SESSION['exams_unlocked'][$exam['classSlug'] . '/' . $exam['id']] = true;
        redirect('/exams/' . rawurlencode($exam['classSlug']) . '/' . rawurlencode($exam['id']));
    }
    usleep(500000); // cheap brake on code guessing
    $_SESSION['exams_code_error'] = true;
    $back = (string)($_POST['back'] ?? '');
    redirect(strpos($back, '/class/') === 0 && strpos($back, '//') === false ? $back : '/');
}

// The exam page itself (questions are loaded by exam.js after the student enters a name).
if ($method === 'GET' && count($decoded) === 3 && $decoded[0] === 'exams') {
    [, $classSlug, $examId] = $decoded;
    $classTitle = Storage::getClassTitle($classSlug);
    $exam = $classTitle === null ? null : Exams::getExam($classSlug, $examId);
    if ($exam === null || empty($exam['published'])) {
        notFound();
    }
    if (!examsUnlocked($classSlug, $examId)) {
        redirect('/');
    }
    $lang = Storage::getClassLang($classSlug);
    View::render('exam', [
        'title' => $exam['title'],
        'bodyClass' => 'page-exam',
        'lang' => $lang,
        'classSlug' => $classSlug,
        'classTitle' => $classTitle,
        'exam' => $exam,
        'i18n' => I18n::group($lang, 'exam.'),
    ]);
    exit;
}

// Start (or resume) an attempt. Returns the exam WITHOUT correct answers.
if ($method === 'POST' && count($decoded) === 4 && $decoded[0] === 'exams' && $decoded[3] === 'start') {
    [, $classSlug, $examId] = $decoded;
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        jsonResponse(['ok' => false, 'error' => 'csrf'], 400);
    }
    $exam = Exams::getExam($classSlug, $examId);
    if ($exam === null || empty($exam['published'])) {
        jsonResponse(['ok' => false, 'error' => 'unavailable'], 403);
    }
    $resume = (string)($_POST['resume'] ?? '');
    $sub = $resume !== '' ? Exams::getSubmission($classSlug, $examId, $resume) : null;
    if ($sub !== null && $sub['status'] === 'working') {
        // Resuming an unfinished attempt: its random id is the credential.
        $sid = $sub['id'];
    } else {
        if (!examsUnlocked($classSlug, $examId)) {
            jsonResponse(['ok' => false, 'error' => 'unavailable'], 403);
        }
        try {
            $sid = Exams::createSubmission($classSlug, $examId, (string)($_POST['first_name'] ?? ''), (string)($_POST['last_name'] ?? ''));
        } catch (StorageException $e) {
            jsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
        $sub = Exams::getSubmission($classSlug, $examId, $sid);
    }
    jsonResponse([
        'ok' => true,
        'submissionId' => $sid,
        'startedAt' => $sub['startedAt'],
        'serverTime' => time(),
        'exam' => Exams::publicExam($exam),
    ]);
}

// Browser telemetry sent via sendBeacon while the page is closing.
if ($method === 'POST' && count($decoded) === 5 && $decoded[0] === 'exams' && $decoded[3] === 'event') {
    [, $classSlug, $examId, , $sid] = $decoded;
    if (Csrf::verify($_POST['csrf_token'] ?? null)) {
        Exams::addBeaconEvent($classSlug, $examId, $sid, (string)($_POST['type'] ?? ''), json_decode((string)($_POST['detail'] ?? 'null'), true));
    }
    http_response_code(204);
    exit;
}

// Final submission: one multipart POST with all answers and files.
if ($method === 'POST' && count($decoded) === 5 && $decoded[0] === 'exams' && $decoded[3] === 'submit') {
    [, $classSlug, $examId, , $sid] = $decoded;
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        jsonResponse(['ok' => false, 'error' => 'csrf'], 400);
    }
    $payload = json_decode((string)($_POST['payload'] ?? ''), true);
    if (!is_array($payload)) {
        jsonResponse(['ok' => false, 'error' => I18n::ui('Bad request.')], 400);
    }
    try {
        Exams::submit($classSlug, $examId, $sid, $payload, $_FILES);
    } catch (StorageException $e) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    // Deliberately no score in the response.
    jsonResponse(['ok' => true]);
}
