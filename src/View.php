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
