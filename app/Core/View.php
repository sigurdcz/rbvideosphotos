<?php

namespace App\Core;

class View
{
    private static string $viewsPath = '';

    public static function render(string $template, array $data = []): void
    {
        $__path = self::resolvePath($template);
        if (!file_exists($__path)) {
            throw new \RuntimeException("View not found: {$__path}");
        }
        extract($data, EXTR_OVERWRITE);
        require $__path;
    }

    public static function renderString(string $template, array $data = []): string
    {
        ob_start();
        self::render($template, $data);
        return ob_get_clean();
    }

    private static function resolvePath(string $template): string
    {
        $base = dirname(__DIR__, 2) . '/admin/views/';
        return $base . str_replace('.', '/', $template) . '.php';
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
