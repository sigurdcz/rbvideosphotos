<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\PublishService;

$pdo = Database::getInstance();

if (in_array('--fresh', $argv ?? [], true)) {
    foreach (['publish_history','seo_settings','content_blocks','media_variants','media','pages','templates','redirects','sites','users'] as $t) {
        $pdo->exec("DELETE FROM $t");
    }
    echo "Tables cleared.\n";
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function upsertUser(PDO $pdo, string $email, string $pw, string $role = 'admin'): int
{
    $h = password_hash($pw, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT OR IGNORE INTO users (email,password_hash,role) VALUES (?,?,?)')->execute([$email,$h,$role]);
    $pdo->prepare('UPDATE users SET password_hash=?,role=?,updated_at=datetime(\'now\') WHERE email=?')->execute([$h,$role,$email]);
    $s = $pdo->prepare('SELECT id FROM users WHERE email=?');
    $s->execute([$email]);
    return (int)$s->fetchColumn();
}

function upsertSite(PDO $pdo, string $name, string $domain, string $ga, string $gtm, string $body): int
{
    $s = $pdo->prepare('SELECT id FROM sites WHERE domain=?');
    $s->execute([$domain]);
    $id = $s->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE sites SET name=?,google_analytics_id=?,google_tag_manager_id=?,body_scripts=?,updated_at=datetime(\'now\') WHERE id=?')
            ->execute([$name,$ga,$gtm,$body,$id]);
        return (int)$id;
    }
    $pdo->prepare('INSERT INTO sites (name,domain,google_analytics_id,google_tag_manager_id,body_scripts) VALUES (?,?,?,?,?)')
        ->execute([$name,$domain,$ga,$gtm,$body]);
    return (int)$pdo->lastInsertId();
}

function tpl(PDO $pdo, int $siteId, string $name, string $html, string $css, string $js): int
{
    $s = $pdo->prepare('SELECT id FROM templates WHERE site_id=? AND name=?');
    $s->execute([$siteId,$name]);
    $id = $s->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE templates SET html_source=?,css_source=?,js_source=?,updated_at=datetime(\'now\') WHERE id=?')
            ->execute([$html,$css,$js,$id]);
        return (int)$id;
    }
    $pdo->prepare('INSERT INTO templates (site_id,name,html_source,css_source,js_source) VALUES (?,?,?,?,?)')
        ->execute([$siteId,$name,$html,$css,$js]);
    return (int)$pdo->lastInsertId();
}

function addPage(PDO $pdo, int $siteId, int $tplId, string $title, string $slug, string $urlPath): int
{
    $pdo->prepare('INSERT OR IGNORE INTO pages (site_id,template_id,title,slug,url_path,status) VALUES (?,?,?,?,?,\'published\')')
        ->execute([$siteId,$tplId,$title,$slug,$urlPath]);
    $pdo->prepare('UPDATE pages SET template_id=?,title=?,url_path=?,status=\'published\',updated_at=datetime(\'now\') WHERE site_id=? AND slug=?')
        ->execute([$tplId,$title,$urlPath,$siteId,$slug]);
    $s = $pdo->prepare('SELECT id FROM pages WHERE site_id=? AND slug=?');
    $s->execute([$siteId,$slug]);
    return (int)$s->fetchColumn();
}

function block(PDO $pdo, int $pid, string $key, string $type, string $label, string $content, int $sort = 0): void
{
    $pdo->prepare('INSERT OR REPLACE INTO content_blocks (page_id,block_key,block_type,label,content,sort_order) VALUES (?,?,?,?,?,?)')
        ->execute([$pid,$key,$type,$label,$content,$sort]);
}

