<?php
// Student-facing exam routes. Included from public/index.php (shares its scope:
// $method, $decoded, redirect(), jsonResponse(), requireCsrf(), notFound()).
//
// Students have no accounts. A class code unlocks the exam list for the PHP
// session; a randomly generated submission id (created when the student enters
// their name) is the only credential needed to submit.

function examsUnlocked(string $classSlug): bool
{
    return !empty($_SESSION['exams_unlocked'][$classSlug]);
}

// Unlock a class's exams with its code.
if ($method === 'POST' && count($decoded) === 3 && $decoded[0] === 'exams' && $decoded[2] === 'unlock') {
    requireCsrf();
    $classSlug = $decoded[1];
    if (Storage::getClassTitle($classSlug) === null) {
        notFound();
    }
    $code = Storage::getClassCode($classSlug);
    $given = trim((string)($_POST['code'] ?? ''));
    if ($code !== '' && $given !== '' && hash_equals(mb_strtolower($code), mb_strtolower($given))) {
        $_SESSION['exams_unlocked'][$classSlug] = true;
        redirect('/class/' . rawurlencode($classSlug) . '?tab=exams');
    }
    usleep(500000); // cheap brake on code guessing
    $_SESSION['exams_code_error'] = true;
    redirect('/class/' . rawurlencode($classSlug) . '?tab=exams');
}

// The exam page itself (questions are loaded by exam.js after the student enters a name).
if ($method === 'GET' && count($decoded) === 3 && $decoded[0] === 'exams') {
    [, $classSlug, $examId] = $decoded;
    $classTitle = Storage::getClassTitle($classSlug);
    $exam = $classTitle === null ? null : Exams::getExam($classSlug, $examId);
    if ($exam === null || empty($exam['published'])) {
        notFound();
    }
    if (!examsUnlocked($classSlug)) {
        redirect('/class/' . rawurlencode($classSlug) . '?tab=exams');
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
    if ($exam === null || empty($exam['published']) || !examsUnlocked($classSlug)) {
        jsonResponse(['ok' => false, 'error' => 'unavailable'], 403);
    }
    $resume = (string)($_POST['resume'] ?? '');
    $sub = $resume !== '' ? Exams::getSubmission($classSlug, $examId, $resume) : null;
    if ($sub !== null && $sub['status'] === 'working') {
        $sid = $sub['id'];
    } else {
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

// Final submission: one multipart POST with all answers and files.
if ($method === 'POST' && count($decoded) === 5 && $decoded[0] === 'exams' && $decoded[3] === 'submit') {
    [, $classSlug, $examId, , $sid] = $decoded;
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        jsonResponse(['ok' => false, 'error' => 'csrf'], 400);
    }
    $payload = json_decode((string)($_POST['payload'] ?? ''), true);
    if (!is_array($payload)) {
        jsonResponse(['ok' => false, 'error' => 'Bad request.'], 400);
    }
    try {
        Exams::submit($classSlug, $examId, $sid, $payload, $_FILES);
    } catch (StorageException $e) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    // Deliberately no score in the response.
    jsonResponse(['ok' => true]);
}
