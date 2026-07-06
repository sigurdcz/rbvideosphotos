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

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Helpers Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

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

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Site Layout Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬
// Sdileny layout Ă˘â‚¬â€ť nav + paticka + HTML obal pro vsechny stranky.
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

// Nav Ă˘â‚¬â€ť homepage: hash anchory pro One Page smooth scroll
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

// Nav Ă˘â‚¬â€ť subpages: prime URL na jednotlive stranky
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

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Subpage base CSS (CenÄ‚Â­k, FAQ, Blog, clanky) Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬
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

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Site + User Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

$gtmNoscript = '<noscript><iframe class="gtm-noscript" src="https://www.googletagmanager.com/ns.html?id=GTM-542MMSL" height="0" width="0"></iframe></noscript>';

$userId = upsertUser($pdo, 'admin@rbphotos.cz', 'admin123');
$siteId = upsertSite($pdo, 'RB Photos & Videos', 'https://rbvideosphotos.kvalitne.cz', 'G-BD4BS9R4JZ', 'GTM-542MMSL', $gtmNoscript, $layoutHtml, $layoutCss, $layoutJs, $layoutNavHome, $layoutNavSub);

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Template: Homepage Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬
// Pouze obsah stranky Ă˘â‚¬â€ť bez <html>/<head>/<body>/nav/footer (ty jsou v layoutu).
// body{padding-top:0} potlaci 60px odsazeni z layoutu Ă˘â‚¬â€ť hero se roztahne pres cely viewport.

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
/* Hero plres cely viewport Ă˘â‚¬â€ť potlaci padding-top:60px z layoutu */
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

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Template: CenÄ‚Â­k Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

$htmlCenik = <<<'HTML'
<section class="page-hero">
  <div class="container">
    <h1 data-block="hero_heading"></h1>
    <p data-block="hero_intro" class="hero-sub"></p>
  </div>
</section>
<section class="section">
  <div class="container">
    <h2 data-block="pricing_weddings_heading"></h2>
    <p data-block="pricing_weddings_sub"></p>
    <div data-block="pricing_packages"></div>
    <h2 data-block="pricing_other_heading"></h2>
    <p data-block="pricing_other_sub"></p>
  </div>
</section>
<section class="section section-light">
  <div class="container">
    <h2 data-block="cta_heading"></h2>
    <p data-block="cta_sub"></p>
    <a href="tel:792483583" class="btn">&#128222; 792&#160;483&#160;583</a>
  </div>
</section>
HTML;

$tplCenik = tpl($pdo, $siteId, 'CenÄ‚Â­k', $htmlCenik, $subpageCss, $subpageJs);

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Template: FAQ Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

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

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Template: Blog listing Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

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

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Template: Blog article Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

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

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Template: SluÄąÄľby Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

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

$tplSluzby = tpl($pdo, $siteId, 'SluÄąÄľby', $htmlSluzby, $serviceCss, $subpageJs);

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Template: Kontakt Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

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

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Pages Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

$pidHome    = addPage($pdo, $siteId, $tplHomeFull, 'Svatební foto a video Olomouc, Morava | RB Videos Photos',                         '',                               '/');
$pidSluzby  = addPage($pdo, $siteId, $tplSluzby,  'Fotograf & Videograf Olomouc Ă˘â‚¬â€ť naÄąË‡e sluÄąÄľby | RB Photos & Videos',            'sluzby',                         '/sluzby');
$pidCenik   = addPage($pdo, $siteId, $tplCenik,   'CenÄ‚Â­k fotografa Olomouc | RB Photos & Videos',                              'cenik',                          '/cenik');
$pidFaq     = addPage($pdo, $siteId, $tplFaq,     'Ă„ĹšastÄ‚Â© otÄ‚Ë‡zky | Fotograf Olomouc Ă˘â‚¬â€ť RB Photos & Videos',                      'faq',                            '/faq');
$pidBlog    = addPage($pdo, $siteId, $tplBlog,    'Blog | RB Videos Photos',                                                 'blog',                           '/blog');
$pidKontakt = addPage($pdo, $siteId, $tplKontakt, 'Kontakt | Fotograf Olomouc Ă˘â‚¬â€ť RB Photos & Videos',                           'kontakt',                        '/kontakt');
// Remove old CMS article pages Ă˘â‚¬â€ť now served as blog_posts
$pdo->prepare("DELETE FROM pages WHERE site_id=? AND slug IN ('priprava-na-svatebni-foceni','rodinne-foceni-s-detmi')")
    ->execute([$siteId]);

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Placeholder media Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬
echo "Fetching placeholder images...\n";
$imgBlog1 = fetchMedia($pdo, $siteId, 'https://placehold.co/800x500/1a1a2e/c4963c.png', 'blog-1', 'PÄąâ„˘Ä‚Â­prava na svatebnÄ‚Â­ focenÄ‚Â­');
$imgBlog2 = fetchMedia($pdo, $siteId, 'https://placehold.co/800x500/0e1a0e/c4963c.png', 'blog-2', 'RodinnÄ‚Â© focenÄ‚Â­ s dĂ„â€ştmi');
$imgBlog3 = fetchMedia($pdo, $siteId, 'https://placehold.co/800x500/1a0e0e/c4963c.png', 'blog-3', 'Jak vybrat fotografa');
$imgPf1   = fetchMedia($pdo, $siteId, 'https://placehold.co/900x600/0a0a1a/c4963c.png', 'pf-1',   'Svatba Ă˘â‚¬â€ť obÄąâ„˘ad');
$imgPf2   = fetchMedia($pdo, $siteId, 'https://placehold.co/900x600/111827/e5c07b.png', 'pf-2',   'Svatba Ă˘â‚¬â€ť pÄ‚Ë‡r');
$imgPf3   = fetchMedia($pdo, $siteId, 'https://placehold.co/900x600/1a1008/c4963c.png', 'pf-3',   'Svatba Ă˘â‚¬â€ť detail');
echo "Done.\n";

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Blog posts Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬
$art1Content = '<h2>1. Konzultace s fotografem Ă˘â‚¬â€ť co nejdÄąâ„˘Ä‚Â­ve</h2><p>OsobnÄ‚Â­ schÄąĹ»zka jeÄąË‡tĂ„â€ş pÄąâ„˘ed svatbou je zÄ‚Ë‡klad. Promluvte si o mÄ‚Â­stĂ„â€ş obÄąâ„˘adu, harmonogramu a vaÄąË‡ich pÄąâ„˘Ä‚Ë‡nÄ‚Â­ch. DobrÄ‚Ëť fotograf vÄ‚Ë‡m pomÄąĹ»ÄąÄľe sestavit realistickÄ‚Ëť Ă„Ĺ¤asovÄ‚Ëť plÄ‚Ë‡n a doporuĂ„Ĺ¤Ä‚Â­ nejlepÄąË‡Ä‚Â­ mÄ‚Â­sta pro pÄ‚Ë‡rovÄ‚Â© fotografie.</p><h2>2. NaplÄ‚Ë‡nujte harmonogram s rezervou</h2><p>PodceÄąÂovÄ‚Ë‡nÄ‚Â­ Ă„Ĺ¤asu je nejĂ„Ĺ¤astĂ„â€şjÄąË‡Ä‚Â­ chyba. Vlasy a make-up trvajÄ‚Â­ dÄ‚Â©le, hostÄ‚Â© pÄąâ„˘ichÄ‚Ë‡zejÄ‚Â­ postupnĂ„â€ş a skupinovÄ‚Â© fotografie s celou rodinou zaberou 20Ă˘â‚¬â€ś30 minut. VÄąÄľdy poĂ„Ĺ¤Ä‚Â­tejte se zÄ‚Ë‡lohou.</p><h2>3. PÄąâ„˘ipravte detaily s lÄ‚Ë‡skou</h2><p>Prsteny, kytice, pozvÄ‚Ë‡nky, ÄąË‡perky, parfÄ‚Â©m Ă˘â‚¬â€ť tyto detaily dĂ„â€şlajÄ‚Â­ snÄ‚Â­mky vÄ‚ËťjimeĂ„Ĺ¤nÄ‚Ëťmi. PÄąâ„˘ipravte je rÄ‚Ë‡no na neutrÄ‚Ë‡lnÄ‚Â­ povrch dÄąâ„˘Ä‚Â­v, neÄąÄľ zaĂ„Ĺ¤ne chaos pÄąâ„˘Ä‚Â­prav.</p><h2>4. VyuÄąÄľijte zlatou hodinu</h2><p>Hodina pÄąâ„˘ed zÄ‚Ë‡padem slunce je Ă„Ĺ¤as nejkrÄ‚Ë‡snĂ„â€şjÄąË‡Ä‚Â­ho svĂ„â€ştla Ă˘â‚¬â€ť teplÄ‚Â©ho, mĂ„â€şkkÄ‚Â©ho, kinematografickÄ‚Â©ho. V Olomouci mÄ‚Ë‡me nÄ‚Ë‡dhernÄ‚Ë‡ mÄ‚Â­sta pro zlatou hodinu Ă˘â‚¬â€ť naplÄ‚Ë‡nujte pÄ‚Ë‡rovÄ‚Â© focenÄ‚Â­ prÄ‚Ë‡vĂ„â€ş na tento Ă„Ĺ¤as.</p><h2>5. ZÄąĹ»staÄąÂte sami sebou</h2><p>Nehledejte do objektivu, hÄ‚Ëťbejte se, smĂ„â€şjte se, ÄąË‡eptejte si. ZkuÄąË‡enÄ‚Ëť fotograf zachytÄ‚Â­ skuteĂ„Ĺ¤nÄ‚Â© emoce Ă˘â‚¬â€ť ty se nedajÄ‚Â­ naplÄ‚Ë‡novat ani zreÄąÄľÄ‚Â­rovat.</p>';

