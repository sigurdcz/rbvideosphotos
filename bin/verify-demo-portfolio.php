<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load(BASE_PATH . '/.env');
$db = Database::getInstance();
$rows = $db->query(
    'SELECT p.slug, p.title, p.content_type, p.video_url, p.published_at, p.sort_order, COUNT(pm.media_id) AS photo_count
     FROM portfolio_entries p
     LEFT JOIN portfolio_media pm ON pm.portfolio_id = p.id
     GROUP BY p.id
     ORDER BY p.sort_order'
)->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) !== 36) {
    throw new RuntimeException('Očekáváno 36 portfolio položek (9+15 fotogalerií + 12 videí), nalezeno ' . count($rows) . '.');
}

$photoRows = array_values(array_filter($rows, static fn(array $r): bool => $r['content_type'] === 'photo'));
$videoRows = array_values(array_filter($rows, static fn(array $r): bool => $r['content_type'] === 'video'));

// First 9 = PortfolioDemoContentImporter::MANIFEST (original curated galleries, sort_order 1-9).
// Next 15 = MIX_MANIFEST (2026-09-06 mixed-photo batch, sort_order 10-24), in manifest order.
$expectedCounts = [2, 3, 7, 2, 5, 1, 6, 4, 3, 5, 3, 2, 3, 3, 2, 4, 5, 4, 8, 10, 5, 15, 5, 2];
if (count($photoRows) !== 24) {
    throw new RuntimeException('Očekáváno 24 fotogalerií (9 + 15), nalezeno ' . count($photoRows) . '.');
}
foreach ($photoRows as $index => $row) {
    if ((int)$row['photo_count'] !== $expectedCounts[$index]) {
        throw new RuntimeException('Nesouhlasí počet fotografií galerie ' . $row['slug'] . ' (ocekavano ' . $expectedCounts[$index] . ', nalezeno ' . $row['photo_count'] . ').');
    }
}

if (count($videoRows) !== 12) {
    throw new RuntimeException('Očekáváno přesně 12 videí ve Videogalerii, nalezeno ' . count($videoRows) . '.');
}
$videoTitles = [];
foreach ($videoRows as $row) {
    if (trim((string)$row['video_url']) === '') {
        throw new RuntimeException('Video položka nemá video_url: ' . $row['slug']);
    }
    if (isset($videoTitles[$row['title']])) {
        throw new RuntimeException('Video položky mají nerozlišitelný název: ' . $row['title']);
    }
    $videoTitles[$row['title']] = true;
}

$legacySlugs = [
    'svatebni-foto-video-olomouc',
    'svatebni-fotograf-brno',
    'rodinne-portretni-foceni',
    'reklamni-foto-video-olomouc',
    'portretni-foceni-olomouc',
];
$placeholders = implode(',', array_fill(0, count($legacySlugs), '?'));
$legacyStmt = $db->prepare('SELECT COUNT(*) FROM portfolio_entries WHERE slug IN (' . $placeholders . ')');
$legacyStmt->execute($legacySlugs);
if ((int)$legacyStmt->fetchColumn() !== 0) {
    throw new RuntimeException('V databázi zůstaly záznamy starého portfolia.');
}

$mediaCount = (int)$db->query("SELECT COUNT(*) FROM media WHERE original_filename LIKE 'portfolio-%'")->fetchColumn();
if ($mediaCount !== 109) {
    throw new RuntimeException('Očekáváno 109 médií nového portfolia (33 + 76), nalezeno ' . $mediaCount . '.');
}

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
echo "Portfolio ověřeno: 24 fotogalerií (109 fotografií), 12 videí, 0 starých záznamů.\n";
