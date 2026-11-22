<?php

final class View
{
    private static string $viewsDir;

    public static function init(string $viewsDir): void
    {
        self::$viewsDir = $viewsDir;
    }

    public static function render(string $template, array $vars = [], ?string $layout = 'layout'): void
    {
        $content = self::capture($template, $vars);
        if ($layout === null) {
            echo $content;
            return;
        }
        $vars['content'] = $content;
        echo self::capture($layout, $vars);
    }

    private static function capture(string $template, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require self::$viewsDir . '/' . $template . '.php';
        return (string)ob_get_clean();
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /** Human-readable file size, e.g. "1.4 MB". */
    public static function fileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        return number_format($value, $value < 10 ? 1 : 0, '.', '') . ' ' . $units[$i];
    }

    /** Translated string as a JS string literal, safe inside an HTML attribute (e.g. onsubmit="return confirm(...)"). */
    public static function jsq(string $text, array $params = []): string
    {
        return self::e(json_encode(I18n::ui($text, $params), JSON_UNESCAPED_UNICODE));
    }

    /** Translated (UI language) and HTML-escaped interface string. */
    public static function tr(string $text, array $params = []): string
    {
        return self::e(I18n::ui($text, $params));
    }

    /**
     * Appends a cache-busting ?v=<mtime> to a /assets/... URL, so browsers
     * fetch a fresh copy whenever the file actually changes instead of
     * serving a stale cached one after a deploy.
     */
    public static function asset(string $path): string
    {
        $file = dirname(__DIR__) . '/public' . $path;
        $version = is_file($file) ? (string)filemtime($file) : (string)time();
        return $path . '?v=' . $version;
    }
}
