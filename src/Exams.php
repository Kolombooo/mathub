<?php

final class Exams
{
    public const TYPES = ['radio', 'checkbox', 'select', 'short_text', 'number', 'long_text', 'match', 'upload', 'draw', 'code'];

    /**
     * Exam content kinds. `written` = the classic question types, `upload` = file
     * upload questions only, `code` = a list of coding assignments (one `code`
     * question each). The kind is chosen when the content is created.
     */
    public const KINDS = ['written', 'code', 'upload'];
    public const CODE_LANGUAGES = ['python', 'javascript'];
    public const MAX_CODE_CHARS = 100000;
    /** Per-student server draft of code answers (autosave); see saveDraft(). */
    private const DRAFT_MIN_INTERVAL_SEC = 1;

    /** Event types the teacher should see in red. */
    public const FLAG_TYPES = ['left_page', 'fullscreen_exit', 'page_closed', 'page_reopened', 'heartbeat_lost', 'guard_missing', 'late_submit'];
    /** Events the browser may report on its own while the page is going away. */
    public const BEACON_TYPES = ['page_closed'];
    /**
     * Event types only the server may create (server clock, tamper-resistant).
     * Anything the client submits with one of these types is discarded.
     */
    public const SERVER_TYPES = ['page_closed', 'heartbeat_lost', 'guard_missing', 'late_submit'];
    public const DEFAULT_SETTINGS = ['monitor' => true, 'fullscreen' => true, 'timeLimitMin' => 0];

