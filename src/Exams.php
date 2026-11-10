<?php

final class Exams
{
    public const TYPES = ['radio', 'checkbox', 'select', 'short_text', 'number', 'long_text', 'match', 'upload', 'draw'];

    /** Event types the teacher should see in red. */
    public const FLAG_TYPES = ['left_page', 'fullscreen_exit', 'page_closed', 'page_reopened'];
    /** Events the browser may report on its own while the page is going away. */
    public const BEACON_TYPES = ['page_closed'];
    public const DEFAULT_SETTINGS = ['monitor' => true, 'fullscreen' => true];

    public const MAX_FILE_BYTES = 10 * 1024 * 1024;
    private const UPLOAD_EXT = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'zip'];
    private const INLINE_IMAGE_EXT = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    // ---------- Paths / ids ----------

    public static function validId(string $id): bool
    {
        return (bool)preg_match('/^[a-z0-9][a-z0-9-]{0,80}$/', $id);
    }

    private static function classDir(string $classSlug): string
    {
        return Config::dataDir() . '/exams/' . $classSlug;
    }

    private static function examDir(string $classSlug, string $examId): ?string
    {
        if (!self::validId($classSlug) || !self::validId($examId)) {
            return null;
        }
        return self::classDir($classSlug) . '/' . $examId;
    }

    private static function newId(): string
    {
        return bin2hex(random_bytes(6));
    }

    private static function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private static function writeJson(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    // ---------- Exams ----------

    public static function listExams(string $classSlug, bool $publishedOnly = false): array
    {
        if (!self::validId($classSlug) || !is_dir(self::classDir($classSlug))) {
            return [];
        }
        $out = [];
        foreach (scandir(self::classDir($classSlug)) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $exam = self::getExam($classSlug, $entry);
            if ($exam === null || ($publishedOnly && empty($exam['published']))) {
                continue;
            }
            $out[] = $exam;
        }
        usort($out, fn($a, $b) => ($b['createdAt'] ?? 0) <=> ($a['createdAt'] ?? 0));
        return $out;
    }

    public static function getExam(string $classSlug, string $examId): ?array
    {
        $dir = self::examDir($classSlug, $examId);
        return $dir === null ? null : self::readJson($dir . '/exam.json');
    }

    public static function createExam(string $classSlug, string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            throw new StorageException('Exam title cannot be empty.');
        }
        $base = Storage::slugify($title);
        $id = $base;
        $i = 2;
        while (is_dir(self::classDir($classSlug) . '/' . $id)) {
            $id = $base . '-' . $i++;
        }
        self::writeJson(self::classDir($classSlug) . '/' . $id . '/exam.json', [
            'id' => $id,
            'title' => $title,
            'published' => false,
            'createdAt' => time(),
            'questions' => [],
            // Reserved for anti-cheating options (see assets/exam.js "Guard").
            'settings' => new stdClass(),
        ]);
        return $id;
    }

    public static function settingsOf(array $exam): array
    {
        return array_merge(self::DEFAULT_SETTINGS, array_intersect_key((array)($exam['settings'] ?? []), self::DEFAULT_SETTINGS));
    }

    public static function saveExam(string $classSlug, string $examId, string $title, bool $published, array $rawQuestions, array $settings = []): void
    {
        $exam = self::getExam($classSlug, $examId);
        if ($exam === null) {
            throw new StorageException('Exam not found.');
        }
        $title = trim($title);
        if ($title === '') {
            throw new StorageException('Exam title cannot be empty.');
        }
        $exam['title'] = $title;
        $exam['published'] = $published;
        $exam['questions'] = self::normalizeQuestions($rawQuestions);
        $exam['settings'] = [
            'monitor' => !empty($settings['monitor']),
            'fullscreen' => !empty($settings['fullscreen']),
        ];
        self::writeJson(self::examDir($classSlug, $examId) . '/exam.json', $exam);
    }

    public static function deleteExam(string $classSlug, string $examId): void
    {
        $dir = self::examDir($classSlug, $examId);
        if ($dir !== null) {
            self::rrmdir($dir);
        }
    }

    public static function deleteForClass(string $classSlug): void
    {
        if (self::validId($classSlug)) {
            self::rrmdir(self::classDir($classSlug));
        }
    }

    /** Sanitises question definitions coming from the teacher's editor. */
    public static function normalizeQuestions(array $raw): array
    {
        $out = [];
        $seen = [];
        $uid = function (?string $id) use (&$seen): string {
            if ($id === null || !preg_match('/^[a-z0-9]{1,16}$/', $id) || isset($seen[$id])) {
                do {
                    $id = self::newId();
                } while (isset($seen[$id]));
            }
            $seen[$id] = true;
            return $id;
        };
        $str = fn($v, int $max = 5000): string => mb_substr(trim((string)$v), 0, $max);

        foreach ($raw as $q) {
            if (!is_array($q) || !in_array($q['type'] ?? '', self::TYPES, true)) {
                continue;
            }
            $type = $q['type'];
            $item = [
                'id' => $uid(isset($q['id']) ? (string)$q['id'] : null),
                'type' => $type,
                'text' => $str($q['text'] ?? ''),
                'points' => max(0.0, round((float)($q['points'] ?? 1), 2)),
            ];

            if (in_array($type, ['radio', 'checkbox', 'select'], true)) {
                $item['options'] = [];
                foreach ((array)($q['options'] ?? []) as $o) {
                    $text = $str($o['text'] ?? '', 500);
                    if ($text === '') {
                        continue;
                    }
                    $item['options'][] = [
                        'id' => $uid(isset($o['id']) ? (string)$o['id'] : null),
                        'text' => $text,
                        'correct' => !empty($o['correct']),
                    ];
                }
            } elseif ($type === 'short_text' || $type === 'number') {
                $accepted = [];
                foreach ((array)($q['accepted'] ?? []) as $a) {
                    $a = $str($a, 500);
                    if ($a === '') {
                        continue;
                    }
                    if ($type === 'number' && !is_numeric(str_replace(',', '.', $a))) {
                        continue;
                    }
                    $accepted[] = $a;
                }
                $item['accepted'] = array_values(array_unique($accepted));
                if ($type === 'number') {
                    $item['tolerance'] = max(0.0, (float)str_replace(',', '.', (string)($q['tolerance'] ?? 0)));
                }
            } elseif ($type === 'match') {
                $item['pairs'] = [];
                foreach ((array)($q['pairs'] ?? []) as $p) {
                    $l = $str($p['left'] ?? '', 500);
                    $r = $str($p['right'] ?? '', 500);
                    if ($l === '' || $r === '') {
                        continue;
                    }
                    $item['pairs'][] = [
                        'id' => $uid(isset($p['id']) ? (string)$p['id'] : null),
                        'left' => $l,
                        'right' => $r,
                    ];
                }
            }
            $out[] = $item;
        }
        return $out;
    }

    /** Exam as sent to students: no correct answers. */
    public static function publicExam(array $exam): array
    {
        $questions = [];
        foreach ($exam['questions'] as $q) {
            $p = ['id' => $q['id'], 'type' => $q['type'], 'text' => $q['text'], 'points' => $q['points']];
            if (isset($q['options'])) {
                $p['options'] = array_map(fn($o) => ['id' => $o['id'], 'text' => $o['text']], $q['options']);
            }
            if (isset($q['pairs'])) {
                $p['lefts'] = array_map(fn($x) => ['id' => $x['id'], 'text' => $x['left']], $q['pairs']);
                $p['rights'] = array_map(fn($x) => ['id' => $x['id'], 'text' => $x['right']], $q['pairs']);
            }
            $questions[] = $p;
        }
        return ['id' => $exam['id'], 'title' => $exam['title'], 'questions' => $questions, 'settings' => self::settingsOf($exam)];
    }

    public static function maxScore(array $exam): float
    {
        return array_sum(array_map(fn($q) => (float)$q['points'], $exam['questions']));
    }

    // ---------- Submissions ----------

    public static function createSubmission(string $classSlug, string $examId, string $first, string $last): string
    {
        $dir = self::examDir($classSlug, $examId);
        $first = mb_substr(trim($first), 0, 80);
        $last = mb_substr(trim($last), 0, 80);
        if ($dir === null || $first === '' || $last === '') {
            throw new StorageException('Name and surname are required.');
        }
        $sid = bin2hex(random_bytes(10));
        self::writeJson($dir . '/submissions/' . $sid . '.json', [
            'id' => $sid,
            'firstName' => $first,
            'lastName' => $last,
            'status' => 'working',
            'startedAt' => time(),
            'submittedAt' => null,
            'answers' => new stdClass(),
            'scores' => new stdClass(),
            'comment' => '',
            'events' => [],
        ]);
        return $sid;
    }

    private static function validSid(string $sid): bool
    {
        return (bool)preg_match('/^[a-f0-9]{20}$/', $sid);
    }

    public static function getSubmission(string $classSlug, string $examId, string $sid): ?array
    {
        $dir = self::examDir($classSlug, $examId);
        if ($dir === null || !self::validSid($sid)) {
            return null;
        }
        return self::readJson($dir . '/submissions/' . $sid . '.json');
    }

    public static function saveSubmission(string $classSlug, string $examId, array $sub): void
    {
        $dir = self::examDir($classSlug, $examId);
        if ($dir === null || !self::validSid((string)$sub['id'])) {
            throw new StorageException('Invalid submission.');
        }
        self::writeJson($dir . '/submissions/' . $sub['id'] . '.json', $sub);
    }

    public static function listSubmissions(string $classSlug, string $examId): array
    {
        $dir = self::examDir($classSlug, $examId);
        if ($dir === null || !is_dir($dir . '/submissions')) {
            return [];
        }
        $out = [];
        foreach (glob($dir . '/submissions/*.json') as $file) {
            $sub = self::readJson($file);
            if ($sub !== null) {
                $out[] = $sub;
            }
        }
        usort($out, fn($a, $b) => strcasecmp($a['lastName'] . $a['firstName'], $b['lastName'] . $b['firstName']));
        return $out;
    }

    public static function deleteSubmission(string $classSlug, string $examId, string $sid): void
    {
        $dir = self::examDir($classSlug, $examId);
        if ($dir === null || !self::validSid($sid)) {
            return;
        }
        @unlink($dir . '/submissions/' . $sid . '.json');
        self::rrmdir($dir . '/files/' . $sid);
    }

    public static function totalScore(array $sub): float
    {
        return array_sum(array_map('floatval', array_filter((array)$sub['scores'], fn($v) => $v !== null)));
    }

    /**
     * Finalises a submission. $payload is the decoded JSON body from the client
     * ({answers: {qid: value}, events: [...]}), $files is the $_FILES array
     * (keys "file_<qid>"). Throws StorageException when it cannot be accepted.
     */
    public static function submit(string $classSlug, string $examId, string $sid, array $payload, array $files): void
    {
        $exam = self::getExam($classSlug, $examId);
        $sub = self::getSubmission($classSlug, $examId, $sid);
        if ($exam === null || $sub === null) {
            throw new StorageException('Unknown submission.');
        }
        if ($sub['status'] !== 'working') {
            throw new StorageException('This exam was already submitted.');
        }

        $raw = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
        $answers = [];
        $scores = [];
        foreach ($exam['questions'] as $q) {
            $qid = $q['id'];
            $answer = self::cleanAnswer($q, $raw[$qid] ?? null);
            if ($q['type'] === 'upload' || $q['type'] === 'draw') {
                $answer = self::storeFile($classSlug, $examId, $sid, $q, $files['file_' . $qid] ?? null);
            }
            $answers[$qid] = $answer;
            $scores[$qid] = self::autoScore($q, $answer);
        }

        $now = time();
        $sub['answers'] = $answers;
        $sub['scores'] = $scores;
        $sub['autoScores'] = $scores;
        $sub['status'] = 'submitted';
        $sub['submittedAt'] = $now;
        $sub['durationSec'] = max(0, $now - (int)$sub['startedAt']);
        // Keep server-recorded events (page_closed beacons) and add the client's.
        $events = array_merge((array)($sub['events'] ?? []), self::cleanEvents($payload['events'] ?? []));
        usort($events, fn($a, $b) => $a['at'] <=> $b['at']);
        $sub['events'] = array_slice($events, 0, 1000);
        self::saveSubmission($classSlug, $examId, $sub);
    }

    /** Records an event reported by the browser as the page closes (server time). */
    public static function addBeaconEvent(string $classSlug, string $examId, string $sid, string $type, $detail): void
    {
        $sub = self::getSubmission($classSlug, $examId, $sid);
        if ($sub === null || $sub['status'] !== 'working' || !in_array($type, self::BEACON_TYPES, true)) {
            return;
        }
        $events = (array)($sub['events'] ?? []);
        if (count($events) >= 1000) {
            return;
        }
        $events[] = ['type' => $type, 'at' => time(), 'detail' => mb_substr(json_encode($detail), 0, 500)];
        $sub['events'] = $events;
        self::saveSubmission($classSlug, $examId, $sub);
    }

    /** @return array{count:int, awaySec:int} red-flag summary for a submission */
    public static function flagSummary(array $sub): array
    {
        $count = 0;
        $away = 0;
        foreach ((array)($sub['events'] ?? []) as $e) {
            if (!in_array($e['type'], self::FLAG_TYPES, true)) {
                continue;
            }
            $count++;
            if ($e['type'] === 'left_page') {
                $d = json_decode((string)$e['detail'], true);
                $away += (int)($d['durationSec'] ?? 0);
            }
        }
        return ['count' => $count, 'awaySec' => $away];
    }

    /** Human-readable line for the teacher: [label, isFlagged]. */
    public static function describeEvent(array $e): array
    {
        $d = json_decode((string)($e['detail'] ?? ''), true) ?: [];
        $dur = isset($d['durationSec']) ? sprintf('%d:%02d', intdiv((int)$d['durationSec'], 60), (int)$d['durationSec'] % 60) : null;
        switch ($e['type']) {
            case 'left_page':
                $how = ($d['reason'] ?? '') === 'hidden' ? 'tab switched / window minimised' : 'window lost focus';
                return ['Left the exam page (' . $how . ') for ' . ($dur ?? '?'), true];
            case 'fullscreen_exit':
                return ['Exited fullscreen for ' . ($dur ?? '?'), true];
            case 'page_closed':
                return ['Closed or reloaded the page / browser', true];
            case 'page_reopened':
                return ['Reopened the exam after being gone ' . ($dur ?? '?'), true];
            case 'fullscreen_unsupported':
                return ['Browser does not support fullscreen', false];
        }
        return [$e['type'], false];
    }

    private static function cleanEvents($events): array
    {
        $out = [];
        foreach (is_array($events) ? array_slice($events, 0, 500) : [] as $e) {
            if (!is_array($e) || !isset($e['type'])) {
                continue;
            }
            $out[] = [
                'type' => mb_substr((string)$e['type'], 0, 40),
                'at' => (int)($e['at'] ?? 0),
                'detail' => mb_substr(json_encode($e['detail'] ?? null), 0, 500),
            ];
        }
        return $out;
    }

    /** Validates a raw client answer against the question; returns the normalised value. */
    private static function cleanAnswer(array $q, $value)
    {
        switch ($q['type']) {
            case 'radio':
            case 'select':
                $ids = array_column($q['options'], 'id');
                return is_string($value) && in_array($value, $ids, true) ? $value : null;
            case 'checkbox':
                $ids = array_column($q['options'], 'id');
                $value = is_array($value) ? $value : [];
                return array_values(array_intersect($ids, array_map('strval', $value)));
            case 'short_text':
                return mb_substr(trim((string)(is_scalar($value) ? $value : '')), 0, 1000);
            case 'number':
                return mb_substr(trim((string)(is_scalar($value) ? $value : '')), 0, 50);
            case 'long_text':
                return mb_substr((string)(is_scalar($value) ? $value : ''), 0, 20000);
            case 'match':
                $lefts = array_column($q['pairs'], 'id');
                $out = [];
                foreach (is_array($value) ? $value : [] as $l => $r) {
                    if (in_array((string)$l, $lefts, true) && in_array((string)$r, $lefts, true)) {
                        $out[(string)$l] = (string)$r;
                    }
                }
                return $out;
        }
        return null;
    }

    private static function storeFile(string $classSlug, string $examId, string $sid, array $q, $file): ?array
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > self::MAX_FILE_BYTES) {
            throw new StorageException('A file is too large or failed to upload.');
        }
        $originalName = mb_substr(basename((string)$file['name']), 0, 150);
        if ($q['type'] === 'draw') {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false || $info[2] !== IMAGETYPE_PNG) {
                throw new StorageException('Invalid drawing.');
            }
            $ext = 'png';
            $originalName = 'drawing.png';
        } else {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, self::UPLOAD_EXT, true)) {
                throw new StorageException('File type not allowed: .' . $ext);
            }
        }
        $dir = self::examDir($classSlug, $examId) . '/files/' . $sid;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $stored = $q['id'] . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
            throw new StorageException('Failed to store file.');
        }
        return ['file' => $stored, 'name' => $originalName];
    }

    /** Absolute path + download name + whether it is safe to display inline. */
    public static function submissionFile(string $classSlug, string $examId, string $sid, string $filename): ?array
    {
        $dir = self::examDir($classSlug, $examId);
        if ($dir === null || !self::validSid($sid)) {
            return null;
        }
        $path = $dir . '/files/' . $sid . '/' . basename($filename);
        if (!is_file($path)) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return ['path' => $path, 'inline' => in_array($ext, self::INLINE_IMAGE_EXT, true), 'ext' => $ext];
    }

    // ---------- Scoring ----------

    private static function norm(string $s): string
    {
        return mb_strtolower(trim((string)preg_replace('/\s+/u', ' ', $s)));
    }

    private static function toFloat(string $s): ?float
    {
        $s = str_replace([',', ' '], ['.', ''], trim($s));
        return is_numeric($s) ? (float)$s : null;
    }

    /** Returns the automatic score, or null when the question needs manual marking. */
    public static function autoScore(array $q, $answer): ?float
    {
        $pts = (float)$q['points'];
        switch ($q['type']) {
            case 'radio':
            case 'select':
                $correct = array_column(array_filter($q['options'], fn($o) => $o['correct']), 'id');
                if (!$correct) {
                    return null;
                }
                return in_array($answer, $correct, true) ? $pts : 0.0;
            case 'checkbox':
                $correct = array_column(array_filter($q['options'], fn($o) => $o['correct']), 'id');
                if (!$correct) {
                    return null;
                }
                $given = (array)$answer;
                sort($correct);
                sort($given);
                return $given === $correct ? $pts : 0.0;
            case 'short_text':
                if (empty($q['accepted'])) {
                    return null;
                }
                $given = self::norm((string)$answer);
                foreach ($q['accepted'] as $a) {
                    if ($given === self::norm($a)) {
                        return $pts;
                    }
                }
                return 0.0;
            case 'number':
                if (empty($q['accepted'])) {
                    return null;
                }
                $given = self::toFloat((string)$answer);
                if ($given === null) {
                    return 0.0;
                }
                $tol = (float)($q['tolerance'] ?? 0) + 1e-9;
                foreach ($q['accepted'] as $a) {
                    $target = self::toFloat($a);
                    if ($target !== null && abs($given - $target) <= $tol) {
                        return $pts;
                    }
                }
                return 0.0;
            case 'match':
                if (empty($q['pairs'])) {
                    return null;
                }
                $right = 0;
                foreach ($q['pairs'] as $p) {
                    if (($answer[$p['id']] ?? null) === $p['id']) {
                        $right++;
                    }
                }
                return round($pts * $right / count($q['pairs']), 2);
        }
        return null; // long_text, upload, draw
    }

    // ---------- Helpers ----------

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
