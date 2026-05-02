<?php

namespace App\Core;

class Logger
{
    public static function info(string $category, string $message, array $context = []): void
    {
        self::write('INFO', $category, $message, $context);
    }

    public static function warning(string $category, string $message, array $context = []): void
    {
        self::write('WARNING', $category, $message, $context);
    }

    public static function error(string $category, string $message, array $context = []): void
    {
        self::write('ERROR', $category, $message, $context);
    }

    public static function critical(string $category, string $message, array $context = []): void
    {
        self::write('CRITICAL', $category, $message, $context);
    }

    private static function write(string $level, string $category, string $message, array $context = []): void
    {
        $directory = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $filename = $directory . '/app-' . date('Y-m-d') . '.log';
        $line = sprintf('[%s] [%s] [%s] %s', $timestamp, $level, $category, $message);

        if ($context !== []) {
            $json = json_encode(self::normalize($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                $line .= ' ' . $json;
            }
        }

        $line .= PHP_EOL;
        @file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
    }

    private static function normalize(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = self::normalize($value);
                continue;
            }

            if (is_object($value)) {
                $normalized[$key] = method_exists($value, '__toString')
                    ? (string) $value
                    : get_class($value);
                continue;
            }

            if (is_resource($value)) {
                $normalized[$key] = get_resource_type($value);
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
