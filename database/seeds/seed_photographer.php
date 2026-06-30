<?php
declare(strict_types=1);

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\PublishService;

$pdo = Database::getInstance();

if (in_array('--fresh', $argv ?? [], true)) {
    foreach (['publish_history','seo_settings','content_blocks','media_variants','blog_media','portfolio_media','blog_posts','media','portfolio_entries','pages','templates','redirects','sites','users'] as $t) {
        $pdo->exec("DELETE FROM $t");
    }
    echo "Tables cleared.\n";
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

if (!function_exists('upsertUser')) {

function upsertUser(PDO $pdo, string $email, string $pw, string $role = 'admin'): int
{
    $h = password_hash($pw, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT IGNORE INTO users (email,password_hash,role) VALUES (?,?,?)')->execute([$email,$h,$role]);
    $pdo->prepare('UPDATE users SET password_hash=?,role=?,updated_at=NOW() WHERE email=?')->execute([$h,$role,$email]);
    $s = $pdo->prepare('SELECT id FROM users WHERE email=?');
    $s->execute([$email]);
    return (int)$s->fetchColumn();
}

function upsertSite(PDO $pdo, string $name, string $domain, string $ga, string $gtm, string $bodyScript, string $layoutHtml, string $layoutCss, string $layoutJs, string $layoutNavHome, string $layoutNavSub): int
{
    $s = $pdo->prepare('SELECT id FROM sites WHERE domain=?');
    $s->execute([$domain]);
    $id = $s->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE sites SET name=?,google_analytics_id=?,google_tag_manager_id=?,body_scripts=?,layout_html=?,layout_css=?,layout_js=?,layout_nav_home=?,layout_nav_sub=?,updated_at=NOW() WHERE id=?')
            ->execute([$name,$ga,$gtm,$bodyScript,$layoutHtml,$layoutCss,$layoutJs,$layoutNavHome,$layoutNavSub,$id]);
        return (int)$id;
    }
    $pdo->prepare('INSERT INTO sites (name,domain,google_analytics_id,google_tag_manager_id,body_scripts,layout_html,layout_css,layout_js,layout_nav_home,layout_nav_sub,active_theme_key) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$name,$domain,$ga,$gtm,$bodyScript,$layoutHtml,$layoutCss,$layoutJs,$layoutNavHome,$layoutNavSub,null]);
    return (int)$pdo->lastInsertId();
}

function tpl(PDO $pdo, int $siteId, string $name, string $html, string $css, string $js): int
{
    $s = $pdo->prepare('SELECT id FROM templates WHERE site_id=? AND name=?');
    $s->execute([$siteId,$name]);
    $id = $s->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE templates SET html_source=?,css_source=?,js_source=?,updated_at=NOW() WHERE id=?')
            ->execute([$html,$css,$js,$id]);
        return (int)$id;
    }
    $pdo->prepare('INSERT INTO templates (site_id,name,html_source,css_source,js_source) VALUES (?,?,?,?,?)')
        ->execute([$siteId,$name,$html,$css,$js]);
    return (int)$pdo->lastInsertId();
}

function addPage(PDO $pdo, int $siteId, int $tplId, string $title, string $slug, string $urlPath): int
{
    $pdo->prepare('INSERT IGNORE INTO pages (site_id,template_id,title,slug,url_path,status) VALUES (?,?,?,?,?,\'published\')')
        ->execute([$siteId,$tplId,$title,$slug,$urlPath]);
    $pdo->prepare('UPDATE pages SET template_id=?,title=?,url_path=?,status=\'published\',updated_at=NOW() WHERE site_id=? AND slug=?')
        ->execute([$tplId,$title,$urlPath,$siteId,$slug]);
    $s = $pdo->prepare('SELECT id FROM pages WHERE site_id=? AND slug=?');
    $s->execute([$siteId,$slug]);
    return (int)$s->fetchColumn();
}

function block(PDO $pdo, int $pid, string $key, string $type, string $label, string $content, int $sort = 0): void
{
    $pdo->prepare('REPLACE INTO content_blocks (page_id,block_key,block_type,label,content,sort_order) VALUES (?,?,?,?,?,?)')
        ->execute([$pid,$key,$type,$label,$content,$sort]);
}

function seo(PDO $pdo, int $pid, array $d): void
{
    $pdo->prepare('REPLACE INTO seo_settings (page_id,meta_title,meta_description,meta_keywords,canonical_url,robots_index,robots_follow,og_title,og_description,twitter_title,twitter_description,schema_type,schema_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $pid,
            $d['title']      ?? null,
            $d['desc']       ?? null,
            $d['kw']         ?? null,
            $d['canonical']  ?? null,
            1, 1,
            $d['og_title']   ?? $d['title']  ?? null,
            $d['og_desc']    ?? $d['desc']   ?? null,
            $d['tw_title']   ?? $d['title']  ?? null,
            $d['tw_desc']    ?? $d['desc']   ?? null,
            $d['schema_type']?? null,
            $d['schema_json']?? null,
        ]);
}

function fetchMedia(PDO $pdo, int $siteId, string $url, string $key, string $altText = ''): int
{
    $year  = date('Y');
    $month = date('m');
    $dir   = BASE_PATH . "/storage/media/{$year}/{$month}";
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename    = "seed-{$key}.png";
    $filepath    = "{$dir}/{$filename}";
    $storagePath = "storage/media/{$year}/{$month}/{$filename}";

    $s = $pdo->prepare('SELECT id FROM media WHERE site_id=? AND storage_path=?');
    $s->execute([$siteId, $storagePath]);
    if ($id = $s->fetchColumn()) {
        return (int)$id;
    }

    if (!is_file($filepath)) {
        $ctx  = stream_context_create(['http' => ['timeout' => 20, 'user_agent' => 'SeedBot/1.0']]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            echo "  Warning: could not fetch {$url}\n";
            return 0;
        }
        file_put_contents($filepath, $data);
    }

    $pdo->prepare('INSERT INTO media (site_id, original_filename, storage_path, alt_text, title, mime_type) VALUES (?,?,?,?,?,?)')
        ->execute([$siteId, $filename, $storagePath, $altText, $altText, 'image/png']);
    return (int)$pdo->lastInsertId();
}

function upsertBlogPost(PDO $pdo, int $siteId, string $slug, string $title, string $excerpt, string $content, ?int $featuredImageId, string $publishedAt): int
{
    $s = $pdo->prepare('SELECT id FROM blog_posts WHERE site_id=? AND slug=?');
    $s->execute([$siteId, $slug]);
    $id = $s->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE blog_posts SET title=?,excerpt=?,content=?,featured_image_id=?,status=\'published\',published_at=?,updated_at=NOW() WHERE id=?')
            ->execute([$title, $excerpt, $content, $featuredImageId, $publishedAt, (int)$id]);
        return (int)$id;
    }
    $pdo->prepare('INSERT INTO blog_posts (site_id, title, slug, excerpt, content, featured_image_id, status, published_at) VALUES (?,?,?,?,?,?,\'published\',?)')
        ->execute([$siteId, $title, $slug, $excerpt, $content, $featuredImageId, $publishedAt]);
    return (int)$pdo->lastInsertId();
}

function upsertPortfolioEntry(PDO $pdo, int $siteId, string $slug, string $title, string $description, ?string $videoUrl, string $publishedAt, int $sortOrder = 0): int
{
    $s = $pdo->prepare('SELECT id FROM portfolio_entries WHERE site_id=? AND slug=?');
    $s->execute([$siteId, $slug]);
    $id = $s->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE portfolio_entries SET title=?,description=?,video_url=?,status=\'published\',published_at=?,sort_order=?,updated_at=NOW() WHERE id=?')
            ->execute([$title, $description, $videoUrl, $publishedAt, $sortOrder, (int)$id]);
        return (int)$id;
    }
    $pdo->prepare('INSERT INTO portfolio_entries (site_id, title, slug, description, video_url, status, published_at, sort_order) VALUES (?,?,?,?,?,\'published\',?,?)')
        ->execute([$siteId, $title, $slug, $description, $videoUrl, $publishedAt, $sortOrder]);
    return (int)$pdo->lastInsertId();
}

function linkPortfolioMedia(PDO $pdo, int $portfolioId, array $mediaIds): void
{
    $pdo->prepare('DELETE FROM portfolio_media WHERE portfolio_id=?')->execute([$portfolioId]);
    $stmt = $pdo->prepare('INSERT INTO portfolio_media (portfolio_id, media_id, sort_order) VALUES (?,?,?)');
    foreach ($mediaIds as $i => $mid) {
        if ($mid) $stmt->execute([$portfolioId, (int)$mid, $i]);
    }
}

} // end if (!function_exists('upsertUser'))

// ─── Site Layout ──────────────────────────────────────────────────────────────
// Sdileny layout — nav + paticka + HTML obal pro vsechny stranky.
// Sablony stranek obsahuji jen obsah (zadny <html>/<head>/<body>/nav/footer).
// Placeholdery: {page_content} = obsah, {site_name} = nazev webu, {nav} = varianta navu.

$layoutHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
</head>
<body>
{nav}
{page_content}
<footer class="footer">
  <p>&copy; 2025 {site_name} &mdash; Olomouc</p>
</footer>
<button id="scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})">&#8593;</button>
</body>
</html>
HTML;

// Nav — homepage: hash anchory pro One Page smooth scroll
$layoutNavHome = <<<'HTML'
<nav class="nav" id="sitenav">
  <div class="container nav-inner">
    <a href="/" class="brand">{site_name}</a>
    <div class="nav-links">
      <a href="#sluzby">Slu&#382;by</a>
      <a href="#nase-prace">Na&#353;e pr&#225;ce</a>
      <a href="#cenik">Cen&#237;k</a>
      <a href="#faq">FAQ</a>
      <a href="#blog">Blog</a>
      <a href="#kontakt" class="nav-cta">Kontakt</a>
    </div>
  </div>
</nav>
HTML;

// Nav — subpages: prime URL na jednotlive stranky
$layoutNavSub = <<<'HTML'
<nav class="nav" id="sitenav">
  <div class="container nav-inner">
    <a href="/" class="brand">{site_name}</a>
    <div class="nav-links">
      <a href="/sluzby">Slu&#382;by</a>
      <a href="/nase-prace">Na&#353;e pr&#225;ce</a>
      <a href="/cenik">Cen&#237;k</a>
      <a href="/faq">FAQ</a>
      <a href="/blog">Blog</a>
      <a href="/kontakt" class="nav-cta">Kontakt</a>
    </div>
  </div>
