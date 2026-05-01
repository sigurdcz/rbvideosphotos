<?php

return [
    'name'        => 'SEO Mini CMS',
    'url'         => getenv('APP_URL') ?: 'http://localhost:8000',
    'debug'       => (bool)(getenv('APP_DEBUG') ?: true),
    'timezone'    => 'Europe/Prague',
    'session_name' => 'seo_cms_session',
    'upload_max_size' => 10 * 1024 * 1024, // 10 MB
    'allowed_image_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'allowed_image_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
];
