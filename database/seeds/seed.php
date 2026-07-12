<?php

/**
 * Generický seed — SEO Editor CMS
 *
 * Vytvoří:
 *   - admin uživatele
 *   - výchozí web se sdíleným layoutem a navigací
 *   - 7 šablon (homepage, sluzby, cenik, faq, kontakt, blog-listing, portfolio-listing)
 *   - 7 stránek (/, /sluzby, /cenik, /faq, /kontakt, /blog, /nase-prace)
 *   - placeholder obsahové bloky na každé stránce
 *
 * Spuštění:
 *   - přes admin: /admin/setup → „Spustit seed"
 *   - přes CLI:   php database/seeds/seed.php
 */

declare(strict_types=1);

if (php_sapi_name() === 'cli') {
    defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__, 2));
    require BASE_PATH . '/vendor/autoload.php';
    \App\Core\Env::load(BASE_PATH . '/.env');
}
defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__, 2));

use App\Core\Database;
use App\Services\MediaIngestionService;
use App\Services\PublishService;

$pdo = Database::getInstance();

// ─── Pomocné funkce ───────────────────────────────────────────────────────────

if (!function_exists('seedUpsertUser')) {
    function seedUpsertUser(PDO $pdo, string $email, string $password, string $role = 'admin'): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('UPDATE users SET password_hash=?, role=? WHERE id=?')
                ->execute([$hash, $role, $row['id']]);
            return (int) $row['id'];
        }
        $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (?,?,?)')
            ->execute([$email, $hash, $role]);
        return (int) $pdo->lastInsertId();
    }
}

if (!function_exists('seedUpsertSite')) {
    function seedUpsertSite(PDO $pdo, string $name, string $domain): int
    {
        $stmt = $pdo->prepare('SELECT id FROM sites WHERE domain = ? LIMIT 1');
        $stmt->execute([$domain]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('UPDATE sites SET name=?, cookie_consent_enabled=0 WHERE id=?')
                ->execute([$name, $row['id']]);
            return (int) $row['id'];
        }
        $pdo->prepare(
            'INSERT INTO sites (name, domain, active_theme_key, cookie_consent_enabled) VALUES (?, ?, ?, ?)'
        )->execute([$name, $domain, null, 0]);
        return (int) $pdo->lastInsertId();
    }
}

if (!function_exists('seedUpdateSiteLayout')) {
    function seedUpdateSiteLayout(PDO $pdo, int $siteId, string $html, string $css, string $js, string $navHome, string $navSub): void
    {
        $pdo->prepare(
            'UPDATE sites SET layout_html=?, layout_css=?, layout_js=?, layout_nav_home=?, layout_nav_sub=? WHERE id=?'
        )->execute([$html, $css, $js, $navHome, $navSub, $siteId]);
    }
}

if (!function_exists('seedUpsertTemplate')) {
    function seedUpsertTemplate(PDO $pdo, int $siteId, string $name, string $html, string $css = '', string $js = ''): int
    {
        $stmt = $pdo->prepare('SELECT id FROM templates WHERE site_id=? AND name=? LIMIT 1');
        $stmt->execute([$siteId, $name]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('UPDATE templates SET html_source=?, css_source=?, js_source=? WHERE id=?')
                ->execute([$html, $css, $js, $row['id']]);
            return (int) $row['id'];
        }
        $pdo->prepare('INSERT INTO templates (site_id, name, html_source, css_source, js_source) VALUES (?,?,?,?,?)')
            ->execute([$siteId, $name, $html, $css, $js]);
        return (int) $pdo->lastInsertId();
    }
}

if (!function_exists('seedUpsertPage')) {
    function seedUpsertPage(PDO $pdo, int $siteId, int $templateId, string $title, string $slug, string $urlPath): int
    {
        $stmt = $pdo->prepare('SELECT id FROM pages WHERE site_id=? AND slug=? LIMIT 1');
        $stmt->execute([$siteId, $slug]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('UPDATE pages SET template_id=?, title=?, url_path=?, status=?, published_at=NOW() WHERE id=?')
                ->execute([$templateId, $title, $urlPath, 'published', $row['id']]);
            return (int) $row['id'];
        }
        $pdo->prepare(
            'INSERT INTO pages (site_id, template_id, title, slug, url_path, status, published_at) VALUES (?,?,?,?,?,?,NOW())'
        )->execute([$siteId, $templateId, $title, $slug, $urlPath, 'published']);
        return (int) $pdo->lastInsertId();
    }
}

if (!function_exists('seedBlock')) {
    function seedBlock(PDO $pdo, int $pageId, string $key, string $type, string $label, string $content, int $order = 0): void
    {
        $stmt = $pdo->prepare('SELECT id FROM content_blocks WHERE page_id=? AND block_key=? LIMIT 1');
        $stmt->execute([$pageId, $key]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('UPDATE content_blocks SET block_type=?, label=?, content=?, sort_order=? WHERE id=?')
                ->execute([$type, $label, $content, $order, $row['id']]);
        } else {
            $pdo->prepare(
                'INSERT INTO content_blocks (page_id, block_key, block_type, label, content, sort_order) VALUES (?,?,?,?,?,?)'
            )->execute([$pageId, $key, $type, $label, $content, $order]);
        }
    }
}

if (!function_exists('seedAltFromFilename')) {
    function seedAltFromFilename(string $filename): string
    {
        $base = (string) pathinfo($filename, PATHINFO_FILENAME);
        return ucfirst(trim(str_replace('-', ' ', $base)));
    }
}

if (!function_exists('seedMediaAsset')) {
    function seedMediaAsset(PDO $pdo, int $siteId, string $filename): array
    {
        $stmt = $pdo->prepare('SELECT id, storage_path FROM media WHERE site_id = ? AND original_filename = ? LIMIT 1');
        $stmt->execute([$siteId, $filename]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'id' => (int) $row['id'],
                'url' => '/' . ltrim((string) $row['storage_path'], '/'),
            ];
        }

        $path = BASE_PATH . '/database/seeds/demo_assets/' . $filename;
        if (!is_file($path)) {
            return ['id' => 0, 'url' => ''];
        }

        $alt = seedAltFromFilename($filename);
        $result = (new MediaIngestionService())->ingestUploadedFile($siteId, $path, $filename, $alt, $alt, false);
        $storagePath = (string) ($result['storage_path'] ?? '');

        return [
            'id' => (int) ($result['media_id'] ?? 0),
            'url' => $storagePath !== '' ? '/' . ltrim($storagePath, '/') : '',
        ];
    }
}

// ─── Vyčistit stará data ──────────────────────────────────────────────────────

foreach ([
    'publish_history', 'seo_settings', 'content_blocks', 'media_variants',
    'blog_media', 'portfolio_media', 'blog_posts', 'media', 'portfolio_entries',
    'faq_items', 'reviews', 'contact_info', 'service_items',
    'pages', 'templates', 'redirects', 'sites', 'users',
] as $table) {
    $pdo->exec("DELETE FROM `{$table}`");
}
$pdo->exec('ALTER TABLE users AUTO_INCREMENT = 1');

// ─── Uživatel ─────────────────────────────────────────────────────────────────

$userId = seedUpsertUser($pdo, 'admin@example.com', 'admin123', 'admin');
echo "Uživatel: admin@example.com / admin123\n";

// ─── Web ──────────────────────────────────────────────────────────────────────

