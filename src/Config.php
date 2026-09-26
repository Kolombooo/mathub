<?php

final class Config
{
    public static function contentDir(): string
    {
        return dirname(__DIR__) . '/content';
    }

    public static function dataDir(): string
    {
        return dirname(__DIR__) . '/data';
    }

    public static function usersFile(): string
    {
        return self::dataDir() . '/users.json';
    }
}
