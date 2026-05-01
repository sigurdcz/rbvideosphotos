<?php

/*
 * Router for PHP built-in server.
 * Usage: php -S localhost:8000 router.php
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Serve real static files directly (CSS, JS, images, etc.)
$localFile = __DIR__ . $path;
if (is_file($localFile)) {
    return false; // Let PHP built-in server handle it
}

// Admin panel -> admin/index.php
if (str_starts_with($path, '/admin')) {
    require __DIR__ . '/admin/index.php';
    return true;
}

// Public front -> public/index.php
require __DIR__ . '/public/index.php';
return true;
