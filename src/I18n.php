<?php

final class I18n
{
    private const DEFAULT_LANG = 'en';

    /** @var array<string, array<string, string>> */
    private static array $cache = [];

    public static function dir(): string
    {
        return dirname(__DIR__) . '/lang';
    }

    public static function defaultLang(): string
    {
        return self::DEFAULT_LANG;
    }

    /** @return list<array{code: string, name: string}> */
    public static function available(): array
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir . '/*.json') as $file) {
            $code = basename($file, '.json');
            $data = self::load($code);
            $out[] = ['code' => $code, 'name' => $data['_name'] ?? $code];
        }
        usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    public static function isAvailable(string $code): bool
    {
        return is_file(self::dir() . '/' . basename($code) . '.json');
    }

    /** @return array<string, string> */
    public static function load(string $code): array
    {
        $code = basename($code);
        if (isset(self::$cache[$code])) {
            return self::$cache[$code];
        }
        $path = self::dir() . '/' . $code . '.json';
        $data = is_file($path) ? (json_decode((string)file_get_contents($path), true) ?: []) : [];
        return self::$cache[$code] = $data;
    }

    public static function t(string $code, string $key): string
    {
        $data = self::load($code);
        if (isset($data[$key])) {
            return $data[$key];
        }
        if ($code !== self::DEFAULT_LANG) {
            $fallback = self::load(self::DEFAULT_LANG);
            if (isset($fallback[$key])) {
                return $fallback[$key];
            }
        }
        return $key;
    }
}