$appUrl = rtrim((string)(\App\Core\Env::get('APP_URL', 'http://localhost:8000') ?: 'http://localhost:8000'), '/');
$siteId = seedUpsertSite($pdo, 'RB Videos & Photos', $appUrl);

// ─── Sdílený layout ───────────────────────────────────────────────────────────

$layoutNavHome = <<<'NAV'
<nav class="site-nav" id="sitenav">
  <div class="container nav-inner">
    <a href="/" class="brand">{site_name}</a>
    <input type="checkbox" id="nav-toggle" class="nav-toggle-input">
    <label for="nav-toggle" class="nav-toggle-label" aria-label="Menu"><span></span></label>
    <div class="nav-links">
      <a href="#sluzby">Služby</a>
      <a href="#nase-prace">Naše práce</a>
      <a href="#blog">Blog</a>
      <a href="#cenik">Ceník</a>
      <a href="#faq">FAQ</a>
      <a href="#kontakt" class="nav-cta">Kontakt</a>
    </div>
  </div>
</nav>
NAV;

$layoutNavSub = <<<'NAV'
<nav class="site-nav" id="sitenav">
  <div class="container nav-inner">
    <a href="/" class="brand">{site_name}</a>
    <input type="checkbox" id="nav-toggle" class="nav-toggle-input">
    <label for="nav-toggle" class="nav-toggle-label" aria-label="Menu"><span></span></label>
    <div class="nav-links">
      <a href="/sluzby">Služby</a>
      <a href="/nase-prace">Naše práce</a>
      <a href="/blog">Blog</a>
      <a href="/cenik">Ceník</a>
      <a href="/faq">FAQ</a>
      <a href="/kontakt" class="nav-cta">Kontakt</a>
    </div>
  </div>
</nav>
NAV;

$layoutHtml = <<<'LAYOUT'
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{site_name}</title>
</head>
<body>
{nav}
<main id="page-content">
  {page_content}
</main>
<footer class="site-footer">
  <div class="container footer-inner">
    <p class="footer-copy">&copy; <span data-site-name></span>{site_name}</p>
  </div>
</footer>
</body>
</html>
LAYOUT;