function seo(PDO $pdo, int $pid, array $d): void
{
    $pdo->prepare('INSERT OR REPLACE INTO seo_settings (page_id,meta_title,meta_description,meta_keywords,canonical_url,robots_index,robots_follow,og_title,og_description,twitter_title,twitter_description,schema_type,schema_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
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

// ─── CSS (wireframe base) ─────────────────────────────────────────────────────

$baseCss = <<<'CSS'
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:sans-serif;color:#222;background:#fff;line-height:1.6;font-size:16px}
.container{max-width:960px;margin:0 auto;padding:0 20px}
a{color:#c4963c;text-decoration:none}
h1{font-size:2rem;margin-bottom:.5rem}
h2{font-size:1.4rem;margin-bottom:.75rem;color:#222}
p{color:#555;margin-bottom:1rem}
.nav{background:#111;padding:14px 0}
.nav-inner{max-width:960px;margin:0 auto;padding:0 20px;display:flex;gap:20px;align-items:center}
.nav-inner a{color:rgba(255,255,255,.6);font-size:13px;font-weight:600}
.brand{font-weight:700;margin-right:auto;color:#fff !important}
.hero{background:#1a1a1a;color:#fff;padding:64px 0}
.hero h1{color:#fff;font-size:2.2rem}
.hero p{color:rgba(255,255,255,.6);max-width:600px}
.hero-tag{display:block;font-size:11px;color:#c4963c;text-transform:uppercase;letter-spacing:.15em;margin-bottom:14px}
.section{padding:60px 0}
.section-dark{background:#1a1a1a;padding:60px 0}
.section-dark h2{color:#fff}
.section-dark p{color:rgba(255,255,255,.6)}
footer{background:#111;color:rgba(255,255,255,.45);padding:28px 20px;text-align:center;font-size:13px;margin-top:0}
.btn{display:inline-block;background:#c4963c;color:#fff !important;padding:10px 22px;font-size:13px;font-weight:600;margin-top:16px}
#scroll-top{position:fixed;bottom:24px;right:24px;background:#c4963c;color:#fff;border:none;width:40px;height:40px;border-radius:50%;cursor:pointer;font-size:18px;opacity:0;visibility:hidden;transition:opacity .25s}
#scroll-top.vis{opacity:1;visibility:visible}
CSS;

$baseJs = <<<'JS'
(function(){
  var b=document.getElementById("scroll-top");
  if(!b)return;
  window.addEventListener("scroll",function(){b.classList.toggle("vis",window.scrollY>300);},{passive:true});
})();
JS;

// ─── Site + User ──────────────────────────────────────────────────────────────

$gtmNoscript = '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-542MMSL" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';

$userId = upsertUser($pdo, 'admin@rbphotos.cz', 'admin123');
$siteId = upsertSite($pdo, 'RB Photos & Videos', 'https://rbvideosphotos.kvalitne.cz', 'G-BD4BS9R4JZ', 'GTM-542MMSL', $gtmNoscript);

// ─── Template: Homepage ───────────────────────────────────────────────────────

$htmlHome = <<<'HTML'
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
<nav class="nav"><div class="nav-inner">
  <a href="/" class="brand">RB Photos &amp; Videos</a>
  <a href="/cenik">Cen&#237;k</a>
  <a href="/faq">FAQ</a>
  <a href="/blog">Blog</a>
  <a href="#kontakt">Kontakt</a>
</div></nav>
<header class="hero"><div class="container">
  <span class="hero-tag" data-block="hero_tag"></span>
  <h1 data-block="hero_h1"></h1>
  <p data-block="hero_sub"></p>
  <a href="tel:792483583" class="btn">&#9742; 792&#160;483&#160;583</a>
</div></header>
<section class="section" id="kontakt"><div class="container">
  <h2>Kontakt</h2>
  <p>Olomouc &bull; Prost&#283;jov &bull; P&#345;erov &bull; cel&aacute; &#268;R</p>
  <p><a href="tel:792483583">792&#160;483&#160;583</a> &nbsp;&bull;&nbsp; <a href="tel:604225032">604&#160;225&#160;032</a></p>
  <p><a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener">Instagram</a> &nbsp;&bull;&nbsp; <a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener">Facebook</a></p>
</div></section>
<footer><p>&copy; 2025 RB Photos &amp; Videos &mdash; Olomouc</p></footer>
<button id="scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})">&#8593;</button>
</body>
</html>
HTML;

$tplHome = tpl($pdo, $siteId, 'Homepage', $htmlHome, $baseCss, $baseJs);

// ─── Template: Ceník ──────────────────────────────────────────────────────────

$htmlCenik = <<<'HTML'
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
</head>
<body>
<nav class="nav"><div class="nav-inner">
  <a href="/" class="brand">RB Photos &amp; Videos</a>
  <a href="/cenik">Cen&#237;k</a>
  <a href="/faq">FAQ</a>
  <a href="/blog">Blog</a>
</div></nav>
<header class="hero"><div class="container">
  <h1 data-block="hero_heading"></h1>
  <p data-block="hero_intro"></p>
</div></header>
<section class="section"><div class="container">
  <h2 data-block="pricing_weddings_heading"></h2>
  <p data-block="pricing_weddings_sub"></p>
  <h2 data-block="pricing_other_heading"></h2>
  <p data-block="pricing_other_sub"></p>
</div></section>
<section class="section-dark"><div class="container">
  <h2 data-block="cta_heading"></h2>
  <p data-block="cta_sub"></p>
  <a href="tel:792483583" class="btn">&#128222; 792&#160;483&#160;583</a>
</div></section>
<footer><p>&copy; 2025 RB Photos &amp; Videos</p></footer>
<button id="scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})">&#8593;</button>
</body>
</html>
HTML;

$tplCenik = tpl($pdo, $siteId, 'Ceník', $htmlCenik, $baseCss, $baseJs);

// ─── Template: FAQ ────────────────────────────────────────────────────────────

$htmlFaq = <<<'HTML'
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
</head>
<body>
<nav class="nav"><div class="nav-inner">
  <a href="/" class="brand">RB Photos &amp; Videos</a>
  <a href="/cenik">Cen&#237;k</a>
  <a href="/faq">FAQ</a>
  <a href="/blog">Blog</a>
</div></nav>
<header class="hero"><div class="container">
  <h1 data-block="hero_heading"></h1>
  <p data-block="hero_sub"></p>
</div></header>
<section class="section"><div class="container">
  <p><a href="/cenik" class="btn">Zobrazit cen&#237;k</a></p>
</div></section>
<footer><p>&copy; 2025 RB Photos &amp; Videos</p></footer>
<button id="scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})">&#8593;</button>
</body>
</html>
HTML;

$tplFaq = tpl($pdo, $siteId, 'FAQ', $htmlFaq, $baseCss, $baseJs);

// ─── Template: Blog listing ───────────────────────────────────────────────────

$htmlBlog = <<<'HTML'
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
</head>
<body>
<nav class="nav"><div class="nav-inner">
  <a href="/" class="brand">RB Photos &amp; Videos</a>
  <a href="/cenik">Cen&#237;k</a>
  <a href="/faq">FAQ</a>
  <a href="/blog">Blog</a>
</div></nav>
<header class="hero"><div class="container">
  <h1 data-block="hero_heading"></h1>
  <p data-block="hero_sub"></p>
</div></header>
<section class="section"><div class="container">
  <p><a href="/blog/priprava-na-svatebni-foceni">10 tip&#367;, jak se p&#345;ipravit na dokonal&eacute; svatebn&#237; focen&#237; &rarr;</a></p>
  <p><a href="/blog/rodinne-foceni-s-detmi">Rodinn&eacute; focen&#237;: jak dostat p&#345;irozen&eacute; sn&#237;mky i s mal&#253;mi d&#283;tmi &rarr;</a></p>
</div></section>
<footer><p>&copy; 2025 RB Photos &amp; Videos</p></footer>
<button id="scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})">&#8593;</button>
</body>
</html>
HTML;

$tplBlog = tpl($pdo, $siteId, 'Blog listing', $htmlBlog, $baseCss, $baseJs);

// ─── Template: Blog article ───────────────────────────────────────────────────

$articleCss = $baseCss . <<<'CSS'

.article-meta{display:flex;gap:12px;align-items:center;margin-bottom:20px;font-size:13px}
.article-cat{background:#c4963c;color:#fff;padding:3px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.1em}
.article-date{color:rgba(255,255,255,.4)}
.article-thumb{position:relative;padding-bottom:56.25%;background:#000;margin:0 0 0}
.article-thumb iframe{position:absolute;inset:0;width:100%;height:100%;border:none}
.thumb-wrap{background:#141414;padding:0 0 40px}
.article-section{margin-bottom:36px}
.article-section h2{font-size:1.2rem;font-weight:700;margin-bottom:10px;padding-left:14px;border-left:3px solid #c4963c}
.article-section div{color:#555;font-size:.97rem;line-height:1.75}
CSS;

$htmlArticle = <<<'HTML'
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
</head>
<body>
<nav class="nav"><div class="nav-inner">
  <a href="/" class="brand">RB Photos &amp; Videos</a>
  <a href="/cenik">Cen&#237;k</a>
  <a href="/faq">FAQ</a>
  <a href="/blog">Blog</a>
</div></nav>
<header class="hero"><div class="container">
  <div class="article-meta">
    <span class="article-cat" data-block="article_cat"></span>
    <span class="article-date" data-block="article_date"></span>
  </div>
  <h1 data-block="article_h1"></h1>
  <p data-block="article_lead"></p>
</div></header>
<div class="thumb-wrap"><div class="container">
  <div class="article-thumb" data-block="article_thumb"></div>
</div></div>
<section class="section"><div class="container">
  <div class="article-section"><h2 data-block="section_1_h2"></h2><div data-block="section_1_body"></div></div>
  <div class="article-section"><h2 data-block="section_2_h2"></h2><div data-block="section_2_body"></div></div>
  <div class="article-section"><h2 data-block="section_3_h2"></h2><div data-block="section_3_body"></div></div>
  <div class="article-section"><h2 data-block="section_4_h2"></h2><div data-block="section_4_body"></div></div>
  <div class="article-section"><h2 data-block="section_5_h2"></h2><div data-block="section_5_body"></div></div>
</div></section>
<section class="section-dark"><div class="container">
  <h2 data-block="cta_heading"></h2>
  <p data-block="cta_sub"></p>
  <a href="tel:792483583" class="btn">&#9742; 792&#160;483&#160;583</a>
</div></section>
<footer><p>&copy; 2025 RB Photos &amp; Videos</p></footer>
<button id="scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})">&#8593;</button>
</body>
</html>
HTML;

$tplArticle = tpl($pdo, $siteId, 'Blog article', $htmlArticle, $articleCss, $baseJs);

// ─── Pages ────────────────────────────────────────────────────────────────────

$pidHome  = addPage($pdo, $siteId, $tplHome,    'Fotograf & Videograf Olomouc | RB Photos & Videos',                         '',                               '/');
$pidCenik = addPage($pdo, $siteId, $tplCenik,   'Ceník fotografa Olomouc | RB Photos & Videos',                             'cenik',                          '/cenik');
$pidFaq   = addPage($pdo, $siteId, $tplFaq,     'Časté otázky | Fotograf Olomouc — RB Photos & Videos',                     'faq',                            '/faq');
$pidBlog  = addPage($pdo, $siteId, $tplBlog,    'Blog | RB Photos & Videos',                                                'blog',                           '/blog');
$pidArt1  = addPage($pdo, $siteId, $tplArticle, '10 tipů jak se připravit na dokonalé svatební focení | RB Photos & Videos', 'blog/priprava-na-svatebni-foceni','/blog/priprava-na-svatebni-foceni');
$pidArt2  = addPage($pdo, $siteId, $tplArticle, 'Rodinné focení s dětmi — tipy pro přirozené snímky | RB Photos & Videos',  'blog/rodinne-foceni-s-detmi',    '/blog/rodinne-foceni-s-detmi');

// ─── Content blocks: Homepage ─────────────────────────────────────────────────

block($pdo, $pidHome, 'hero_tag', 'text',     'Hero Tag',  'Fotograf & Videograf — Olomouc', 1);
block($pdo, $pidHome, 'hero_h1',  'text',     'Hero H1',   'Fotograf & Videograf Olomouc, který zachytí váš příběh', 2);
block($pdo, $pidHome, 'hero_sub', 'textarea', 'Hero Sub',  'Profesionální svatební fotograf Olomouc s 8+ lety praxe. Rodinné focení, portréty, reklamní videa — po celé ČR. Zálohy 5 let zdarma.', 3);

// ─── Content blocks: Ceník ───────────────────────────────────────────────────

block($pdo, $pidCenik, 'hero_heading',            'text',     'Hero Nadpis',       'Ceník fotografických a video služeb — Olomouc', 1);
block($pdo, $pidCenik, 'hero_intro',              'textarea', 'Hero Intro',        'Transparentní ceny bez překvapení. Každá zakázka je individuální — vždy se domluvíme na balíčku přesně pro vás. Přes 150 spokojených klientů, 8 let praxe.', 2);
block($pdo, $pidCenik, 'pricing_weddings_heading','text',     'Ceník Svatby',      'Svatební fotograf Olomouc — ceník', 3);
block($pdo, $pidCenik, 'pricing_weddings_sub',    'textarea', 'Ceník Svatby Info', 'Základní balíček od 13 000 Kč (do 4 hodin) · Kompletní svatební den od 22 000 Kč (6–8 hod) · Rozšířený balíček od 27 000 Kč. Individuální nabídka na míru — zavolejte nám.', 4);
block($pdo, $pidCenik, 'pricing_other_heading',   'text',     'Ceník Ostatní',     'Rodinné, portrétní a firemní focení', 5);
block($pdo, $pidCenik, 'pricing_other_sub',       'textarea', 'Ceník Ostatní Info','Rodinné focení od 4 500 Kč · Portrétní focení od 3 000 Kč · Firemní focení od 6 000 Kč · Zásnuby & Maternity od 3 500 Kč · Videografie od 8 000 Kč.', 6);
block($pdo, $pidCenik, 'cta_heading',             'text',     'CTA Nadpis',        'Rezervujte termín ještě dnes', 7);
block($pdo, $pidCenik, 'cta_sub',                 'textarea', 'CTA Sub',           'Oblíbené soboty se obsazují rok dopředu — čím dříve, tím větší výběr. Zavolejte nebo napište, odpovíme do 24 hodin.', 8);

// ─── Content blocks: FAQ ─────────────────────────────────────────────────────

block($pdo, $pidFaq, 'hero_heading', 'text',     'Hero Nadpis', 'Časté otázky', 1);
block($pdo, $pidFaq, 'hero_sub',     'textarea', 'Hero Sub',    'Vše, co potřebujete vědět před rezervací. Ceny, termíny, příprava i to, co se bojíte zeptat.', 2);

// ─── Content blocks: Blog ────────────────────────────────────────────────────

block($pdo, $pidBlog, 'hero_heading', 'text',     'Hero Nadpis', 'Tipy a inspirace', 1);
block($pdo, $pidBlog, 'hero_sub',     'textarea', 'Hero Sub',    'Rady od profesionálního fotografa — příprava na focení, tipy pro klienty a pohled do naší práce.', 2);

// ─── Content blocks: Article 1 ───────────────────────────────────────────────

block($pdo, $pidArt1, 'article_cat',    'text',     'Kategorie',   'Svatební fotografie', 1);
block($pdo, $pidArt1, 'article_date',   'text',     'Datum',       '12. dubna 2025', 2);
block($pdo, $pidArt1, 'article_h1',     'text',     'Titulek',     '10 tipů, jak se připravit na dokonalé svatební focení', 3);
block($pdo, $pidArt1, 'article_lead',   'textarea', 'Perex',       'Váš svatební den je jedním z nejdůležitějších okamžiků života. S těmito tipy zajistíte, že každý záběr bude dokonalý — a vy si ten den budete užívat bez zbytečného stresu.', 4);
block($pdo, $pidArt1, 'article_thumb',  'html',     'Video',       '<iframe src="https://player.vimeo.com/video/1133996815?wmode=opaque" allowfullscreen="allowfullscreen" loading="lazy" title="Uk&#225;zka na&#353;&#237; pr&#225;ce"></iframe>', 5);
block($pdo, $pidArt1, 'section_1_h2',  'text',     'Sekce 1 H2',  '1. Konzultace s fotografem — co nejdříve', 10);
block($pdo, $pidArt1, 'section_1_body','textarea',  'Sekce 1',     'Osobní schůzka s fotografem ještě před svatbou je absolutní základ. Promluvte si o místě obřadu, harmonogramu dne a vašich přáních. Dobrý fotograf vám pomůže sestavit realistický časový plán a doporučí nejlepší místa pro párové fotografie.', 11);
block($pdo, $pidArt1, 'section_2_h2',  'text',     'Sekce 2 H2',  '2. Naplánujte harmonogram s rezervou', 20);
block($pdo, $pidArt1, 'section_2_body','textarea',  'Sekce 2',     'Jednou z nejčastějších chyb je podceňování času. Přidejte ke každé části dne záložní čas — vlasy a make-up trvají déle, než čekáte, hosté přicházejí postupně a skupinové fotografie s celou rodinou zaberou alespoň 20–30 minut.', 21);
block($pdo, $pidArt1, 'section_3_h2',  'text',     'Sekce 3 H2',  '3. Připravte detaily s láskou', 30);
block($pdo, $pidArt1, 'section_3_body','textarea',  'Sekce 3',     'Prsteny, kytice, pozvánky, šperky, parfém — to jsou detaily, které dělají snímky výjimečnými. Připravte je na pevnou podložku nebo neutrální povrch ráno, než začne chaos příprav.', 31);
block($pdo, $pidArt1, 'section_4_h2',  'text',     'Sekce 4 H2',  '4. Využijte zlatou hodinu — tajemství krásného světla', 40);
block($pdo, $pidArt1, 'section_4_body','textarea',  'Sekce 4',     'Hodina před západem slunce je čas, kdy je světlo nejkrásnější — teplé, měkké, kinematografické. Pokud je to možné, naplánujte párové focení právě na tento čas. V Olomouci máme spoustu míst, kde zlatá hodinka vypadá úchvatně.', 41);
block($pdo, $pidArt1, 'section_5_h2',  'text',     'Sekce 5 H2',  '5. Zůstaňte sami sebou — přirozenost je základ', 50);
block($pdo, $pidArt1, 'section_5_body','textarea',  'Sekce 5',     'Nejkrásnější fotografie jsou ty přirozené. Nehledejte do objektivu, hýbejte se, smějte se, šeptejte si. Zkušený fotograf zachytí skutečné emoce — ty se nedají režírovat.', 51);
block($pdo, $pidArt1, 'cta_heading',   'text',     'CTA Nadpis',  'Chcete dokonalé svatební fotografie?', 60);
block($pdo, $pidArt1, 'cta_sub',       'textarea', 'CTA Sub',     'Oblíbené soboty se obsazují rok i více dopředu. Kontaktujte nás co nejdříve a zajistěte si váš termín.', 61);

// ─── Content blocks: Article 2 ───────────────────────────────────────────────

block($pdo, $pidArt2, 'article_cat',    'text',     'Kategorie',   'Rodinné focení', 1);
block($pdo, $pidArt2, 'article_date',   'text',     'Datum',       '28. března 2025', 2);
block($pdo, $pidArt2, 'article_h1',     'text',     'Titulek',     'Rodinné focení: jak dostat přirozené snímky i s malými dětmi', 3);
block($pdo, $pidArt2, 'article_lead',   'textarea', 'Perex',       'Malé děti a fotoaparát? Zní to jako recept na chaos. Ale přesně v tom chaosu se skrývají ty nejkrásnější momenty. Přečtěte si, jak z rodinného focení udělat zábavný zážitek.', 4);
block($pdo, $pidArt2, 'article_thumb',  'html',     'Video',       '<iframe src="https://player.vimeo.com/video/1122538281?wmode=opaque" allowfullscreen="allowfullscreen" loading="lazy" title="Uk&#225;zka na&#353;&#237; pr&#225;ce"></iframe>', 5);
block($pdo, $pidArt2, 'section_1_h2',  'text',     'Sekce 1 H2',  '1. Správný čas je základ', 10);
block($pdo, $pidArt2, 'section_1_body','textarea',  'Sekce 1',     'S malými dětmi je načasování vše. Naplánujte focení na dobu, kdy jsou děti nejvíce odpočaté — typicky dopoledne po snídani nebo krátce po odpoledním spánku. Nikdy ne těsně před jídlem nebo spaním.', 11);
block($pdo, $pidArt2, 'section_2_h2',  'text',     'Sekce 2 H2',  '2. Oblečení v harmonických barvách', 20);
block($pdo, $pidArt2, 'section_2_body','textarea',  'Sekce 2',     'Nemusíte mít všichni stejné tričko. Stačí zvolit dva nebo tři harmonické tóny a kombinovat je volně. Vyhněte se agresivním vzorům a nápisům. Neutrální barvy (béžová, terakota, modrá) ve spojení s přírodním prostředím fungují vždy skvěle.', 21);
block($pdo, $pidArt2, 'section_3_h2',  'text',     'Sekce 3 H2',  '3. Hrajte si — ignorujte fotoaparát', 30);
block($pdo, $pidArt2, 'section_3_body','textarea',  'Sekce 3',     'Nejhorší, co rodiče dělají, je říkat dětem: „Koukej na pana fotografa a usměj se." Výsledek je nucený úsměv a strnulé pózy. Místo toho se věnujte dětem přirozeně — šimrejte, honiček, čtěte pohádku.', 31);
block($pdo, $pidArt2, 'section_4_h2',  'text',     'Sekce 4 H2',  '4. Příroda je nejlepší kulisa', 40);
block($pdo, $pidArt2, 'section_4_body','textarea',  'Sekce 4',     'Parky, lesy, louky nebo zahrada — příroda je pro rodinné focení ideální prostředí. Děti mají prostor k pohybu, přirozené světlo je krásné. V Olomouci máme nádherné lokace: Smetanovy sady, Tabulový vrch, Bystřice pod Hostýnem.', 41);
block($pdo, $pidArt2, 'section_5_h2',  'text',     'Sekce 5 H2',  '5. Krátká session — méně je více', 50);
block($pdo, $pidArt2, 'section_5_body','textarea',  'Sekce 5',     'S malými dětmi plánujte focení na 45–60 minut, ne na celé odpoledne. Kratší čas znamená, že děti jsou svěží a spolupracují. Pokud máte více dětí různého věku, začněte s nejmenšími — jejich výdrž je nejkratší.', 51);
block($pdo, $pidArt2, 'cta_heading',   'text',     'CTA Nadpis',  'Rezervujte rodinné focení', 60);
block($pdo, $pidArt2, 'cta_sub',       'textarea', 'CTA Sub',     'Přirozené, krásné rodinné snímky z Olomouce a okolí. Zavolejte nebo napište — domluvíme termín i lokaci přesně podle vás.', 61);

// ─── SEO settings ─────────────────────────────────────────────────────────────

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

seo($pdo, $pidArt1, [
    'title'       => '10 tipů jak se připravit na dokonalé svatební focení | RB Photos & Videos',
    'desc'        => 'Profesionální fotograf z Olomouce sdílí 10 klíčových tipů, jak se připravit na svatební focení. Od harmonogramu až po zlatou hodinu.',
    'kw'          => 'příprava na svatební focení, tipy od fotografa, svatební fotografie Olomouc',
    'canonical'   => '/blog/priprava-na-svatebni-foceni',
    'schema_type' => 'BlogPosting',
    'schema_json' => '{"@context":"https://schema.org","@type":"BlogPosting","headline":"10 tipů jak se připravit na dokonalé svatební focení | RB Photos & Videos","datePublished":"2025-04-12","dateModified":"2025-04-12","author":{"@type":"Organization","name":"RB Photos & Videos","url":"' . $d . '/"},"publisher":{"@type":"Organization","name":"RB Photos & Videos","url":"' . $d . '/"},"url":"' . $d . '/blog/priprava-na-svatebni-foceni"}',
]);

seo($pdo, $pidArt2, [
    'title'       => 'Rodinné focení s dětmi — tipy pro přirozené snímky | RB Photos & Videos',
    'desc'        => 'Jak nafotit přirozené rodinné fotografie i s malými dětmi? Tipy profesionálního fotografa z Olomouce — správný čas, lokace, oblečení.',
    'kw'          => 'rodinné focení Olomouc, focení s dětmi, přirozené rodinné fotografie',
    'canonical'   => '/blog/rodinne-foceni-s-detmi',
    'schema_type' => 'BlogPosting',
    'schema_json' => '{"@context":"https://schema.org","@type":"BlogPosting","headline":"Rodinné focení s dětmi — tipy pro přirozené snímky | RB Photos & Videos","datePublished":"2025-03-28","dateModified":"2025-03-28","author":{"@type":"Organization","name":"RB Photos & Videos","url":"' . $d . '/"},"publisher":{"@type":"Organization","name":"RB Photos & Videos","url":"' . $d . '/"},"url":"' . $d . '/blog/rodinne-foceni-s-detmi"}',
]);

// ─── Publish ──────────────────────────────────────────────────────────────────

$pub    = new PublishService();
$result = $pub->publishSite($siteId, $userId);
$errors = $result['errors'] ?? [];
echo 'publishSite done. Errors: ' . count($errors) . "\n";
foreach ($errors as $e) {
    echo '  ERROR: ' . $e . "\n";
}
echo "Seeding complete.\n";
