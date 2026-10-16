<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/vendor/autoload.php';
use App\Core\Env;
use App\Repositories\SiteRepository;
use App\Repositories\UserRepository;
use App\Services\PublishService;
Env::load(__DIR__ . '/.env');
$site = (new SiteRepository())->findFirst();
$userId = (new UserRepository())->firstId();
if ($site === null || $userId === null) { throw new RuntimeException('Publish requires an existing site and user.'); }
$result = (new PublishService())->publishSite((int)$site['id'], $userId);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit(($result['success'] ?? false) ? 0 : 1);
