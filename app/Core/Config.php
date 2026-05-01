<?php

namespace App\Core;

class Config
{
    private static array $cache = [];

    public static function get(string $file, string $key = '', mixed $default = null): mixed
    {
        if (!isset(self::$cache[$file])) {
            $path = dirname(__DIR__, 2) . '/config/' . $file . '.php';
            self::$cache[$file] = file_exists($path) ? require $path : [];
        }

        if ($key === '') {
            return self::$cache[$file];
        }

        return self::$cache[$file][$key] ?? $default;
    }
}
