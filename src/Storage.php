<?php

final class StorageException extends RuntimeException
{
}

final class Storage
{
    public static function slugify(string $text): string
    {
        $text = trim($text);
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($translit !== false) {
            $text = $translit;
        }
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        return $text !== '' ? $text : 'item';
    }

    private static function uniqueSlug(string $base, string $parentDir): string
    {
        $slug = $base;
        $i = 2;
        while (is_dir($parentDir . '/' . $slug) || is_file($parentDir . '/' . $slug . '.md')) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    private static function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private static function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    // ---------- Classes ----------

    public static function listClasses(): array
    {
        $root = Config::contentDir();
        if (!is_dir($root)) {
            return [];
        }
        $out = [];
        foreach (scandir($root) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $dir = $root . '/' . $entry;
            if (!is_dir($dir)) {
                continue;
            }
            $meta = self::readJson($dir . '/meta.json');
            $out[] = ['slug' => $entry, 'title' => $meta['title'] ?? $entry];
        }
        usort($out, fn($a, $b) => strcasecmp($a['title'], $b['title']));
        return $out;
    }

    public static function classExists(string $classSlug): bool
    {
        return is_dir(Config::contentDir() . '/' . $classSlug);
    }

    public static function getClassTitle(string $classSlug): ?string
    {
        $dir = Config::contentDir() . '/' . $classSlug;
        if (!is_dir($dir)) {
            return null;
        }
        $meta = self::readJson($dir . '/meta.json');
        return $meta['title'] ?? $classSlug;
    }

    public static function createClass(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            throw new StorageException('Class name cannot be empty.');
        }
        $root = Config::contentDir();
        if (!is_dir($root)) {
            mkdir($root, 0775, true);
        }
        $slug = self::uniqueSlug(self::slugify($title), $root);
        mkdir($root . '/' . $slug, 0775, true);
        self::writeJson($root . '/' . $slug . '/meta.json', ['title' => $title]);
        return $slug;
    }

    public static function renameClass(string $classSlug, string $newTitle): void
    {
        $dir = Config::contentDir() . '/' . $classSlug;
        if (!is_dir($dir)) {
            throw new StorageException('Class not found.');
        }
        $newTitle = trim($newTitle);
        if ($newTitle === '') {
            throw new StorageException('Class name cannot be empty.');
        }
        $meta = self::readJson($dir . '/meta.json');
        $meta['title'] = $newTitle;
        self::writeJson($dir . '/meta.json', $meta);
    }

    public static function deleteClass(string $classSlug): void
    {
        $dir = Config::contentDir() . '/' . $classSlug;
        self::rrmdir($dir);
    }

    // ---------- Topics ----------

    public static function listTopics(string $classSlug): array
    {
        $classDir = Config::contentDir() . '/' . $classSlug;
        if (!is_dir($classDir)) {
            return [];
        }
        $out = [];
        foreach (scandir($classDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $dir = $classDir . '/' . $entry;
            if (!is_dir($dir)) {
                continue;
            }
            $meta = self::readJson($dir . '/meta.json');
            $out[] = ['slug' => $entry, 'title' => $meta['title'] ?? $entry];
        }
        usort($out, fn($a, $b) => strcasecmp($a['title'], $b['title']));
        return $out;
    }

    public static function topicExists(string $classSlug, string $topicSlug): bool
    {
        return is_dir(Config::contentDir() . '/' . $classSlug . '/' . $topicSlug);
    }

    public static function getTopicTitle(string $classSlug, string $topicSlug): ?string
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug;
        if (!is_dir($dir)) {
            return null;
        }
        $meta = self::readJson($dir . '/meta.json');
        return $meta['title'] ?? $topicSlug;
    }

