<?php

use App\Core\Env;

return [
    'driver'   => Env::get('DB_DRIVER') ?: 'mysql',
    'host'     => Env::get('DB_HOST') ?: '127.0.0.1',
    'port'     => Env::get('DB_PORT') ?: '3306',
    'dbname'   => Env::get('DB_NAME') ?: 'seo_editor_cms',
    'username' => Env::get('DB_USER') ?: 'seo_editor_app',
    'password' => Env::get('DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
];
