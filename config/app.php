<?php

$debugEnv = getenv('APP_DEBUG');

// Auto-detect base URL from the current request when APP_URL is not set.
// Falls back to localhost:8000 only in CLI context (e.g. seed scripts).
if (!function_exists('_detectAppUrl')) {
    function _detectAppUrl(): string {
        if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
            return getenv('APP_URL') ?: 'http://localhost:8000';
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'];
    }
}

return [
    'name'        => 'SEO Mini CMS',
    'url'         => getenv('APP_URL') ?: _detectAppUrl(),
    'debug'       => $debugEnv === false
        ? false
        : filter_var($debugEnv, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
    'timezone'    => 'Europe/Prague',
    'session_name' => 'seo_cms_session',
    'upload_max_size'       => 10  * 1024 * 1024,  // 10 MB  — obrázky
    'upload_video_max_size' => 512 * 1024 * 1024,  // 512 MB — videa
    'allowed_image_types'      => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    'allowed_image_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    'allowed_video_types'      => ['video/mp4', 'video/quicktime', 'video/webm', 'video/avi', 'video/x-msvideo', 'video/x-matroska'],
    'allowed_video_extensions' => ['mp4', 'mov', 'webm', 'avi', 'mkv'],
];