$art2Content = '<h2>1. SprÄ‚Ë‡vnÄ‚Ëť Ă„Ĺ¤as je zÄ‚Ë‡klad</h2><p>S malÄ‚Ëťmi dĂ„â€ştmi je naĂ„Ĺ¤asovÄ‚Ë‡nÄ‚Â­ vÄąË‡e. FocenÄ‚Â­ plÄ‚Ë‡nujte na dobu, kdy jsou dĂ„â€şti nejvÄ‚Â­ce odpoĂ„Ĺ¤atÄ‚Â© Ă˘â‚¬â€ť typicky dopoledne po snÄ‚Â­dani nebo krÄ‚Ë‡tce po odpolednÄ‚Â­m spÄ‚Ë‡nku. Nikdy ne tĂ„â€şsnĂ„â€ş pÄąâ„˘ed jÄ‚Â­dlem nebo spanÄ‚Â­m.</p><h2>2. ObleĂ„Ĺ¤enÄ‚Â­ v harmonickÄ‚Ëťch barvÄ‚Ë‡ch</h2><p>NemusÄ‚Â­te mÄ‚Â­t vÄąË‡ichni stejnÄ‚Â© triĂ„Ĺ¤ko. StaĂ„Ĺ¤Ä‚Â­ dvaĂ˘â‚¬â€śtÄąâ„˘i harmonickÄ‚Â© tÄ‚Ĺ‚ny a kombinovat je volnĂ„â€ş. NeutrÄ‚Ë‡lnÄ‚Â­ barvy Ă˘â‚¬â€ť bÄ‚Â©ÄąÄľovÄ‚Ë‡, terakota, modrÄ‚Ë‡ Ă˘â‚¬â€ť ve spojenÄ‚Â­ s pÄąâ„˘Ä‚Â­rodnÄ‚Â­m prostÄąâ„˘edÄ‚Â­m fungujÄ‚Â­ vÄąÄľdy skvĂ„â€şle.</p><h2>3. Hrajte si, ignorujte fotoaparÄ‚Ë‡t</h2><p>ÄąÂÄ‚Â­kat dĂ„â€ştem Ă˘â‚¬Ĺľkoukej a usmĂ„â€şj se" zpÄąĹ»sobÄ‚Â­ nucenÄ‚Â© Ä‚ĹźsmĂ„â€şvy a strnulÄ‚Â© pÄ‚Ĺ‚zy. MÄ‚Â­sto toho se jim pÄąâ„˘irozenĂ„â€ş vĂ„â€şnujte Ă˘â‚¬â€ť ÄąË‡imrejte, Ă„Ĺ¤tĂ„â€şte pohÄ‚Ë‡dku. ZkuÄąË‡enÄ‚Ëť fotograf zachytÄ‚Â­ opravdovÄ‚Â© momenty.</p><h2>4. PÄąâ„˘Ä‚Â­roda je nejlepÄąË‡Ä‚Â­ kulisa</h2><p>Parky, lesy, louky nebo zahrada Ă˘â‚¬â€ť pÄąâ„˘Ä‚Â­roda je ideÄ‚Ë‡lnÄ‚Â­ prostÄąâ„˘edÄ‚Â­. DĂ„â€şti majÄ‚Â­ prostor, svĂ„â€ştlo je pÄąâ„˘irozenÄ‚Â©. V Olomouci: Smetanovy sady, TabulovÄ‚Ëť vrch nebo les u BystÄąâ„˘ice jsou skvĂ„â€şlÄ‚Â© lokace.</p><h2>5. KrÄ‚Ë‡tkÄ‚Ë‡ session Ă˘â‚¬â€ť mÄ‚Â©nĂ„â€ş je vÄ‚Â­ce</h2><p>45Ă˘â‚¬â€ś60 minut je optimum pro rodinnÄ‚Â© focenÄ‚Â­ s malÄ‚Ëťmi dĂ„â€ştmi. KratÄąË‡Ä‚Â­ Ă„Ĺ¤as znamenÄ‚Ë‡ svĂ„â€şÄąÄľÄ‚Â­, spolupracujÄ‚Â­cÄ‚Â­ dĂ„â€şti. ZaĂ„Ĺ¤nĂ„â€şte s nejmenÄąË‡Ä‚Â­mi Ă˘â‚¬â€ť majÄ‚Â­ nejkratÄąË‡Ä‚Â­ vÄ‚ËťdrÄąÄľ.</p>';

$art3Content = '<h2>1. ProhlÄ‚Â©dnĂ„â€şte si portfolio</h2><p>Portfolio je nejdÄąĹ»leÄąÄľitĂ„â€şjÄąË‡Ä‚Â­ kritÄ‚Â©rium. PodÄ‚Â­vejte se, zda mÄ‚Ë‡ fotograf zkuÄąË‡enosti s vaÄąË‡Ä‚Â­m typem akce a zda se vÄ‚Ë‡m lÄ‚Â­bÄ‚Â­ jeho styl. VelkÄ‚Ëť rozdÄ‚Â­l je mezi reportÄ‚Ë‡ÄąÄľnÄ‚Â­m a pÄ‚Ĺ‚zenÄ‚Ëťm stylem fotografie.</p><h2>2. PÄąâ„˘eĂ„Ĺ¤tĂ„â€şte si recenze</h2><p>Recenze na Googlu, Facebooku nebo doporuĂ„Ĺ¤enÄ‚Â­ od pÄąâ„˘Ä‚Ë‡tel odhalÄ‚Â­, jak fotograf skuteĂ„Ĺ¤nĂ„â€ş pracuje Ă˘â‚¬â€ť dochvilnost, komunikace, dodÄ‚Ë‡nÄ‚Â­ materiÄ‚Ë‡lu a profesionalita na mÄ‚Â­stĂ„â€ş.</p><h2>3. Proberte podmÄ‚Â­nky pÄąâ„˘edem</h2><p>Cena, termÄ‚Â­n dodÄ‚Ë‡nÄ‚Â­, forma vÄ‚Ëťstupu (online galerie, tisk, USB), prÄ‚Ë‡va na fotografie Ă˘â‚¬â€ť to vÄąË‡e by mĂ„â€şlo bÄ‚Ëťt jasnĂ„â€ş domluveno pÄąâ„˘ed podpisem smlouvy. Vyvarujte se pÄąâ„˘ekvapenÄ‚Â­m.</p><h2>4. OsobnÄ‚Â­ chemistry je dÄąĹ»leÄąÄľitÄ‚Ë‡</h2><p>StrÄ‚Ë‡vÄ‚Â­te s fotografem celÄ‚Ëť den nebo pÄąâ„˘i focenÄ‚Â­ hodiny. MusÄ‚Â­ vÄ‚Ë‡m bÄ‚Ëťt pÄąâ„˘Ä‚Â­jemnÄ‚Ëť. DobrÄ‚Ëť fotograf vÄ‚Ë‡s uvolnÄ‚Â­, nezÄ‚Â­skÄ‚Ë‡ strnulÄ‚Â© zÄ‚Ë‡bĂ„â€şry. SchÄąĹ»zka nebo telefonÄ‚Ë‡t pÄąâ„˘edem hodnĂ„â€ş napovÄ‚Â­.</p><h2>5. Rezervujte vĂ„Ĺ¤as</h2><p>OblÄ‚Â­benÄ‚Â­ fotografovÄ‚Â© majÄ‚Â­ termÄ‚Â­ny obsazenÄ‚Â© mĂ„â€şsÄ‚Â­ce dopÄąâ„˘edu. Pro letnÄ‚Â­ svatby a akce se doporuĂ„Ĺ¤uje rezervace rok napÄąâ„˘ed. Ă„ĹšÄ‚Â­m dÄąâ„˘Ä‚Â­ve, tÄ‚Â­m vĂ„â€ştÄąË‡Ä‚Â­ vÄ‚ËťbĂ„â€şr termÄ‚Â­nÄąĹ».</p>';

upsertBlogPost($pdo, $siteId, 'priprava-na-svatebni-foceni', '10 tipÄąĹ», jak se pÄąâ„˘ipravit na dokonalÄ‚Â© svatebnÄ‚Â­ focenÄ‚Â­', 'VÄ‚Ë‡ÄąË‡ svatebnÄ‚Â­ den si zaslouÄąÄľÄ‚Â­ dokonalou pÄąâ„˘Ä‚Â­pravu. PÄąâ„˘eĂ„Ĺ¤tĂ„â€şte si, co udĂ„â€şlat jinak neÄąÄľ ostatnÄ‚Â­, a zajistĂ„â€şte si snÄ‚Â­mky, na kterÄ‚Â© budete hrdÄ‚Â­ celÄ‚Ëť ÄąÄľivot.', $art1Content, $imgBlog1, '2025-04-12 10:00:00');
upsertBlogPost($pdo, $siteId, 'rodinne-foceni-s-detmi', 'RodinnÄ‚Â© focenÄ‚Â­: jak dostat pÄąâ„˘irozenÄ‚Â© snÄ‚Â­mky i s malÄ‚Ëťmi dĂ„â€ştmi', 'MalÄ‚Â© dĂ„â€şti a fotoaparÄ‚Ë‡t? PÄąâ„˘esnĂ„â€ş v tom chaosu se skrÄ‚ËťvajÄ‚Â­ ty nejkrÄ‚Ë‡snĂ„â€şjÄąË‡Ä‚Â­ momenty. Zde je nÄ‚Ë‡vod, jak z rodinnÄ‚Â©ho focenÄ‚Â­ udĂ„â€şlat zÄ‚Ë‡bavnÄ‚Ëť zÄ‚Ë‡ÄąÄľitek.', $art2Content, $imgBlog2, '2025-03-28 10:00:00');
upsertBlogPost($pdo, $siteId, 'jak-vybrat-fotografa', 'Jak vybrat sprÄ‚Ë‡vnÄ‚Â©ho fotografa pro vaÄąË‡i akci?', 'Na co se zamĂ„â€şÄąâ„˘it pÄąâ„˘i vÄ‚ËťbĂ„â€şru fotografa Ă˘â‚¬â€ť portfolio, styl, komunikace a podmÄ‚Â­nky spoluprÄ‚Ë‡ce. PoradÄ‚Â­me, na co se neptat zapomenout.', $art3Content, $imgBlog3, '2025-05-01 10:00:00');

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Portfolio entry Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬
$pfId = upsertPortfolioEntry($pdo, $siteId, 'svatba-martin-helena', 'Svatba Martin & Helena Ă˘â‚¬â€ť Olomouc 2025', 'PÄąâ„˘irozenÄ‚Â© zÄ‚Ë‡bĂ„â€şry z krÄ‚Ë‡snÄ‚Â©ho svatebnÄ‚Â­ho dne v Olomouci. ZlatÄ‚Ë‡ hodina v SmetanovÄ‚Ëťch sadech, veĂ„Ĺ¤ernÄ‚Â­ obÄąâ„˘ad a nezapomenutelnÄ‚Â© detaily.', null, '2025-05-03 10:00:00', 0);
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
            . '<div class="pt-caption">Svatba Martin &amp; Helena Ă˘â‚¬â€ť Olomouc 2025</div>'
            . '</a></div>';
        $pdo->prepare('REPLACE INTO content_blocks (page_id,block_key,block_type,label,content,sort_order) VALUES (?,?,?,?,?,?)')
            ->execute([$pfPage['id'], 'portfolio-tiles', 'html', 'NaÄąË‡e prÄ‚Ë‡ce Ă˘â‚¬â€ť dlaÄąÄľdice', $tileHtml, 38]);
    }
}

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Content blocks: Homepage Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

