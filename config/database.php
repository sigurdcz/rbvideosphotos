<?php

return [
    // 'sqlite' pro lokální vývoj, 'mysql' pro produkci (Active24)
    'driver'   => getenv('DB_DRIVER') ?: 'sqlite',

    // SQLite — cesta k souboru (automaticky vytvořen)
    'path'     => getenv('DB_PATH') ?: dirname(__DIR__) . '/storage/database.sqlite',

    // MySQL/MariaDB — pro produkci na Active24
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => getenv('DB_PORT') ?: '3306',
    'dbname'   => getenv('DB_NAME') ?: 'seo_cms',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
];
