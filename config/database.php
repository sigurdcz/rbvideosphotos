<?php

return [
    'driver'   => getenv('DB_DRIVER') ?: 'mysql',
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => getenv('DB_PORT') ?: '3306',
    'dbname'   => getenv('DB_NAME') ?: 'seo_editor_cms',
    'username' => getenv('DB_USER') ?: 'seo_editor_app',
    'password' => getenv('DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
];