block($pdo, $pidHome, 'hero_bg',         'image',    'Hero Ă˘â‚¬â€ť pozadÄ‚Â­ (foto)',    '', 1);
block($pdo, $pidHome, 'hero_video',      'video',    'Hero Ă˘â‚¬â€ť video (URL)',      '', 2);
block($pdo, $pidHome, 'hero_tag',        'text',     'Hero Ă˘â‚¬â€ť tag',              'Fotograf & Videograf Ă˘â‚¬â€ť Olomouc', 3);
block($pdo, $pidHome, 'hero_h1',         'text',     'Hero Ă˘â‚¬â€ť nadpis',           'ZachytÄ‚Â­me vÄ‚Ë‡ÄąË‡ pÄąâ„˘Ä‚Â­bĂ„â€şh. Na vÄąÄľdy....', 4);
block($pdo, $pidHome, 'hero_sub',        'textarea', 'Hero Ă˘â‚¬â€ť podnadpis',        'ProfesionÄ‚Ë‡lnÄ‚Â­ svatebnÄ‚Â­ fotografie a videografie v Olomouci a celÄ‚Â© Ă„ĹšeskÄ‚Â© republice. KaÄąÄľdÄ‚Ëť okamÄąÄľik si zaslouÄąÄľÄ‚Â­ bÄ‚Ëťt nezapomenutelnÄ‚Ëť.', 5);

block($pdo, $pidHome, 'stat_1', 'html', 'Statistika 1', '<span class="stat-num">150+</span><span class="stat-lbl">SpokojenÄ‚Ëťch klientÄąĹ»</span>', 10);
block($pdo, $pidHome, 'stat_2', 'html', 'Statistika 2', '<span class="stat-num">8+</span><span class="stat-lbl">Let zkuÄąË‡enostÄ‚Â­</span>', 11);
block($pdo, $pidHome, 'stat_3', 'html', 'Statistika 3', '<span class="stat-num">5 let</span><span class="stat-lbl">ZÄ‚Ë‡lohy fotek zdarma</span>', 12);
block($pdo, $pidHome, 'stat_4', 'html', 'Statistika 4', '<span class="stat-num">2 fotografovÄ‚Â©</span><span class="stat-lbl">TÄ‚Ëťm profesionÄ‚Ë‡lÄąĹ»</span>', 13);

block($pdo, $pidHome, 'services_title', 'text',     'SluÄąÄľby Ă˘â‚¬â€ť nadpis', 'Co pro vÄ‚Ë‡s udĂ„â€şlÄ‚Ë‡me', 20);
block($pdo, $pidHome, 'services_intro', 'textarea', 'SluÄąÄľby Ă˘â‚¬â€ť intro',  'AÄąÄ„ jde o svatbu, portrÄ‚Â©t nebo firemnÄ‚Â­ projekt Ă˘â‚¬â€ť jsme tu pro vÄ‚Ë‡s.', 21);
block($pdo, $pidHome, 'service_1', 'html', 'SluÄąÄľba 1 Ă˘â‚¬â€ť Svatby', '<span class="svc-num">01</span><h3>SvatebnÄ‚Â­ foto &amp; video</h3><p>ZachytÄ‚Â­me kaÄąÄľdÄ‚Ëť dotek, kaÄąÄľdÄ‚Ëť pohled, kaÄąÄľdou slzu ÄąË‡tĂ„â€şstÄ‚Â­. PÄąâ„˘irozenÄ‚Â© reportÄ‚Ë‡ÄąÄľnÄ‚Â­ fotografie a filmovÄ‚Â© video, kterÄ‚Â© vÄ‚Ë‡m budou vyprÄ‚Ë‡vĂ„â€şt pÄąâ„˘Ä‚Â­bĂ„â€şh vaÄąË‡eho dne po celÄ‚Ëť ÄąÄľivot.</p>', 22);
block($pdo, $pidHome, 'service_2', 'html', 'SluÄąÄľba 2 Ă˘â‚¬â€ť Rodiny', '<span class="svc-num">02</span><h3>RodinnÄ‚Â© &amp; portrÄ‚Â©tnÄ‚Â­ focenÄ‚Â­</h3><p>RodinnÄ‚Â© vzpomÄ‚Â­nky, tĂ„â€şhotenskÄ‚Â© focenÄ‚Â­, kÄąâ„˘tiny nebo portrÄ‚Â©ty. PÄąâ„˘irozenÄ‚Â©, nenucenÄ‚Â© a krÄ‚Ë‡snÄ‚Â© snÄ‚Â­mky, na kterÄ‚Â© budete hrdÄ‚Â­.</p>', 23);
block($pdo, $pidHome, 'service_3', 'html', 'SluÄąÄľba 3 Ă˘â‚¬â€ť Reklama', '<span class="svc-num">03</span><h3>ReklamnÄ‚Â­ foto &amp; video</h3><p>ProfesionÄ‚Ë‡lnÄ‚Â­ vizuÄ‚Ë‡lnÄ‚Â­ obsah pro firmy, kapely, produkty nebo brand. Videoklipy, firemnÄ‚Â­ prezentace a obsah, kterÄ‚Ëť prodÄ‚Ë‡vÄ‚Ë‡.</p>', 24);
block($pdo, $pidHome, 'service_4', 'html', 'SluÄąÄľba 4 Ă˘â‚¬â€ť Akce', '<span class="svc-num">04</span><h3>Akce &amp; udÄ‚Ë‡losti</h3><p>Konference, kulturnÄ‚Â­ akce, plesy, narozeniny. KaÄąÄľdÄ‚Ë‡ udÄ‚Ë‡lost si zaslouÄąÄľÄ‚Â­ bÄ‚Ëťt profesionÄ‚Ë‡lnĂ„â€ş zdokumentovÄ‚Ë‡na.</p>', 25);

block($pdo, $pidHome, 'why_title', 'text',     'ProĂ„Ĺ¤ my Ă˘â‚¬â€ť nadpis', 'ProĂ„Ĺ¤ si vybrat RB Photos & Videos?', 30);
block($pdo, $pidHome, 'why_intro', 'textarea', 'ProĂ„Ĺ¤ my Ă˘â‚¬â€ť intro',  'Nejsme jen fotografovÄ‚Â©. Jsme vypravĂ„â€şĂ„Ĺ¤i pÄąâ„˘Ä‚Â­bĂ„â€şhÄąĹ».', 31);
block($pdo, $pidHome, 'why_1', 'html', 'DÄąĹ»vod 1', '<span class="feat-num">01</span><h3>OsobnÄ‚Â­ pÄąâ„˘Ä‚Â­stup</h3><p>KaÄąÄľdÄ‚Ëť klient je pro nÄ‚Ë‡s jedineĂ„Ĺ¤nÄ‚Ëť. PÄąâ„˘Ä‚Ë‡telskÄ‚Ëť tÄ‚Ëťm, kterÄ‚Ëť naslouchÄ‚Ë‡ vaÄąË‡im pÄąâ„˘Ä‚Ë‡nÄ‚Â­m a pomÄąĹ»ÄąÄľe i s kompozicÄ‚Â­.</p>', 32);
block($pdo, $pidHome, 'why_2', 'html', 'DÄąĹ»vod 2', '<span class="feat-num">02</span><h3>ZkuÄąË‡enÄ‚Ëť tÄ‚Ëťm</h3><p>VÄ‚Â­ce neÄąÄľ 8 let zkuÄąË‡enostÄ‚Â­, profesionÄ‚Ë‡lnÄ‚Â­ vybavenÄ‚Â­ a neustÄ‚Ë‡lÄ‚Â© vzdĂ„â€şlÄ‚Ë‡vÄ‚Ë‡nÄ‚Â­. Investujeme do kvality pro vÄ‚Ë‡s.</p>', 33);
block($pdo, $pidHome, 'why_3', 'html', 'DÄąĹ»vod 3', '<span class="feat-num">03</span><h3>5 let zÄ‚Ë‡lohy zdarma</h3><p>VaÄąË‡e fotografie a videa bezpeĂ„Ĺ¤nĂ„â€ş zÄ‚Ë‡lohujeme po dobu 5 let. Nikdy o vzpomÄ‚Â­nky nepÄąâ„˘ijdete.</p>', 34);
block($pdo, $pidHome, 'why_4', 'html', 'DÄąĹ»vod 4', '<span class="feat-num">04</span><h3>CelÄ‚Ë‡ Ă„ĹšR i zahraniĂ„Ĺ¤Ä‚Â­</h3><p>SÄ‚Â­dlÄ‚Â­me v Olomouci, ale fotografujeme po celÄ‚Â© Ă„ĹšeskÄ‚Â© republice i v zahraniĂ„Ĺ¤Ä‚Â­. PÄąâ„˘ijedeme za vÄ‚Ë‡mi.</p>', 35);

block($pdo, $pidHome, 'portfolio_title', 'text',     'PrÄ‚Ë‡ce Ă˘â‚¬â€ť nadpis',    'To nejlepÄąË‡Ä‚Â­ od nÄ‚Ë‡s', 40);
block($pdo, $pidHome, 'portfolio_intro', 'textarea', 'PrÄ‚Ë‡ce Ă˘â‚¬â€ť intro',     'SvatebnÄ‚Â­ filmy, klipy a reportÄ‚Ë‡ÄąÄľe Ă˘â‚¬â€ť podÄ‚Â­vejte se jak pracujeme.', 41);
block($pdo, $pidHome, 'portfolio_btns',  'html',     'PrÄ‚Ë‡ce Ă˘â‚¬â€ť tlaĂ„Ĺ¤Ä‚Â­tka',  '<a href="/nase-prace" class="btn-g">V&#353;echny pr&#225;ce</a>', 46);
block($pdo, $pidHome, 'portfolio-tiles', 'html', 'NaÄąË‡e prÄ‚Ë‡ce Ă˘â‚¬â€ť dlaÄąÄľdice', '', 38);

