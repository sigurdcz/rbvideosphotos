<?php
declare(strict_types=1);

// No database writes: render the real gallery panel with mixed content fixtures.
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$portfolioEntries = [
    ['id' => 7, 'title' => '<Photo>', 'content_type' => 'photo', 'video_url' => 'https://youtu.be/example', 'sort_order' => 1, 'status' => 'draft'],
    ['id' => 9, 'title' => 'Video', 'content_type' => 'video', 'sort_order' => 2, 'status' => 'published'],
];
ob_start();
require dirname(__DIR__) . '/admin/views/pages/portfolio-gallery.php';
$html = ob_get_clean();
$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xpath = new DOMXPath($dom);
$check($xpath->query('//section[@aria-label="Fotogalerie"]//a[contains(@href,"edit?id=7")]')->length === 1, 'Photo with a video URL must stay in the photo group.');
$check($xpath->query('//section[@aria-label="Videogalerie"]//a[contains(@href,"edit?id=9")]')->length === 1, 'Video edit link missing.');
$check(str_contains($html, '&lt;Photo&gt;'), 'Gallery titles must be escaped.');
$check($xpath->query('//a[@target="_blank" and @rel="noopener"]')->length === 5, 'All editor links must preserve the open page.');
$check(!str_contains($html, '<form'), 'Panel must not nest mutation forms in the page editor.');
foreach (['photo', 'video'] as $type) {
    $check(str_contains($html, '/admin/portfolio/create?content_type=' . $type), 'Missing typed create link.');
}
$portfolioEntries = [];
ob_start();
require dirname(__DIR__) . '/admin/views/pages/portfolio-gallery.php';
$empty = ob_get_clean();
$check(substr_count($empty, 'Zatím nejsou') === 2, 'Empty groups must remain actionable.');
echo "PASS page gallery panel: grouping, escaping, edit/create links, empty states and no nested forms\n";
