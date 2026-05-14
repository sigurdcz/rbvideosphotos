<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\PublishService;

Env::load(BASE_PATH . '/.env');

$pdo = Database::getInstance();
$site = $pdo->query('SELECT id FROM sites ORDER BY id ASC LIMIT 1')->fetch();
$user = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetch();

if (!$site) {
    throw new RuntimeException('No site found. Run the main seed first.');
}

if (!$user) {
    throw new RuntimeException('No user found. Run the main seed first.');
}

$siteId = (int)$site['id'];
$userId = (int)$user['id'];

$templateHtml = <<<'HTML'
<section class="page-hero">
  <div class="container">
    <h1 data-block="hero_h1">Podmínky užití</h1>
    <p data-block="hero_sub" class="hero-sub">Pravidla provozu webu, pouzivani obsahu a informace o cookies.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <p class="section-label">Pravidla provozu</p>
    <h2 data-block="rules_title" class="section-title">Co plati pri pouzivani tohoto webu</h2>
    <p data-block="rules_intro" class="section-intro">Stranka shrnuje zakladni podminky pouzivani, pravidla pro cookies a zpusob kontaktu.</p>
    <div class="legal-grid">
      <article data-block="rule_1" class="legal-card"></article>
      <article data-block="rule_2" class="legal-card"></article>
      <article data-block="rule_3" class="legal-card"></article>
      <article data-block="rule_4" class="legal-card"></article>
    </div>
    <div data-block="cta_wrap" class="hp-section-cta"></div>
  </div>
</section>
HTML;

$templateCss = <<<'CSS'
.legal-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px}
.legal-card{padding:24px;border:1px solid rgba(198,148,61,.3);background:rgba(255,255,255,.025)}
.legal-card h3{margin:0 0 10px;color:#fff;font-size:1.1rem}
.legal-card p,.legal-card li{color:rgba(255,255,255,.72);line-height:1.65}
.legal-card ul{margin:10px 0 0 18px;padding:0}
CSS;

$stmt = $pdo->prepare('SELECT id FROM templates WHERE site_id = ? AND name IN (?, ?) ORDER BY FIELD(name, ?, ?) LIMIT 1');
$stmt->execute([$siteId, 'Podmínky užití', 'Ty krása no', 'Podmínky užití', 'Ty krása no']);
$template = $stmt->fetch();

if ($template) {
    $templateId = (int)$template['id'];
    $pdo->prepare('UPDATE templates SET name = ?, html_source = ?, css_source = ?, js_source = ? WHERE id = ?')
        ->execute(['Podmínky užití', $templateHtml, $templateCss, '', $templateId]);
} else {
    $pdo->prepare('INSERT INTO templates (site_id, name, html_source, css_source, js_source) VALUES (?,?,?,?,?)')
        ->execute([$siteId, 'Podmínky užití', $templateHtml, $templateCss, '']);
    $templateId = (int)$pdo->lastInsertId();
}

$stmt = $pdo->prepare('SELECT id FROM pages WHERE site_id = ? AND slug IN (?, ?) ORDER BY FIELD(slug, ?, ?) LIMIT 1');
$stmt->execute([$siteId, 'podminky-uziti', 'ty-krasa-no', 'podminky-uziti', 'ty-krasa-no']);
$page = $stmt->fetch();

if ($page) {
    $pageId = (int)$page['id'];
    $pdo->prepare('UPDATE pages SET template_id = ?, title = ?, slug = ?, url_path = ?, status = ?, published_at = NOW() WHERE id = ?')
        ->execute([$templateId, 'Podmínky užití', 'podminky-uziti', 'podminky-uziti', 'published', $pageId]);
} else {
    $pdo->prepare('INSERT INTO pages (site_id, template_id, title, slug, url_path, language, status, published_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([$siteId, $templateId, 'Podmínky užití', 'podminky-uziti', 'podminky-uziti', 'cs', 'published']);
    $pageId = (int)$pdo->lastInsertId();
}

$blocks = [
    ['hero_h1', 'text', 'Hero - nadpis', 'Podmínky užití', 1],
    ['hero_sub', 'textarea', 'Hero - intro', 'Pravidla provozu webu, pouzivani obsahu a informace o cookies.', 2],
    ['rules_title', 'text', 'Pravidla - nadpis', 'Co plati pri pouzivani tohoto webu', 3],
    ['rules_intro', 'textarea', 'Pravidla - intro', 'Pouzivanim tohoto webu potvrzujete, ze jste si precetli tato pravidla. Pokud s nimi nesouhlasite, web nepouzivejte.', 4],
    ['rule_1', 'html', 'Pravidlo 1', '<h3>Pouzivani webu</h3><p>Web pouzivejte pouze zakonnym a bezpecnym zpusobem. Nesmite se pokouset o naruseni provozu, zneuziti formularu ani o zasahy do zabezpeceni.</p>', 5],
    ['rule_2', 'html', 'Pravidlo 2', '<h3>Cookies a souhlasy</h3><p>Nezbytne cookies pouzivame pro fungovani webu. Analyticke a marketingove cookies se aktivuji az po vasem jasnem souhlasu v cookie liste. Sve volby muzete kdykoli zmenit pres nastaveni cookies.</p>', 6],
    ['rule_3', 'html', 'Pravidlo 3', '<h3>Obsah a autorska prava</h3><p>Fotografie, videa, texty a znacka webu zustavaji chranenym obsahem provozovatele nebo jeho klientu. Bez souhlasu je nepouzivejte dale, nekopirujte a nevydavejte za vlastni.</p>', 7],
    ['rule_4', 'html', 'Pravidlo 4', '<h3>Zmeny a kontakt</h3><p>Obsah webu i tato pravidla se mohou prubezne menit. Pokud mate dotaz k provozu webu, soukromi nebo souhlasu s cookies, napiste nam pres kontaktni stranku.</p>', 8],
    ['cta_wrap', 'html', 'CTA', '<a href="/kontakt" class="btn-secondary">Kontaktovat nas</a>', 9],
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

$result = (new PublishService())->publishPage($pageId, $userId);
if (!$result['success']) {
    throw new RuntimeException('Publishing failed: ' . ($result['message'] ?? 'unknown error'));
}

echo "Terms page seeded at /podminky-uziti\n";