block($pdo, $pidHome, 'reviews_title', 'text',     'Recenze Ă˘â‚¬â€ť nadpis', 'Co Äąâ„˘Ä‚Â­kajÄ‚Â­ naÄąË‡i klienti', 50);
block($pdo, $pidHome, 'reviews_intro', 'textarea', 'Recenze Ă˘â‚¬â€ť intro',  'PÄąâ„˘es 150 spokojenÄ‚Ëťch klientÄąĹ» Ă˘â‚¬â€ť jejich slova mluvÄ‚Â­ za nÄ‚Ë‡s.', 51);
block($pdo, $pidHome, 'review_1', 'html', 'Recenze 1', '<div class="rev-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div><blockquote>"Kluci jsou naprostÄ‚Â­ profesionÄ‚Ë‡lovÄ‚Â©. Jsou to opravdu pÄąâ„˘Ä‚Ë‡telÄąË‡tÄ‚Â­ borci, kteÄąâ„˘Ä‚Â­ vÄ‚Ë‡m udĂ„â€şlajÄ‚Â­ fotky a videa podle vaÄąË‡ich pÄąâ„˘edstav a jeÄąË‡tĂ„â€ş lÄ‚Â­p. Za mĂ„â€ş TOP ve svÄ‚Â©m oboru. DĂ„â€şkujeme, ÄąÄľe jste mohli bÄ‚Ëťt na naÄąË‡Ä‚Â­ svatbĂ„â€ş!"</blockquote><div class="rev-name">Jan JÄ‚Ä˝ngling</div><div class="rev-type">Svatba M+H</div>', 52);
block($pdo, $pidHome, 'review_2', 'html', 'Recenze 2', '<div class="rev-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div><blockquote>"Super domluva, milÄ‚Ëť a profesionÄ‚Ë‡lnÄ‚Â­ pÄąâ„˘Ä‚Â­stup k focenÄ‚Â­. Zdena nÄ‚Ë‡m nafotil svatebnÄ‚Â­ obÄąâ„˘ad s poobÄąâ„˘adnÄ‚Â­m focenÄ‚Â­m v zÄ‚Ë‡meckÄ‚Â©m parku. Z kvality fotek jsme nadÄąË‡enÄ‚Â­ a byly dodÄ‚Ë‡ny dÄąâ„˘Ä‚Â­ve neÄąÄľ jsme byli domluveni. VelkÄ‚Ë‡ spokojenost. DoporuĂ„Ĺ¤uji."</blockquote><div class="rev-name">VÄ‚Ë‡clav Konopka</div><div class="rev-type">SvatebnÄ‚Â­ focenÄ‚Â­</div>', 53);
block($pdo, $pidHome, 'review_3', 'html', 'Recenze 3', '<div class="rev-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div><blockquote>"Moc doporuĂ„Ĺ¤uji, pan fotograf nÄ‚Ë‡m vyĂ„Ĺ¤aroval ty nejkrÄ‚Ë‡snĂ„â€şjÄąË‡Ä‚Â­ rodinnÄ‚Â© fotky i s dĂ„â€ştmi, co nemĂ„â€şly zrovna fotÄ‚Â­cÄ‚Â­ nÄ‚Ë‡ladu. ProfesionÄ‚Ë‡lnÄ‚Â­ pÄąâ„˘Ä‚Â­stup a ÄąË‡piĂ„Ĺ¤kovÄ‚Ë‡ kvalita. UÄąÄľ se tĂ„â€şÄąË‡Ä‚Â­m na dalÄąË‡Ä‚Â­ focenÄ‚Â­!"</blockquote><div class="rev-name">Jana DudkovÄ‚Ë‡</div><div class="rev-type">RodinnÄ‚Â© focenÄ‚Â­</div>', 54);
block($pdo, $pidHome, 'reviews_cta', 'html', 'Recenze Ă˘â‚¬â€ť tlaĂ„Ĺ¤Ä‚Â­tko', '<a href="https://www.facebook.com/rbvideosandphotos/reviews" target="_blank" rel="noopener" class="btn-od">VÄąË‡echny recenze na Facebooku</a>', 55);

block($pdo, $pidHome, 'pricing_title', 'text',     'CenÄ‚Â­k Ă˘â‚¬â€ť nadpis',   'TransparentnÄ‚Â­ cenÄ‚Â­k bez pÄąâ„˘ekvapenÄ‚Â­', 70);
block($pdo, $pidHome, 'pricing_intro', 'textarea', 'CenÄ‚Â­k Ă˘â‚¬â€ť intro',   'KaÄąÄľdÄ‚Ë‡ zakÄ‚Ë‡zka je individuÄ‚Ë‡lnÄ‚Â­. NÄ‚Â­ÄąÄľe najdete orientaĂ„Ĺ¤nÄ‚Â­ ceny Ă˘â‚¬â€ť pÄąâ„˘esnou nabÄ‚Â­dku pÄąâ„˘ipravÄ‚Â­me vÄąÄľdy na mÄ‚Â­ru.', 71);
block($pdo, $pidHome, 'pricing_body',  'html',     'CenÄ‚Â­k Ă˘â‚¬â€ť obsah',   '', 72);
block($pdo, $pidHome, 'pricing_cta',   'html',     'CenÄ‚Â­k Ă˘â‚¬â€ť tlaĂ„Ĺ¤Ä‚Â­tko', '<a href="/cenik" class="btn-g">Cel&#253; cen&#237;k</a>', 73);

block($pdo, $pidHome, 'blog_title', 'text',     'Blog Ă˘â‚¬â€ť nadpis', 'Tipy a inspirace', 80);
block($pdo, $pidHome, 'blog_intro', 'textarea', 'Blog Ă˘â‚¬â€ť intro',  'Rady od profesionÄ‚Ë‡lnÄ‚Â­ho fotografa Ă˘â‚¬â€ť jak se pÄąâ„˘ipravit, co oĂ„Ĺ¤ekÄ‚Ë‡vat a jak dostat ty nejkrÄ‚Ë‡snĂ„â€şjÄąË‡Ä‚Â­ snÄ‚Â­mky.', 81);
block($pdo, $pidHome, 'blog_1',     'html',     'Ă„ĹšlÄ‚Ë‡nek 1',      '', 82);
block($pdo, $pidHome, 'blog_2',     'html',     'Ă„ĹšlÄ‚Ë‡nek 2',      '', 83);
block($pdo, $pidHome, 'blog_3',     'html',     'Ă„ĹšlÄ‚Ë‡nek 3',      '', 84);
block($pdo, $pidHome, 'blog_cta',   'html',     'Blog Ă˘â‚¬â€ť tlaĂ„Ĺ¤Ä‚Â­tko', '<a href="/blog" class="btn-od">V&#353;echny &#269;l&#225;nky</a>', 85);

block($pdo, $pidHome, 'faq_title', 'text',     'FAQ Ă˘â‚¬â€ť nadpis', 'NejĂ„Ĺ¤astĂ„â€şjÄąË‡Ä‚Â­ otÄ‚Ë‡zky', 90);
block($pdo, $pidHome, 'faq_intro', 'textarea', 'FAQ Ă˘â‚¬â€ť intro',  'VÄąË‡e, co potÄąâ„˘ebujete vĂ„â€şdĂ„â€şt pÄąâ„˘ed rezervacÄ‚Â­.', 91);
block($pdo, $pidHome, 'faq_1',     'html',     'FAQ 1',        '<div class="faq-q">Kdy je nejlepÄąË‡Ä‚Â­ rezervovat termÄ‚Â­n svatby?</div><div class="faq-a">IdeÄ‚Ë‡lnĂ„â€ş co nejdÄąâ„˘Ä‚Â­ve po potvrzenÄ‚Â­ data a mÄ‚Â­sta svatby. U vÄ‚Â­kendovÄ‚Ëťch termÄ‚Â­nÄąĹ» ve svatebnÄ‚Â­ sezÄ‚Ĺ‚nĂ„â€ş doporuĂ„Ĺ¤uji rezervaci nĂ„â€şkolik mĂ„â€şsÄ‚Â­cÄąĹ» dopÄąâ„˘edu.</div>', 92);
block($pdo, $pidHome, 'faq_2',     'html',     'FAQ 2',        '<div class="faq-q">Kolik fotografiÄ‚Â­ dostaneme?</div><div class="faq-a">ZÄ‚Ë‡leÄąÄľÄ‚Â­ na dÄ‚Â©lce focenÄ‚Â­ a zvolenÄ‚Â©m balÄ‚Â­Ă„Ĺ¤ku. U menÄąË‡Ä‚Â­ch svateb obvykle pÄąâ„˘edÄ‚Ë‡vÄ‚Ë‡m niÄąÄľÄąË‡Ä‚Â­ stovky fotografiÄ‚Â­, u celodennÄ‚Â­ch svateb nĂ„â€şkolik stovek upravenÄ‚Ëťch snÄ‚Â­mkÄąĹ».</div>', 93);
block($pdo, $pidHome, 'faq_3',     'html',     'FAQ 3',        '<div class="faq-q">Za jak dlouho dostaneme hotovÄ‚Â© fotografie?</div><div class="faq-a">ObvyklÄ‚Ë‡ doba dodÄ‚Ë‡nÄ‚Â­ je podle rozsahu zakÄ‚Ë‡zky. MenÄąË‡Ä‚Â­ focenÄ‚Â­ bÄ‚ËťvÄ‚Ë‡ hotovÄ‚Â© rychleji, u vĂ„â€ştÄąË‡Ä‚Â­ch svateb a kombinace foto + video je potÄąâ„˘eba poĂ„Ĺ¤Ä‚Â­tat s delÄąË‡Ä‚Â­ postprodukcÄ‚Â­.</div>', 94);
block($pdo, $pidHome, 'faq_cta',   'html',     'FAQ Ă˘â‚¬â€ť tlaĂ„Ĺ¤Ä‚Â­tko', '<a href="/faq" class="btn-od">V&#353;echny ot&#225;zky</a>', 95);