$layoutCss = <<<'CSS'
/* ── Reset & základy ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;font-size:16px}
body{font-family:"Inter",system-ui,-apple-system,sans-serif;background:#111;color:#e8e8e8;line-height:1.65;-webkit-font-smoothing:antialiased}
img{max-width:100%;height:auto;display:block}
a{color:inherit;text-decoration:none}
/* ── Layout ── */
.container{max-width:1180px;margin:0 auto;padding:0 24px}
.section{padding:80px 0}
.section--alt{background:#18181c}
.section--dark{background:#0d0d10}
/* ── Navigace ── */
.site-nav{position:sticky;top:0;z-index:100;background:#111;border-bottom:1px solid rgba(255,255,255,.07);backdrop-filter:blur(10px)}
.nav-inner{display:flex;align-items:center;justify-content:space-between;min-height:72px;gap:20px}
.brand{font-weight:800;font-size:1.1rem;letter-spacing:.06em;text-transform:uppercase;color:#fff}
.nav-links{display:flex;align-items:center;gap:28px}
.nav-links a{font-size:.85rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:rgba(255,255,255,.72);transition:color .2s}
.nav-links a:hover{color:#fff}
.nav-cta{background:#c4963c;color:#fff!important;padding:10px 18px;border-radius:4px;opacity:1!important}
.nav-cta:hover{background:#d4a64c!important}
/* Hamburger */
.nav-toggle-input{display:none}
.nav-toggle-label{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:8px}
.nav-toggle-label span,.nav-toggle-label::before,.nav-toggle-label::after{content:'';display:block;width:24px;height:2px;background:#fff;transition:transform .25s}
@media(max-width:768px){
  .nav-toggle-label{display:flex}
  .nav-links{display:none;position:absolute;top:72px;left:0;right:0;flex-direction:column;align-items:flex-start;background:#111;border-bottom:1px solid rgba(255,255,255,.07);padding:16px 24px;gap:16px}
  .nav-toggle-input:checked ~ .nav-links{display:flex}
  .nav-cta{align-self:flex-start}
}
/* ── Tlačítka ── */
.btn-primary{display:inline-block;background:#c4963c;color:#fff;padding:14px 28px;border-radius:6px;font-weight:700;font-size:.95rem;transition:background .2s}
.btn-primary:hover{background:#d4a64c}
.btn-secondary{display:inline-block;border:1px solid rgba(196,150,60,.5);color:#c4963c;padding:12px 24px;border-radius:6px;font-weight:600;font-size:.9rem;transition:all .2s}
.btn-secondary:hover{background:rgba(196,150,60,.1);border-color:#c4963c}
/* ── Nadpisy sekcí ── */
.section-label{font-size:.75rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#c4963c;margin-bottom:12px}
.section-title{font-size:clamp(1.8rem,3.5vw,3rem);font-weight:800;color:#fff;margin-bottom:16px;line-height:1.15}
.section-intro{color:rgba(255,255,255,.65);font-size:1.05rem;max-width:640px;margin-bottom:40px;line-height:1.7}
/* ── Hero ── */
.page-hero{padding:80px 0 64px;background:#0d0d10;text-align:center}
.page-hero h1{font-size:clamp(2rem,5vw,3.8rem);font-weight:900;color:#fff;margin-bottom:16px;line-height:1.1}
.page-hero .hero-sub{color:rgba(255,255,255,.65);font-size:1.1rem;max-width:680px;margin:0 auto}
/* ── Patička ── */
.site-footer{padding:36px 0;background:#0a0a0d;border-top:1px solid rgba(255,255,255,.06)}
.footer-inner{display:flex;align-items:center;justify-content:center}
.footer-copy{color:rgba(255,255,255,.36);font-size:.88rem}
CSS;

$layoutJs = <<<'JS'
// Smooth scroll pro hash linky
document.querySelectorAll('a[href^="#"]').forEach(function(a){
  a.addEventListener('click',function(e){
    var id=this.getAttribute('href').slice(1);
    var el=document.getElementById(id);
    if(el){e.preventDefault();el.scrollIntoView({behavior:'smooth',block:'start'});}
  });
});
JS;

seedUpdateSiteLayout($pdo, $siteId, $layoutHtml, $layoutCss, $layoutJs, $layoutNavHome, $layoutNavSub);
echo "Layout webu nastaven.\n";

// ─── Šablony ──────────────────────────────────────────────────────────────────

// ── Homepage ──────────────────────────────────────────────────────────────────

$tplHomepageHtml = <<<'HTML'
<section class="hp-hero" id="uvod">
  <div class="container">
    <p data-block="hero_label" class="section-label">Fotograf a kameraman - Olomouc, Praha</p>
    <video data-block="hero_video" class="hero-video" autoplay muted loop playsinline preload="metadata"></video>
    <h1 data-block="hero_h1">Zachytíme váš příběh <span>navždy</span>.</h1>
    <p data-block="hero_sub" class="hero-sub">Profesionální svatební fotografie a videografie v Olomouci a po celé České republice. Každý okamžik si zaslouží být nezapomenutelný.</p>
    <div data-block="hero_cta" class="hero-cta-wrap"><a href="#kontakt" class="btn-primary">Kontaktujte nás</a></div>
  </div>
</section>

<section class="section section--alt" id="sluzby">
  <div class="container">
    <p class="section-label">Co nabízíme</p>
    <h2 data-block="sluzby_title" class="section-title">Naše služby</h2>
    <p data-block="sluzby_intro" class="section-intro">Každý příběh je jedinečný. Naše služby přizpůsobíme přesně vám.</p>
    <div data-block="sluzby_items" class="hp-services-grid">
      <!-- Doplňte karty služeb -->
    </div>
    <div data-block="sluzby_cta"><a href="/sluzby" class="btn-secondary">Všechny služby</a></div>
  </div>
</section>

<section class="section section--dark" id="nase-prace">
  <div class="container">
    <p class="section-label">Reference</p>
    <h2 data-block="portfolio_title" class="section-title">Naše práce</h2>
    <p data-block="portfolio_intro" class="section-intro">Ukázka projektů a realizací. Přidejte první práci v sekci Naše práce.</p>
    <div data-block="portfolio-tiles" class="hp-portfolio-placeholder">
      <p style="color:rgba(255,255,255,.4);text-align:center;padding:48px 0">Přidejte první práci v administraci — dlaždice se zobrazí automaticky.</p>
    </div>
    <div data-block="portfolio_cta"><a href="/nase-prace" class="btn-secondary">Všechny práce</a></div>
  </div>
</section>

<section class="section" id="blog">
  <div class="container">
    <p class="section-label">Tipy a inspirace</p>
    <h2 data-block="blog_title" class="section-title">Nejnovější články</h2>
    <p data-block="blog_intro" class="section-intro">Rady a novinky z naší praxe.</p>
    <div class="hp-blog-grid">
      <div data-block="blog_1" class="hp-blog-card"><!-- Článek 1: doplňte v adminu --></div>
      <div data-block="blog_2" class="hp-blog-card"><!-- Článek 2: doplňte v adminu --></div>
      <div data-block="blog_3" class="hp-blog-card"><!-- Článek 3: doplňte v adminu --></div>
    </div>
    <div data-block="blog_cta"><a href="/blog" class="btn-secondary">Všechny články</a></div>
  </div>
</section>

<section class="section section--alt" id="cenik">
  <div class="container">
    <p class="section-label">Ceny</p>
    <h2 data-block="cenik_title" class="section-title">Ceník</h2>
    <p data-block="cenik_intro" class="section-intro">Orientační přehled cen. Každá zakázka je individuální.</p>
    <div data-block="cenik_body" class="hp-pricing"><!-- Doplňte přehled cen --></div>
    <div data-block="cenik_cta"><a href="/cenik" class="btn-secondary">Celý ceník</a></div>
  </div>
</section>

<section class="section section--dark" id="faq">
  <div class="container">
    <p class="section-label">Otázky</p>
    <h2 data-block="faq_title" class="section-title">Časté dotazy</h2>
    <div class="hp-faq">
      <div data-block="faq_1" class="hp-faq-item"><!-- FAQ 1 --></div>
      <div data-block="faq_2" class="hp-faq-item"><!-- FAQ 2 --></div>
      <div data-block="faq_3" class="hp-faq-item"><!-- FAQ 3 --></div>
    </div>
    <div data-block="faq_cta"><a href="/faq" class="btn-secondary">Všechny otázky</a></div>
  </div>
</section>

<section class="section section--dark hp-reviews-demo" id="hodnoceni">
  <div class="container">
    <p class="section-label">Co o nas rikaji</p>
    <h2 data-block="reviews_title" class="section-title">Hodnoceni klientu</h2>
    <p data-block="reviews_intro" class="section-intro">Vybrane reference klientu, kterym jsme dodali fotografie nebo video.</p>
    <div data-block="reviews_demo"><!-- Recenze --></div>
    <div data-block="reviews_cta"><a href="/hodnoceni" class="btn-secondary">Vsechny recenze</a></div>
  </div>
</section>

<section class="section" id="kontakt">
  <div class="container">
    <p class="section-label">Kontakt</p>
    <h2 data-block="kontakt_title" class="section-title">Ozvěte se nám</h2>
    <p data-block="kontakt_sub" class="section-intro">Rádi odpovíme na vaše otázky.</p>
    <div data-block="kontakt_info"><!-- Doplňte kontaktní informace --></div>
  </div>
</section>
HTML;

$tplHomepageCss = <<<'CSS'
.hp-hero{padding:120px 0 100px;background:#0d0d10;text-align:center}
.hp-hero h1{font-size:clamp(2.8rem,6vw,5rem);font-weight:900;color:#fff;margin-bottom:20px;line-height:1.05}
.hp-hero .hero-sub{color:rgba(255,255,255,.65);font-size:1.15rem;max-width:680px;margin:0 auto 36px}
.hero-cta-wrap{display:flex;gap:16px;justify-content:center;flex-wrap:wrap}
.hp-services-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;margin-bottom:36px}
.hp-portfolio-placeholder{margin-bottom:32px}
.hp-blog-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px;margin-bottom:36px}
.hp-blog-card{background:#1b1b1f;border-radius:12px;padding:28px;color:rgba(255,255,255,.8)}
.hp-pricing{margin-bottom:32px}
.hp-faq{display:flex;flex-direction:column;gap:16px;margin-bottom:32px}
.hp-faq-item{padding:20px 24px;background:#18181c;border-radius:10px;border-left:3px solid #c4963c}
@media(max-width:600px){.hp-hero{padding:80px 0 64px}}
CSS;

$tplHomepageJs = <<<'JS'
// Aktivní odkaz v navigaci podle scrollu
(function(){
  var sections=document.querySelectorAll('section[id]');
  var navLinks=document.querySelectorAll('.nav-links a[href^="#"]');
  if(!sections.length||!navLinks.length) return;
  var isHome = window.location.pathname.replace(/\/+$/, '') === '' || window.location.pathname === '/';
  if(!isHome) return;
  var io=new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(e.isIntersecting){
        navLinks.forEach(function(a){a.classList.remove('nav-active')});
        var active=document.querySelector('.nav-links a[href="#'+e.target.id+'"]');
        if(active) active.classList.add('nav-active');
      }
    });
  },{rootMargin:'-40% 0px -40% 0px'});
  sections.forEach(function(s){io.observe(s)});
})();
JS;

$tplHomepage = seedUpsertTemplate($pdo, $siteId, 'Homepage', $tplHomepageHtml, $tplHomepageCss, $tplHomepageJs);

// ── Služby ────────────────────────────────────────────────────────────────────

$tplSluzbyHtml = <<<'HTML'
<section class="page-hero">
  <div class="container">
    <h1 data-block="hero_h1">Naše služby</h1>
    <p data-block="hero_sub" class="hero-sub">Stručný popis toho, co nabízíme.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div data-block="sluzby_grid" class="svc-grid"><!-- Doplňte karty služeb --></div>
    <div data-block="cta_wrap" class="svc-cta"><a href="/kontakt" class="btn-primary">Kontaktujte nás</a></div>
  </div>
</section>
HTML;

$tplSluzbyCss = <<<'CSS'
.svc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px;margin-bottom:40px}
.svc-cta{text-align:center;margin-top:16px}
CSS;

$tplSluzby = seedUpsertTemplate($pdo, $siteId, 'Služby', $tplSluzbyHtml, $tplSluzbyCss, '');

// ── Ceník ─────────────────────────────────────────────────────────────────────

$tplCenikHtml = <<<'HTML'
<section class="page-hero">
  <div class="container">
    <h1 data-block="hero_h1">Ceník</h1>
    <p data-block="hero_sub" class="hero-sub">Transparentní přehled cen bez překvapení.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="cenik-grid">

      <div class="cenik-card">
        <div class="cenik-card-body">
          <h3 data-block="cenik_card_1_title" class="cenik-card-title">Základní</h3>
          <div data-block="cenik_card_1_desc" class="cenik-card-desc">Popis tohoto balíčku.</div>
        </div>
        <div data-block="cenik_card_1_price" class="cenik-card-price">od 5 000 Kč</div>
      </div>

      <div class="cenik-card cenik-card--featured">
        <div class="cenik-card-body">
          <h3 data-block="cenik_card_2_title" class="cenik-card-title">Standardní</h3>
          <div data-block="cenik_card_2_desc" class="cenik-card-desc">Popis tohoto balíčku.</div>
        </div>
        <div data-block="cenik_card_2_price" class="cenik-card-price">od 10 000 Kč</div>
      </div>

      <div class="cenik-card">
        <div class="cenik-card-body">
          <h3 data-block="cenik_card_3_title" class="cenik-card-title">Prémiový</h3>
          <div data-block="cenik_card_3_desc" class="cenik-card-desc">Popis tohoto balíčku.</div>
        </div>
        <div data-block="cenik_card_3_price" class="cenik-card-price">od 20 000 Kč</div>
      </div>

    </div>
    <div data-block="cta_wrap" class="section-cta"><a href="/kontakt" class="btn-primary">Nezávazná poptávka</a></div>
  </div>
</section>
HTML;

$tplCenikCss = <<<'CSS'
.cenik-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;margin-bottom:48px}
.cenik-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:32px;display:flex;flex-direction:column;gap:24px}
.cenik-card--featured{border-color:rgba(255,255,255,.3);background:rgba(255,255,255,.09)}
.cenik-card-body{flex:1}
.cenik-card-title{font-size:1.25rem;font-weight:700;color:#fff;margin-bottom:12px}
.cenik-card-desc{color:rgba(255,255,255,.65);line-height:1.7;font-size:.95rem;white-space:pre-line}
.cenik-card-price{font-size:1.3rem;font-weight:800;color:#fff;padding-top:20px;border-top:1px solid rgba(255,255,255,.1)}
.section-cta{text-align:center;margin-top:16px}
@media(max-width:720px){.cenik-grid{grid-template-columns:1fr;gap:10px;margin-bottom:22px}.cenik-card{padding:15px 16px;gap:10px}.cenik-card-title{font-size:1.08rem;margin-bottom:6px}.cenik-card-desc{font-size:.78rem;line-height:1.45}.cenik-card-price{font-size:1.05rem;padding-top:10px}.section-cta{margin-top:10px}}
CSS;

$tplCenik = seedUpsertTemplate($pdo, $siteId, 'Ceník', $tplCenikHtml, $tplCenikCss, '');

// ── FAQ ───────────────────────────────────────────────────────────────────────

$tplFaqHtml = <<<'HTML'
<section class="page-hero">
  <div class="container">
    <h1 data-block="hero_h1">Časté otázky</h1>
    <p data-block="hero_sub" class="hero-sub">Odpovědi na nejčastější dotazy.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="faq-list">
      <div data-block="faq_1" class="faq-item"><!-- Otázka 1 --></div>
      <div data-block="faq_2" class="faq-item"><!-- Otázka 2 --></div>
      <div data-block="faq_3" class="faq-item"><!-- Otázka 3 --></div>
      <div data-block="faq_4" class="faq-item"><!-- Otázka 4 --></div>
      <div data-block="faq_5" class="faq-item"><!-- Otázka 5 --></div>
    </div>
    <div data-block="cta_wrap" style="margin-top:40px"><a href="/kontakt" class="btn-primary">Máte další otázku?</a></div>
  </div>
</section>
HTML;

$tplFaqCss = <<<'CSS'
.faq-list{display:flex;flex-direction:column;gap:16px;max-width:820px;margin:0 auto}
.faq-item{padding:24px 28px;background:#18181c;border-radius:12px;border-left:3px solid #c4963c}
.faq-item:empty{display:none}
@media(max-width:720px){.faq-list{gap:8px}.faq-item{padding:12px 14px;border-radius:8px;border-left-width:2px}.faq-q{font-size:.94rem;line-height:1.25;margin-bottom:6px}.faq-a{font-size:.78rem;line-height:1.48}}
CSS;

$tplFaq = seedUpsertTemplate($pdo, $siteId, 'FAQ', $tplFaqHtml, $tplFaqCss, '');

// ── Kontakt ───────────────────────────────────────────────────────────────────

$tplKontaktHtml = <<<'HTML'
<section class="page-hero">
  <div class="container">
    <h1 data-block="hero_h1">Kontakt</h1>
    <p data-block="hero_sub" class="hero-sub">Ozvěte se nám. Odpovídáme rychle.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="kontakt-grid">
      <div data-block="kontakt_info" class="kontakt-info"><!-- Kontaktní informace --></div>
      <div data-block="kontakt_card" class="kontakt-card"><!-- CTA karta / formulář --></div>
    </div>
  </div>
</section>
HTML;

$tplKontaktCss = <<<'CSS'
.kontakt-grid{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start}
.kontakt-info{color:rgba(255,255,255,.82);line-height:2}
.kontakt-card{background:#18181c;border-radius:16px;padding:36px}
@media(max-width:680px){.kontakt-grid{grid-template-columns:1fr;gap:32px}}
CSS;

$tplKontakt = seedUpsertTemplate($pdo, $siteId, 'Kontakt', $tplKontaktHtml, $tplKontaktCss, '');

// ── Blog listing ──────────────────────────────────────────────────────────────
// Dynamický seznam článků renderuje public/index.php (tryServeBlogListingPage).
// Tato šablona slouží jako CMS stránka /blog — hero a intro text.

$tplBlogHtml = <<<'HTML'
<section class="page-hero">
  <div class="container">
    <h1 data-block="hero_h1">Blog</h1>
    <p data-block="hero_sub" class="hero-sub">Tipy, inspirace a novinky z naší praxe.</p>
  </div>
</section>
HTML;

$tplBlogListing = seedUpsertTemplate($pdo, $siteId, 'Blog — přehled', $tplBlogHtml, '', '');

// ── Portfolio listing (Naše práce) ────────────────────────────────────────────
// Dynamický grid renderuje public/index.php (tryServePortfolioPage).
// Tato šablona slouží jako CMS stránka /nase-prace — hero a intro text.

$tplPortfolioHtml = <<<'HTML'
<section class="page-hero">
  <div class="container">
    <h1 data-block="hero_h1">Naše práce</h1>
    <p data-block="hero_sub" class="hero-sub">Ukázka projektů a realizací.</p>
  </div>
</section>
HTML;

$tplPortfolioListing = seedUpsertTemplate($pdo, $siteId, 'Naše práce — přehled', $tplPortfolioHtml, '', '');

$tplRulesHtml = <<<'HTML'
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

$tplRulesCss = <<<'CSS'
.legal-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px}
.legal-card{padding:24px;border:1px solid rgba(198,148,61,.3);background:rgba(255,255,255,.025)}
.legal-card h3{margin:0 0 10px;color:#fff;font-size:1.1rem}
.legal-card p,.legal-card li{color:rgba(255,255,255,.72);line-height:1.65}
.legal-card ul{margin:10px 0 0 18px;padding:0}
CSS;

$tplRules = seedUpsertTemplate($pdo, $siteId, 'Podmínky užití', $tplRulesHtml, $tplRulesCss, '');

echo "Šablony vytvořeny.\n";

// ─── Stránky ──────────────────────────────────────────────────────────────────

$pidHome      = seedUpsertPage($pdo, $siteId, $tplHomepage,        'Domů',         'homepage',   '');
$pidSluzby    = seedUpsertPage($pdo, $siteId, $tplSluzby,          'Služby',        'sluzby',     'sluzby');
$pidCenik     = seedUpsertPage($pdo, $siteId, $tplCenik,           'Ceník',         'cenik',      'cenik');
$pidFaq       = seedUpsertPage($pdo, $siteId, $tplFaq,             'FAQ',           'faq',        'faq');
$pidKontakt   = seedUpsertPage($pdo, $siteId, $tplKontakt,         'Kontakt',       'kontakt',    'kontakt');
$pidBlog      = seedUpsertPage($pdo, $siteId, $tplBlogListing,     'Blog',          'blog',       'blog');
$pidPortfolio = seedUpsertPage($pdo, $siteId, $tplPortfolioListing,'Naše práce',    'nase-prace', 'nase-prace');
$pidRules     = seedUpsertPage($pdo, $siteId, $tplRules,           'Podmínky užití',   'podminky-uziti','podminky-uziti');

echo "Stránky vytvořeny.\n";

$seedMedia = [];
foreach ([
    'sluzby.png',
    'cenik.png',
    'faq.png',
    'kontakt.png',
    'nase-prace.png',
    'blog.png',
    'podminky-uziti.png',
    'weare2.png',
] as $seedAsset) {
    $seedMedia[$seedAsset] = seedMediaAsset($pdo, $siteId, $seedAsset);
}
echo "Demo obrazky nahrany.\n";

// ─── Obsahové bloky — Homepage ────────────────────────────────────────────────

seedBlock($pdo, $pidHome, 'hero_label',     'text',     'Hero — štítek',       'Fotograf a kameraman - Olomouc, Praha',                            1);
seedBlock($pdo, $pidHome, 'hero_video',     'video',    'Hero — video (URL)',  '', 2);
seedBlock($pdo, $pidHome, 'hero_h1',        'text',     'Hero — nadpis',       'Zachytíme váš příběh <span>navždy</span>.',                        3);
seedBlock($pdo, $pidHome, 'hero_sub',       'textarea', 'Hero — podnadpis',    'Profesionální svatební fotografie a videografie v Olomouci a po celé České republice. Každý okamžik si zaslouží být nezapomenutelný.', 4);
seedBlock($pdo, $pidHome, 'hero_cta',       'html',     'Hero — CTA',          '<a href="#kontakt" class="btn-primary">Kontaktujte nás</a>', 5);

seedBlock($pdo, $pidHome, 'sluzby_title',   'text',     'Služby — nadpis',     'Naše služby',                                                     10);
seedBlock($pdo, $pidHome, 'sluzby_intro',   'textarea', 'Služby — intro',      'Každý příběh je jedinečný. Naše služby přizpůsobíme přesně vám.', 11);
seedBlock($pdo, $pidHome, 'sluzby_items',   'html',     'Služby — karty',      '', 12);
seedBlock($pdo, $pidHome, 'sluzby_cta',     'html',     'Služby — tlačítko',   '<a href="/sluzby" class="btn-secondary">V&#353;echny slu&#382;by</a>', 13);

seedBlock($pdo, $pidHome, 'portfolio_title','text',     'Portfolio — nadpis',  'Naše práce',                                                      20);
seedBlock($pdo, $pidHome, 'portfolio_intro','textarea', 'Portfolio — intro',   'Ukázka projektů a realizací.',                                    21);
seedBlock($pdo, $pidHome, 'portfolio-tiles','html',     'Portfolio — dlaždice','', 22);
seedBlock($pdo, $pidHome, 'portfolio_cta',  'html',     'Portfolio — tlačítko','<a href="/nase-prace" class="btn-secondary">V&#353;echny pr&#225;ce</a>', 23);

seedBlock($pdo, $pidHome, 'blog_title',     'text',     'Blog — nadpis',       'Nejnovější články',                                               30);
seedBlock($pdo, $pidHome, 'blog_intro',     'textarea', 'Blog — intro',        'Rady a novinky z naší praxe.',                                    31);
seedBlock($pdo, $pidHome, 'blog_1',         'html',     'Článek 1',            '', 32);
seedBlock($pdo, $pidHome, 'blog_2',         'html',     'Článek 2',            '', 33);
seedBlock($pdo, $pidHome, 'blog_3',         'html',     'Článek 3',            '', 34);
seedBlock($pdo, $pidHome, 'blog_cta',       'html',     'Blog — tlačítko',     '<a href="/blog" class="btn-secondary">V&#353;echny &#269;l&#225;nky</a>', 35);

seedBlock($pdo, $pidHome, 'cenik_title',    'text',     'Ceník — nadpis',      'Ceník',                                                           40);
seedBlock($pdo, $pidHome, 'cenik_intro',    'textarea', 'Ceník — intro',       'Orientační přehled cen. Každá zakázka je individuální.',           41);
seedBlock($pdo, $pidHome, 'cenik_body',     'html',     'Ceník — obsah',       '', 42);
seedBlock($pdo, $pidHome, 'cenik_cta',      'html',     'Ceník — tlačítko',    '<a href="/cenik" class="btn-secondary">Cel&#253; cen&#237;k</a>', 43);

seedBlock($pdo, $pidHome, 'faq_title',      'text',     'FAQ — nadpis',        'Časté dotazy',                                                    50);
seedBlock($pdo, $pidHome, 'faq_1',          'html',     'FAQ 1',               '', 51);
seedBlock($pdo, $pidHome, 'faq_2',          'html',     'FAQ 2',               '', 52);
seedBlock($pdo, $pidHome, 'faq_3',          'html',     'FAQ 3',               '', 53);
seedBlock($pdo, $pidHome, 'faq_cta',        'html',     'FAQ — tlačítko',      '<a href="/faq" class="btn-secondary">V&#353;echny ot&#225;zky</a>',54);

seedBlock($pdo, $pidHome, 'kontakt_title',  'text',     'Kontakt — nadpis',    'Ozvěte se nám',                                                   60);
seedBlock($pdo, $pidHome, 'kontakt_sub',    'textarea', 'Kontakt — podnadpis', 'Rádi odpovíme na vaše otázky.',                                   61);
seedBlock($pdo, $pidHome, 'kontakt_info',   'html',     'Kontakt — info',      '', 62);

// ─── Obsahové bloky — Služby ──────────────────────────────────────────────────

seedBlock($pdo, $pidSluzby, 'hero_h1',   'text',     'Hero — nadpis',  'Naše služby',                                             1);
seedBlock($pdo, $pidSluzby, 'hero_sub',  'textarea', 'Hero — intro',   'Od svateb přes portréty až po firemní vizuál. Každou zakázku stavíme na emocích, detailu a profesionálním výsledku.', 2);
seedBlock($pdo, $pidSluzby, 'sluzby_grid','html',    'Služby — karty', '<p style="color:rgba(255,255,255,.45)">Doplňte karty služeb.</p>', 3);
seedBlock($pdo, $pidSluzby, 'cta_wrap',  'html',     'CTA',            '<a href="/kontakt" class="btn-primary">Kontaktujte n&#225;s</a>', 4);

// ─── Obsahové bloky — Ceník ───────────────────────────────────────────────────

seedBlock($pdo, $pidCenik, 'hero_h1',           'text',     'Hero — nadpis',             'Ceník',                                      1);
seedBlock($pdo, $pidCenik, 'hero_sub',          'textarea', 'Hero — intro',              'Transparentní přehled cen bez překvapení.',   2);
seedBlock($pdo, $pidCenik, 'cenik_card_1_title','text',     'Karta 1 — nadpis',          'Základní',                                   3);
seedBlock($pdo, $pidCenik, 'cenik_card_1_desc', 'textarea', 'Karta 1 — popis',           'Ideální pro menší projekty a začínající klienty.',  4);
seedBlock($pdo, $pidCenik, 'cenik_card_1_price','text',     'Karta 1 — cena / patička',  'od 5 000 Kč',                                5);
seedBlock($pdo, $pidCenik, 'cenik_card_2_title','text',     'Karta 2 — nadpis',          'Standardní',                                 6);
seedBlock($pdo, $pidCenik, 'cenik_card_2_desc', 'textarea', 'Karta 2 — popis',           'Nejoblíbenější volba pro většinu klientů.',  7);
seedBlock($pdo, $pidCenik, 'cenik_card_2_price','text',     'Karta 2 — cena / patička',  'od 10 000 Kč',                               8);
seedBlock($pdo, $pidCenik, 'cenik_card_3_title','text',     'Karta 3 — nadpis',          'Prémiový',                                   9);
seedBlock($pdo, $pidCenik, 'cenik_card_3_desc', 'textarea', 'Karta 3 — popis',           'Komplexní řešení na míru bez kompromisů.',  10);
seedBlock($pdo, $pidCenik, 'cenik_card_3_price','text',     'Karta 3 — cena / patička',  'od 20 000 Kč',                              11);
seedBlock($pdo, $pidCenik, 'cta_wrap',          'html',     'CTA',                       '<a href="/kontakt" class="btn-primary">Nez&#225;vazn&#225; popt&#225;vka</a>', 12);

// ─── Obsahové bloky — FAQ ─────────────────────────────────────────────────────

seedBlock($pdo, $pidFaq, 'hero_h1',  'text',     'Hero — nadpis', 'Časté otázky',                                                           1);
seedBlock($pdo, $pidFaq, 'hero_sub', 'textarea', 'Hero — intro',  'Odpovědi na nejčastější dotazy.',                                        2);
seedBlock($pdo, $pidFaq, 'faq_1',   'html',     'Otázka 1',      '<p style="color:rgba(255,255,255,.45)">Doplňte první otázku a odpověď.</p>', 3);
seedBlock($pdo, $pidFaq, 'faq_2',   'html',     'Otázka 2',      '',                                                                        4);
seedBlock($pdo, $pidFaq, 'faq_3',   'html',     'Otázka 3',      '',                                                                        5);
seedBlock($pdo, $pidFaq, 'faq_4',   'html',     'Otázka 4',      '',                                                                        6);
seedBlock($pdo, $pidFaq, 'faq_5',   'html',     'Otázka 5',      '',                                                                        7);
seedBlock($pdo, $pidFaq, 'cta_wrap','html',     'CTA',           '<a href="/kontakt" class="btn-primary">M&#225;te dal&#353;&#237; ot&#225;zku?</a>', 8);

// ─── Obsahové bloky — Kontakt ─────────────────────────────────────────────────

seedBlock($pdo, $pidKontakt, 'hero_h1',      'text',     'Hero — nadpis',   'Kontakt',                                                           1);
seedBlock($pdo, $pidKontakt, 'hero_sub',     'textarea', 'Hero — intro',    'Ozvěte se nám. Odpovídáme rychle.',                                 2);
seedBlock($pdo, $pidKontakt, 'kontakt_info', 'html',     'Kontakt — info',  '<p style="color:rgba(255,255,255,.45)">Doplňte kontaktní informace.</p>', 3);
seedBlock($pdo, $pidKontakt, 'kontakt_card', 'html',     'Kontakt — karta', '',                                                                  4);

// ─── Obsahové bloky — Blog ────────────────────────────────────────────────────

seedBlock($pdo, $pidBlog, 'hero_h1',  'text',     'Hero — nadpis', 'Blog',                                      1);
seedBlock($pdo, $pidBlog, 'hero_sub', 'textarea', 'Hero — intro',  'Tipy, inspirace a novinky z naší praxe.',   2);

// ─── Obsahové bloky — Naše práce ─────────────────────────────────────────────

seedBlock($pdo, $pidPortfolio, 'hero_h1',  'text',     'Hero — nadpis', 'Naše práce',                           1);
seedBlock($pdo, $pidPortfolio, 'hero_sub', 'textarea', 'Hero — intro',  'Ukázka projektů a realizací.',          2);

seedBlock($pdo, $pidRules, 'hero_h1', 'text', 'Hero - nadpis', 'Podmínky užití', 1);
seedBlock($pdo, $pidRules, 'hero_sub', 'textarea', 'Hero - intro', 'Pravidla provozu webu, pouzivani obsahu a informace o cookies.', 2);
seedBlock($pdo, $pidRules, 'rules_title', 'text', 'Pravidla - nadpis', 'Co plati pri pouzivani tohoto webu', 3);
seedBlock($pdo, $pidRules, 'rules_intro', 'textarea', 'Pravidla - intro', 'Pouzivanim tohoto webu potvrzujete, ze jste si precetli tato pravidla. Pokud s nimi nesouhlasite, web nepouzivejte.', 4);
seedBlock($pdo, $pidRules, 'rule_1', 'html', 'Pravidlo 1', '<h3>Pouzivani webu</h3><p>Web pouzivejte pouze zakonnym a bezpecnym zpusobem. Nesmite se pokouset o naruseni provozu, zneuziti formularu ani o zasahy do zabezpeceni.</p>', 5);
seedBlock($pdo, $pidRules, 'rule_2', 'html', 'Pravidlo 2', '<h3>Cookies a souhlasy</h3><p>Nezbytne cookies pouzivame pro fungovani webu. Analyticke a marketingove cookies se aktivuji az po vasem jasnem souhlasu v cookie liste. Sve volby muzete kdykoli zmenit pres nastaveni cookies.</p>', 6);
seedBlock($pdo, $pidRules, 'rule_3', 'html', 'Pravidlo 3', '<h3>Obsah a autorska prava</h3><p>Fotografie, videa, texty a znacka webu zustavaji chranenym obsahem provozovatele nebo jeho klientu. Bez souhlasu je nepouzivejte dale, nekopirujte a nevydavejte za vlastni.</p>', 7);
seedBlock($pdo, $pidRules, 'rule_4', 'html', 'Pravidlo 4', '<h3>Zmeny a kontakt</h3><p>Obsah webu i tato pravidla se mohou prubezne menit. Pokud mate dotaz k provozu webu, soukromi nebo souhlasu s cookies, napiste nam pres kontaktni stranku.</p>', 8);
seedBlock($pdo, $pidRules, 'cta_wrap', 'html', 'CTA', '<a href="/kontakt" class="btn-secondary">Kontaktovat nas</a>', 9);

foreach ([
    $pidSluzby => 'sluzby.png',
    $pidCenik => 'cenik.png',
    $pidFaq => 'faq.png',
    $pidKontakt => 'kontakt.png',
    $pidBlog => 'blog.png',
    $pidPortfolio => 'nase-prace.png',
    $pidRules => 'podminky-uziti.png',
] as $heroPageId => $heroFile) {
    $heroUrl = (string)($seedMedia[$heroFile]['url'] ?? '');
    if ($heroUrl !== '') {
        seedBlock($pdo, (int)$heroPageId, 'hero_bg', 'image', 'Hero foto', $heroUrl, 5);
    }
}

echo "Obsahové bloky vytvořeny.\n";

// ─── FAQ položky ──────────────────────────────────────────────────────────────

$pdo->exec("DELETE FROM service_items WHERE site_id = {$siteId}");
$serviceStmt = $pdo->prepare('
    INSERT INTO service_items (
        site_id, title, description, image_url, icon_url, button_text, button_url, sort_order, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
');
$weareImageUrl = (string)($seedMedia['weare2.png']['url'] ?? '');
$serviceStmt->execute([$siteId, 'Svatební foto & video', 'Zachytíme každý dotek, každý pohled i každou slzu štěstí. Přirozené reportážní fotografie a filmové video, které vám budou váš den připomínat celý život.', $weareImageUrl, '', 'Více informací', '/kontakt', 1]);
$serviceStmt->execute([$siteId, 'Rodinné & portrétní focení', 'Rodinné vzpomínky, těhotenské focení, křtiny i portréty. Přirozené, nenucené a krásné snímky, ke kterým se budete rádi vracet.', '', '', 'Více informací', '/kontakt', 2]);
$serviceStmt->execute([$siteId, 'Reklamní foto & video', 'Profesionální vizuální obsah pro firmy, produkty, kapely i značky. Obsah, který buduje důvěru, odlišuje a prodává.', '', '', 'Více informací', '/kontakt', 3]);
$serviceStmt->execute([$siteId, 'Akce & události', 'Konference, kulturní akce, plesy, koncerty i firemní večery. Každá událost si zaslouží profesionální dokumentaci bez kompromisů.', '', '', 'Více informací', '/kontakt', 4]);
echo "Služby vytvořeny.\n";

$serviceContent = new \App\Services\HomepageContentService();
$homeServicesHtml = $serviceContent->buildServicesHtml($siteId, 3);
$allServicesHtml = $serviceContent->buildServicesHtml($siteId, 0);

seedBlock($pdo, $pidHome, 'sluzby_items', 'html', 'Služby — karty', $homeServicesHtml, 12);
seedBlock($pdo, $pidSluzby, 'sluzby_grid', 'html', 'Služby — karty', $allServicesHtml, 3);
echo "Výpisy služeb synchronizovány.\n";

$pdo->exec("DELETE FROM faq_items WHERE site_id = {$siteId}");
$faqStmt = $pdo->prepare('INSERT INTO faq_items (site_id, question, answer, sort_order, is_active) VALUES (?, ?, ?, ?, 1)');
$faqStmt->execute([$siteId, 'Jak probíhá první konzultace?',      'První konzultace probíhá zdarma a trvá přibližně 30 minut. Probereme vaše potřeby a navrhneme optimální řešení.', 1]);
$faqStmt->execute([$siteId, 'Jaká je typická doba realizace?',    'Záleží na rozsahu projektu. Jednodušší zakázky zvládáme do 1–2 týdnů, komplexní projekty do 4–6 týdnů.', 2]);
$faqStmt->execute([$siteId, 'Jak mohu uhradit objednávku?',       'Přijímáme platby bankovním převodem nebo hotově. Po dohodě je možná i platba na splátky.', 3]);
$faqStmt->execute([$siteId, 'Poskytujete záruku na práci?',       'Ano, na všechny naše práce poskytujeme záruku. Podmínky záruky upřesníme při konzultaci.', 4]);
$faqStmt->execute([$siteId, 'Kde vás mohu kontaktovat?',          'Nejrychlejší odpověď dostanete přes kontaktní formulář nebo telefonicky. Odpovídáme obvykle do 24 hodin.', 5]);
echo "FAQ položky vytvořeny.\n";

// ─── Kontaktní informace ──────────────────────────────────────────────────────

$pdo->exec("DELETE FROM contact_info WHERE site_id = {$siteId}");
$pdo->prepare('
    INSERT INTO contact_info (
        site_id, phone_1, phone_2, email, address_1, address_2, form_email,
        social_1_type, social_1_url,
        social_2_type, social_2_url,
        social_3_type, social_3_url
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
')->execute([
    $siteId,
    '+420 792 483 583',
    '+420 604 225 032',
    'rb_vp@volny.cz',
    'Horní náměstí 583/1',
    '779 00 Olomouc',
    'rb_vp@volny.cz',
    'instagram',
    'https://www.instagram.com/rb_videos_photos/',
    'facebook',
    'https://www.facebook.com/rbvideosandphotos',
    'youtube',
    'https://www.youtube.com/channel/UC5miHVzPxIs2MjEsoPAzGCw',
]);
echo "Kontaktní informace vytvořeny.\n";

// ─── Publikovat ───────────────────────────────────────────────────────────────

$tplReviewsHtml = <<<'HTML'
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

$tplReviewsCss = <<<'CSS'
.reviews-page-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px}
.reviews-page-card{padding:22px;border:1px solid rgba(198,148,61,.35);background:rgba(255,255,255,.025)}
.reviews-page-stars{color:#e0b866;letter-spacing:3px;margin-bottom:10px}
.reviews-page-card p{color:rgba(255,255,255,.72);font-style:italic;line-height:1.65}
.reviews-page-card strong{display:block;margin-top:16px;color:#fff}
.reviews-page-card span{display:block;color:rgba(255,255,255,.5);font-size:12px}
CSS;

$tplReviews = seedUpsertTemplate($pdo, $siteId, 'Hodnoceni', $tplReviewsHtml, $tplReviewsCss, '');
$pidReviews = seedUpsertPage($pdo, $siteId, $tplReviews, 'Hodnoceni', 'hodnoceni', 'hodnoceni');

seedBlock($pdo, $pidHome, 'reviews_title', 'text', 'Recenze - nadpis', 'Co o nas rikaji klienti', 55);
seedBlock($pdo, $pidHome, 'reviews_intro', 'textarea', 'Recenze - intro', 'Vybrane reference klientu, kterym jsme dodali fotografie nebo video.', 56);
seedBlock($pdo, $pidHome, 'reviews_demo', 'html', 'Recenze - grid', '', 57);
seedBlock($pdo, $pidHome, 'reviews_cta', 'html', 'Recenze - tlacitko', '<a href="/hodnoceni" class="btn-secondary">Vsechny recenze</a>', 58);

seedBlock($pdo, $pidReviews, 'hero_h1', 'text', 'Hero - nadpis', 'Hodnoceni klientu', 1);
seedBlock($pdo, $pidReviews, 'hero_sub', 'textarea', 'Hero - intro', 'Slova paru, rodin a firem, se kterymi jsme tvorili fotografie a video.', 2);
seedBlock($pdo, $pidReviews, 'reviews_body', 'html', 'Recenze - grid', '', 3);

$pdo->exec("DELETE FROM reviews WHERE site_id = {$siteId}");
$reviewStmt = $pdo->prepare('INSERT INTO reviews (site_id, author_name, context_label, rating, review_text, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
$reviewStmt->execute([$siteId, 'Jan Jungling', 'Svatba M+H', 5, 'Kluci jsou naprosti profesionalove. Jsou pecivi a dokazou zachytit skutecne emoce.', 1, 1]);
$reviewStmt->execute([$siteId, 'Vaclav Konopka', 'Svatebni foceni', 5, 'Super domluva, mily a profesionalni pristup k foceni. Velka spokojenost.', 2, 1]);
$reviewStmt->execute([$siteId, 'Jana Dudkova', 'Rodinne foceni', 5, 'Moc doporucuji. Vse profesionalne nafocene, krasne barvy a skvela domluva.', 3, 1]);
$reviewStmt->execute([$siteId, 'Marketa a Tomas', 'Svatebni video', 5, 'Video vystihlo atmosferu dne do posledniho detailu. Vracime se k nemu porad dokola.', 4, 1]);
$reviewStmt->execute([$siteId, 'Lucie Novakova', 'Portretni foceni', 5, 'Foceni bylo uvolnene a bez stresu. Vysledek pusobi prirozene a profesionalne.', 5, 1]);
$reviewStmt->execute([$siteId, 'Petr Maly', 'Firemni akce', 5, 'Rychla domluva, jistota na miste a vystupy pouzitelne rovnou na web i socialni site.', 6, 1]);

$reviewStmt->execute([$siteId, 'Ellie Lachnitova', 'Foceni s Bannym', 5, 'Foceni s Bannym me moc bavilo. Super pristup, atmosfera i fotky. Rozhodne doporucuji! :)', 7, 1]);
$reviewStmt->execute([$siteId, 'Michael Latal', 'Foceni', 5, 'Velmi super pristup i fotky.', 8, 1]);
$reviewStmt->execute([$siteId, 'Roman Vrana', 'Svatebni foceni', 5, 'Rychlost, super provedeni, nadherne fotky, ktere rychle dodal upravene. 11/10, jeste jednou moc dekujeme <3', 9, 1]);
$reviewStmt->execute([$siteId, 'Tomas Pavlat', 'Portretni foceni', 5, 'Profesionalita, ochota, vstricnost a hlavne poradi i se scenou. Chces mit profi foto a nevis komu rict a poradit s kompozici? Tak ted uz vis.', 10, 1]);
$reviewStmt->execute([$siteId, 'Lucie Fedorova', 'Foceni', 5, 'Lepsiho fotografa neznam. :) Uzasny pristup, nadherny fotky. Doporucuji.', 11, 1]);
$reviewStmt->execute([$siteId, 'Zuzana Horakova', 'Rodinne foceni', 5, 'Perfektni. Foceni velmi prijemne a fotky krasne. Doporucuji vsemi deseti.', 12, 1]);
$reviewStmt->execute([$siteId, 'Martina Bobkova', 'Foceni', 5, 'Super fotky a super pokec :) doporucuji moc moc.', 13, 1]);
$reviewStmt->execute([$siteId, 'Darca Polaskova', 'Svatebni foceni', 5, 'Super domluva, zabava a vtipky pri samotnem foceni, fotky skvele a je jich hodne :3 Rozhodne doporucuji :)', 14, 1]);
$reviewStmt->execute([$siteId, 'Dana Kneblova', 'Foceni', 5, 'Bylo to zabavne a uplne pohodove foceni ;)', 15, 1]);
$reviewStmt->execute([$siteId, 'Veronika Bokova', 'Rodinne foceni', 5, 'Domluva skvela, foceni zabavne, takze z cloveka hned odpadne nervozita a fotky paradni...Doporucuji! :)', 16, 1]);
$reviewStmt->execute([$siteId, 'Michal Kucera', 'Foceni', 5, 'Skvela komunikace, super fotky jen doporucuji.', 17, 1]);
$reviewStmt->execute([$siteId, 'Dominika Jecminkova', 'Foceni', 5, 'Skvela komunikace, skvele fotky ^^ Kdykoliv se jde domluvit, aby to vyhovovalo obema stranam :) Muzu jen doporucit.', 18, 1]);
$reviewStmt->execute([$siteId, 'Rose De Clermont', 'Foceni', 5, 'Velka spokojenost! Dvakrat nam foceni zrusilo spatne pocasi, ale diky dobre komunikaci s fotografem se foceni fakt uskutecnilo :) I po foceni je paradni domluva, kdyz chci jeste nejakou dalsi z fotek upravit. Doporucuji :)', 19, 1]);
$reviewStmt->execute([$siteId, 'Aneta Jenesova', 'Foceni', 5, 'Super fotky a skvela domluva s panem fotografem doporucuji :)', 20, 1]);
$reviewStmt->execute([$siteId, 'Jenny Molatova', 'Foceni', 5, 'Super fotky ^^', 21, 1]);
$reviewStmt->execute([$siteId, 'Martin Jenes', 'Foceni', 5, 'Vrele doporucuji. Perfektni prace pana fotografa.', 22, 1]);
$reviewStmt->execute([$siteId, 'Vojtech Vrana', 'Hudebni klip', 5, 'Cerstvy klip z nove desky WE ARE HERE. Jsme nataceli s RB - Videos & Photos, jsou to sikovni panove.', 23, 1]);
$reviewStmt->execute([$siteId, 'Veronika Saskova', 'Celodenni foceni', 5, 'Diky klukum z rbvideosphotos jsem mela moznost vyzkouset si celodenni foceni v pronajatem apartmanu, kde se vystridalo nekolik modelek. Byla to fajn zkusenost :)', 24, 1]);
$reviewStmt->execute([$siteId, 'Diana Smidova', 'Foceni', 5, 'Dekujeme za nejkrasnejsi vzpominku! Super prace.', 25, 1]);
$reviewStmt->execute([$siteId, 'Clarissa cosplayer', 'Photo shoot', 5, 'Today is nice day. I already have photos from the photo shoot. Thank you so much to the photographer for the photos and video.', 26, 1]);
$reviewStmt->execute([$siteId, 'Tomas Fyfty Fiala', 'Hudebni klip', 5, 'A je to venku. Klip k novemu songu SVETLO. Velke diky klukum z RB - Videos & Photos.', 27, 1]);
$reviewStmt->execute([$siteId, 'Katerina Vaclavkova', 'Foceni', 5, 'Super :) :)', 28, 1]);
$reviewStmt->execute([$siteId, 'Radka Polynkova', 'Foceni', 5, 'Moc dekujeme za uzasne fotky <3', 29, 1]);
(new \App\Services\ReviewContentService())->ensureHomepageSection($siteId);
echo "Recenze vytvoreny.\n";

try {
    $pub = new PublishService();
    $pub->publishSite($siteId, $userId);
    echo "Stránky publikovány.\n";
} catch (\Throwable $e) {
    echo "Varování při publikování: " . $e->getMessage() . "\n";
}

echo "\n✓ Seed dokončen.\n";
echo "  Přihlášení: admin@example.com / admin123\n";
echo "  Změňte heslo po prvním přihlášení!\n";