    /** The exam page pings the server this often (seconds); see exam.js. */
    public const HEARTBEAT_SEC = 10;
    /** Silence longer than this is recorded as `heartbeat_lost`. */
    public const HEARTBEAT_GAP_SEC = 45;
    /** Slack on the time limit for slow uploads / clock skew before a submission counts as late. */
    public const TIME_GRACE_SEC = 60;
    private const MAX_EVENTS = 1000;

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
        // Write to a temp file and rename: readers never see a half-written file.
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new StorageException('Could not write ' . basename($path));
        }
    }

    // ---------- Exam content (the teacher's question library) ----------
    //
    // An ExamContent is a titled series of questions stored once in
    // data/exam_contents/<id>.json. An Exam (below) points at exactly one.

    private static function contentsDir(): string
    {
        return Config::dataDir() . '/exam_contents';
    }

    public static function listContents(): array
    {
        $out = [];
        foreach (glob(self::contentsDir() . '/*.json') ?: [] as $file) {
            $c = self::readJson($file);
            if ($c !== null && isset($c['id'])) {
                $c['questions'] = (array)($c['questions'] ?? []);
                $c['kind'] = self::kindOf($c);
                $out[] = $c;
            }
        }
        usort($out, fn($a, $b) => strcasecmp($a['title'] ?? '', $b['title'] ?? ''));
        return $out;
    }

    public static function getContent(string $id): ?array
    {
        if (!self::validId($id)) {
            return null;
        }
        $c = self::readJson(self::contentsDir() . '/' . $id . '.json');
        if ($c !== null) {
            $c['questions'] = (array)($c['questions'] ?? []);
            $c['kind'] = self::kindOf($c);
        }
        return $c;
    }

    /**
     * Where Pyodide (Python in the browser) is loaded from. Pinned to one release so
     * an upstream update cannot change behaviour mid-exam. Override with the
     * MATHUB_PYODIDE_URL environment variable (must end in "/") to self-host.
     */
    public static function pyodideBase(): string
    {
        $url = (string)getenv('MATHUB_PYODIDE_URL');
        return $url !== '' ? rtrim($url, '/') . '/' : 'https://cdn.jsdelivr.net/pyodide/v314.0.8/full/';
    }

    public static function kindOf(array $content): string
    {
        return in_array($content['kind'] ?? '', self::KINDS, true) ? $content['kind'] : 'written';
    }

    /** Question types a content of this kind may contain. */
    public static function typesForKind(string $kind): array
    {
        if ($kind === 'code') {
            return ['code'];
        }
        if ($kind === 'upload') {
            return ['upload'];
        }
        return array_values(array_diff(self::TYPES, ['code']));
    }

    public static function createContent(string $title, array $questions = [], string $kind = 'written'): string
    {
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'written';
        $title = trim($title);
        if ($title === '') {
            throw new StorageException(I18n::ui('Exam content title cannot be empty.'));
        }
        $base = Storage::slugify($title);
        $id = $base;
        $i = 2;
        while (is_file(self::contentsDir() . '/' . $id . '.json')) {
            $id = $base . '-' . $i++;
        }
        self::writeJson(self::contentsDir() . '/' . $id . '.json', [
            'id' => $id,
            'title' => mb_substr($title, 0, 200),
            'createdAt' => time(),
            'updatedAt' => time(),
            'kind' => $kind,
            'questions' => $questions,
        ]);
        return $id;
    }

    public static function saveContent(string $id, string $title, array $rawQuestions): void
    {
        $c = self::getContent($id);
        if ($c === null) {
            throw new StorageException(I18n::ui('Exam content not found.'));
        }
        $title = trim($title);
        if ($title === '') {
            throw new StorageException(I18n::ui('Exam content title cannot be empty.'));
        }
        $c['title'] = mb_substr($title, 0, 200);
        $allowed = self::typesForKind($c['kind']);
        $c['questions'] = array_values(array_filter(
            self::normalizeQuestions($rawQuestions),
            fn($q) => in_array($q['type'], $allowed, true)
        ));
        $c['updatedAt'] = time();
        self::writeJson(self::contentsDir() . '/' . $id . '.json', $c);
    }

    /** Exams (any class) that use this content. */
    public static function contentUsage(string $contentId): array
    {
        return array_values(array_filter(self::allExams(), fn($e) => ($e['contentId'] ?? null) === $contentId));
    }

    public static function deleteContent(string $id): void
    {
        if (!self::validId($id) || self::getContent($id) === null) {
            return;
        }
        if (self::contentUsage($id)) {
            throw new StorageException(I18n::ui('This exam content is used by an exam. Detach or delete those exams first.'));
        }
        @unlink(self::contentsDir() . '/' . $id . '.json');
    }

    public static function contentMaxScore(array $content): float
    {
        return array_sum(array_map(fn($q) => (float)$q['points'], $content['questions']));
    }

    // ---------- Exams ----------
    //
    // An Exam lives in one lesson (a material: class + topic + material) and
    // has its own code. Students enter the code, then their name. The questions
    // come from exactly one ExamContent (`contentId`). Submissions and uploaded
    // files stay in the exam's folder.

    /** Raw exam.json as stored (no content merged in). */
    private static function rawExam(string $classSlug, string $examId): ?array
    {
        $dir = self::examDir($classSlug, $examId);
        return $dir === null ? null : self::readJson($dir . '/exam.json');
    }

    /**
     * Merges the exam's content into it, so callers can keep using
     * $exam['questions']. Exams not yet migrated fall back to their own
     * embedded questions (read-only), so old data keeps working.
     */
    private static function hydrate(array $exam, string $classSlug): array
    {
        $exam['classSlug'] = $classSlug;
        if (!empty($exam['contentId'])) {
            $content = self::getContent((string)$exam['contentId']);
            $exam['questions'] = $content['questions'] ?? [];
            $exam['kind'] = $content !== null ? $content['kind'] : 'written';
            $exam['contentTitle'] = $content['title'] ?? null;
        } else {
            $exam['questions'] = (array)($exam['questions'] ?? []);
            $exam['contentTitle'] = null;
        }
        return $exam;
    }

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

    /** Every exam of every class. */
    public static function allExams(): array
    {
        $root = Config::dataDir() . '/exams';
        $out = [];
        foreach (is_dir($root) ? scandir($root) : [] as $entry) {
            if ($entry !== '.' && $entry !== '..' && self::validId($entry) && is_dir($root . '/' . $entry)) {
                $out = array_merge($out, self::listExams($entry));
            }
        }
        return $out;
    }

    public static function getExam(string $classSlug, string $examId): ?array
    {
        $exam = self::rawExam($classSlug, $examId);
        return $exam === null ? null : self::hydrate($exam, $classSlug);
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)));
    }

    public static function generateCode(): string
    {
        // Unambiguous characters only (no 0/O, 1/I/L).
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::findByCode($code) !== null);
        return $code;
    }

    /** Finds the exam a code belongs to (case-insensitive), whether published or not. */
    public static function findByCode(string $code): ?array
    {
        $code = self::normalizeCode($code);
        if ($code === '') {
            return null;
        }
        foreach (self::allExams() as $exam) {
            if (self::normalizeCode((string)($exam['code'] ?? '')) === $code) {
                return $exam;
            }
        }
        return null;
    }

    /** Published exams attached to a lesson. */
    public static function examsForLesson(string $classSlug, string $topicSlug, string $materialSlug): array
    {
        return array_values(array_filter(
            self::listExams($classSlug, true),
            fn($e) => ($e['topic'] ?? null) === $topicSlug && ($e['material'] ?? null) === $materialSlug
        ));
    }

    /** "topic/material" as posted by the lesson <select>; returns [topic, material] or null. */
    public static function parseLesson(string $classSlug, string $value): ?array
    {
        $parts = explode('/', $value);
        if (count($parts) !== 2 || !Storage::materialExists($classSlug, $parts[0], $parts[1])) {
            return null;
        }
        return $parts;
    }

    public static function createExam(string $classSlug, string $title, ?array $lesson, ?string $contentId): string
    {
        $title = trim($title);
        if ($title === '') {
            throw new StorageException(I18n::ui('Exam title cannot be empty.'));
        }
        if ($lesson === null) {
            throw new StorageException(I18n::ui('Choose the lesson this exam belongs to.'));
        }
        if ($contentId !== null && $contentId !== '' && self::getContent($contentId) === null) {
            throw new StorageException(I18n::ui('Exam content not found.'));
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
            'topic' => $lesson[0],
            'material' => $lesson[1],
            'contentId' => $contentId !== '' ? $contentId : null,
            'code' => self::generateCode(),
            'settings' => new stdClass(),
        ]);
        return $id;
    }

    public static function settingsOf(array $exam): array
    {
        return array_merge(self::DEFAULT_SETTINGS, array_intersect_key((array)($exam['settings'] ?? []), self::DEFAULT_SETTINGS));
    }

    public static function submissionCount(string $classSlug, string $examId): int
    {
        return count(self::listSubmissions($classSlug, $examId));
    }

    /**
     * Updates an exam's own settings. Questions are NOT edited here: they
     * belong to the exam content.
     */
    public static function saveExam(string $classSlug, string $examId, string $title, bool $published, array $settings, ?array $lesson, string $contentId, string $code): void
    {
        $exam = self::rawExam($classSlug, $examId);
        if ($exam === null) {
            throw new StorageException(I18n::ui('Exam not found.'));
        }
        $title = trim($title);
        if ($title === '') {
            throw new StorageException(I18n::ui('Exam title cannot be empty.'));
        }
        if ($lesson === null) {
            throw new StorageException(I18n::ui('Choose the lesson this exam belongs to.'));
        }
        $code = self::normalizeCode($code);
        if (mb_strlen($code) < 4 || mb_strlen($code) > 40) {
            throw new StorageException(I18n::ui('The exam code must be 4 to 40 characters.'));
        }
        $owner = self::findByCode($code);
        if ($owner !== null && !($owner['classSlug'] === $classSlug && $owner['id'] === $examId)) {
            throw new StorageException(I18n::ui('That code is already used by another exam.'));
        }
        if ($contentId !== '' && self::getContent($contentId) === null) {
            throw new StorageException(I18n::ui('Exam content not found.'));
        }
        $newContent = $contentId !== '' ? $contentId : null;
        if ($newContent !== ($exam['contentId'] ?? null) && !empty($exam['contentId']) && self::submissionCount($classSlug, $examId) > 0) {
            throw new StorageException(I18n::ui('This exam already has submissions, so its content cannot be changed. Create a new exam instead.'));
        }
        if ($published && $newContent === null) {
            throw new StorageException(I18n::ui('Select an exam content before publishing.'));
        }
        $exam['title'] = $title;
        $exam['published'] = $published;
        $exam['topic'] = $lesson[0];
        $exam['material'] = $lesson[1];
        $exam['contentId'] = $newContent;
        $exam['code'] = $code;
        $exam['settings'] = [
            'monitor' => !empty($settings['monitor']),
            'fullscreen' => !empty($settings['fullscreen']),
            'timeLimitMin' => max(0, min(1440, (int)($settings['timeLimitMin'] ?? 0))),
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

    // ---------- Migration from the old "questions embedded in the exam" layout ----------

    /**
     * One-off, idempotent and non-destructive. Every exam that still carries
     * its own questions gets an ExamContent made from them (question ids are
     * kept, so existing answers and scores stay valid) and a code of its own.
     * The embedded `questions` stay in exam.json untouched and nothing is ever
     * deleted. A marker file stops it from re-running.
     */
    public static function migrate(): void
    {
        $marker = Config::dataDir() . '/.exam_architecture_v2';
        if (is_file($marker)) {
            return;
        }
        $root = Config::dataDir() . '/exams';
        foreach (is_dir($root) ? scandir($root) : [] as $classSlug) {
            if ($classSlug === '.' || $classSlug === '..' || !self::validId($classSlug) || !is_dir($root . '/' . $classSlug)) {
                continue;
            }
            foreach (scandir($root . '/' . $classSlug) as $examId) {
                $exam = self::validId($examId) ? self::rawExam($classSlug, $examId) : null;
                if ($exam === null) {
                    continue;
                }
                $changed = false;
                if (empty($exam['contentId']) && is_array($exam['questions'] ?? null)) {
                    $exam['contentId'] = self::createContent((string)($exam['title'] ?? $examId), $exam['questions']);
                    $changed = true;
                }
                if (empty($exam['code'])) {
                    $exam['code'] = self::generateCode();
                    $changed = true;
                }
                if ($changed) {
                    // Content is already on disk before the exam points at it.
                    self::writeJson(self::examDir($classSlug, $examId) . '/exam.json', $exam);
                }
            }
        }
        if (!is_dir(Config::dataDir())) {
            mkdir(Config::dataDir(), 0775, true);
        }
        file_put_contents($marker, date('c'));
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
            } elseif ($type === 'code') {
                // `text` is the assignment title (shown in the student's sidebar).
                $item['text'] = mb_substr($item['text'], 0, 200);
                $item['description'] = $str($q['description'] ?? '', 20000);
                $item['language'] = in_array($q['language'] ?? '', self::CODE_LANGUAGES, true) ? $q['language'] : 'python';
                $item['starter'] = mb_substr(str_replace("\r\n", "\n", (string)($q['starter'] ?? '')), 0, 50000);
                // Reserved: the guarded chatbot is not implemented yet; the flag is stored so
                // content written today keeps working once it is.
                $item['chat'] = !empty($q['chat']);
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
            if ($q['type'] === 'code') {
                $p['descriptionHtml'] = Markdown::toHtml((string)($q['description'] ?? ''));
                $p['language'] = $q['language'] ?? 'python';
                $p['starter'] = (string)($q['starter'] ?? '');
                $p['chat'] = !empty($q['chat']);
            }
            if (isset($q['pairs'])) {
                $p['lefts'] = array_map(fn($x) => ['id' => $x['id'], 'text' => $x['left']], $q['pairs']);
                $p['rights'] = array_map(fn($x) => ['id' => $x['id'], 'text' => $x['right']], $q['pairs']);
            }
            $questions[] = $p;
        }
        return ['id' => $exam['id'], 'title' => $exam['title'], 'kind' => $exam['kind'] ?? 'written', 'questions' => $questions, 'settings' => self::settingsOf($exam)];
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
            throw new StorageException(I18n::ui('Name and surname are required.'));
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
            // Reserved for the guarded chatbot: { questionId: [ {role, text, at}, ... ] }
            'chats' => new stdClass(),
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

    /**
     * Atomic read-modify-write of one submission. Takes an exclusive lock, re-reads
     * the file, calls $fn($sub) and saves what it returns (return null to leave the
     * file untouched). Everything that changes a submission that may be written
     * concurrently (beacons, heartbeats, submit, teacher marking) goes through here,
     * so no update can overwrite another. Exceptions thrown by $fn propagate
     * (the lock is released). Returns the saved submission, or null.
     */
    public static function mutateSubmission(string $classSlug, string $examId, string $sid, callable $fn): ?array
    {
        $dir = self::examDir($classSlug, $examId);
        if ($dir === null || !self::validSid($sid)) {
            return null;
        }
        $path = $dir . '/submissions/' . $sid . '.json';
        if (!is_file($path)) {
            return null;
        }
        $lock = fopen($path . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new StorageException(I18n::ui('Invalid submission.'));
        }
        try {
            $sub = self::readJson($path);
            if ($sub === null) {
                return null;
            }
            $new = $fn($sub);
            if (!is_array($new)) {
                return null;
            }
            self::writeJson($path, $new);
            return $new;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function serverEvent(string $type, array $detail, int $at): array
    {
        return ['type' => $type, 'at' => $at, 'detail' => json_encode($detail), 'server' => true];
    }

    public static function saveSubmission(string $classSlug, string $examId, array $sub): void
    {
        $dir = self::examDir($classSlug, $examId);
        if ($dir === null || !self::validSid((string)$sub['id'])) {
            throw new StorageException(I18n::ui('Invalid submission.'));
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
        @unlink($dir . '/submissions/' . $sid . '.json.lock');
        @unlink($dir . '/drafts/' . $sid . '.json');
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
        if ($exam === null) {
            throw new StorageException(I18n::ui('Unknown submission.'));
        }
        $settings = self::settingsOf($exam);

        $saved = self::mutateSubmission($classSlug, $examId, $sid, function (array $sub) use ($exam, $settings, $classSlug, $examId, $sid, $payload, $files): array {
            if ($sub['status'] !== 'working') {
                throw new StorageException(I18n::ui('This exam was already submitted.'));
            }

            $raw = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
            $draft = self::readDraft($classSlug, $examId, $sid);
            $answers = [];
            $scores = [];
            foreach ($exam['questions'] as $q) {
                $qid = $q['id'];
                if ($q['type'] === 'code' && !is_string($raw[$qid] ?? null) && isset($draft['answers'][$qid]['code'])) {
                    $raw[$qid] = $draft['answers'][$qid]['code']; // the browser sent nothing: keep the autosaved code
                }
                $answer = self::cleanAnswer($q, $raw[$qid] ?? null);
                if ($q['type'] === 'upload' || $q['type'] === 'draw') {
                    $answer = self::storeFile($classSlug, $examId, $sid, $q, $files['file_' . $qid] ?? null);
                }
                $answers[$qid] = $answer;
                $scores[$qid] = self::autoScore($q, $answer);
            }

            $now = time();
            $started = (int)$sub['startedAt'];
            $sub['answers'] = $answers;
            $sub['scores'] = $scores;
            $sub['autoScores'] = $scores;
            $sub['status'] = 'submitted';
            $sub['submittedAt'] = $now;
            $sub['durationSec'] = max(0, $now - $started);

            // Server-authoritative evidence (server clock, not the browser's).
            $server = array_values(array_filter((array)($sub['events'] ?? []), fn($e) => !empty($e['server']) || in_array($e['type'] ?? '', self::SERVER_TYPES, true)));

            // 1. Time limit. The work is kept (a teacher decides), but flagged.
            $limitSec = (int)$settings['timeLimitMin'] * 60;
            if ($limitSec > 0) {
                $over = $now - ($started + $limitSec);
                if ($over > self::TIME_GRACE_SEC) {
                    $sub['late'] = true;
                    $sub['overtimeSec'] = $over;
                    $server[] = self::serverEvent('late_submit', ['durationSec' => $over, 'limitMin' => (int)$settings['timeLimitMin']], $now);
                }
            }

            if (!empty($settings['monitor'])) {
                $beat = (array)($sub['beat'] ?? []);
                $last = (int)($beat['last'] ?? $started);
                // 2. Silence between the last heartbeat and now.
                if ($now - $last > self::HEARTBEAT_GAP_SEC) {
                    $server[] = self::serverEvent('heartbeat_lost', ['durationSec' => $now - $last, 'final' => true], $last);
                }
                // 3. The guard never reported in: script blocked/removed or tampered with.
                if (empty($beat['guard']) && $sub['durationSec'] >= self::HEARTBEAT_SEC * 2) {
                    $server[] = self::serverEvent('guard_missing', ['beats' => (int)($beat['count'] ?? 0)], $now);
                }
            }

            // Client events never get to use server-only types; server events are kept first
            // so a flood of client events cannot push them out of the cap.
            $client = array_values(array_filter(self::cleanEvents($payload['events'] ?? []), fn($e) => !in_array($e['type'], self::SERVER_TYPES, true)));
            $client = array_slice($client, 0, max(0, self::MAX_EVENTS - count($server)));
            $events = array_merge($server, $client);
            usort($events, fn($a, $b) => $a['at'] <=> $b['at']);
            $sub['events'] = array_slice($events, 0, self::MAX_EVENTS);
            return $sub;
        });
        if ($saved !== null) {
            self::closeDraft($classSlug, $examId, $sid);
        }
        if ($saved === null) {
            throw new StorageException(I18n::ui('Unknown submission.'));
        }
    }

    /** Records an event reported by the browser as the page closes (server time). */
    public static function addBeaconEvent(string $classSlug, string $examId, string $sid, string $type, $detail): void
    {
        if (!in_array($type, self::BEACON_TYPES, true)) {
            return;
        }
        self::mutateSubmission($classSlug, $examId, $sid, function (array $sub) use ($type, $detail): ?array {
            if ($sub['status'] !== 'working') {
                return null;
            }
            $events = (array)($sub['events'] ?? []);
            if (count($events) >= self::MAX_EVENTS - 100) { // headroom for later server events
                return null;
            }
            $events[] = ['type' => $type, 'at' => time(), 'detail' => mb_substr(json_encode($detail), 0, 500), 'server' => true];
            $sub['events'] = $events;
            return $sub;
        });
    }

    /**
     * Heartbeat from the open exam page. Records the server time of the ping and,
     * when the previous ping is too old, a `heartbeat_lost` event measured entirely
     * on the server's clock. $guard says whether the guard scripts are running.
     */
    public static function addHeartbeat(string $classSlug, string $examId, string $sid, bool $guard): void
    {
        self::mutateSubmission($classSlug, $examId, $sid, function (array $sub) use ($guard): ?array {
            if ($sub['status'] !== 'working') {
                return null;
            }
            $now = time();
            $beat = (array)($sub['beat'] ?? ['last' => (int)$sub['startedAt'], 'count' => 0, 'guard' => false]);
            $last = (int)($beat['last'] ?? $sub['startedAt']);
            if ($now - $last < 3 && !empty($beat['count'])) {
                return null; // ignore floods; no write
            }
            $gap = $now - $last;
            $events = (array)($sub['events'] ?? []);
            if ($gap > self::HEARTBEAT_GAP_SEC && !empty($beat['count']) && count($events) < self::MAX_EVENTS - 100) {
                $events[] = self::serverEvent('heartbeat_lost', ['durationSec' => $gap], $last);
                $sub['events'] = $events;
            }
            $sub['beat'] = [
                'last' => $now,
                'count' => (int)($beat['count'] ?? 0) + 1,
                'guard' => !empty($beat['guard']) || $guard,
            ];
            return $sub;
        });
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
        $time = $dur ?? '?';
        switch ($e['type']) {
            case 'left_page':
                $how = ($d['reason'] ?? '') === 'hidden' ? I18n::ui('tab switched / window minimised') : I18n::ui('window lost focus');
                return [I18n::ui('Left the exam page ({how}) for {time}', ['how' => $how, 'time' => $time]), true];
            case 'fullscreen_exit':
                return [I18n::ui('Exited fullscreen for {time}', ['time' => $time]), true];
            case 'page_closed':
                return [I18n::ui('Closed or reloaded the page / browser'), true];
            case 'page_reopened':
                return [I18n::ui('Reopened the exam after being gone {time}', ['time' => $time]), true];
            case 'heartbeat_lost':
                return [I18n::ui('No contact with the exam page for {time} (measured by the server)', ['time' => $time]), true];
            case 'guard_missing':
                return [I18n::ui('Monitoring script never reported in (blocked or tampered with)'), true];
            case 'late_submit':
                return [I18n::ui('Submitted {time} after the time limit', ['time' => $time]), true];
            case 'fullscreen_unsupported':
                return [I18n::ui('Browser does not support fullscreen'), false];
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
            case 'code':
                return mb_substr(str_replace("\r\n", "\n", (string)(is_string($value) ? $value : '')), 0, self::MAX_CODE_CHARS);
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
            throw new StorageException(I18n::ui('A file is too large or failed to upload.'));
        }
        $originalName = mb_substr(basename((string)$file['name']), 0, 150);
        if ($q['type'] === 'draw') {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false || $info[2] !== IMAGETYPE_PNG) {
                throw new StorageException(I18n::ui('Invalid drawing.'));
            }
            $ext = 'png';
            $originalName = 'drawing.png';
        } else {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, self::UPLOAD_EXT, true)) {
                throw new StorageException(I18n::ui('File type not allowed: .{ext}', ['ext' => $ext]));
            }
        }
        $dir = self::examDir($classSlug, $examId) . '/files/' . $sid;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $stored = $q['id'] . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
            throw new StorageException(I18n::ui('Failed to store file.'));
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

    // ---------- Code autosave (server drafts) ----------
    //
    // While a student writes code the browser sends changed assignments every few
    // seconds. Drafts live in their own file per student (drafts/<sid>.json), NOT
    // in the submission: that file is locked and rewritten by heartbeats, events
    // and the final submit, and with ~50 students typing at once every autosave
    // would queue behind them. A draft has exactly one writer (its student's
    // browser, one request in flight at a time), so a plain atomic
    // write-temp-and-rename is enough, with no locking and no contention.

    private static function draftPath(string $classSlug, string $examId, string $sid): ?string
    {
        $dir = self::examDir($classSlug, $examId);
        return ($dir === null || !self::validSid($sid)) ? null : $dir . '/drafts/' . $sid . '.json';
    }

    /** @return array{answers: array<string, array{code: string, at: int}>, final?: bool}|null */
    public static function readDraft(string $classSlug, string $examId, string $sid): ?array
    {
        $path = self::draftPath($classSlug, $examId, $sid);
        $d = $path === null ? null : self::readJson($path);
        if ($d === null) {
            return null;
        }
        $d['answers'] = (array)($d['answers'] ?? []);
        return $d;
    }

    /**
     * Stores the given code answers ({questionId: code}) into the student's draft.
     * Returns the server time of the save, or null when it was refused: unknown
     * student, already submitted, or saving faster than DRAFT_MIN_INTERVAL_SEC.
     */
    public static function saveDraft(string $classSlug, string $examId, string $sid, array $codes): ?int
    {
        $path = self::draftPath($classSlug, $examId, $sid);
        if ($path === null || !is_file(dirname($path, 2) . '/submissions/' . $sid . '.json')) {
            return null;
        }
        $exam = self::getExam($classSlug, $examId);
        if ($exam === null || empty($exam['published'])) {
            return null;
        }
        $draft = self::readDraft($classSlug, $examId, $sid) ?? ['answers' => []];
        if (!empty($draft['final'])) {
            return null;
        }
        clearstatcache(true, $path);
        $now = time();
        if (is_file($path) && $now - (int)filemtime($path) < self::DRAFT_MIN_INTERVAL_SEC) {
            return null;
        }
        $known = [];
        foreach ($exam['questions'] as $q) {
            if ($q['type'] === 'code') {
                $known[$q['id']] = $q;
            }
        }
        foreach ($codes as $qid => $code) {
            if (isset($known[(string)$qid]) && is_string($code)) {
                $draft['answers'][(string)$qid] = [
                    'code' => self::cleanAnswer($known[(string)$qid], $code),
                    'at' => $now,
                ];
            }
        }
        self::writeJson($path, $draft);
        return $now;
    }

    /** Marks the draft closed after the final submit so a late autosave cannot revive it. */
    private static function closeDraft(string $classSlug, string $examId, string $sid): void
    {
        $path = self::draftPath($classSlug, $examId, $sid);
        if ($path !== null) {
            self::writeJson($path, ['answers' => new stdClass(), 'final' => true]);
        }
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