    public static function createTopic(string $classSlug, string $title): string
    {
        $classDir = Config::contentDir() . '/' . $classSlug;
        if (!is_dir($classDir)) {
            throw new StorageException('Class not found.');
        }
        $title = trim($title);
        if ($title === '') {
            throw new StorageException('Topic name cannot be empty.');
        }
        $slug = self::uniqueSlug(self::slugify($title), $classDir);
        mkdir($classDir . '/' . $slug, 0775, true);
        mkdir($classDir . '/' . $slug . '/files', 0775, true);
        self::writeJson($classDir . '/' . $slug . '/meta.json', ['title' => $title, 'order' => [], 'lang' => I18n::defaultLang()]);
        return $slug;
    }

    public static function renameTopic(string $classSlug, string $topicSlug, string $newTitle): void
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug;
        if (!is_dir($dir)) {
            throw new StorageException('Topic not found.');
        }
        $newTitle = trim($newTitle);
        if ($newTitle === '') {
            throw new StorageException('Topic name cannot be empty.');
        }
        $meta = self::readJson($dir . '/meta.json');
        $meta['title'] = $newTitle;
        self::writeJson($dir . '/meta.json', $meta);
    }

    public static function deleteTopic(string $classSlug, string $topicSlug): void
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug;
        self::rrmdir($dir);
    }

    public static function getTopicLang(string $classSlug, string $topicSlug): string
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug;
        $meta = self::readJson($dir . '/meta.json');
        return $meta['lang'] ?? I18n::defaultLang();
    }

    public static function setTopicLang(string $classSlug, string $topicSlug, string $lang): void
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug;
        if (!is_dir($dir)) {
            throw new StorageException('Topic not found.');
        }
        if (!I18n::isAvailable($lang)) {
            throw new StorageException('Unknown language.');
        }
        $meta = self::readJson($dir . '/meta.json');
        $meta['lang'] = $lang;
        self::writeJson($dir . '/meta.json', $meta);
    }

    // ---------- Materials ----------

    public static function listMaterials(string $classSlug, string $topicSlug): array
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug;
        if (!is_dir($dir)) {
            return [];
        }
        $meta = self::readJson($dir . '/meta.json');
        $order = $meta['order'] ?? [];

        $existing = [];
        foreach (scandir($dir) as $entry) {
            if (substr($entry, -3) === '.md') {
                $existing[] = substr($entry, 0, -3);
            }
        }

        $ordered = array_values(array_intersect($order, $existing));
        $remaining = array_values(array_diff($existing, $ordered));
        sort($remaining);
        $slugs = array_merge($ordered, $remaining);

        $out = [];
        foreach ($slugs as $slug) {
            $out[] = ['slug' => $slug, 'title' => self::materialTitle($dir, $slug)];
        }
        return $out;
    }

    private static function materialTitle(string $topicDir, string $materialSlug): string
    {
        $path = $topicDir . '/' . $materialSlug . '.md';
        if (is_file($path)) {
            $handle = fopen($path, 'r');
            if ($handle) {
                $firstLine = fgets($handle);
                fclose($handle);
                if ($firstLine !== false && preg_match('/^#\s+(.+)/', trim($firstLine), $m)) {
                    return trim($m[1]);
                }
            }
        }
        return ucwords(str_replace('-', ' ', $materialSlug));
    }

    public static function materialExists(string $classSlug, string $topicSlug, string $materialSlug): bool
    {
        return is_file(Config::contentDir() . '/' . $classSlug . '/' . $topicSlug . '/' . $materialSlug . '.md');
    }

    public static function getMaterialContent(string $classSlug, string $topicSlug, string $materialSlug): ?string
    {
        $path = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug . '/' . $materialSlug . '.md';
        if (!is_file($path)) {
            return null;
        }
        return (string)file_get_contents($path);
    }

    public static function getMaterialTitle(string $classSlug, string $topicSlug, string $materialSlug): string
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug;
        return self::materialTitle($dir, $materialSlug);
    }

    /**
     * Creates a new material (when $materialSlug is null) or updates an existing one.
     * Returns the slug used.
     */
    public static function saveMaterial(string $classSlug, string $topicSlug, ?string $materialSlug, string $title, string $body): string
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug;
        if (!is_dir($dir)) {
            throw new StorageException('Topic not found.');
        }
        $title = trim($title);
        if ($title === '') {
            throw new StorageException('Title cannot be empty.');
        }

        $isNew = $materialSlug === null || !is_file($dir . '/' . $materialSlug . '.md');
        if ($isNew) {
            $materialSlug = self::uniqueMaterialSlug(self::slugify($title), $dir);
        }

        $content = '# ' . $title . "\n\n" . ltrim($body, "\n");
        file_put_contents($dir . '/' . $materialSlug . '.md', $content);

        $meta = self::readJson($dir . '/meta.json');
        $order = $meta['order'] ?? [];
        if (!in_array($materialSlug, $order, true)) {
            $order[] = $materialSlug;
        }
        $meta['order'] = $order;
        self::writeJson($dir . '/meta.json', $meta);

        return $materialSlug;
    }

    private static function uniqueMaterialSlug(string $base, string $topicDir): string
    {
        $slug = $base;
        $i = 2;
        while (is_file($topicDir . '/' . $slug . '.md')) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    public static function deleteMaterial(string $classSlug, string $topicSlug, string $materialSlug): void
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug;
        $path = $dir . '/' . $materialSlug . '.md';
        if (is_file($path)) {
            unlink($path);
        }
        $meta = self::readJson($dir . '/meta.json');
        $meta['order'] = array_values(array_diff($meta['order'] ?? [], [$materialSlug]));
        self::writeJson($dir . '/meta.json', $meta);
    }

    public static function reorderMaterials(string $classSlug, string $topicSlug, array $slugOrder): void
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug;
        if (!is_dir($dir)) {
            throw new StorageException('Topic not found.');
        }
        $meta = self::readJson($dir . '/meta.json');
        $meta['order'] = array_values($slugOrder);
        self::writeJson($dir . '/meta.json', $meta);
    }

    // ---------- Uploads (images / PDFs) ----------

    private const ALLOWED_UPLOAD_EXT = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'pdf'];

    public static function saveUpload(string $classSlug, string $topicSlug, string $tmpPath, string $originalName): array
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug . '/files';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_UPLOAD_EXT, true)) {
            throw new StorageException('File type not allowed: .' . $ext);
        }

        $base = self::slugify(pathinfo($originalName, PATHINFO_FILENAME));
        $filename = $base . '.' . $ext;
        $i = 2;
        while (is_file($dir . '/' . $filename)) {
            $filename = $base . '-' . $i . '.' . $ext;
            $i++;
        }

        if (!move_uploaded_file($tmpPath, $dir . '/' . $filename)) {
            throw new StorageException('Failed to save uploaded file.');
        }

        return [
            'filename' => $filename,
            'url' => '/media/' . rawurlencode($classSlug) . '/' . rawurlencode($topicSlug) . '/' . rawurlencode($filename),
        ];
    }

    public static function listUploads(string $classSlug, string $topicSlug): array
    {
        $dir = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug . '/files';
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..' || !is_file($dir . '/' . $entry)) {
                continue;
            }
            $out[] = [
                'filename' => $entry,
                'url' => '/media/' . rawurlencode($classSlug) . '/' . rawurlencode($topicSlug) . '/' . rawurlencode($entry),
            ];
        }
        sort($out);
        return $out;
    }

    public static function deleteUpload(string $classSlug, string $topicSlug, string $filename): void
    {
        $path = Config::contentDir() . '/' . $classSlug . '/' . $topicSlug . '/files/' . basename($filename);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public static function mediaPath(string $classSlug, string $topicSlug, string $filename): ?string
    {
        $path = Config::contentDir() . '/' . basename($classSlug) . '/' . basename($topicSlug) . '/files/' . basename($filename);
        return is_file($path) ? $path : null;
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
            if (is_dir($path)) {
                self::rrmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