block($pdo, $pidHome, 'cta_title',    'text',     'CTA Ă˘â‚¬â€ť nadpis',     'PojĂ„Ĺąme vytvoÄąâ„˘it nĂ„â€şco krÄ‚Ë‡snÄ‚Â©ho', 60);
block($pdo, $pidHome, 'cta_sub',      'textarea', 'CTA Ă˘â‚¬â€ť podnadpis',  'NezÄ‚Ë‡vaznĂ„â€ş se nÄ‚Ë‡m ozvĂ„â€şte Ă˘â‚¬â€ť rÄ‚Ë‡di odpovÄ‚Â­me na vaÄąË‡e otÄ‚Ë‡zky a pÄąâ„˘ipravÄ‚Â­me nabÄ‚Â­dku na mÄ‚Â­ru pro vÄ‚Ë‡ÄąË‡ den.', 61);
block($pdo, $pidHome, 'contact_info', 'html',     'Kontakt Ă˘â‚¬â€ť info',   '', 62);
block($pdo, $pidHome, 'contact_card', 'html',     'Kontakt Ă˘â‚¬â€ť karta',  '<h3>OzvĂ„â€şte se nÄ‚Ë‡m</h3><p class="cta-r-sub">Zavolejte nebo napiÄąË‡te na sociÄ‚Ë‡lnÄ‚Â­ sÄ‚Â­tĂ„â€ş. OdpovÄ‚Â­dÄ‚Ë‡me rychle!</p><a href="tel:792483583" class="cta-btn cta-btn--gold">Zavolat: 792&nbsp;483&nbsp;583</a><a href="tel:604225032" class="cta-btn cta-btn--dark">Zavolat: 604&nbsp;225&nbsp;032</a><a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener" class="cta-btn cta-btn--dark">Instagram</a><a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener" class="cta-btn cta-btn--dark">Facebook</a>', 63);

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Content blocks: CenÄ‚Â­k Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

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
    <h3 class="ckp-title">ZÄ‚Ë‡kladnÄ‚Â­ svatba</h3>
    <div class="ckp-price">od 14 900 KĂ„Ĺ¤</div>
    <p class="ckp-desc">Pro menÄąË‡Ä‚Â­ svatby, obÄąâ„˘ad, gratulace, skupinovÄ‚Â© fotografie a krÄ‚Ë‡tkÄ‚Â© focenÄ‚Â­ novomanÄąÄľelÄąĹ».</p>
    <ul class="ckp-feats">
      <li>focenÄ‚Â­ cca 3 hodiny</li>
      <li>obÄąâ„˘ad, gratulace, skupinovÄ‚Â© fotografie</li>
      <li>krÄ‚Ë‡tkÄ‚Â© pÄ‚Ë‡rovÄ‚Â© focenÄ‚Â­ novomanÄąÄľelÄąĹ»</li>
      <li>minimÄ‚Ë‡lnĂ„â€ş 100 upravenÄ‚Ëťch fotografiÄ‚Â­</li>
      <li>online galerie</li>
      <li>pÄąâ„˘edÄ‚Ë‡nÄ‚Â­ fotek ke staÄąÄľenÄ‚Â­</li>
      <li>archivace hotovÄ‚Ëťch vÄ‚ËťstupÄąĹ» na 2 roky</li>
    </ul>
  </div>
  <div class="ckp-card ckp-card--featured">
    <span class="ckp-badge">NejoblÄ‚Â­benĂ„â€şjÄąË‡Ä‚Â­</span>
    <h3 class="ckp-title">StandardnÄ‚Â­ svatba</h3>
    <div class="ckp-price">od 24 900 KĂ„Ĺ¤</div>
    <p class="ckp-desc">NejoblÄ‚Â­benĂ„â€şjÄąË‡Ä‚Â­ volba pro vĂ„â€ştÄąË‡inu pÄ‚Ë‡rÄąĹ». Pokryje hlavnÄ‚Â­ Ă„Ĺ¤Ä‚Ë‡st svatebnÄ‚Â­ho dne od pÄąâ„˘Ä‚Â­prav po prvnÄ‚Â­ tanec nebo veĂ„Ĺ¤ernÄ‚Â­ program.</p>
    <ul class="ckp-feats">
      <li>focenÄ‚Â­ cca 8 hodin</li>
      <li>pÄąâ„˘Ä‚Â­pravy, obÄąâ„˘ad, gratulace, skupinky</li>
      <li>pÄ‚Ë‡rovÄ‚Â© focenÄ‚Â­ novomanÄąÄľelÄąĹ»</li>
      <li>hostina, detaily a atmosfÄ‚Â©ra dne</li>
      <li>minimÄ‚Ë‡lnĂ„â€ş 300Ă˘â‚¬â€ś500 upravenÄ‚Ëťch fotografiÄ‚Â­</li>
      <li>svatebnÄ‚Â­ video / sestÄąâ„˘ih dne</li>
      <li>online galerie</li>
      <li>konzultace pÄąâ„˘ed svatbou</li>
      <li>archivace hotovÄ‚Ëťch vÄ‚ËťstupÄąĹ» na 2 roky</li>
    </ul>
  </div>
  <div class="ckp-card">
    <h3 class="ckp-title">PrÄ‚Â©miovÄ‚Ë‡ svatba</h3>
    <div class="ckp-price">od 34 900 KĂ„Ĺ¤</div>
    <p class="ckp-desc">KompletnÄ‚Â­ zachycenÄ‚Â­ celÄ‚Â©ho svatebnÄ‚Â­ho dne bez spĂ„â€şchu a kompromisÄąĹ».</p>
    <ul class="ckp-feats">
      <li>focenÄ‚Â­ cca 10Ă˘â‚¬â€ś12 hodin</li>
      <li>celÄ‚Ëť den od pÄąâ„˘Ä‚Â­prav aÄąÄľ po veĂ„Ĺ¤ernÄ‚Â­ zÄ‚Ë‡bavu</li>
      <li>vĂ„â€ştÄąË‡Ä‚Â­ poĂ„Ĺ¤et upravenÄ‚Ëťch fotografiÄ‚Â­</li>
      <li>detailnÄ‚Â­ zachycenÄ‚Â­ atmosfÄ‚Â©ry, hostÄąĹ» a momentÄąĹ»</li>
      <li>svatebnÄ‚Â­ video + krÄ‚Ë‡tkÄ‚Ëť sestÄąâ„˘ih / reels v cenĂ„â€ş</li>
      <li>online galerie a video ke staÄąÄľenÄ‚Â­</li>
      <li>konzultace pÄąâ„˘ed svatbou</li>
      <li>prioritnÄ‚Â­ zpracovÄ‚Ë‡nÄ‚Â­</li>
      <li>archivace hotovÄ‚Ëťch vÄ‚ËťstupÄąĹ» na 5 let</li>
    </ul>
  </div>
</div>
HTML;

block($pdo, $pidCenik, 'hero_heading',            'text',     'Hero Nadpis',       'Ceník', 1);
block($pdo, $pidCenik, 'hero_intro',              'textarea', 'Hero Intro',        'TransparentnÄ‚Â­ ceny bez pÄąâ„˘ekvapenÄ‚Â­. KaÄąÄľdÄ‚Ë‡ zakÄ‚Ë‡zka je individuÄ‚Ë‡lnÄ‚Â­ Ă˘â‚¬â€ť vÄąÄľdy se domluvÄ‚Â­me na balÄ‚Â­Ă„Ĺ¤ku pÄąâ„˘esnĂ„â€ş pro vÄ‚Ë‡s. PÄąâ„˘es 150 spokojenÄ‚Ëťch klientÄąĹ», 8 let praxe.', 2);
block($pdo, $pidCenik, 'pricing_weddings_heading','text',     'CenÄ‚Â­k Svatby',      'SvatebnÄ‚Â­ balÄ‚Â­Ă„Ĺ¤ky', 3);
block($pdo, $pidCenik, 'pricing_weddings_sub',    'textarea', 'CenÄ‚Â­k Svatby Info', 'Vyberte si balÄ‚Â­Ă„Ĺ¤ek podle rozsahu svatebnÄ‚Â­ho dne. KaÄąÄľdÄ‚Ë‡ zakÄ‚Ë‡zka je individuÄ‚Ë‡lnÄ‚Â­ Ă˘â‚¬â€ť finÄ‚Ë‡lnÄ‚Â­ nabÄ‚Â­dku pÄąâ„˘ipravÄ‚Â­me vÄąÄľdy na mÄ‚Â­ru. Ceny jsou orientaĂ„Ĺ¤nÄ‚Â­, Ă˘â‚¬Ĺľod".', 4);
block($pdo, $pidCenik, 'pricing_packages',        'html',     'SvatebnÄ‚Â­ balÄ‚Â­Ă„Ĺ¤ky',  $pricingPackages, 5);
block($pdo, $pidCenik, 'pricing_other_heading',   'text',     'CenÄ‚Â­k OstatnÄ‚Â­',     'RodinnÄ‚Â©, portrÄ‚Â©tnÄ‚Â­ a firemnÄ‚Â­ focenÄ‚Â­', 5);
block($pdo, $pidCenik, 'pricing_other_sub',       'textarea', 'CenÄ‚Â­k OstatnÄ‚Â­ Info','RodinnÄ‚Â© focenÄ‚Â­ od 4 500 KĂ„Ĺ¤ Ă‚Â· PortrÄ‚Â©tnÄ‚Â­ focenÄ‚Â­ od 3 000 KĂ„Ĺ¤ Ă‚Â· FiremnÄ‚Â­ focenÄ‚Â­ od 6 000 KĂ„Ĺ¤ Ă‚Â· ZÄ‚Ë‡snuby & Maternity od 3 500 KĂ„Ĺ¤ Ă‚Â· Videografie od 8 000 KĂ„Ĺ¤.', 6);
block($pdo, $pidCenik, 'cta_heading',             'text',     'CTA Nadpis',        'Domluvme termín', 7);
block($pdo, $pidCenik, 'cta_sub',                 'textarea', 'CTA Sub',           'OblÄ‚Â­benÄ‚Â© soboty se obsazujÄ‚Â­ rok dopÄąâ„˘edu Ă˘â‚¬â€ť Ă„Ĺ¤Ä‚Â­m dÄąâ„˘Ä‚Â­ve, tÄ‚Â­m vĂ„â€ştÄąË‡Ä‚Â­ vÄ‚ËťbĂ„â€şr. Zavolejte nebo napiÄąË‡te, odpovÄ‚Â­me do 24 hodin.', 8);

