<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load(dirname(__DIR__) . '/.env');
$db = Database::getInstance();
$site = $db->query('SELECT * FROM sites ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$site) {
    fwrite(STDERR, "No site found.\n");
    exit(1);
}

$pageStatement = $db->prepare(
    'SELECT p.id,p.url_path,p.title,p.status,
            s.meta_title,s.meta_description,s.canonical_url,s.robots_index,s.robots_follow,
            s.og_title,s.og_description,s.og_image_id,
            s.twitter_title,s.twitter_description,s.twitter_image_id,s.schema_type,
            s.schema_name,s.schema_description,s.schema_image_id,s.schema_contact_auto,
            s.schema_telephone,s.schema_email,s.schema_address_street,s.schema_address_locality
     FROM pages p
     LEFT JOIN seo_settings s ON s.page_id=p.id
     WHERE p.site_id=?
     ORDER BY p.url_path'
);
$pageStatement->execute([(int)$site['id']]);
$pages = $pageStatement->fetchAll(PDO::FETCH_ASSOC);

$blockStatement = $db->prepare(
    'SELECT p.url_path,b.block_key,b.block_type,b.label,b.content
     FROM pages p
     JOIN content_blocks b ON b.page_id=p.id
     WHERE p.site_id=?
     ORDER BY p.url_path,b.sort_order,b.id'
);
$blockStatement->execute([(int)$site['id']]);
$mediaStatement = $db->prepare(
    'SELECT id,original_filename,storage_path,mime_type,width,height
     FROM media
     WHERE site_id=?
     ORDER BY id'
);
$mediaStatement->execute([(int)$site['id']]);

echo json_encode([
    'site' => [
        'id' => (int)$site['id'],
        'domain' => (string)$site['domain'],
        'active_theme_key' => (string)($site['active_theme_key'] ?? ''),
    ],
    'pages' => $pages,
    'blocks' => $blockStatement->fetchAll(PDO::FETCH_ASSOC),
    'media' => $mediaStatement->fetchAll(PDO::FETCH_ASSOC),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
