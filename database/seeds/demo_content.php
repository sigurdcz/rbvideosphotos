<?php

declare(strict_types=1);

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/vendor/autoload.php';

\App\Core\Env::load(BASE_PATH . '/.env');

$userId = (int)(\App\Core\Database::getInstance()
    ->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')
    ->fetchColumn() ?: 0);

$result = (new \App\Services\DemoContentSeeder())->run(null, $userId ?: null, $userId > 0);

echo 'Demo content seed dokončen: '
    . (int)$result['blog_posts'] . ' článků, '
    . (int)$result['portfolio_entries'] . ' prací, '
    . (int)$result['reviews'] . ' recenzí'
    . (!empty($result['published']) ? ' a web byl znovu publikován' : '')
    . ".\n";

foreach (($result['portfolio_warnings'] ?? []) as $warning) {
    fwrite(STDERR, "UPOZORNĚNÍ Naše práce: {$warning}\n");
}