// BalÄ‚Â­Ă„Ĺ¤ky Ă˘â‚¬â€ť renderuje buildPricingHtml() na homepage a cenik strÄ‚Ë‡nce
block($pdo, $pidCenik, 'cenik_card_1_title', 'text',     'Karta 1 Ă˘â‚¬â€ť nadpis', 'ZÄ‚Ë‡kladnÄ‚Â­', 11);
block($pdo, $pidCenik, 'cenik_card_1_desc',  'textarea', 'Karta 1 Ă˘â‚¬â€ť popis',
'MenÄąË‡Ä‚Â­ svatba, obÄąâ„˘ad, skupinky a krÄ‚Ë‡tkÄ‚Â© focenÄ‚Â­ novomanÄąÄľelÄąĹ».

Archivace: 2 roky', 12);
block($pdo, $pidCenik, 'cenik_card_1_price', 'text',     'Karta 1 Ă˘â‚¬â€ť cena',  'od 14 900 KĂ„Ĺ¤', 13);

block($pdo, $pidCenik, 'cenik_card_2_title', 'text',     'Karta 2 Ă˘â‚¬â€ť nadpis', 'StandardnÄ‚Â­', 14);
block($pdo, $pidCenik, 'cenik_card_2_desc',  'textarea', 'Karta 2 Ă˘â‚¬â€ť popis',
'NejoblÄ‚Â­benĂ„â€şjÄąË‡Ä‚Â­ balÄ‚Â­Ă„Ĺ¤ek pro hlavnÄ‚Â­ Ă„Ĺ¤Ä‚Ë‡st svatebnÄ‚Â­ho dne.

Archivace: 2 roky', 15);
block($pdo, $pidCenik, 'cenik_card_2_price', 'text',     'Karta 2 Ă˘â‚¬â€ť cena',  'od 24 900 KĂ„Ĺ¤', 16);

block($pdo, $pidCenik, 'cenik_card_3_title', 'text',     'Karta 3 Ă˘â‚¬â€ť nadpis', 'PrÄ‚Â©miovÄ‚Ëť', 17);
block($pdo, $pidCenik, 'cenik_card_3_desc',  'textarea', 'Karta 3 Ă˘â‚¬â€ť popis',
'CelodennÄ‚Â­ zachycenÄ‚Â­ svatby s rozÄąË‡Ä‚Â­Äąâ„˘enou pÄ‚Â©Ă„Ĺ¤Ä‚Â­ a delÄąË‡Ä‚Â­ archivacÄ‚Â­.

Archivace: 5 let', 18);
block($pdo, $pidCenik, 'cenik_card_3_price', 'text',     'Karta 3 Ă˘â‚¬â€ť cena',  'od 34 900 KĂ„Ĺ¤', 19);

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Content blocks: FAQ Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

$faqBody = <<<'HTML'
<div class="faq-item"><div class="faq-q">Kdy je nejlepÄąË‡Ä‚Â­ rezervovat termÄ‚Â­n svatby?</div><div class="faq-a">IdeÄ‚Ë‡lnĂ„â€ş co nejdÄąâ„˘Ä‚Â­ve po potvrzenÄ‚Â­ data a mÄ‚Â­sta svatby. U vÄ‚Â­kendovÄ‚Ëťch termÄ‚Â­nÄąĹ» ve svatebnÄ‚Â­ sezÄ‚Ĺ‚nĂ„â€ş doporuĂ„Ĺ¤uji rezervaci nĂ„â€şkolik mĂ„â€şsÄ‚Â­cÄąĹ» dopÄąâ„˘edu.</div></div>
<div class="faq-item"><div class="faq-q">Je moÄąÄľnÄ‚Â© nafotit jen obÄąâ„˘ad?</div><div class="faq-a">Ano. Pro menÄąË‡Ä‚Â­ svatby nebo pouze obÄąâ„˘ad je vhodnÄ‚Ëť zÄ‚Ë‡kladnÄ‚Â­ balÄ‚Â­Ă„Ĺ¤ek. Zahrnuje obÄąâ„˘ad, gratulace, skupinovÄ‚Â© fotografie a krÄ‚Ë‡tkÄ‚Â© focenÄ‚Â­ novomanÄąÄľelÄąĹ».</div></div>
<div class="faq-item"><div class="faq-q">Kolik fotografiÄ‚Â­ dostaneme?</div><div class="faq-a">ZÄ‚Ë‡leÄąÄľÄ‚Â­ na dÄ‚Â©lce focenÄ‚Â­ a zvolenÄ‚Â©m balÄ‚Â­Ă„Ĺ¤ku. U menÄąË‡Ä‚Â­ch svateb obvykle pÄąâ„˘edÄ‚Ë‡vÄ‚Ë‡m niÄąÄľÄąË‡Ä‚Â­ stovky fotografiÄ‚Â­, u celodennÄ‚Â­ch svateb nĂ„â€şkolik stovek upravenÄ‚Ëťch snÄ‚Â­mkÄąĹ».</div></div>
<div class="faq-item"><div class="faq-q">Za jak dlouho dostaneme hotovÄ‚Â© fotografie?</div><div class="faq-a">ObvyklÄ‚Ë‡ doba dodÄ‚Ë‡nÄ‚Â­ je podle rozsahu zakÄ‚Ë‡zky. MenÄąË‡Ä‚Â­ focenÄ‚Â­ bÄ‚ËťvÄ‚Ë‡ hotovÄ‚Â© rychleji, u vĂ„â€ştÄąË‡Ä‚Â­ch svateb a kombinace foto + video je potÄąâ„˘eba poĂ„Ĺ¤Ä‚Â­tat s delÄąË‡Ä‚Â­ postprodukcÄ‚Â­.</div></div>
<div class="faq-item"><div class="faq-q">Jak nÄ‚Ë‡m budou fotografie pÄąâ„˘edÄ‚Ë‡ny?</div><div class="faq-a">HotovÄ‚Â© fotografie pÄąâ„˘edÄ‚Ë‡vÄ‚Ë‡m online formou galerie nebo odkazu ke staÄąÄľenÄ‚Â­. DÄ‚Â­ky tomu si je mÄąĹ»ÄąÄľete jednoduÄąË‡e stÄ‚Ë‡hnout a sdÄ‚Â­let s rodinou nebo hosty.</div></div>
<div class="faq-item"><div class="faq-q">Jak dlouho archivujete hotovÄ‚Â© vÄ‚Ëťstupy?</div><div class="faq-a">Podle zvolenÄ‚Â©ho balÄ‚Â­Ă„Ĺ¤ku archivuji finÄ‚Ë‡lnĂ„â€ş pÄąâ„˘edanÄ‚Â© vÄ‚Ëťstupy po dobu 2 nebo 5 let. Archivace se vztahuje na hotovÄ‚Â© pÄąâ„˘edanÄ‚Â© fotografie a videa, ne na vÄąË‡echny surovÄ‚Â© zÄ‚Ë‡znamy.</div></div>
<div class="faq-item"><div class="faq-q">Dostaneme i neupravenÄ‚Â© fotografie nebo RAW soubory?</div><div class="faq-a">StandardnĂ„â€ş pÄąâ„˘edÄ‚Ë‡vÄ‚Ë‡m pouze vybranÄ‚Â© a upravenÄ‚Â© fotografie. NeupravenÄ‚Â© fotografie ani RAW soubory nejsou bĂ„â€şÄąÄľnou souĂ„Ĺ¤Ä‚Ë‡stÄ‚Â­ pÄąâ„˘edÄ‚Ë‡nÄ‚Â­.</div></div>
<div class="faq-item"><div class="faq-q">FotÄ‚Â­te i video?</div><div class="faq-a">Ano. KromĂ„â€ş fotografovÄ‚Ë‡nÄ‚Â­ je moÄąÄľnÄ‚Â© domluvit takÄ‚Â© video vÄ‚Ëťstup, krÄ‚Ë‡tkÄ‚Ëť sestÄąâ„˘ih nebo reels/shorts verze pro sociÄ‚Ë‡lnÄ‚Â­ sÄ‚Â­tĂ„â€ş.</div></div>
<div class="faq-item"><div class="faq-q">Je doprava v cenĂ„â€ş?</div><div class="faq-a">Doprava zÄ‚Ë‡leÄąÄľÄ‚Â­ na mÄ‚Â­stĂ„â€ş konÄ‚Ë‡nÄ‚Â­ svatby nebo akce. U kaÄąÄľdÄ‚Â© poptÄ‚Ë‡vky ji Äąâ„˘eÄąË‡Ä‚Â­m individuÄ‚Ë‡lnĂ„â€ş podle lokality.</div></div>
<div class="faq-item"><div class="faq-q">Jak probÄ‚Â­hÄ‚Ë‡ rezervace termÄ‚Â­nu?</div><div class="faq-a">Po odeslÄ‚Ë‡nÄ‚Â­ poptÄ‚Ë‡vky si spoleĂ„Ĺ¤nĂ„â€ş upÄąâ„˘esnÄ‚Â­me datum, mÄ‚Â­sto, rozsah focenÄ‚Â­ nebo natÄ‚Ë‡Ă„Ĺ¤enÄ‚Â­ a poÄąÄľadovanÄ‚Â© vÄ‚Ëťstupy. PotÄ‚Â© pÄąâ„˘ipravÄ‚Â­m konkrÄ‚Â©tnÄ‚Â­ nabÄ‚Â­dku.</div></div>
<div class="faq-item"><div class="faq-q">MusÄ‚Â­me umĂ„â€şt pÄ‚Ĺ‚zovat?</div><div class="faq-a">Ne. VĂ„â€ştÄąË‡inu dne fotÄ‚Â­m pÄąâ„˘irozenĂ„â€ş a reportÄ‚Ë‡ÄąÄľnĂ„â€ş. PÄąâ„˘i pÄ‚Ë‡rovÄ‚Â©m focenÄ‚Â­ vÄ‚Ë‡s navedu tak, aby fotky pÄąĹ»sobily pÄąâ„˘irozenĂ„â€ş a nebyly strojenÄ‚Â©.</div></div>
<div class="faq-item"><div class="faq-q">Je moÄąÄľnÄ‚Â© upravit balÄ‚Â­Ă„Ĺ¤ek na mÄ‚Â­ru?</div><div class="faq-a">Ano. KaÄąÄľdÄ‚Ë‡ svatba nebo akce je jinÄ‚Ë‡, proto je moÄąÄľnÄ‚Â© rozsah focenÄ‚Â­, videa, poĂ„Ĺ¤et hodin i typ vÄ‚ËťstupÄąĹ» upravit individuÄ‚Ë‡lnĂ„â€ş.</div></div>
HTML;

block($pdo, $pidFaq, 'hero_heading', 'text',     'Hero Nadpis', 'Ă„Ĺšasto kladenÄ‚Â© otÄ‚Ë‡zky', 1);
block($pdo, $pidFaq, 'hero_sub',     'textarea', 'Hero Sub',    'VÄąË‡e, co potÄąâ„˘ebujete vĂ„â€şdĂ„â€şt pÄąâ„˘ed rezervacÄ‚Â­ Ă˘â‚¬â€ť termÄ‚Â­ny, fotografie, video, pÄąâ„˘edÄ‚Ë‡nÄ‚Â­ i archivace.', 2);
block($pdo, $pidFaq, 'faq_body',     'html',     'FAQ Ă˘â‚¬â€ť otÄ‚Ë‡zky', $faqBody, 3);
block($pdo, $pidFaq, 'faq_footer',   'textarea', 'FAQ Ă˘â‚¬â€ť patiĂ„Ĺ¤ka', 'NenaÄąË‡li jste odpovĂ„â€şĂ„Ĺą na svou otÄ‚Ë‡zku? NapiÄąË‡te mi a pÄąâ„˘ipravÄ‚Â­m vÄ‚Ë‡m konkrÄ‚Â©tnÄ‚Â­ nabÄ‚Â­dku podle termÄ‚Â­nu, mÄ‚Â­sta, rozsahu focenÄ‚Â­ nebo natÄ‚Ë‡Ă„Ĺ¤enÄ‚Â­ a poÄąÄľadovanÄ‚Ëťch vÄ‚ËťstupÄąĹ».', 4);

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Content blocks: Blog Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

block($pdo, $pidBlog, 'hero_heading', 'text',     'Hero Nadpis', 'Tipy a inspirace', 1);
block($pdo, $pidBlog, 'hero_sub',     'textarea', 'Hero Sub',    'Rady od profesionÄ‚Ë‡lnÄ‚Â­ho fotografa Ă˘â‚¬â€ť pÄąâ„˘Ä‚Â­prava na focenÄ‚Â­, tipy pro klienty a pohled do naÄąË‡Ä‚Â­ prÄ‚Ë‡ce.', 2);


// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Content blocks: SluÄąÄľby Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

block($pdo, $pidSluzby, 'hero_heading', 'text',     'Hero Nadpis', 'Fotografie a videografie na mÄ‚Â­ru', 1);
block($pdo, $pidSluzby, 'hero_sub',     'textarea', 'Hero Sub',    'AÄąÄ„ jde o svatbu, portrÄ‚Â©t nebo firemnÄ‚Â­ projekt Ă˘â‚¬â€ť jsme tu pro vÄ‚Ë‡s. ProfesionÄ‚Ë‡lnÄ‚Â­ vÄ‚Ëťsledky, pÄąâ„˘Ä‚Ë‡telskÄ‚Ëť pÄąâ„˘Ä‚Â­stup.', 2);
block($pdo, $pidSluzby, 'service_1', 'html', 'SluÄąÄľba 1', '<span class="svc-num">01</span><h3>SvatebnÄ‚Â­ foto &amp; video</h3><p>ZachytÄ‚Â­me kaÄąÄľdÄ‚Ëť dotek, kaÄąÄľdÄ‚Ëť pohled, kaÄąÄľdou slzu ÄąË‡tĂ„â€şstÄ‚Â­. PÄąâ„˘irozenÄ‚Â© reportÄ‚Ë‡ÄąÄľnÄ‚Â­ fotografie a filmovÄ‚Â© video, kterÄ‚Â© vÄ‚Ë‡m budou vyprÄ‚Ë‡vĂ„â€şt pÄąâ„˘Ä‚Â­bĂ„â€şh vaÄąË‡eho dne po celÄ‚Ëť ÄąÄľivot.</p>', 10);
block($pdo, $pidSluzby, 'service_2', 'html', 'SluÄąÄľba 2', '<span class="svc-num">02</span><h3>RodinnÄ‚Â© &amp; portrÄ‚Â©tnÄ‚Â­ focenÄ‚Â­</h3><p>RodinnÄ‚Â© vzpomÄ‚Â­nky, tĂ„â€şhotenskÄ‚Â© focenÄ‚Â­, kÄąâ„˘tiny nebo portrÄ‚Â©ty. PÄąâ„˘irozenÄ‚Â©, nenucenÄ‚Â© a krÄ‚Ë‡snÄ‚Â© snÄ‚Â­mky, na kterÄ‚Â© budete hrdÄ‚Â­.</p>', 11);
block($pdo, $pidSluzby, 'service_3', 'html', 'SluÄąÄľba 3', '<span class="svc-num">03</span><h3>ReklamnÄ‚Â­ foto &amp; video</h3><p>ProfesionÄ‚Ë‡lnÄ‚Â­ vizuÄ‚Ë‡lnÄ‚Â­ obsah pro firmy, kapely, produkty nebo brand. Videoklipy, firemnÄ‚Â­ prezentace a obsah, kterÄ‚Ëť prodÄ‚Ë‡vÄ‚Ë‡.</p>', 12);
block($pdo, $pidSluzby, 'service_4', 'html', 'SluÄąÄľba 4', '<span class="svc-num">04</span><h3>Akce &amp; udÄ‚Ë‡losti</h3><p>Konference, kulturnÄ‚Â­ akce, plesy, narozeniny. KaÄąÄľdÄ‚Ë‡ udÄ‚Ë‡lost si zaslouÄąÄľÄ‚Â­ bÄ‚Ëťt profesionÄ‚Ë‡lnĂ„â€ş zdokumentovÄ‚Ë‡na.</p>', 13);
block($pdo, $pidSluzby, 'cta_heading', 'text',     'CTA Nadpis', 'PÄąâ„˘ipravme nezapomenutelnÄ‚Ëť den', 20);
block($pdo, $pidSluzby, 'cta_sub',     'textarea', 'CTA Sub',    'Zavolejte nebo napiÄąË‡te Ă˘â‚¬â€ť rÄ‚Ë‡di odpovÄ‚Â­me na vaÄąË‡e otÄ‚Ë‡zky a pÄąâ„˘ipravÄ‚Â­me nabÄ‚Â­dku na mÄ‚Â­ru.', 21);

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Content blocks: Kontakt Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

block($pdo, $pidKontakt, 'hero_heading',   'text',     'Hero Nadpis',        'Kontaktujte nÄ‚Ë‡s', 1);
block($pdo, $pidKontakt, 'hero_sub',       'textarea', 'Hero Sub',           'Zavolejte nebo napiÄąË‡te na sociÄ‚Ë‡lnÄ‚Â­ sÄ‚Â­tĂ„â€ş. OdpovÄ‚Â­dÄ‚Ë‡me rychle Ă˘â‚¬â€ť oblÄ‚Â­benÄ‚Â© termÄ‚Â­ny se obsazujÄ‚Â­ rok dopÄąâ„˘edu.', 2);
block($pdo, $pidKontakt, 'contact_heading','text',     'Kontakt Ă˘â‚¬â€ť nadpis',   'OzvĂ„â€şte se nÄ‚Ë‡m', 3);
block($pdo, $pidKontakt, 'contact_info',   'html',     'Kontakt Ă˘â‚¬â€ť info',     '<div class="cta-info"><p><strong>792&nbsp;483&nbsp;583</strong></p><p><strong>604&nbsp;225&nbsp;032</strong></p><p>Horní náměstí 583/1<br>779 00 Olomouc</p><p><a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener">Facebook: RB Videos &amp; Photos</a></p><p><a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener">Instagram: @rb_videos_photos</a></p><p><a href="https://www.youtube.com/@rbvideosandphotos" target="_blank" rel="noopener">YouTube kanÄ‚Ë‡l</a></p></div>', 4);
block($pdo, $pidKontakt, 'contact_card',   'html',     'Kontakt Ă˘â‚¬â€ť karta',    '<h3>OzvĂ„â€şte se nÄ‚Ë‡m</h3><p class="cta-r-sub">Zavolejte nebo napiÄąË‡te na sociÄ‚Ë‡lnÄ‚Â­ sÄ‚Â­tĂ„â€ş. OdpovÄ‚Â­dÄ‚Ë‡me rychle!</p><a href="tel:792483583" class="cta-btn cta-btn--gold">Zavolat: 792&nbsp;483&nbsp;583</a><a href="tel:604225032" class="cta-btn cta-btn--dark">Zavolat: 604&nbsp;225&nbsp;032</a><a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener" class="cta-btn cta-btn--dark">Instagram</a><a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener" class="cta-btn cta-btn--dark">Facebook</a>', 5);

// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ SEO settings Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

$pdo->exec("DELETE FROM reviews WHERE site_id = {$siteId}");
$reviewStmt = $pdo->prepare('INSERT INTO reviews (site_id, author_name, context_label, rating, review_text, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
$reviewStmt->execute([$siteId, 'Jan Jungling', 'Svatba M+H', 5, 'Kluci jsou naprosti profesionalove. Jsou to pratelski borci, kteri umi zachytit emoce a dodat fotky i video na vysoke urovni.', 1, 1]);
$reviewStmt->execute([$siteId, 'Vaclav Konopka', 'Svatebni foceni', 5, 'Super domluva, mily a profesionalni pristup k foceni. Velka spokojenost.', 2, 1]);
$reviewStmt->execute([$siteId, 'Jana Dudkova', 'Rodinne foceni', 5, 'Moc doporucuji. Profesionalni pristup a spickova kvalita. Uz se tesime na dalsi foceni.', 3, 1]);

$d = 'https://rbvideosphotos.kvalitne.cz';

seo($pdo, $pidHome, [
    'title'       => 'SvatebnĂ­ foto a video Olomouc, Morava | RB Videos Photos',
    'desc'        => 'SvatebnĂ­ fotografie a video z Olomouce pro pĂˇry z celĂ© Moravy. PĹ™irozenĂ© focenĂ­, filmovĂ© svatebnĂ­ video, rychlĂ© dodĂˇnĂ­ a balĂ­ÄŤky od 21 000 KÄŤ.',
    'kw'          => 'svatebnĂ­ foto a video Olomouc, svatebnĂ­ fotograf Olomouc, svatebnĂ­ video Olomouc, Morava',
    'canonical'   => '/',
    'og_title'    => 'SvatebnĂ­ foto a video Olomouc, Morava | RB Videos Photos',
    'og_desc'     => 'SvatebnĂ­ fotografie a video z Olomouce pro pĂˇry z celĂ© Moravy. PĹ™irozenĂ© focenĂ­ a filmovĂ© svatebnĂ­ video.',
    'schema_type' => 'LocalBusiness',
    'schema_json' => '{"@context":"https://schema.org","@graph":[{"@type":"LocalBusiness","name":"RB Videos Photos","url":"' . $d . '/","telephone":"+420604225032","address":{"@type":"PostalAddress","streetAddress":"Horní náměstí 583/1","postalCode":"779 00","addressLocality":"Olomouc","addressCountry":"CZ"},"areaServed":["Olomouc","Olomoucký kraj","Morava","Česká republika"],"priceRange":"$$","sameAs":["https://www.facebook.com/rbvideosandphotos","https://www.instagram.com/rb_videos_photos/"]},{"@type":"WebSite","name":"RB Videos Photos","url":"' . $d . '/"}]}' ,
]);

seo($pdo, $pidCenik, [
    'title'     => 'CenĂ­k svatebnĂ­ho foto a videa | RB Videos Photos',
    'desc'      => 'OrientaÄŤnĂ­ cenĂ­k svatebnĂ­ho focenĂ­ a videa. NejoblĂ­benÄ›jĹˇĂ­ foto + video balĂ­ÄŤek od 21 000 KÄŤ, archivace a rychlĂ© dodĂˇnĂ­.',
    'kw'        => 'cenĂ­k svatebnĂ­ho foto a videa, foto video balĂ­ÄŤek, svatebnĂ­ cenĂ­k',
    'canonical' => '/cenik',
]);

seo($pdo, $pidFaq, [
    'title'     => 'ÄŚastĂ© otĂˇzky | RB Videos Photos',
    'desc'      => 'OdpovÄ›di na nejÄŤastÄ›jĹˇĂ­ otĂˇzky o svatebnĂ­m focenĂ­ a videu. Ceny, dodĂˇnĂ­ fotek, pĹ™Ă­prava a prĹŻbÄ›h spoluprĂˇce.',
    'kw'        => 'ÄŤastĂ© otĂˇzky svatebnĂ­ foto video, dodĂˇnĂ­ fotek, pĹ™Ă­prava na focenĂ­',
    'canonical' => '/faq',
]);

seo($pdo, $pidBlog, [
    'title'     => 'Blog | RB Videos Photos',
    'desc'      => 'Tipy a inspirace od svatebnĂ­ho fotografa a kameramana. PĹ™Ă­prava na focenĂ­, vĂ˝bÄ›r mĂ­sta a praktickĂ© rady pro klienty.',
    'canonical' => '/blog',
]);

seo($pdo, $pidSluzby, [
    'title'     => 'SvatebnĂ­ foto a video | RB Videos Photos',
    'desc'      => 'SvatebnĂ­ focenĂ­ a video od jednoho tĂ˝mu. PĹ™irozenĂ© momenty, filmovĂ˝ vĂ˝stup, rychlĂ© dodĂˇnĂ­ a pĹŻsobnost Olomouc, Morava i celĂˇ ÄŚR.',
    'kw'        => 'svatebnĂ­ foto a video, svatebnĂ­ fotograf Olomouc, svatebnĂ­ kameraman Olomouc',
    'canonical' => '/sluzby',
]);

seo($pdo, $pidKontakt, [
    'title'     => 'Kontakt | RB Videos Photos',
    'desc'      => 'NapiĹˇte nĂˇm poptĂˇvku na svatebnĂ­ foto nebo video. PĹŻsobĂ­me v Olomouci, na MoravÄ› i po celĂ© ÄŚeskĂ© republice.',
    'kw'        => 'kontakt svatebnĂ­ foto video, poptĂˇvka termĂ­nu, fotograf telefon',
    'canonical' => '/kontakt',
]);


// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ FAQ items (tabulka faq_items Ă˘â‚¬â€ť zdroj pro /faq stranku) Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

$pdo->prepare('DELETE FROM faq_items WHERE site_id = ?')->execute([$siteId]);
$faqItems = [
    ['Kdy je nejlepÄąË‡Ä‚Â­ rezervovat termÄ‚Â­n svatby?', 'IdeÄ‚Ë‡lnĂ„â€ş co nejdÄąâ„˘Ä‚Â­ve po potvrzenÄ‚Â­ data a mÄ‚Â­sta svatby. U vÄ‚Â­kendovÄ‚Ëťch termÄ‚Â­nÄąĹ» ve svatebnÄ‚Â­ sezÄ‚Ĺ‚nĂ„â€ş doporuĂ„Ĺ¤uji rezervaci nĂ„â€şkolik mĂ„â€şsÄ‚Â­cÄąĹ» dopÄąâ„˘edu.'],
    ['Je moÄąÄľnÄ‚Â© nafotit jen obÄąâ„˘ad?', 'Ano. Pro menÄąË‡Ä‚Â­ svatby nebo pouze obÄąâ„˘ad je vhodnÄ‚Ëť zÄ‚Ë‡kladnÄ‚Â­ balÄ‚Â­Ă„Ĺ¤ek. Zahrnuje obÄąâ„˘ad, gratulace, skupinovÄ‚Â© fotografie a krÄ‚Ë‡tkÄ‚Â© focenÄ‚Â­ novomanÄąÄľelÄąĹ».'],
    ['Kolik fotografiÄ‚Â­ dostaneme?', 'ZÄ‚Ë‡leÄąÄľÄ‚Â­ na dÄ‚Â©lce focenÄ‚Â­ a zvolenÄ‚Â©m balÄ‚Â­Ă„Ĺ¤ku. U menÄąË‡Ä‚Â­ch svateb obvykle pÄąâ„˘edÄ‚Ë‡vÄ‚Ë‡m niÄąÄľÄąË‡Ä‚Â­ stovky fotografiÄ‚Â­, u celodennÄ‚Â­ch svateb nĂ„â€şkolik stovek upravenÄ‚Ëťch snÄ‚Â­mkÄąĹ».'],
    ['Za jak dlouho dostaneme hotovÄ‚Â© fotografie?', 'ObvyklÄ‚Ë‡ doba dodÄ‚Ë‡nÄ‚Â­ je podle rozsahu zakÄ‚Ë‡zky. MenÄąË‡Ä‚Â­ focenÄ‚Â­ bÄ‚ËťvÄ‚Ë‡ hotovÄ‚Â© rychleji, u vĂ„â€ştÄąË‡Ä‚Â­ch svateb a kombinace foto + video je potÄąâ„˘eba poĂ„Ĺ¤Ä‚Â­tat s delÄąË‡Ä‚Â­ postprodukcÄ‚Â­.'],
    ['Jak nÄ‚Ë‡m budou fotografie pÄąâ„˘edÄ‚Ë‡ny?', 'HotovÄ‚Â© fotografie pÄąâ„˘edÄ‚Ë‡vÄ‚Ë‡m online formou galerie nebo odkazu ke staÄąÄľenÄ‚Â­. DÄ‚Â­ky tomu si je mÄąĹ»ÄąÄľete jednoduÄąË‡e stÄ‚Ë‡hnout a sdÄ‚Â­let s rodinou nebo hosty.'],
    ['Jak dlouho archivujete hotovÄ‚Â© vÄ‚Ëťstupy?', 'Podle zvolenÄ‚Â©ho balÄ‚Â­Ă„Ĺ¤ku archivuji finÄ‚Ë‡lnĂ„â€ş pÄąâ„˘edanÄ‚Â© vÄ‚Ëťstupy po dobu 2 nebo 5 let. Archivace se vztahuje na hotovÄ‚Â© pÄąâ„˘edanÄ‚Â© fotografie a videa, ne na vÄąË‡echny surovÄ‚Â© zÄ‚Ë‡znamy.'],
    ['Dostaneme i neupravenÄ‚Â© fotografie nebo RAW soubory?', 'StandardnĂ„â€ş pÄąâ„˘edÄ‚Ë‡vÄ‚Ë‡m pouze vybranÄ‚Â© a upravenÄ‚Â© fotografie. NeupravenÄ‚Â© fotografie ani RAW soubory nejsou bĂ„â€şÄąÄľnou souĂ„Ĺ¤Ä‚Ë‡stÄ‚Â­ pÄąâ„˘edÄ‚Ë‡nÄ‚Â­.'],
    ['FotÄ‚Â­te i video?', 'Ano. KromĂ„â€ş fotografovÄ‚Ë‡nÄ‚Â­ je moÄąÄľnÄ‚Â© domluvit takÄ‚Â© video vÄ‚Ëťstup, krÄ‚Ë‡tkÄ‚Ëť sestÄąâ„˘ih nebo reels/shorts verze pro sociÄ‚Ë‡lnÄ‚Â­ sÄ‚Â­tĂ„â€ş.'],
    ['Je doprava v cenĂ„â€ş?', 'Doprava zÄ‚Ë‡leÄąÄľÄ‚Â­ na mÄ‚Â­stĂ„â€ş konÄ‚Ë‡nÄ‚Â­ svatby nebo akce. U kaÄąÄľdÄ‚Â© poptÄ‚Ë‡vky ji Äąâ„˘eÄąË‡Ä‚Â­m individuÄ‚Ë‡lnĂ„â€ş podle lokality.'],
    ['Jak probÄ‚Â­hÄ‚Ë‡ rezervace termÄ‚Â­nu?', 'Po odeslÄ‚Ë‡nÄ‚Â­ poptÄ‚Ë‡vky si spoleĂ„Ĺ¤nĂ„â€ş upÄąâ„˘esnÄ‚Â­me datum, mÄ‚Â­sto, rozsah focenÄ‚Â­ nebo natÄ‚Ë‡Ă„Ĺ¤enÄ‚Â­ a poÄąÄľadovanÄ‚Â© vÄ‚Ëťstupy. PotÄ‚Â© pÄąâ„˘ipravÄ‚Â­m konkrÄ‚Â©tnÄ‚Â­ nabÄ‚Â­dku.'],
    ['MusÄ‚Â­me umĂ„â€şt pÄ‚Ĺ‚zovat?', 'Ne. VĂ„â€ştÄąË‡inu dne fotÄ‚Â­m pÄąâ„˘irozenĂ„â€ş a reportÄ‚Ë‡ÄąÄľnĂ„â€ş. PÄąâ„˘i pÄ‚Ë‡rovÄ‚Â©m focenÄ‚Â­ vÄ‚Ë‡s navedu tak, aby fotky pÄąĹ»sobily pÄąâ„˘irozenĂ„â€ş a nebyly strojenÄ‚Â©.'],
    ['Je moÄąÄľnÄ‚Â© upravit balÄ‚Â­Ă„Ĺ¤ek na mÄ‚Â­ru?', 'Ano. KaÄąÄľdÄ‚Ë‡ svatba nebo akce je jinÄ‚Ë‡, proto je moÄąÄľnÄ‚Â© rozsah focenÄ‚Â­, videa, poĂ„Ĺ¤et hodin i typ vÄ‚ËťstupÄąĹ» upravit individuÄ‚Ë‡lnĂ„â€ş.'],
];
$faqStmt = $pdo->prepare('INSERT INTO faq_items (site_id, question, answer, sort_order, is_active) VALUES (?, ?, ?, ?, 1)');
$faqOrder = 0;
foreach ($faqItems as [$faqQ, $faqA]) {
    $faqStmt->execute([$siteId, $faqQ, $faqA, ++$faqOrder]);
}
echo 'FAQ items seeded: ' . count($faqItems) . "\n";


// Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬ Publish Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬Ă˘â€ťâ‚¬

$pub    = new PublishService();
$result = $pub->publishSite($siteId, $userId);
$errors = $result['errors'] ?? [];
echo 'publishSite done. Errors: ' . count($errors) . "\n";
foreach ($errors as $e) {
    echo '  ERROR: ' . $e . "\n";
}
echo "Seeding complete.\n";
