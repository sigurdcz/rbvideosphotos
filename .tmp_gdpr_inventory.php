<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/vendor/autoload.php';
use App\Core\Database;
use App\Core\Env;
Env::load(__DIR__ . '/.env');
$db = Database::getInstance();
$result = [];
foreach (['blog_posts', 'portfolio_entries', 'redirects'] as $table) {
    $result[$table] = $db->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
