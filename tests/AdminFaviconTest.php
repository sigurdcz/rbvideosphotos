<?php

declare(strict_types=1);

function register_admin_favicon_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('all admin entry layouts use the dedicated favicon', function () use ($root, $assert): void {
        foreach ([
            'admin/views/layouts/main.php',
            'admin/views/auth/login.php',
            'admin/views/auth/recovery.php',
        ] as $relative) {
            $source = file_get_contents($root . '/' . $relative);
            $assert(is_string($source), 'Admin view could not be read: ' . $relative);
            $assert(str_contains($source, 'admin-favicon.svg'), 'Admin favicon is missing from: ' . $relative);
            $assert(str_contains($source, 'name="theme-color" content="#172033"'), 'Admin theme color is missing from: ' . $relative);
        }
    });

    $test('admin favicon is a self-contained square SVG', function () use ($root, $assert): void {
        $source = file_get_contents($root . '/admin/assets/admin-favicon.svg');
        $assert(is_string($source), 'Admin favicon could not be read.');
        $assert(str_contains($source, 'viewBox="0 0 64 64"'), 'Admin favicon must use a square viewBox.');
        $assert(str_contains($source, 'fill="#172033"'), 'Admin favicon must retain its distinguishing background.');
        $assert(!preg_match('/<(?:script|image|use)\b/i', $source), 'Admin favicon must not load executable or external content.');
    });
}
