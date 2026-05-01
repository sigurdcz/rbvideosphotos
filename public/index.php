<?php
/*
 * Public front-end entry point.
 *
 * In production, serve static files from /storage/published directly via
 * the web server. This PHP file is only used for local development routing
 * or when you want to serve the published static files through PHP.
 */

$publishedDir = dirname(__DIR__) . '/storage/published';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Serve static assets (CSS, JS, images) from /public/assets
if (str_starts_with($uri, '/assets/')) {
    $file = __DIR__ . $uri;
    if (is_file($file)) {
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg','jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($file);
        exit;
    }
}

// Resolve to published HTML file
$path = ltrim($uri, '/');
if ($path === '') {
    $candidate = $publishedDir . '/index.html';
} else {
    $candidate = $publishedDir . '/' . $path . '/index.html';
    if (!file_exists($candidate)) {
        $candidate = $publishedDir . '/' . $path . '.html';
    }
}

if (file_exists($candidate)) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($candidate);
    exit;
}

// Serve any root-level static file directly from storage/published
$rootFile = $publishedDir . '/' . $path;
if ($path !== '' && is_file($rootFile)) {
    $ext  = strtolower(pathinfo($rootFile, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'ico'          => 'image/x-icon',
        'png'          => 'image/png',
        'jpg', 'jpeg'  => 'image/jpeg',
        'webp'         => 'image/webp',
        'svg'          => 'image/svg+xml',
        'webmanifest'  => 'application/manifest+json',
        'json'         => 'application/json',
        'xml'          => 'application/xml',
        'txt'          => 'text/plain',
        default        => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    readfile($rootFile);
    exit;
}

http_response_code(404);
echo '<!DOCTYPE html><html><body><h1>404 Stránka nenalezena</h1></body></html>';
