<?php

namespace App\Core;

class Env
{
    public static function load(string $path): void
    {
        if (!\is_file($path) || !\is_readable($path)) {
            return;
        }

        $lines = \file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = \trim($line);
            if ($line === '' || \str_starts_with($line, '#')) {
                continue;
            }

            $parts = \explode('=', $line, 2);
            if (\count($parts) !== 2) {
                continue;
            }

            $name = \trim($parts[0]);
            $value = \trim($parts[1]);

            if ($name === '') {
                continue;
            }

            if (
                (\str_starts_with($value, '"') && \str_ends_with($value, '"')) ||
                (\str_starts_with($value, "'") && \str_ends_with($value, "'"))
            ) {
                $value = \substr($value, 1, -1);
            }

            if (self::has($name)) {
                continue;
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        if (\array_key_exists($name, $_ENV)) {
            return self::normalize($_ENV[$name], $default);
        }

        if (\array_key_exists($name, $_SERVER)) {
            return self::normalize($_SERVER[$name], $default);
        }

        $value = \getenv($name);
        if ($value !== false) {
            return self::normalize($value, $default);
        }

        return $default;
    }

    public static function has(string $name): bool
    {
        return \array_key_exists($name, $_ENV)
            || \array_key_exists($name, $_SERVER)
            || \getenv($name) !== false;
    }

    private static function normalize(mixed $value, ?string $default): ?string
    {
        if ($value === null) {
            return $default;
        }

        if (\is_string($value)) {
            return $value;
        }

        return (string) $value;
    }
}
