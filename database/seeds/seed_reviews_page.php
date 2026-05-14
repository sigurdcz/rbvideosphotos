<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\ReviewContentService;

Env::load(BASE_PATH . '/.env');

$pdo = Database::getInstance();
$site = $pdo->query('SELECT id FROM sites ORDER BY id ASC LIMIT 1')->fetch();

if (!$site) {
    throw new RuntimeException('No site found. Run the main seed first.');
}

$siteId = (int)$site['id'];

$templateHtml = <<<'HTML'
<section class="page-hero">
  <div class="container">
    <h1 data-block="hero_h1">Hodnoceni klientu</h1>
    <p data-block="hero_sub" class="hero-sub">Slova paru, rodin a firem, se kterymi jsme tvorili fotografie a video.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div data-block="reviews_body" class="reviews-page-grid"><!-- Recenze --></div>
  </div>
</section>
HTML;

$templateCss = <<<'CSS'
.reviews-page-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px}
.reviews-page-card{padding:22px;border:1px solid rgba(198,148,61,.35);background:rgba(255,255,255,.025)}
.reviews-page-stars{color:#e0b866;letter-spacing:3px;margin-bottom:10px}
.reviews-page-card p{color:rgba(255,255,255,.72);font-style:italic;line-height:1.65}
.reviews-page-card strong{display:block;margin-top:16px;color:#fff}
.reviews-page-card span{display:block;color:rgba(255,255,255,.5);font-size:12px}
CSS;

$stmt = $pdo->prepare('SELECT id FROM templates WHERE site_id = ? AND name = ? LIMIT 1');
$stmt->execute([$siteId, 'Hodnoceni']);
$template = $stmt->fetch();

if ($template) {
    $templateId = (int)$template['id'];
    $pdo->prepare('UPDATE templates SET html_source = ?, css_source = ?, js_source = ? WHERE id = ?')
        ->execute([$templateHtml, $templateCss, '', $templateId]);
} else {
    $pdo->prepare('INSERT INTO templates (site_id, name, html_source, css_source, js_source) VALUES (?,?,?,?,?)')
        ->execute([$siteId, 'Hodnoceni', $templateHtml, $templateCss, '']);
    $templateId = (int)$pdo->lastInsertId();
}

$stmt = $pdo->prepare('SELECT id FROM pages WHERE site_id = ? AND slug = ? LIMIT 1');
$stmt->execute([$siteId, 'hodnoceni']);
$page = $stmt->fetch();

if ($page) {
    $pageId = (int)$page['id'];
    $pdo->prepare('UPDATE pages SET template_id = ?, title = ?, url_path = ?, status = ?, published_at = NOW() WHERE id = ?')
        ->execute([$templateId, 'Hodnoceni', 'hodnoceni', 'published', $pageId]);
} else {
    $pdo->prepare('INSERT INTO pages (site_id, template_id, title, slug, url_path, language, status, published_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([$siteId, $templateId, 'Hodnoceni', 'hodnoceni', 'hodnoceni', 'cs', 'published']);
    $pageId = (int)$pdo->lastInsertId();
}

$blocks = [
    ['hero_h1', 'text', 'Hero - nadpis', 'Hodnoceni klientu', 1],
    ['hero_sub', 'textarea', 'Hero - intro', 'Slova paru, rodin a firem, se kterymi jsme tvorili fotografie a video.', 2],
    ['reviews_body', 'html', 'Recenze', '', 3],
];

foreach ($blocks as [$key, $type, $label, $content, $order]) {
    $stmt = $pdo->prepare('SELECT id FROM content_blocks WHERE page_id = ? AND block_key = ? LIMIT 1');
    $stmt->execute([$pageId, $key]);
    $block = $stmt->fetch();
    if ($block) {
        $pdo->prepare('UPDATE content_blocks SET block_type = ?, label = ?, content = ?, sort_order = ? WHERE id = ?')
            ->execute([$type, $label, $content, $order, (int)$block['id']]);
        continue;
    }
    $pdo->prepare('INSERT INTO content_blocks (page_id, block_key, block_type, label, content, sort_order) VALUES (?,?,?,?,?,?)')
        ->execute([$pageId, $key, $type, $label, $content, $order]);
}

$pdo->exec("DELETE FROM reviews WHERE site_id = {$siteId}");
$stmt = $pdo->prepare('INSERT INTO reviews (site_id, author_name, context_label, rating, review_text, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');

$reviews = [
    ['Jan Jungling', 'Svatba M+H', 5, 'Kluci jsou naprosti profesionalove. Jsou peclivi a dokazou zachytit skutecne emoce.', 1, 1],
    ['Vaclav Konopka', 'Svatebni foceni', 5, 'Super domluva, mily a profesionalni pristup k foceni. Velka spokojenost.', 2, 1],
    ['Jana Dudkova', 'Rodinne foceni', 5, 'Moc doporucuji. Vse profesionalne nafocene, krasne barvy a skvela domluva.', 3, 1],
    ['Marketa a Tomas', 'Svatebni video', 5, 'Video vystihlo atmosferu dne do posledniho detailu. Vracime se k nemu porad dokola.', 4, 1],
    ['Lucie Novakova', 'Portretni foceni', 5, 'Foceni bylo uvolnene a bez stresu. Vysledek pusobi prirozene a profesionalne.', 5, 1],
    ['Petr Maly', 'Firemni akce', 5, 'Rychla domluva, jistota na miste a vystupy pouzitelne rovnou na web i socialni site.', 6, 1],
];

foreach ($reviews as [$author, $context, $rating, $text, $sortOrder, $isActive]) {
    $stmt->execute([$siteId, $author, $context, $rating, $text, $sortOrder, $isActive]);
}

(new ReviewContentService())->ensureHomepageSection($siteId);

echo "Reviews page seeded at /hodnoceni\n";