</nav>
HTML;

$layoutCss = <<<'CSS'
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;color:#222;background:#fff;line-height:1.6;font-size:16px;padding-top:60px}
.container{max-width:1100px;margin:0 auto;padding:0 24px}
a{color:#c4963c;text-decoration:none}
img{max-width:100%;height:auto}
.nav{position:fixed;top:0;left:0;right:0;z-index:500;background:rgba(17,17,17,.96);transition:background .3s}
.nav-inner{display:flex;align-items:center;height:60px}
.brand{font-size:13px;font-weight:800;letter-spacing:.1em;color:#fff;text-transform:uppercase;margin-right:auto}
.nav-links{display:flex;gap:28px;align-items:center}
.nav-links a{color:rgba(255,255,255,.6);font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;transition:color .2s}
.nav-links a:hover{color:#fff}
.nav-cta{background:#c4963c !important;color:#fff !important;padding:8px 18px}
.nav-cta:hover{background:#b5853a !important}
.footer{background:#111;color:rgba(255,255,255,.35);padding:24px;text-align:center;font-size:12px}
#scroll-top{position:fixed;bottom:24px;right:24px;background:#c4963c;color:#fff;border:none;width:44px;height:44px;border-radius:50%;cursor:pointer;font-size:20px;opacity:0;visibility:hidden;transition:opacity .25s;z-index:999;line-height:1}
#scroll-top.vis{opacity:1;visibility:visible}
@media(max-width:640px){.nav-links a:not(.nav-cta){display:none}}
CSS;

$layoutJs = <<<'JS'
(function(){
  var nav=document.getElementById('sitenav');
  var btn=document.getElementById('scroll-top');
  if(nav&&document.getElementById('cms-adminbar')){nav.style.top='40px';}
  window.addEventListener('scroll',function(){
    var y=window.pageYOffset;
    if(btn)btn.classList.toggle('vis',y>300);
    if(nav)nav.style.background=y>80?'rgba(10,10,10,.98)':'rgba(17,17,17,.96)';
  },{passive:true});
})();
JS;

// ─── Subpage base CSS (Ceník, FAQ, Blog, clanky) ─────────────────────────────
// Nav, paticka a zakladni reset jsou v layoutu.
// Tento CSS definuje jen styly obsahu podstranek.

$subpageCss = <<<'CSS'
.hero{background:#1a1a1a;color:#fff;padding:48px 0 56px}
.hero h1{color:#fff;font-size:2.2rem;margin-bottom:.5rem}
.hero p{color:rgba(255,255,255,.6);max-width:600px;margin-bottom:1rem}
.hero-tag{display:block;font-size:11px;color:#c4963c;text-transform:uppercase;letter-spacing:.15em;margin-bottom:14px}
h1{font-size:2rem;margin-bottom:.5rem}
h2{font-size:1.4rem;margin-bottom:.75rem;color:#222}
p{color:#555;margin-bottom:1rem}
.section{padding:60px 0}
.section-dark{background:#1a1a1a;padding:60px 0}
.section-dark h2{color:#fff}
.section-dark p{color:rgba(255,255,255,.6)}
.btn{display:inline-block;background:#c4963c;color:#fff !important;padding:10px 22px;font-size:13px;font-weight:600;margin-top:16px}
CSS;

$subpageJs = <<<'JS'
(function(){
  var b=document.getElementById("scroll-top");
  if(!b)return;
  window.addEventListener("scroll",function(){b.classList.toggle("vis",window.scrollY>300);},{passive:true});
})();
JS;

// ─── Site + User ──────────────────────────────────────────────────────────────

$gtmNoscript = '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-542MMSL" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';

$userId = upsertUser($pdo, 'admin@rbphotos.cz', 'admin123');
$siteId = upsertSite($pdo, 'RB Photos & Videos', 'https://rbvideosphotos.kvalitne.cz', 'G-BD4BS9R4JZ', 'GTM-542MMSL', $gtmNoscript, $layoutHtml, $layoutCss, $layoutJs, $layoutNavHome, $layoutNavSub);

// ─── Template: Homepage ───────────────────────────────────────────────────────
// Pouze obsah stranky — bez <html>/<head>/<body>/nav/footer (ty jsou v layoutu).
// body{padding-top:0} potlaci 60px odsazeni z layoutu — hero se roztahne pres cely viewport.

$htmlHomeFull = <<<'HTML'
<header class="hero" id="hero">
  <video data-block="hero_video" class="hero-bg hero-video" autoplay muted loop playsinline preload="metadata" data-media="hero_bg"></video>
  <div class="hero-overlay"></div>
  <div class="container hero-body">
    <span class="hero-tag" data-block="hero_tag"></span>
    <h1 data-block="hero_h1"></h1>
    <p class="hero-sub" data-block="hero_sub"></p>
    <div class="hero-btns">
      <a href="tel:792483583" class="btn-h btn-h--gold">792&#160;483&#160;583</a>
      <a href="#sluzby" class="btn-h btn-h--outline">Na&#353;e slu&#382;by</a>
    </div>
  </div>
</header>
<section class="stats-bar">
  <div class="container stats-row">
    <div class="stat-item" data-block="stat_1"></div>
    <div class="stat-item" data-block="stat_2"></div>
    <div class="stat-item" data-block="stat_3"></div>
    <div class="stat-item" data-block="stat_4"></div>
  </div>
</section>
<section class="section" id="sluzby">
  <div class="container">
    <div class="sec-hdr">
      <h2 data-block="services_title"></h2>
      <p data-block="services_intro"></p>
    </div>
    <div class="svc-grid">
      <div class="svc-card svc-card--dark" data-block="service_1"></div>
      <div class="svc-card" data-block="service_2"></div>
      <div class="svc-card" data-block="service_3"></div>
      <div class="svc-card" data-block="service_4"></div>
    </div>
  </div>
</section>
<section class="section section-dark" id="proc-my">
  <div class="container">
    <div class="sec-hdr">
      <h2 data-block="why_title"></h2>
      <p data-block="why_intro"></p>
    </div>
    <div class="feat-grid">
      <div class="feat" data-block="why_1"></div>
      <div class="feat" data-block="why_2"></div>
      <div class="feat" data-block="why_3"></div>
      <div class="feat" data-block="why_4"></div>
    </div>
  </div>
</section>
<section class="section section-dark" id="nase-prace">
  <div class="container">
    <div class="sec-hdr">
      <h2 data-block="portfolio_title"></h2>
      <p data-block="portfolio_intro"></p>
    </div>
    <div data-block="portfolio-tiles"></div>
    <div class="sec-btns" data-block="portfolio_btns"></div>
  </div>
</section>
<section class="section section-light" id="reference">
  <div class="container">
    <div class="sec-hdr">
      <h2 data-block="reviews_title"></h2>
      <p data-block="reviews_intro"></p>
    </div>
    <div data-block="reviews_demo" class="rev-grid"></div>
    <div class="sec-btns" data-block="reviews_cta"></div>
  </div>
</section>
<section class="section" id="cenik">
  <div class="container">
    <div class="sec-hdr">
      <h2 data-block="pricing_title"></h2>
      <p data-block="pricing_intro"></p>
    </div>
    <div data-block="pricing_body"></div>
    <div class="sec-btns" data-block="pricing_cta"></div>
  </div>
</section>
<section class="section section-dark" id="blog">
  <div class="container">
    <div class="sec-hdr">
      <h2 data-block="blog_title"></h2>
      <p data-block="blog_intro"></p>
    </div>
    <div class="blog-grid">
      <div class="blog-card" data-block="blog_1"></div>
      <div class="blog-card" data-block="blog_2"></div>
      <div class="blog-card" data-block="blog_3"></div>
    </div>
    <div class="sec-btns" data-block="blog_cta"></div>
  </div>
</section>
<section class="section section-light" id="faq">
  <div class="container">
    <div class="sec-hdr">
      <h2 data-block="faq_title"></h2>
      <p data-block="faq_intro"></p>
    </div>
    <div class="faq-list">
      <div class="faq-item" data-block="faq_1"></div>
      <div class="faq-item" data-block="faq_2"></div>
      <div class="faq-item" data-block="faq_3"></div>
    </div>
    <div class="sec-btns" data-block="faq_cta"></div>
  </div>
</section>
<section class="section" id="kontakt">
  <div class="container cta-2col">
    <div class="cta-l">
      <h2 data-block="cta_title"></h2>
      <p data-block="cta_sub"></p>
      <div data-block="contact_info"></div>
    </div>
    <div class="cta-r" data-block="contact_card"></div>
  </div>
</section>
HTML;

$cssHomeFull = <<<'CSS'
/* Hero plres cely viewport — potlaci padding-top:60px z layoutu */
body{padding-top:0}
.hero{position:relative;min-height:100vh;display:flex;align-items:center;overflow:hidden;background:#0f0f0f}
.hero-bg{position:absolute;inset:-15% 0;width:100%;height:130%;object-fit:cover;will-change:transform;opacity:.6;z-index:0;pointer-events:none}
.hero-overlay{position:absolute;inset:0;background:linear-gradient(160deg,rgba(0,0,0,.4) 0%,rgba(0,0,0,.65) 100%);z-index:1}
.hero-body{position:relative;z-index:2;max-width:820px;padding:100px 0 60px}
.hero-tag{display:inline-block;font-size:10px;color:#c4963c;text-transform:uppercase;letter-spacing:.22em;border:1px solid rgba(196,150,60,.55);padding:5px 14px;margin-bottom:24px}
.hero h1{font-size:clamp(2.4rem,5.5vw,4.2rem);font-weight:900;color:#fff;line-height:1.1;margin-bottom:18px}
.hero-sub{font-size:1.05rem;color:rgba(255,255,255,.65);max-width:520px;margin-bottom:36px;line-height:1.7}
.hero-btns{display:flex;gap:14px;flex-wrap:wrap}
.btn-h{display:inline-block;padding:14px 28px;font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;transition:all .2s;border:2px solid transparent}
.btn-h--gold{background:#c4963c;color:#fff;border-color:#c4963c}
.btn-h--gold:hover{background:#b5853a;border-color:#b5853a;color:#fff}
.btn-h--outline{background:transparent;color:#fff;border-color:rgba(255,255,255,.7)}
.btn-h--outline:hover{background:#fff;color:#111;border-color:#fff}
.stats-bar{background:#111;padding:40px 0}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr)}
.stat-item{text-align:center;padding:10px 20px;border-right:1px solid rgba(255,255,255,.07)}
.stat-item:last-child{border-right:none}
.stat-num{display:block;font-size:2.4rem;font-weight:900;color:#c4963c;line-height:1}
.stat-lbl{display:block;font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.12em;margin-top:8px}
.section{padding:88px 0}
.section-dark{background:#111;padding:88px 0}
.section-light{background:#f8f6f2;padding:88px 0}
.sec-hdr{text-align:center;margin-bottom:60px}
.sec-hdr h2{font-size:2.1rem;font-weight:900;color:#111;display:inline-block;margin-bottom:8px}
.sec-hdr h2::after{content:'';display:block;width:36px;height:3px;background:#c4963c;margin:12px auto 0}
.sec-hdr p{color:#666;font-size:1rem;max-width:500px;margin:0 auto}
.section-dark .sec-hdr h2{color:#fff}
.section-dark .sec-hdr p{color:rgba(255,255,255,.5)}
.svc-grid{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #e5e5e5}
.svc-card{padding:30px 22px;border-right:1px solid #e5e5e5}
.svc-card:last-child{border-right:none}
.svc-card--dark{background:#111}
.svc-num{display:block;font-size:10px;color:#c4963c;font-weight:700;letter-spacing:.12em;margin-bottom:14px}
.svc-card h3{font-size:1rem;font-weight:700;margin-bottom:10px;color:#111}
.svc-card--dark h3{color:#fff}
.svc-card p{font-size:.87rem;color:#666;line-height:1.7}
.svc-card--dark p{color:rgba(255,255,255,.5)}
.feat-grid{display:grid;grid-template-columns:repeat(4,1fr);border-top:1px solid rgba(255,255,255,.07)}
.feat{padding:32px 20px;border-right:1px solid rgba(255,255,255,.07)}
.feat:last-child{border-right:none}
.feat-num{display:block;font-size:10px;color:#c4963c;font-weight:700;letter-spacing:.12em;margin-bottom:14px}
.feat h3{font-size:.95rem;font-weight:700;color:#fff;margin-bottom:8px}
.feat p{font-size:.87rem;color:rgba(255,255,255,.45);line-height:1.7}
.sec-btns{text-align:center;display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:8px}
.btn-g{display:inline-block;background:#c4963c;color:#fff;padding:12px 22px;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
.btn-g:hover{background:#b5853a;color:#fff}
.btn-od{display:inline-block;border:2px solid #222;color:#222;padding:12px 22px;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
.btn-od:hover{background:#222;color:#fff}
.rev-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;margin-bottom:36px}
.rev-card{background:#fff;padding:26px;box-shadow:0 2px 14px rgba(0,0,0,.07)}
.rev-stars{color:#c4963c;font-size:.9rem;margin-bottom:14px}
.rev-card blockquote{font-size:.88rem;color:#444;line-height:1.75;font-style:italic;margin-bottom:16px}
.rev-name{font-weight:700;color:#111;font-size:.88rem}
.rev-type{font-size:.78rem;color:#999;margin-top:3px}
.cta-2col{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:start}
.cta-l h2{font-size:2rem;font-weight:900;margin-bottom:14px;color:#111}
.cta-l p{color:#666;margin-bottom:20px;font-size:.95rem}
.cta-info{margin-top:12px}
.cta-info p{color:#333;font-size:.93rem;margin-bottom:6px}
.cta-info a{color:#c4963c;font-weight:600}
.cta-r{background:#111;padding:30px}
.cta-r h3{color:#fff;font-size:1rem;font-weight:700;margin-bottom:6px}
.cta-r-sub{color:rgba(255,255,255,.45);font-size:.85rem;margin-bottom:22px}
.cta-btn{display:block;padding:13px 16px;text-align:center;font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;margin-bottom:10px;transition:all .2s}
.cta-btn:last-child{margin-bottom:0}
.cta-btn--gold{background:#c4963c;color:#fff}
.cta-btn--gold:hover{background:#b5853a;color:#fff}
.cta-btn--dark{border:1px solid rgba(255,255,255,.18);color:rgba(255,255,255,.8)}
.cta-btn--dark:hover{background:rgba(255,255,255,.08);color:#fff}
/* Portfolio tiles */
.pt-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;margin-bottom:8px}
.pt-tile{display:block;text-decoration:none;color:inherit;background:#1e1e2e;border-radius:10px;overflow:hidden;transition:transform .2s,box-shadow .2s}
.pt-tile:hover{transform:translateY(-4px);box-shadow:0 10px 28px rgba(0,0,0,.45)}
.pt-thumb{position:relative;height:200px;overflow:hidden;background:#111;line-height:0}
.pt-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s}
.pt-tile:hover .pt-thumb img{transform:scale(1.04)}
.pt-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:2.2rem;color:rgba(255,255,255,.7);background:rgba(0,0,0,.18);transition:background .2s}
.pt-tile:hover .pt-play{background:rgba(0,0,0,.32)}
.pt-photos{display:flex;gap:4px;height:64px;overflow:hidden}
.pt-photos img{flex:1;min-width:0;height:100%;object-fit:cover}
.pt-caption{padding:12px 14px;font-size:.9rem;font-weight:700;color:#fff;background:#1a1a2e}
/* Pricing section */
.pricing-body{background:#f8f6f2;border-left:4px solid #c4963c;padding:28px 32px;font-size:.97rem;color:#333;line-height:1.8;margin-bottom:8px}
.pricing-body strong{color:#111}
/* Blog section */
.blog-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:8px}
.blog-card{background:#1e1e2e;padding:28px 24px;text-decoration:none;display:block;transition:transform .2s}
.blog-card:hover{transform:translateY(-3px)}
.blog-cat{display:inline-block;font-size:10px;color:#c4963c;text-transform:uppercase;letter-spacing:.15em;border:1px solid rgba(196,150,60,.5);padding:3px 10px;margin-bottom:14px}
.blog-card h3{font-size:1rem;font-weight:700;color:#fff;margin-bottom:8px;line-height:1.4}
.blog-card p{font-size:.85rem;color:rgba(255,255,255,.45);line-height:1.6}
.blog-card a{color:#c4963c;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;text-decoration:none;margin-top:12px;display:inline-block}
/* FAQ section */
.faq-list{max-width:780px;margin:0 auto 8px}
.faq-item{border-bottom:1px solid #e0dcd5;padding:22px 0}
.faq-item:first-child{border-top:1px solid #e0dcd5}
.faq-q{font-size:1rem;font-weight:700;color:#111;margin-bottom:8px}
.faq-a{font-size:.9rem;color:#555;line-height:1.75}
.faq-foot{max-width:780px;margin:26px auto 8px;color:#555;line-height:1.75;font-size:.95rem}
@media(max-width:1024px){.svc-grid{grid-template-columns:1fr 1fr}.feat-grid{grid-template-columns:1fr 1fr}.blog-grid{grid-template-columns:1fr 1fr}.svc-card{border-bottom:1px solid #e5e5e5}.feat{border-bottom:1px solid rgba(255,255,255,.07)}}
@media(max-width:900px){.cta-2col{grid-template-columns:1fr;gap:32px}.stats-row{grid-template-columns:repeat(2,1fr)}.rev-grid{grid-template-columns:1fr 1fr}}
@media(max-width:640px){.rev-grid{grid-template-columns:1fr}.hero h1{font-size:2.2rem}.pt-grid{grid-template-columns:1fr}.blog-grid{grid-template-columns:1fr}}
CSS;

$jsHomeFull = <<<'JS'
(function(){
  var heroBg = document.querySelector('.hero-bg');
  if (!heroBg) return;
  window.addEventListener('scroll', function(){
    heroBg.style.transform = 'translateY(' + (window.pageYOffset * 0.32) + 'px)';
  }, {passive: true});
})();
JS;

$tplHomeFull = tpl($pdo, $siteId, 'Homepage', $htmlHomeFull, $cssHomeFull, $jsHomeFull);

// ─── Template: Ceník ──────────────────────────────────────────────────────────

$htmlCenik = <<<'HTML'
<header class="hero">
  <div class="container">
    <h1 data-block="hero_heading"></h1>
    <p data-block="hero_intro"></p>
  </div>
</header>
<section class="section">
  <div class="container">
    <h2 data-block="pricing_weddings_heading"></h2>
    <p data-block="pricing_weddings_sub"></p>
    <div data-block="pricing_packages"></div>
    <h2 data-block="pricing_other_heading"></h2>
    <p data-block="pricing_other_sub"></p>
  </div>
</section>
<section class="section-dark">
  <div class="container">
    <h2 data-block="cta_heading"></h2>
    <p data-block="cta_sub"></p>
    <a href="tel:792483583" class="btn">&#128222; 792&#160;483&#160;583</a>
  </div>
</section>
HTML;

$tplCenik = tpl($pdo, $siteId, 'Ceník', $htmlCenik, $subpageCss, $subpageJs);

// ─── Template: FAQ ────────────────────────────────────────────────────────────

$htmlFaq = <<<'HTML'
<header class="hero">
  <div class="container">
    <h1 data-block="hero_heading"></h1>
    <p data-block="hero_sub"></p>
  </div>
</header>
<section class="section">
  <div class="container">
    <div class="faq-list" data-block="faq_body"></div>
    <p class="faq-foot" data-block="faq_footer"></p>
    <p><a href="/cenik" class="btn">Zobrazit cen&#237;k</a></p>
  </div>
</section>
HTML;

$tplFaq = tpl($pdo, $siteId, 'FAQ', $htmlFaq, $subpageCss, $subpageJs);

// ─── Template: Blog listing ───────────────────────────────────────────────────

$htmlBlog = <<<'HTML'
<header class="hero">
  <div class="container">
    <h1 data-block="hero_heading"></h1>
    <p data-block="hero_sub"></p>
  </div>
</header>
<section class="section">
  <div class="container">
    <p><a href="/priprava-na-svatebni-foceni">10 tip&#367;, jak se p&#345;ipravit na dokonal&eacute; svatebn&#237; focen&#237; &rarr;</a></p>
    <p><a href="/rodinne-foceni-s-detmi">Rodinn&eacute; focen&#237;: jak dostat p&#345;irozen&eacute; sn&#237;mky i s mal&#253;mi d&#283;tmi &rarr;</a></p>
  </div>
</section>
HTML;

$tplBlog = tpl($pdo, $siteId, 'Blog listing', $htmlBlog, $subpageCss, $subpageJs);

// ─── Template: Blog article ───────────────────────────────────────────────────

$articleCss = $subpageCss . <<<'CSS'

.article-meta{display:flex;gap:12px;align-items:center;margin-bottom:20px;font-size:13px}
.article-cat{background:#c4963c;color:#fff;padding:3px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.1em}
.article-date{color:rgba(255,255,255,.4)}
.article-thumb{position:relative;padding-bottom:56.25%;background:#000;margin:0}
.article-thumb iframe{position:absolute;inset:0;width:100%;height:100%;border:none}
.thumb-wrap{background:#141414;padding:0 0 40px}
.article-section{margin-bottom:36px}
.article-section h2{font-size:1.2rem;font-weight:700;margin-bottom:10px;padding-left:14px;border-left:3px solid #c4963c}
.article-section div{color:#555;font-size:.97rem;line-height:1.75}
CSS;

$htmlArticle = <<<'HTML'
<header class="hero">
  <div class="container">
    <div class="article-meta">
      <span class="article-cat" data-block="article_cat"></span>
      <span class="article-date" data-block="article_date"></span>
    </div>
    <h1 data-block="article_h1"></h1>
    <p data-block="article_lead"></p>
  </div>
</header>
<div class="thumb-wrap">
  <div class="container">
    <div class="article-thumb" data-block="article_thumb"></div>
  </div>
</div>
<section class="section">
  <div class="container">
    <div class="article-section"><h2 data-block="section_1_h2"></h2><div data-block="section_1_body"></div></div>
    <div class="article-section"><h2 data-block="section_2_h2"></h2><div data-block="section_2_body"></div></div>
    <div class="article-section"><h2 data-block="section_3_h2"></h2><div data-block="section_3_body"></div></div>
    <div class="article-section"><h2 data-block="section_4_h2"></h2><div data-block="section_4_body"></div></div>
    <div class="article-section"><h2 data-block="section_5_h2"></h2><div data-block="section_5_body"></div></div>
  </div>
</section>
<section class="section-dark">
  <div class="container">
    <h2 data-block="cta_heading"></h2>
    <p data-block="cta_sub"></p>
    <a href="tel:792483583" class="btn">&#9742; 792&#160;483&#160;583</a>
  </div>
</section>
HTML;

$tplArticle = tpl($pdo, $siteId, 'Blog article', $htmlArticle, $articleCss, $subpageJs);

// ─── Template: Služby ─────────────────────────────────────────────────────────

$serviceCss = $subpageCss . <<<'CSS'

.svc-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:0;border:1px solid rgba(255,255,255,.08);margin-top:32px}
.svc-card{padding:32px 24px;border-right:1px solid rgba(255,255,255,.08);border-bottom:1px solid rgba(255,255,255,.08)}
.svc-card:nth-child(2n){border-right:none}
.svc-card:nth-last-child(-n+2){border-bottom:none}
.svc-card--dark{background:#111}
.svc-num{display:block;font-size:10px;color:#c4963c;font-weight:700;letter-spacing:.12em;margin-bottom:14px}
.svc-card h3{font-size:1rem;font-weight:700;margin-bottom:10px;color:#fff}
.svc-card p{font-size:.87rem;color:rgba(255,255,255,.5);line-height:1.7}
@media(max-width:640px){.svc-grid{grid-template-columns:1fr}.svc-card{border-right:none}}
CSS;

$htmlSluzby = <<<'HTML'
<header class="hero">
  <div class="container">
    <h1 data-block="hero_heading"></h1>
    <p data-block="hero_sub"></p>
  </div>
</header>
<section class="section-dark">
  <div class="container">
    <div class="svc-grid">
      <div class="svc-card svc-card--dark" data-block="service_1"></div>
      <div class="svc-card svc-card--dark" data-block="service_2"></div>
      <div class="svc-card svc-card--dark" data-block="service_3"></div>
      <div class="svc-card svc-card--dark" data-block="service_4"></div>
    </div>
  </div>
</section>
<section class="section">
  <div class="container">
    <h2 data-block="cta_heading"></h2>
    <p data-block="cta_sub"></p>
    <a href="tel:792483583" class="btn">&#128222; 792&#160;483&#160;583</a>
  </div>
</section>
HTML;

$tplSluzby = tpl($pdo, $siteId, 'Služby', $htmlSluzby, $serviceCss, $subpageJs);

// ─── Template: Kontakt ────────────────────────────────────────────────────────

$kontaktCss = $subpageCss . <<<'CSS'

.cta-2col{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:start;margin-top:32px}
.cta-l h2{font-size:1.6rem;font-weight:900;margin-bottom:14px;color:#222}
.cta-l p{color:#666;margin-bottom:20px;font-size:.95rem}
.cta-info{margin-top:12px}
.cta-info p{color:#333;font-size:.93rem;margin-bottom:6px}
.cta-info a{color:#c4963c;font-weight:600}
.cta-r{background:#111;padding:30px}
.cta-r h3{color:#fff;font-size:1rem;font-weight:700;margin-bottom:6px}
.cta-r-sub{color:rgba(255,255,255,.45);font-size:.85rem;margin-bottom:22px}
.cta-btn{display:block;padding:13px 16px;text-align:center;font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;margin-bottom:10px;transition:all .2s;text-decoration:none}
.cta-btn:last-child{margin-bottom:0}
.cta-btn--gold{background:#c4963c;color:#fff}
.cta-btn--gold:hover{background:#b5853a;color:#fff}
.cta-btn--dark{border:1px solid rgba(255,255,255,.18);color:rgba(255,255,255,.8)}
.cta-btn--dark:hover{background:rgba(255,255,255,.08);color:#fff}
@media(max-width:720px){.cta-2col{grid-template-columns:1fr;gap:32px}}
CSS;

$htmlKontakt = <<<'HTML'
<header class="hero">
  <div class="container">
    <h1 data-block="hero_heading"></h1>
    <p data-block="hero_sub"></p>
  </div>
</header>
<section class="section">
  <div class="container">
    <div class="cta-2col">
      <div class="cta-l">
        <h2 data-block="contact_heading"></h2>
        <div data-block="contact_info"></div>
      </div>
      <div class="cta-r" data-block="contact_card"></div>
    </div>
  </div>
</section>
HTML;

$tplKontakt = tpl($pdo, $siteId, 'Kontakt', $htmlKontakt, $kontaktCss, $subpageJs);

// ─── Pages ────────────────────────────────────────────────────────────────────

$pidHome    = addPage($pdo, $siteId, $tplHomeFull, 'Fotograf & Videograf Olomouc | RB Photos & Videos',                         '',                               '/');
$pidSluzby  = addPage($pdo, $siteId, $tplSluzby,  'Fotograf & Videograf Olomouc — naše služby | RB Photos & Videos',            'sluzby',                         '/sluzby');
$pidCenik   = addPage($pdo, $siteId, $tplCenik,   'Ceník fotografa Olomouc | RB Photos & Videos',                              'cenik',                          '/cenik');
$pidFaq     = addPage($pdo, $siteId, $tplFaq,     'Časté otázky | Fotograf Olomouc — RB Photos & Videos',                      'faq',                            '/faq');
$pidBlog    = addPage($pdo, $siteId, $tplBlog,    'Blog | RB Photos & Videos',                                                 'blog',                           '/blog');
$pidKontakt = addPage($pdo, $siteId, $tplKontakt, 'Kontakt | Fotograf Olomouc — RB Photos & Videos',                           'kontakt',                        '/kontakt');
// Remove old CMS article pages — now served as blog_posts
$pdo->prepare("DELETE FROM pages WHERE site_id=? AND slug IN ('priprava-na-svatebni-foceni','rodinne-foceni-s-detmi')")
    ->execute([$siteId]);

// ─── Placeholder media ────────────────────────────────────────────────────────
echo "Fetching placeholder images...\n";
$imgBlog1 = fetchMedia($pdo, $siteId, 'https://placehold.co/800x500/1a1a2e/c4963c.png', 'blog-1', 'Příprava na svatební focení');
$imgBlog2 = fetchMedia($pdo, $siteId, 'https://placehold.co/800x500/0e1a0e/c4963c.png', 'blog-2', 'Rodinné focení s dětmi');
$imgBlog3 = fetchMedia($pdo, $siteId, 'https://placehold.co/800x500/1a0e0e/c4963c.png', 'blog-3', 'Jak vybrat fotografa');
$imgPf1   = fetchMedia($pdo, $siteId, 'https://placehold.co/900x600/0a0a1a/c4963c.png', 'pf-1',   'Svatba — obřad');
$imgPf2   = fetchMedia($pdo, $siteId, 'https://placehold.co/900x600/111827/e5c07b.png', 'pf-2',   'Svatba — pár');
$imgPf3   = fetchMedia($pdo, $siteId, 'https://placehold.co/900x600/1a1008/c4963c.png', 'pf-3',   'Svatba — detail');
echo "Done.\n";

// ─── Blog posts ───────────────────────────────────────────────────────────────
$art1Content = '<h2>1. Konzultace s fotografem — co nejdříve</h2><p>Osobní schůzka ještě před svatbou je základ. Promluvte si o místě obřadu, harmonogramu a vašich přáních. Dobrý fotograf vám pomůže sestavit realistický časový plán a doporučí nejlepší místa pro párové fotografie.</p><h2>2. Naplánujte harmonogram s rezervou</h2><p>Podceňování času je nejčastější chyba. Vlasy a make-up trvají déle, hosté přicházejí postupně a skupinové fotografie s celou rodinou zaberou 20–30 minut. Vždy počítejte se zálohou.</p><h2>3. Připravte detaily s láskou</h2><p>Prsteny, kytice, pozvánky, šperky, parfém — tyto detaily dělají snímky výjimečnými. Připravte je ráno na neutrální povrch dřív, než začne chaos příprav.</p><h2>4. Využijte zlatou hodinu</h2><p>Hodina před západem slunce je čas nejkrásnějšího světla — teplého, měkkého, kinematografického. V Olomouci máme nádherná místa pro zlatou hodinu — naplánujte párové focení právě na tento čas.</p><h2>5. Zůstaňte sami sebou</h2><p>Nehledejte do objektivu, hýbejte se, smějte se, šeptejte si. Zkušený fotograf zachytí skutečné emoce — ty se nedají naplánovat ani zrežírovat.</p>';

$art2Content = '<h2>1. Správný čas je základ</h2><p>S malými dětmi je načasování vše. Focení plánujte na dobu, kdy jsou děti nejvíce odpočaté — typicky dopoledne po snídani nebo krátce po odpoledním spánku. Nikdy ne těsně před jídlem nebo spaním.</p><h2>2. Oblečení v harmonických barvách</h2><p>Nemusíte mít všichni stejné tričko. Stačí dva–tři harmonické tóny a kombinovat je volně. Neutrální barvy — béžová, terakota, modrá — ve spojení s přírodním prostředím fungují vždy skvěle.</p><h2>3. Hrajte si, ignorujte fotoaparát</h2><p>Říkat dětem „koukej a usměj se" způsobí nucené úsměvy a strnulé pózy. Místo toho se jim přirozeně věnujte — šimrejte, čtěte pohádku. Zkušený fotograf zachytí opravdové momenty.</p><h2>4. Příroda je nejlepší kulisa</h2><p>Parky, lesy, louky nebo zahrada — příroda je ideální prostředí. Děti mají prostor, světlo je přirozené. V Olomouci: Smetanovy sady, Tabulový vrch nebo les u Bystřice jsou skvělé lokace.</p><h2>5. Krátká session — méně je více</h2><p>45–60 minut je optimum pro rodinné focení s malými dětmi. Kratší čas znamená svěží, spolupracující děti. Začněte s nejmenšími — mají nejkratší výdrž.</p>';

$art3Content = '<h2>1. Prohlédněte si portfolio</h2><p>Portfolio je nejdůležitější kritérium. Podívejte se, zda má fotograf zkušenosti s vaším typem akce a zda se vám líbí jeho styl. Velký rozdíl je mezi reportážním a pózeným stylem fotografie.</p><h2>2. Přečtěte si recenze</h2><p>Recenze na Googlu, Facebooku nebo doporučení od přátel odhalí, jak fotograf skutečně pracuje — dochvilnost, komunikace, dodání materiálu a profesionalita na místě.</p><h2>3. Proberte podmínky předem</h2><p>Cena, termín dodání, forma výstupu (online galerie, tisk, USB), práva na fotografie — to vše by mělo být jasně domluveno před podpisem smlouvy. Vyvarujte se překvapením.</p><h2>4. Osobní chemistry je důležitá</h2><p>Strávíte s fotografem celý den nebo při focení hodiny. Musí vám být příjemný. Dobrý fotograf vás uvolní, nezíská strnulé záběry. Schůzka nebo telefonát předem hodně napoví.</p><h2>5. Rezervujte včas</h2><p>Oblíbení fotografové mají termíny obsazené měsíce dopředu. Pro letní svatby a akce se doporučuje rezervace rok napřed. Čím dříve, tím větší výběr termínů.</p>';

upsertBlogPost($pdo, $siteId, 'priprava-na-svatebni-foceni', '10 tipů, jak se připravit na dokonalé svatební focení', 'Váš svatební den si zaslouží dokonalou přípravu. Přečtěte si, co udělat jinak než ostatní, a zajistěte si snímky, na které budete hrdí celý život.', $art1Content, $imgBlog1, '2025-04-12 10:00:00');
upsertBlogPost($pdo, $siteId, 'rodinne-foceni-s-detmi', 'Rodinné focení: jak dostat přirozené snímky i s malými dětmi', 'Malé děti a fotoaparát? Přesně v tom chaosu se skrývají ty nejkrásnější momenty. Zde je návod, jak z rodinného focení udělat zábavný zážitek.', $art2Content, $imgBlog2, '2025-03-28 10:00:00');
upsertBlogPost($pdo, $siteId, 'jak-vybrat-fotografa', 'Jak vybrat správného fotografa pro vaši akci?', 'Na co se zaměřit při výběru fotografa — portfolio, styl, komunikace a podmínky spolupráce. Poradíme, na co se neptat zapomenout.', $art3Content, $imgBlog3, '2025-05-01 10:00:00');

// ─── Portfolio entry ──────────────────────────────────────────────────────────
$pfId = upsertPortfolioEntry($pdo, $siteId, 'svatba-martin-helena', 'Svatba Martin & Helena — Olomouc 2025', 'Přirozené záběry z krásného svatebního dne v Olomouci. Zlatá hodina v Smetanových sadech, večerní obřad a nezapomenutelné detaily.', null, '2025-05-03 10:00:00', 0);
if ($pfId && $imgPf1 && $imgPf2 && $imgPf3) {
    linkPortfolioMedia($pdo, $pfId, [$imgPf1, $imgPf2, $imgPf3]);
}

// Regenerate portfolio-tiles block on homepage
if ($pfId) {
    $pfPage = (new \App\Repositories\PageRepository())->findByUrlPath('');
    if ($pfPage) {
        $ym = date('Y/m');
        $h  = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $p1 = "/storage/media/{$ym}/seed-pf-1.png";
        $p2 = "/storage/media/{$ym}/seed-pf-2.png";
        $tileHtml = '<div class="pt-grid"><a class="pt-tile" href="/svatba-martin-helena">'
            . '<div class="pt-thumb"><img src="' . $p1 . '" alt="Svatba Martin &amp; Helena" loading="lazy"></div>'
            . '<div class="pt-photos"><img src="' . $p2 . '" alt="" loading="lazy"><img src="/storage/media/' . $ym . '/seed-pf-3.png" alt="" loading="lazy"></div>'
            . '<div class="pt-caption">Svatba Martin &amp; Helena — Olomouc 2025</div>'
            . '</a></div>';
        $pdo->prepare('REPLACE INTO content_blocks (page_id,block_key,block_type,label,content,sort_order) VALUES (?,?,?,?,?,?)')
            ->execute([$pfPage['id'], 'portfolio-tiles', 'html', 'Naše práce — dlaždice', $tileHtml, 38]);
    }
}

// ─── Content blocks: Homepage ─────────────────────────────────────────────────

block($pdo, $pidHome, 'hero_bg',         'image',    'Hero — pozadí (foto)',    '', 1);
block($pdo, $pidHome, 'hero_video',      'video',    'Hero — video (URL)',      '', 2);
block($pdo, $pidHome, 'hero_tag',        'text',     'Hero — tag',              'Fotograf & Videograf — Olomouc', 3);
block($pdo, $pidHome, 'hero_h1',         'text',     'Hero — nadpis',           'Zachytíme váš příběh. Na vždy....', 4);
block($pdo, $pidHome, 'hero_sub',        'textarea', 'Hero — podnadpis',        'Profesionální svatební fotografie a videografie v Olomouci a celé České republice. Každý okamžik si zaslouží být nezapomenutelný.', 5);

block($pdo, $pidHome, 'stat_1', 'html', 'Statistika 1', '<span class="stat-num">150+</span><span class="stat-lbl">Spokojených klientů</span>', 10);
block($pdo, $pidHome, 'stat_2', 'html', 'Statistika 2', '<span class="stat-num">8+</span><span class="stat-lbl">Let zkušeností</span>', 11);
block($pdo, $pidHome, 'stat_3', 'html', 'Statistika 3', '<span class="stat-num">5 let</span><span class="stat-lbl">Zálohy fotek zdarma</span>', 12);
block($pdo, $pidHome, 'stat_4', 'html', 'Statistika 4', '<span class="stat-num">2 fotografové</span><span class="stat-lbl">Tým profesionálů</span>', 13);

block($pdo, $pidHome, 'services_title', 'text',     'Služby — nadpis', 'Co pro vás uděláme', 20);
block($pdo, $pidHome, 'services_intro', 'textarea', 'Služby — intro',  'Ať jde o svatbu, portrét nebo firemní projekt — jsme tu pro vás.', 21);
block($pdo, $pidHome, 'service_1', 'html', 'Služba 1 — Svatby', '<span class="svc-num">01</span><h3>Svatební foto &amp; video</h3><p>Zachytíme každý dotek, každý pohled, každou slzu štěstí. Přirozené reportážní fotografie a filmové video, které vám budou vyprávět příběh vašeho dne po celý život.</p>', 22);
block($pdo, $pidHome, 'service_2', 'html', 'Služba 2 — Rodiny', '<span class="svc-num">02</span><h3>Rodinné &amp; portrétní focení</h3><p>Rodinné vzpomínky, těhotenské focení, křtiny nebo portréty. Přirozené, nenucené a krásné snímky, na které budete hrdí.</p>', 23);
block($pdo, $pidHome, 'service_3', 'html', 'Služba 3 — Reklama', '<span class="svc-num">03</span><h3>Reklamní foto &amp; video</h3><p>Profesionální vizuální obsah pro firmy, kapely, produkty nebo brand. Videoklipy, firemní prezentace a obsah, který prodává.</p>', 24);
block($pdo, $pidHome, 'service_4', 'html', 'Služba 4 — Akce', '<span class="svc-num">04</span><h3>Akce &amp; události</h3><p>Konference, kulturní akce, plesy, narozeniny. Každá událost si zaslouží být profesionálně zdokumentována.</p>', 25);

block($pdo, $pidHome, 'why_title', 'text',     'Proč my — nadpis', 'Proč si vybrat RB Photos & Videos?', 30);
block($pdo, $pidHome, 'why_intro', 'textarea', 'Proč my — intro',  'Nejsme jen fotografové. Jsme vypravěči příběhů.', 31);
block($pdo, $pidHome, 'why_1', 'html', 'Důvod 1', '<span class="feat-num">01</span><h3>Osobní přístup</h3><p>Každý klient je pro nás jedinečný. Přátelský tým, který naslouchá vašim přáním a pomůže i s kompozicí.</p>', 32);
block($pdo, $pidHome, 'why_2', 'html', 'Důvod 2', '<span class="feat-num">02</span><h3>Zkušený tým</h3><p>Více než 8 let zkušeností, profesionální vybavení a neustálé vzdělávání. Investujeme do kvality pro vás.</p>', 33);
block($pdo, $pidHome, 'why_3', 'html', 'Důvod 3', '<span class="feat-num">03</span><h3>5 let zálohy zdarma</h3><p>Vaše fotografie a videa bezpečně zálohujeme po dobu 5 let. Nikdy o vzpomínky nepřijdete.</p>', 34);
block($pdo, $pidHome, 'why_4', 'html', 'Důvod 4', '<span class="feat-num">04</span><h3>Celá ČR i zahraničí</h3><p>Sídlíme v Olomouci, ale fotografujeme po celé České republice i v zahraničí. Přijedeme za vámi.</p>', 35);

block($pdo, $pidHome, 'portfolio_title', 'text',     'Práce — nadpis',    'To nejlepší od nás', 40);
block($pdo, $pidHome, 'portfolio_intro', 'textarea', 'Práce — intro',     'Svatební filmy, klipy a reportáže — podívejte se jak pracujeme.', 41);
block($pdo, $pidHome, 'portfolio_btns',  'html',     'Práce — tlačítka',  '<a href="/nase-prace" class="btn-g">V&#353;echny pr&#225;ce</a>', 46);
block($pdo, $pidHome, 'portfolio-tiles', 'html', 'Naše práce — dlaždice', '', 38);

block($pdo, $pidHome, 'reviews_title', 'text',     'Recenze — nadpis', 'Co říkají naši klienti', 50);
block($pdo, $pidHome, 'reviews_intro', 'textarea', 'Recenze — intro',  'Přes 150 spokojených klientů — jejich slova mluví za nás.', 51);
block($pdo, $pidHome, 'review_1', 'html', 'Recenze 1', '<div class="rev-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div><blockquote>"Kluci jsou naprostí profesionálové. Jsou to opravdu přátelští borci, kteří vám udělají fotky a videa podle vašich představ a ještě líp. Za mě TOP ve svém oboru. Děkujeme, že jste mohli být na naší svatbě!"</blockquote><div class="rev-name">Jan Jüngling</div><div class="rev-type">Svatba M+H</div>', 52);
block($pdo, $pidHome, 'review_2', 'html', 'Recenze 2', '<div class="rev-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div><blockquote>"Super domluva, milý a profesionální přístup k focení. Zdena nám nafotil svatební obřad s poobřadním focením v zámeckém parku. Z kvality fotek jsme nadšení a byly dodány dříve než jsme byli domluveni. Velká spokojenost. Doporučuji."</blockquote><div class="rev-name">Václav Konopka</div><div class="rev-type">Svatební focení</div>', 53);
block($pdo, $pidHome, 'review_3', 'html', 'Recenze 3', '<div class="rev-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div><blockquote>"Moc doporučuji, pan fotograf nám vyčaroval ty nejkrásnější rodinné fotky i s dětmi, co neměly zrovna fotící náladu. Profesionální přístup a špičková kvalita. Už se těším na další focení!"</blockquote><div class="rev-name">Jana Dudková</div><div class="rev-type">Rodinné focení</div>', 54);
block($pdo, $pidHome, 'reviews_cta', 'html', 'Recenze — tlačítko', '<a href="https://www.facebook.com/rbvideosandphotos/reviews" target="_blank" rel="noopener" class="btn-od">Všechny recenze na Facebooku</a>', 55);

block($pdo, $pidHome, 'pricing_title', 'text',     'Ceník — nadpis',   'Transparentní ceník bez překvapení', 70);
block($pdo, $pidHome, 'pricing_intro', 'textarea', 'Ceník — intro',   'Každá zakázka je individuální. Níže najdete orientační ceny — přesnou nabídku připravíme vždy na míru.', 71);
block($pdo, $pidHome, 'pricing_body',  'html',     'Ceník — obsah',   '', 72);
block($pdo, $pidHome, 'pricing_cta',   'html',     'Ceník — tlačítko', '<a href="/cenik" class="btn-g">Cel&#253; cen&#237;k</a>', 73);

block($pdo, $pidHome, 'blog_title', 'text',     'Blog — nadpis', 'Tipy a inspirace', 80);
block($pdo, $pidHome, 'blog_intro', 'textarea', 'Blog — intro',  'Rady od profesionálního fotografa — jak se připravit, co očekávat a jak dostat ty nejkrásnější snímky.', 81);
block($pdo, $pidHome, 'blog_1',     'html',     'Článek 1',      '', 82);
block($pdo, $pidHome, 'blog_2',     'html',     'Článek 2',      '', 83);
block($pdo, $pidHome, 'blog_3',     'html',     'Článek 3',      '', 84);
block($pdo, $pidHome, 'blog_cta',   'html',     'Blog — tlačítko', '<a href="/blog" class="btn-od">V&#353;echny &#269;l&#225;nky</a>', 85);

block($pdo, $pidHome, 'faq_title', 'text',     'FAQ — nadpis', 'Nejčastější otázky', 90);
block($pdo, $pidHome, 'faq_intro', 'textarea', 'FAQ — intro',  'Vše, co potřebujete vědět před rezervací.', 91);
block($pdo, $pidHome, 'faq_1',     'html',     'FAQ 1',        '<div class="faq-q">Kdy je nejlepší rezervovat termín svatby?</div><div class="faq-a">Ideálně co nejdříve po potvrzení data a místa svatby. U víkendových termínů ve svatební sezóně doporučuji rezervaci několik měsíců dopředu.</div>', 92);
block($pdo, $pidHome, 'faq_2',     'html',     'FAQ 2',        '<div class="faq-q">Kolik fotografií dostaneme?</div><div class="faq-a">Záleží na délce focení a zvoleném balíčku. U menších svateb obvykle předávám nižší stovky fotografií, u celodenních svateb několik stovek upravených snímků.</div>', 93);
block($pdo, $pidHome, 'faq_3',     'html',     'FAQ 3',        '<div class="faq-q">Za jak dlouho dostaneme hotové fotografie?</div><div class="faq-a">Obvyklá doba dodání je podle rozsahu zakázky. Menší focení bývá hotové rychleji, u větších svateb a kombinace foto + video je potřeba počítat s delší postprodukcí.</div>', 94);
block($pdo, $pidHome, 'faq_cta',   'html',     'FAQ — tlačítko', '<a href="/faq" class="btn-od">V&#353;echny ot&#225;zky</a>', 95);

block($pdo, $pidHome, 'cta_title',    'text',     'CTA — nadpis',     'Pojďme vytvořit něco krásného', 60);
block($pdo, $pidHome, 'cta_sub',      'textarea', 'CTA — podnadpis',  'Nezávazně se nám ozvěte — rádi odpovíme na vaše otázky a připravíme nabídku na míru pro váš den.', 61);
block($pdo, $pidHome, 'contact_info', 'html',     'Kontakt — info',   '', 62);
block($pdo, $pidHome, 'contact_card', 'html',     'Kontakt — karta',  '<h3>Ozvěte se nám</h3><p class="cta-r-sub">Zavolejte nebo napište na sociální sítě. Odpovídáme rychle!</p><a href="tel:792483583" class="cta-btn cta-btn--gold">Zavolat: 792&nbsp;483&nbsp;583</a><a href="tel:604225032" class="cta-btn cta-btn--dark">Zavolat: 604&nbsp;225&nbsp;032</a><a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener" class="cta-btn cta-btn--dark">Instagram</a><a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener" class="cta-btn cta-btn--dark">Facebook</a>', 63);

// ─── Content blocks: Ceník ───────────────────────────────────────────────────

// Detailni svatebni balicky (self-contained styl, robustni napric tematy).
$pricingPackages = <<<'HTML'
<style>
.ckp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px;margin:8px 0 40px}
.ckp-card{position:relative;display:flex;flex-direction:column;background:#fff;border:1px solid #e7e0d6;border-radius:14px;padding:30px 26px;box-shadow:0 10px 30px rgba(48,38,30,.06)}
.ckp-card--featured{border-color:#c4963c;box-shadow:0 16px 40px rgba(196,150,60,.18)}
.ckp-badge{position:absolute;top:-12px;left:26px;background:#c4963c;color:#fff;font-size:.66rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;padding:5px 12px;border-radius:999px}
.ckp-title{font-size:1.3rem;font-weight:800;color:#1a1208;margin:0 0 6px}
.ckp-price{font-size:1.5rem;font-weight:800;color:#c4963c;margin:0 0 14px}
.ckp-desc{font-size:.92rem;color:#5f5143;line-height:1.65;margin:0 0 18px}
.ckp-feats{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:9px}
.ckp-feats li{position:relative;padding-left:22px;font-size:.9rem;color:#3a2f24;line-height:1.5}
.ckp-feats li::before{content:"\2713";position:absolute;left:0;top:0;color:#c4963c;font-weight:800}
@media(max-width:640px){.ckp-grid{grid-template-columns:1fr}}
</style>
<div class="ckp-grid">
  <div class="ckp-card">
    <h3 class="ckp-title">Základní svatba</h3>
    <div class="ckp-price">od 14 900 Kč</div>
    <p class="ckp-desc">Pro menší svatby, obřad, gratulace, skupinové fotografie a krátké focení novomanželů.</p>
    <ul class="ckp-feats">
      <li>focení cca 3 hodiny</li>
      <li>obřad, gratulace, skupinové fotografie</li>
      <li>krátké párové focení novomanželů</li>
      <li>minimálně 100 upravených fotografií</li>
      <li>online galerie</li>
      <li>předání fotek ke stažení</li>
      <li>archivace hotových výstupů na 2 roky</li>
    </ul>
  </div>
  <div class="ckp-card ckp-card--featured">
    <span class="ckp-badge">Nejoblíbenější</span>
    <h3 class="ckp-title">Standardní svatba</h3>
    <div class="ckp-price">od 24 900 Kč</div>
    <p class="ckp-desc">Nejoblíbenější volba pro většinu párů. Pokryje hlavní část svatebního dne od příprav po první tanec nebo večerní program.</p>
    <ul class="ckp-feats">
      <li>focení cca 8 hodin</li>
      <li>přípravy, obřad, gratulace, skupinky</li>
      <li>párové focení novomanželů</li>
      <li>hostina, detaily a atmosféra dne</li>
      <li>minimálně 300–500 upravených fotografií</li>
      <li>svatební video / sestřih dne</li>
      <li>online galerie</li>
      <li>konzultace před svatbou</li>
      <li>archivace hotových výstupů na 2 roky</li>
    </ul>
  </div>
  <div class="ckp-card">
    <h3 class="ckp-title">Prémiová svatba</h3>
    <div class="ckp-price">od 34 900 Kč</div>
    <p class="ckp-desc">Kompletní zachycení celého svatebního dne bez spěchu a kompromisů.</p>
    <ul class="ckp-feats">
      <li>focení cca 10–12 hodin</li>
      <li>celý den od příprav až po večerní zábavu</li>
      <li>větší počet upravených fotografií</li>
      <li>detailní zachycení atmosféry, hostů a momentů</li>
      <li>svatební video + krátký sestřih / reels v ceně</li>
      <li>online galerie a video ke stažení</li>
      <li>konzultace před svatbou</li>
      <li>prioritní zpracování</li>
      <li>archivace hotových výstupů na 5 let</li>
    </ul>
  </div>
</div>
HTML;

block($pdo, $pidCenik, 'hero_heading',            'text',     'Hero Nadpis',       'Ceník fotografických a video služeb — Olomouc', 1);
block($pdo, $pidCenik, 'hero_intro',              'textarea', 'Hero Intro',        'Transparentní ceny bez překvapení. Každá zakázka je individuální — vždy se domluvíme na balíčku přesně pro vás. Přes 150 spokojených klientů, 8 let praxe.', 2);
block($pdo, $pidCenik, 'pricing_weddings_heading','text',     'Ceník Svatby',      'Svatební balíčky', 3);
block($pdo, $pidCenik, 'pricing_weddings_sub',    'textarea', 'Ceník Svatby Info', 'Vyberte si balíček podle rozsahu svatebního dne. Každá zakázka je individuální — finální nabídku připravíme vždy na míru. Ceny jsou orientační, „od".', 4);
block($pdo, $pidCenik, 'pricing_packages',        'html',     'Svatební balíčky',  $pricingPackages, 5);
block($pdo, $pidCenik, 'pricing_other_heading',   'text',     'Ceník Ostatní',     'Rodinné, portrétní a firemní focení', 5);
block($pdo, $pidCenik, 'pricing_other_sub',       'textarea', 'Ceník Ostatní Info','Rodinné focení od 4 500 Kč · Portrétní focení od 3 000 Kč · Firemní focení od 6 000 Kč · Zásnuby & Maternity od 3 500 Kč · Videografie od 8 000 Kč.', 6);
block($pdo, $pidCenik, 'cta_heading',             'text',     'CTA Nadpis',        'Rezervujte termín ještě dnes', 7);
block($pdo, $pidCenik, 'cta_sub',                 'textarea', 'CTA Sub',           'Oblíbené soboty se obsazují rok dopředu — čím dříve, tím větší výběr. Zavolejte nebo napište, odpovíme do 24 hodin.', 8);

// Balíčky — renderuje buildPricingHtml() na homepage a cenik stránce
block($pdo, $pidCenik, 'cenik_card_1_title', 'text',     'Karta 1 — nadpis', 'Základní', 11);
block($pdo, $pidCenik, 'cenik_card_1_desc',  'textarea', 'Karta 1 — popis',
'Menší svatba, obřad, skupinky a krátké focení novomanželů.

Archivace: 2 roky', 12);
block($pdo, $pidCenik, 'cenik_card_1_price', 'text',     'Karta 1 — cena',  'od 14 900 Kč', 13);

block($pdo, $pidCenik, 'cenik_card_2_title', 'text',     'Karta 2 — nadpis', 'Standardní', 14);
block($pdo, $pidCenik, 'cenik_card_2_desc',  'textarea', 'Karta 2 — popis',
'Nejoblíbenější balíček pro hlavní část svatebního dne.

Archivace: 2 roky', 15);
block($pdo, $pidCenik, 'cenik_card_2_price', 'text',     'Karta 2 — cena',  'od 24 900 Kč', 16);

block($pdo, $pidCenik, 'cenik_card_3_title', 'text',     'Karta 3 — nadpis', 'Prémiový', 17);
block($pdo, $pidCenik, 'cenik_card_3_desc',  'textarea', 'Karta 3 — popis',
'Celodenní zachycení svatby s rozšířenou péčí a delší archivací.

Archivace: 5 let', 18);
block($pdo, $pidCenik, 'cenik_card_3_price', 'text',     'Karta 3 — cena',  'od 34 900 Kč', 19);

// ─── Content blocks: FAQ ─────────────────────────────────────────────────────

$faqBody = <<<'HTML'
<div class="faq-item"><div class="faq-q">Kdy je nejlepší rezervovat termín svatby?</div><div class="faq-a">Ideálně co nejdříve po potvrzení data a místa svatby. U víkendových termínů ve svatební sezóně doporučuji rezervaci několik měsíců dopředu.</div></div>
<div class="faq-item"><div class="faq-q">Je možné nafotit jen obřad?</div><div class="faq-a">Ano. Pro menší svatby nebo pouze obřad je vhodný základní balíček. Zahrnuje obřad, gratulace, skupinové fotografie a krátké focení novomanželů.</div></div>
<div class="faq-item"><div class="faq-q">Kolik fotografií dostaneme?</div><div class="faq-a">Záleží na délce focení a zvoleném balíčku. U menších svateb obvykle předávám nižší stovky fotografií, u celodenních svateb několik stovek upravených snímků.</div></div>
<div class="faq-item"><div class="faq-q">Za jak dlouho dostaneme hotové fotografie?</div><div class="faq-a">Obvyklá doba dodání je podle rozsahu zakázky. Menší focení bývá hotové rychleji, u větších svateb a kombinace foto + video je potřeba počítat s delší postprodukcí.</div></div>
<div class="faq-item"><div class="faq-q">Jak nám budou fotografie předány?</div><div class="faq-a">Hotové fotografie předávám online formou galerie nebo odkazu ke stažení. Díky tomu si je můžete jednoduše stáhnout a sdílet s rodinou nebo hosty.</div></div>
<div class="faq-item"><div class="faq-q">Jak dlouho archivujete hotové výstupy?</div><div class="faq-a">Podle zvoleného balíčku archivuji finálně předané výstupy po dobu 2 nebo 5 let. Archivace se vztahuje na hotové předané fotografie a videa, ne na všechny surové záznamy.</div></div>
<div class="faq-item"><div class="faq-q">Dostaneme i neupravené fotografie nebo RAW soubory?</div><div class="faq-a">Standardně předávám pouze vybrané a upravené fotografie. Neupravené fotografie ani RAW soubory nejsou běžnou součástí předání.</div></div>
<div class="faq-item"><div class="faq-q">Fotíte i video?</div><div class="faq-a">Ano. Kromě fotografování je možné domluvit také video výstup, krátký sestřih nebo reels/shorts verze pro sociální sítě.</div></div>
<div class="faq-item"><div class="faq-q">Je doprava v ceně?</div><div class="faq-a">Doprava záleží na místě konání svatby nebo akce. U každé poptávky ji řeším individuálně podle lokality.</div></div>
<div class="faq-item"><div class="faq-q">Jak probíhá rezervace termínu?</div><div class="faq-a">Po odeslání poptávky si společně upřesníme datum, místo, rozsah focení nebo natáčení a požadované výstupy. Poté připravím konkrétní nabídku.</div></div>
<div class="faq-item"><div class="faq-q">Musíme umět pózovat?</div><div class="faq-a">Ne. Většinu dne fotím přirozeně a reportážně. Při párovém focení vás navedu tak, aby fotky působily přirozeně a nebyly strojené.</div></div>
<div class="faq-item"><div class="faq-q">Je možné upravit balíček na míru?</div><div class="faq-a">Ano. Každá svatba nebo akce je jiná, proto je možné rozsah focení, videa, počet hodin i typ výstupů upravit individuálně.</div></div>
HTML;

block($pdo, $pidFaq, 'hero_heading', 'text',     'Hero Nadpis', 'Často kladené otázky', 1);
block($pdo, $pidFaq, 'hero_sub',     'textarea', 'Hero Sub',    'Vše, co potřebujete vědět před rezervací — termíny, fotografie, video, předání i archivace.', 2);
block($pdo, $pidFaq, 'faq_body',     'html',     'FAQ — otázky', $faqBody, 3);
block($pdo, $pidFaq, 'faq_footer',   'textarea', 'FAQ — patička', 'Nenašli jste odpověď na svou otázku? Napište mi a připravím vám konkrétní nabídku podle termínu, místa, rozsahu focení nebo natáčení a požadovaných výstupů.', 4);

// ─── Content blocks: Blog ────────────────────────────────────────────────────

block($pdo, $pidBlog, 'hero_heading', 'text',     'Hero Nadpis', 'Tipy a inspirace', 1);
block($pdo, $pidBlog, 'hero_sub',     'textarea', 'Hero Sub',    'Rady od profesionálního fotografa — příprava na focení, tipy pro klienty a pohled do naší práce.', 2);


// ─── Content blocks: Služby ───────────────────────────────────────────────────

block($pdo, $pidSluzby, 'hero_heading', 'text',     'Hero Nadpis', 'Fotografie a videografie na míru', 1);
block($pdo, $pidSluzby, 'hero_sub',     'textarea', 'Hero Sub',    'Ať jde o svatbu, portrét nebo firemní projekt — jsme tu pro vás. Profesionální výsledky, přátelský přístup.', 2);
block($pdo, $pidSluzby, 'service_1', 'html', 'Služba 1', '<span class="svc-num">01</span><h3>Svatební foto &amp; video</h3><p>Zachytíme každý dotek, každý pohled, každou slzu štěstí. Přirozené reportážní fotografie a filmové video, které vám budou vyprávět příběh vašeho dne po celý život.</p>', 10);
block($pdo, $pidSluzby, 'service_2', 'html', 'Služba 2', '<span class="svc-num">02</span><h3>Rodinné &amp; portrétní focení</h3><p>Rodinné vzpomínky, těhotenské focení, křtiny nebo portréty. Přirozené, nenucené a krásné snímky, na které budete hrdí.</p>', 11);
block($pdo, $pidSluzby, 'service_3', 'html', 'Služba 3', '<span class="svc-num">03</span><h3>Reklamní foto &amp; video</h3><p>Profesionální vizuální obsah pro firmy, kapely, produkty nebo brand. Videoklipy, firemní prezentace a obsah, který prodává.</p>', 12);
block($pdo, $pidSluzby, 'service_4', 'html', 'Služba 4', '<span class="svc-num">04</span><h3>Akce &amp; události</h3><p>Konference, kulturní akce, plesy, narozeniny. Každá událost si zaslouží být profesionálně zdokumentována.</p>', 13);
block($pdo, $pidSluzby, 'cta_heading', 'text',     'CTA Nadpis', 'Připravme nezapomenutelný den', 20);
block($pdo, $pidSluzby, 'cta_sub',     'textarea', 'CTA Sub',    'Zavolejte nebo napište — rádi odpovíme na vaše otázky a připravíme nabídku na míru.', 21);

// ─── Content blocks: Kontakt ──────────────────────────────────────────────────

block($pdo, $pidKontakt, 'hero_heading',   'text',     'Hero Nadpis',        'Kontaktujte nás', 1);
block($pdo, $pidKontakt, 'hero_sub',       'textarea', 'Hero Sub',           'Zavolejte nebo napište na sociální sítě. Odpovídáme rychle — oblíbené termíny se obsazují rok dopředu.', 2);
block($pdo, $pidKontakt, 'contact_heading','text',     'Kontakt — nadpis',   'Ozvěte se nám', 3);
block($pdo, $pidKontakt, 'contact_info',   'html',     'Kontakt — info',     '<div class="cta-info"><p><strong>792&nbsp;483&nbsp;583</strong></p><p><strong>604&nbsp;225&nbsp;032</strong></p><p>Olomouc a celá ČR</p><p><a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener">Facebook: RB Videos &amp; Photos</a></p><p><a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener">Instagram: @rb_videos_photos</a></p><p><a href="https://www.youtube.com/@rbvideosandphotos" target="_blank" rel="noopener">YouTube kanál</a></p></div>', 4);
block($pdo, $pidKontakt, 'contact_card',   'html',     'Kontakt — karta',    '<h3>Ozvěte se nám</h3><p class="cta-r-sub">Zavolejte nebo napište na sociální sítě. Odpovídáme rychle!</p><a href="tel:792483583" class="cta-btn cta-btn--gold">Zavolat: 792&nbsp;483&nbsp;583</a><a href="tel:604225032" class="cta-btn cta-btn--dark">Zavolat: 604&nbsp;225&nbsp;032</a><a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener" class="cta-btn cta-btn--dark">Instagram</a><a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener" class="cta-btn cta-btn--dark">Facebook</a>', 5);

// ─── SEO settings ─────────────────────────────────────────────────────────────

$pdo->exec("DELETE FROM reviews WHERE site_id = {$siteId}");
$reviewStmt = $pdo->prepare('INSERT INTO reviews (site_id, author_name, context_label, rating, review_text, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
$reviewStmt->execute([$siteId, 'Jan Jungling', 'Svatba M+H', 5, 'Kluci jsou naprosti profesionalove. Jsou to pratelski borci, kteri umi zachytit emoce a dodat fotky i video na vysoke urovni.', 1, 1]);
$reviewStmt->execute([$siteId, 'Vaclav Konopka', 'Svatebni foceni', 5, 'Super domluva, mily a profesionalni pristup k foceni. Velka spokojenost.', 2, 1]);
$reviewStmt->execute([$siteId, 'Jana Dudkova', 'Rodinne foceni', 5, 'Moc doporucuji. Profesionalni pristup a spickova kvalita. Uz se tesime na dalsi foceni.', 3, 1]);

$d = 'https://rbvideosphotos.kvalitne.cz';

seo($pdo, $pidHome, [
    'title'       => 'Fotograf & Videograf Olomouc | RB Photos & Videos — Svatby, Rodiny, Akce',
    'desc'        => 'Profesionální fotograf a videograf Olomouc. Svatební fotografie a video, rodinné focení, portréty. 8+ let praxe, 150+ spokojených klientů, zálohy 5 let zdarma. Volejte: 792 483 583.',
    'kw'          => 'fotograf Olomouc, svatební fotograf Olomouc, videograf Olomouc, rodinné focení Olomouc',
    'canonical'   => '/',
    'og_title'    => 'Fotograf & Videograf Olomouc | RB Photos & Videos',
    'og_desc'     => 'Profesionální fotograf a videograf Olomouc. Svatební foto a video, rodinné focení, portréty. 8+ let praxe. ☎ 792 483 583',
    'schema_type' => 'LocalBusiness',
    'schema_json' => '{"@context":"https://schema.org","@type":"LocalBusiness","name":"RB Photos & Videos","url":"' . $d . '/","telephone":["+420792483583","+420604225032"],"address":{"@type":"PostalAddress","addressLocality":"Olomouc","addressCountry":"CZ"},"aggregateRating":{"@type":"AggregateRating","ratingValue":"5","reviewCount":"150","bestRating":"5"},"sameAs":["https://www.facebook.com/rbvideosandphotos","https://www.instagram.com/rb_videos_photos/"]}',
]);

seo($pdo, $pidCenik, [
    'title'     => 'Ceník fotografa Olomouc | RB Photos & Videos — Svatby, Rodiny, Portréty',
    'desc'      => 'Ceník fotografa a videografa v Olomouci. Balíčky pro svatební focení od 13 000 Kč, rodinné focení od 4 500 Kč. Individuální nabídka na míru. ☎ 792 483 583.',
    'kw'        => 'ceník fotografa Olomouc, cena svatební fotografie Olomouc, kolik stojí fotograf Olomouc',
    'canonical' => '/cenik',
]);

seo($pdo, $pidFaq, [
    'title'     => 'Časté otázky | Fotograf Olomouc — RB Photos & Videos',
    'desc'      => 'Odpovědi na nejčastější otázky o focení v Olomouci. Ceny, dodání fotek, příprava na focení, svatby, rodinné focení i videa.',
    'kw'        => 'fotograf Olomouc otázky, kolik stojí fotograf Olomouc, jak se připravit na focení',
    'canonical' => '/faq',
]);

seo($pdo, $pidBlog, [
    'title'     => 'Blog | RB Photos & Videos — Fotograf Olomouc',
    'desc'      => 'Tipy a inspirace od profesionálního fotografa z Olomouce. Příprava na svatební focení, rodinné focení s dětmi a další rady.',
    'canonical' => '/blog',
]);

seo($pdo, $pidSluzby, [
    'title'     => 'Naše služby | RB Photos & Videos — Fotograf Olomouc',
    'desc'      => 'Svatební fotografie a video, rodinné focení, portréty, reklamní foto a akce. Profesionální fotograf a videograf z Olomouce. Volejte: 792 483 583.',
    'kw'        => 'fotograf Olomouc služby, svatební fotograf, videograf, rodinné focení',
    'canonical' => '/sluzby',
]);

seo($pdo, $pidKontakt, [
    'title'     => 'Kontakt | RB Photos & Videos — Fotograf Olomouc',
    'desc'      => 'Kontaktujte nás — volejte 792 483 583 nebo napište na sociální sítě. Olomouc a celá ČR.',
    'kw'        => 'kontakt fotograf Olomouc, rezervace termínu fotograf',
    'canonical' => '/kontakt',
]);


// ─── FAQ items (tabulka faq_items — zdroj pro /faq stranku) ───────────────────

$pdo->prepare('DELETE FROM faq_items WHERE site_id = ?')->execute([$siteId]);
$faqItems = [
    ['Kdy je nejlepší rezervovat termín svatby?', 'Ideálně co nejdříve po potvrzení data a místa svatby. U víkendových termínů ve svatební sezóně doporučuji rezervaci několik měsíců dopředu.'],
    ['Je možné nafotit jen obřad?', 'Ano. Pro menší svatby nebo pouze obřad je vhodný základní balíček. Zahrnuje obřad, gratulace, skupinové fotografie a krátké focení novomanželů.'],
    ['Kolik fotografií dostaneme?', 'Záleží na délce focení a zvoleném balíčku. U menších svateb obvykle předávám nižší stovky fotografií, u celodenních svateb několik stovek upravených snímků.'],
    ['Za jak dlouho dostaneme hotové fotografie?', 'Obvyklá doba dodání je podle rozsahu zakázky. Menší focení bývá hotové rychleji, u větších svateb a kombinace foto + video je potřeba počítat s delší postprodukcí.'],
    ['Jak nám budou fotografie předány?', 'Hotové fotografie předávám online formou galerie nebo odkazu ke stažení. Díky tomu si je můžete jednoduše stáhnout a sdílet s rodinou nebo hosty.'],
    ['Jak dlouho archivujete hotové výstupy?', 'Podle zvoleného balíčku archivuji finálně předané výstupy po dobu 2 nebo 5 let. Archivace se vztahuje na hotové předané fotografie a videa, ne na všechny surové záznamy.'],
    ['Dostaneme i neupravené fotografie nebo RAW soubory?', 'Standardně předávám pouze vybrané a upravené fotografie. Neupravené fotografie ani RAW soubory nejsou běžnou součástí předání.'],
    ['Fotíte i video?', 'Ano. Kromě fotografování je možné domluvit také video výstup, krátký sestřih nebo reels/shorts verze pro sociální sítě.'],
    ['Je doprava v ceně?', 'Doprava záleží na místě konání svatby nebo akce. U každé poptávky ji řeším individuálně podle lokality.'],
    ['Jak probíhá rezervace termínu?', 'Po odeslání poptávky si společně upřesníme datum, místo, rozsah focení nebo natáčení a požadované výstupy. Poté připravím konkrétní nabídku.'],
    ['Musíme umět pózovat?', 'Ne. Většinu dne fotím přirozeně a reportážně. Při párovém focení vás navedu tak, aby fotky působily přirozeně a nebyly strojené.'],
    ['Je možné upravit balíček na míru?', 'Ano. Každá svatba nebo akce je jiná, proto je možné rozsah focení, videa, počet hodin i typ výstupů upravit individuálně.'],
];
$faqStmt = $pdo->prepare('INSERT INTO faq_items (site_id, question, answer, sort_order, is_active) VALUES (?, ?, ?, ?, 1)');
$faqOrder = 0;
foreach ($faqItems as [$faqQ, $faqA]) {
    $faqStmt->execute([$siteId, $faqQ, $faqA, ++$faqOrder]);
}
echo 'FAQ items seeded: ' . count($faqItems) . "\n";


// ─── Publish ──────────────────────────────────────────────────────────────────

$pub    = new PublishService();
$result = $pub->publishSite($siteId, $userId);
$errors = $result['errors'] ?? [];
echo 'publishSite done. Errors: ' . count($errors) . "\n";
foreach ($errors as $e) {
    echo '  ERROR: ' . $e . "\n";
}
echo "Seeding complete.\n";
