<?php
/**
 * Refactor: blog v navigaci, blog index stránka (/blog),
 * oprava video bloků, interní linking, blog preview na homepage.
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

$db = new PDO('sqlite:' . BASE_PATH . '/storage/database.sqlite', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA foreign_keys=ON');

// ═══════════════════════════════════════════════════════════════════════════════
// 1. HOMEPAGE TEMPLATE — Blog do nav + Blog preview sekce
// ═══════════════════════════════════════════════════════════════════════════════
$hp = $db->query('SELECT html_source, css_source FROM templates WHERE id=1')->fetch();
$hpHtml = $hp['html_source'];
$hpCss  = $hp['css_source'];

// Přidat Blog do homepage navigace (před Kontakt)
if (!str_contains($hpHtml, 'href="/blog"')) {
    $hpHtml = str_replace(
        '<li><a href="#kontakt" class="btn-nav">Kontakt</a></li>',
        '<li><a href="/blog">Blog</a></li>' . "\n        " . '<li><a href="#kontakt" class="btn-nav">Kontakt</a></li>',
        $hpHtml
    );
}

// Blog preview sekce před footerem
if (!str_contains($hpHtml, 'id="blog"')) {
    $blogSection = <<<'BHTML'

  <!-- BLOG PREVIEW -->
  <section class="section" id="blog">
    <div class="container">
      <div class="section-header centered">
        <h2>Tipy a inspirace</h2>
        <p>Rady od profesionálního fotografa přímo pro vás.</p>
      </div>
      <div class="blog-preview-grid">
        <a href="/blog/priprava-na-svatebni-foceni" class="blog-card">
          <span class="blog-card-cat">Svatební fotografie</span>
          <h3>10 tipů, jak se připravit na dokonalé svatební focení</h3>
          <p>Váš svatební den je jedním z nejdůležitějších okamžiků života. S těmito tipy zajistíte, že každý záběr bude dokonalý.</p>
          <span class="blog-read-more">Číst článek &#8594;</span>
        </a>
        <a href="/blog/rodinne-foceni-s-detmi" class="blog-card">
          <span class="blog-card-cat">Rodinné focení</span>
          <h3>Rodinné focení: přirozené snímky i s malými dětmi</h3>
          <p>Právě v chaosu se skrývají ty nejkrásnější momenty. Přečtěte si, jak z focení udělat zábavný zážitek pro celou rodinu.</p>
          <span class="blog-read-more">Číst článek &#8594;</span>
        </a>
      </div>
      <div style="text-align:center;margin-top:36px">
        <a href="/blog" class="btn btn-outline-dark">Všechny články</a>
      </div>
    </div>
  </section>

BHTML;
    $hpHtml = str_replace('  <!-- FOOTER -->', $blogSection . '  <!-- FOOTER -->', $hpHtml);
}

// Blog preview CSS
if (!str_contains($hpCss, 'blog-preview-grid')) {
    $hpCss .= <<<'BCSS'

/* BLOG PREVIEW */
.blog-preview-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.blog-card { display: block; background: #fff; border: 1px solid var(--border); padding: 32px 28px; transition: all .22s; }
.blog-card:hover { border-color: var(--gold); transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,.08); }
.blog-card-cat { display: block; font-size: 10.5px; font-weight: 700; color: var(--gold); letter-spacing: .18em; text-transform: uppercase; margin-bottom: 14px; }
.blog-card h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 10px; color: var(--text); line-height: 1.4; }
.blog-card p { font-size: .88rem; color: var(--muted); line-height: 1.7; margin-bottom: 18px; }
.blog-read-more { font-size: 11px; font-weight: 700; color: var(--gold); letter-spacing: .1em; text-transform: uppercase; }
@media (max-width: 600px) { .blog-preview-grid { grid-template-columns: 1fr; } }
BCSS;
}

$db->prepare('UPDATE templates SET html_source=?, css_source=? WHERE id=1')
   ->execute([$hpHtml, $hpCss]);
echo "✅ Homepage: Blog v nav + blog preview sekce.\n";

// ═══════════════════════════════════════════════════════════════════════════════
// 2. BLOG ARTICLE TEMPLATE — Blog do nav, oprava video JS, related articles
// ═══════════════════════════════════════════════════════════════════════════════
$bt = $db->query('SELECT html_source, css_source, js_source FROM templates WHERE id=2')->fetch();
$btHtml = $bt['html_source'];
$btCss  = $bt['css_source'];
$btJs   = $bt['js_source'];

// Blog do blog-article navigace
if (!str_contains($btHtml, 'href="/blog"')) {
    $btHtml = str_replace(
        '<li><a href="/#kontakt" class="btn-nav">Kontakt</a></li>',
        '<li><a href="/blog">Blog</a></li>' . "\n        " . '<li><a href="/#kontakt" class="btn-nav">Kontakt</a></li>',
        $btHtml
    );
}

// Opravit video embed: iframe s data-block → hidden span pro JS injekci
if (str_contains($btHtml, 'data-block="article_video_embed"')) {
    $btHtml = str_replace(
        '<iframe data-block="article_video_embed" src="https://player.vimeo.com/video/1133996815?wmode=opaque" allowfullscreen loading="lazy" title="Ukázka naší práce"></iframe>',
        '<span id="article-vid-id" data-block="article_video_id" style="display:none">1133996815</span>',
        $btHtml
    );
}

// Related articles + blog odkaz do sidebaru
if (!str_contains($btHtml, 'id="related-articles"')) {
    $relatedCard = <<<'RHTML'

      <div class="sidebar-card" id="related-articles">
        <p class="sidebar-label">Další čtení</p>
        <nav class="related-links">
          <a href="/blog/priprava-na-svatebni-foceni">10 tipů pro svatební focení</a>
          <a href="/blog/rodinne-foceni-s-detmi">Rodinné focení s malými dětmi</a>
          <a href="/blog" class="related-all">Všechny články &#8594;</a>
        </nav>
      </div>
RHTML;
    $btHtml = str_replace('    </aside>', $relatedCard . "\n    </aside>", $btHtml);
}

// CSS pro related articles
if (!str_contains($btCss, 'related-links')) {
    $btCss .= <<<'RCSS'

/* RELATED ARTICLES */
.related-links { display: flex; flex-direction: column; gap: 2px; margin-top: 10px; }
.related-links a { display: block; font-size: .87rem; color: var(--text); font-weight: 500; padding: 9px 0; border-bottom: 1px solid var(--border); transition: color .2s; }
.related-links a:last-child { border-bottom: none; }
.related-links a:hover { color: var(--gold); }
.related-all { font-size: 11px !important; color: var(--gold) !important; font-weight: 700 !important; letter-spacing: .08em; text-transform: uppercase; }
RCSS;
}

// JS: video inject + skrytí prázdné video sekce
if (!str_contains($btJs ?? '', 'article-vid-id')) {
    $btJs = ($btJs ?? '') . <<<'VJS'

(function() {
  var el = document.getElementById('article-vid-id');
  var wrapSection = el ? el.closest('.article-thumb-wrap') : null;
  if (!el || !el.textContent.trim()) {
    if (wrapSection) wrapSection.style.display = 'none';
    return;
  }
  var wrap = el.parentElement;
  var iframe = document.createElement('iframe');
  iframe.src = 'https://player.vimeo.com/video/' + encodeURIComponent(el.textContent.trim()) + '?wmode=opaque';
  iframe.setAttribute('allowfullscreen', '');
  iframe.setAttribute('loading', 'lazy');
  iframe.title = 'Video ukázka';
  iframe.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:none';
  wrap.appendChild(iframe);
})();
VJS;
}

$db->prepare('UPDATE templates SET html_source=?, css_source=?, js_source=? WHERE id=2')
   ->execute([$btHtml, $btCss, $btJs]);
echo "✅ Blog article template: nav + video JS + related articles.\n";

// ═══════════════════════════════════════════════════════════════════════════════
// 3. OPRAVIT BLOKY — article_video_embed → article_video_id (jen Vimeo ID)
// ═══════════════════════════════════════════════════════════════════════════════
$db->exec("UPDATE content_blocks
           SET block_key='article_video_id', block_type='text', content='1133996815'
           WHERE page_id=2 AND block_key='article_video_embed'");
$db->exec("UPDATE content_blocks
           SET block_key='article_video_id', block_type='text', content='1122538281'
           WHERE page_id=3 AND block_key='article_video_embed'");
echo "✅ Content blocks: article_video_embed → article_video_id opraveno.\n";

// ═══════════════════════════════════════════════════════════════════════════════
// 4. BLOG INDEX TEMPLATE (ID 3) + STRÁNKA /blog
// ═══════════════════════════════════════════════════════════════════════════════
$blogIndexHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blog &#8212; RB Photos &amp; Videos</title>
</head>
<body>

  <nav class="navbar-blog">
    <div class="nb-inner">
      <a href="/" class="logo">RB<span class="logo-sep">&nbsp;&middot;&nbsp;</span>Photos &amp; Videos</a>
      <ul class="nav-links">
        <li><a href="/#sluzby">Služby</a></li>
        <li><a href="/#videa">Videa</a></li>
        <li><a href="/#reference">Reference</a></li>
        <li><a href="/blog" class="nav-active">Blog</a></li>
        <li><a href="/#kontakt" class="btn-nav">Kontakt</a></li>
      </ul>
    </div>
  </nav>

  <header class="bi-hero">
    <div class="container">
      <span class="bi-eyebrow">Tipy &amp; Inspirace</span>
      <h1>Blog</h1>
      <p>Rady a tipy od profesionálního fotografa z Olomouce — pro nevěsty, rodiny i firmy.</p>
    </div>
  </header>

  <main class="bi-main">
    <div class="container">
      <div class="bi-grid">

        <article class="bi-card">
          <a href="/blog/priprava-na-svatebni-foceni" class="bi-card-link">
            <div class="bi-card-media bi-media-wedding">
              <span class="bi-media-label">Svatební</span>
            </div>
            <div class="bi-card-body">
              <span class="bi-card-cat">Svatební fotografie</span>
              <h2>10 tipů, jak se připravit na dokonalé svatební focení</h2>
              <p>Váš svatební den je jedním z nejdůležitějších okamžiků života. S těmito tipy od profesionálního fotografa zajistíte, že každý záběr bude dokonalý — a vy si den budete moci naplno užívat.</p>
              <div class="bi-card-footer">
                <span class="bi-date">12. dubna 2025</span>
                <span class="bi-more">Číst článek &#8594;</span>
              </div>
            </div>
          </a>
        </article>

        <article class="bi-card">
          <a href="/blog/rodinne-foceni-s-detmi" class="bi-card-link">
            <div class="bi-card-media bi-media-family">
              <span class="bi-media-label">Rodinné</span>
            </div>
            <div class="bi-card-body">
              <span class="bi-card-cat">Rodinné focení</span>
              <h2>Rodinné focení: jak dostat přirozené snímky i s malými dětmi</h2>
              <p>Malé děti a fotoaparát? Právě v tom chaosu se skrývají ty nejkrásnější momenty. Přečtěte si, jak z rodinného focení udělat zábavný zážitek a domů si odnést snímky na celý život.</p>
              <div class="bi-card-footer">
                <span class="bi-date">28. března 2025</span>
                <span class="bi-more">Číst článek &#8594;</span>
              </div>
            </div>
          </a>
        </article>

      </div>
    </div>
  </main>

  <section class="bi-cta">
    <div class="container bi-cta-inner">
      <div>
        <h2>Rezervujte termín focení</h2>
        <p>Obsazujeme se rychle — kontaktujte nás co nejdříve a zajistěte si váš termín.</p>
      </div>
      <div class="bi-cta-btns">
        <a href="tel:792483583" class="btn btn-primary">792 483 583</a>
        <a href="/#kontakt" class="btn btn-outline-light">Napsat zprávu</a>
      </div>
    </div>
  </section>

  <footer class="footer">
    <div class="container footer-inner">
      <div class="footer-brand">
        <span class="logo">RB<span class="logo-sep">&nbsp;&middot;&nbsp;</span>Photos &amp; Videos</span>
        <p>Profesionální fotografie a videografie &#8212; Olomouc</p>
      </div>
      <div class="footer-links">
        <a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener">Facebook</a>
        <a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener">Instagram</a>
        <a href="https://www.youtube.com/channel/UC5miHVzPxIs2MjEsoPAzGCw/videos" target="_blank" rel="noopener">YouTube</a>
        <a href="tel:792483583">792 483 583</a>
      </div>
      <p class="footer-copy">&copy; 2025 RB Photos &amp; Videos. Všechna práva vyhrazena.</p>
    </div>
  </footer>

</body>
</html>
HTML;

$blogIndexCss = <<<'CSS'
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --gold:   #c4963c;
  --gold-d: #a67c2a;
  --dark:   #0c0c0e;
  --dark2:  #141416;
  --light:  #f9f8f6;
  --text:   #1c1c1e;
  --muted:  #6b7280;
  --border: #e4e4e7;
  --radius: 3px;
}
body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; color: var(--text); background: #fff; line-height: 1.65; -webkit-font-smoothing: antialiased; }
a { color: inherit; text-decoration: none; }
.container { max-width: 1100px; margin: 0 auto; padding: 0 28px; }

/* NAV */
.navbar-blog { background: var(--dark); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid rgba(196,150,60,.12); }
.nb-inner { max-width: 1100px; margin: 0 auto; padding: 0 28px; display: flex; align-items: center; justify-content: space-between; height: 64px; }
.logo { font-size: 15px; font-weight: 700; color: #fff; letter-spacing: .08em; text-transform: uppercase; }
.logo-sep { color: var(--gold); font-weight: 300; }
.nav-links { display: flex; list-style: none; gap: 28px; align-items: center; }
.nav-links a { color: rgba(255,255,255,.5); font-size: 12px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; transition: color .2s; }
.nav-links a:hover { color: #fff; }
.nav-active { color: var(--gold) !important; }
.btn-nav { background: var(--gold) !important; color: #fff !important; padding: 8px 20px; border-radius: var(--radius); }

/* HERO */
.bi-hero { background: var(--dark); padding: 80px 0 68px; text-align: center; }
.bi-eyebrow { display: inline-block; font-size: 11px; font-weight: 700; color: var(--gold); letter-spacing: .2em; text-transform: uppercase; border: 1px solid rgba(196,150,60,.35); padding: 5px 18px; margin-bottom: 22px; }
.bi-hero h1 { font-size: clamp(2.2rem, 4vw, 3.2rem); font-weight: 800; color: #fff; margin-bottom: 14px; letter-spacing: -.03em; }
.bi-hero p { font-size: 1rem; color: rgba(255,255,255,.42); max-width: 540px; margin: 0 auto; }

/* GRID */
.bi-main { padding: 60px 0 80px; }
.bi-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }

/* CARD */
.bi-card { border: 1px solid var(--border); overflow: hidden; transition: all .22s; }
.bi-card:hover { border-color: var(--gold); box-shadow: 0 8px 36px rgba(0,0,0,.1); transform: translateY(-3px); }
.bi-card-link { display: block; }
.bi-card-media { aspect-ratio: 16/7; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
.bi-media-wedding { background: linear-gradient(135deg, #0f0c1a 0%, #1a0d2e 100%); }
.bi-media-wedding::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 40% 60%, rgba(196,150,60,.14) 0%, transparent 65%); }
.bi-media-family  { background: linear-gradient(135deg, #0a1520 0%, #0c1c30 100%); }
.bi-media-family::before  { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 60% 40%, rgba(196,150,60,.12) 0%, transparent 65%); }
.bi-media-label { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.18); letter-spacing: .25em; text-transform: uppercase; position: relative; z-index: 1; }
.bi-card-body { padding: 30px 28px 24px; }
.bi-card-cat { display: block; font-size: 10.5px; font-weight: 700; color: var(--gold); letter-spacing: .18em; text-transform: uppercase; margin-bottom: 12px; }
.bi-card h2 { font-size: 1.15rem; font-weight: 700; line-height: 1.4; margin-bottom: 10px; color: var(--text); }
.bi-card p { font-size: .9rem; color: var(--muted); line-height: 1.75; margin-bottom: 22px; }
.bi-card-footer { display: flex; align-items: center; justify-content: space-between; }
.bi-date { font-size: 11px; color: var(--muted); }
.bi-more { font-size: 11px; font-weight: 700; color: var(--gold); letter-spacing: .1em; text-transform: uppercase; }

/* CTA */
.bi-cta { background: var(--dark); padding: 72px 0; border-top: 1px solid rgba(196,150,60,.1); }
.bi-cta-inner { display: flex; align-items: center; justify-content: space-between; gap: 40px; flex-wrap: wrap; }
.bi-cta-inner h2 { font-size: 1.8rem; font-weight: 800; color: #fff; margin-bottom: 8px; letter-spacing: -.02em; }
.bi-cta-inner p { color: rgba(255,255,255,.4); font-size: .97rem; }
.bi-cta-btns { display: flex; gap: 12px; flex-wrap: wrap; flex-shrink: 0; }

/* BUTTONS */
.btn { display: inline-block; padding: 12px 26px; font-weight: 600; font-size: 12px; letter-spacing: .07em; text-transform: uppercase; border-radius: var(--radius); transition: all .22s; }
.btn-primary { background: var(--gold); color: #fff; }
.btn-primary:hover { background: var(--gold-d); }
.btn-outline-light { background: transparent; border: 1px solid rgba(255,255,255,.22); color: rgba(255,255,255,.75); }
.btn-outline-light:hover { border-color: rgba(255,255,255,.55); color: #fff; }

/* FOOTER */
.footer { background: var(--dark); padding: 48px 0 28px; border-top: 1px solid rgba(196,150,60,.08); }
.footer-inner { display: flex; flex-direction: column; align-items: center; gap: 20px; text-align: center; }
.footer-brand p { color: rgba(255,255,255,.28); font-size: .82rem; margin-top: 6px; }
.footer-links { display: flex; gap: 24px; flex-wrap: wrap; justify-content: center; }
.footer-links a { color: rgba(255,255,255,.3); font-size: .78rem; letter-spacing: .07em; text-transform: uppercase; transition: color .2s; }
.footer-links a:hover { color: var(--gold); }
.footer-copy { color: rgba(255,255,255,.18); font-size: .72rem; }

/* RESPONSIVE */
@media (max-width: 700px) {
  .bi-grid { grid-template-columns: 1fr; }
  .bi-cta-inner { flex-direction: column; text-align: center; }
  .bi-cta-btns { justify-content: center; }
  .nav-links { display: none; }
}
CSS;

$db->prepare('INSERT OR REPLACE INTO templates (id, site_id, name, html_source, css_source, js_source) VALUES (3, 1, ?, ?, ?, ?)')
   ->execute(['RB Photos & Videos — Blog Index', $blogIndexHtml, $blogIndexCss, '']);
echo "✅ Blog index šablona (ID 3) uložena.\n";

// Blog index stránka
$eb = $db->query("SELECT id FROM pages WHERE site_id=1 AND slug='blog-index'")->fetch();
if ($eb) {
    $db->prepare('DELETE FROM seo_settings WHERE page_id=?')->execute([$eb['id']]);
    $db->prepare('DELETE FROM content_blocks WHERE page_id=?')->execute([$eb['id']]);
    $db->prepare('DELETE FROM pages WHERE id=?')->execute([$eb['id']]);
}
$db->prepare("INSERT INTO pages (site_id, template_id, title, slug, url_path, language, status) VALUES (1, 3, ?, 'blog-index', 'blog', 'cs', 'published')")
   ->execute(['Blog — RB Photos & Videos']);
$blogPageId = (int)$db->lastInsertId();

$db->prepare("INSERT INTO seo_settings (page_id,meta_title,meta_description,meta_keywords,canonical_url,robots_index,robots_follow,og_title,og_description,twitter_title,twitter_description,schema_type,schema_json)
VALUES (?,?,?,?,?,1,1,?,?,?,?,'WebPage',?)")
->execute([
    $blogPageId,
    'Blog — Tipy od fotografa | RB Photos & Videos Olomouc',
    'Tipy, inspirace a rady od profesionálního fotografa z Olomouce. Přečtěte si o přípravě na svatební focení, rodinném focení s dětmi a dalších tématech.',
    'blog fotografie Olomouc, tipy pro svatební focení, rodinné focení tipy, fotograf Olomouc blog',
    'http://localhost:8000/blog',
    'Blog — RB Photos & Videos',
    'Tipy a inspirace od profesionálního fotografa z Olomouce.',
    'Blog — RB Photos & Videos',
    'Tipy a inspirace od profesionálního fotografa z Olomouce.',
    json_encode([
        '@context'  => 'https://schema.org',
        '@type'     => 'Blog',
        'name'      => 'Blog — RB Photos & Videos',
        'url'       => 'http://localhost:8000/blog',
        'publisher' => ['@type' => 'Organization', 'name' => 'RB Photos & Videos'],
        'blogPost'  => [
            ['@type' => 'BlogPosting', 'headline' => '10 tipů, jak se připravit na dokonalé svatební focení', 'url' => 'http://localhost:8000/blog/priprava-na-svatebni-foceni'],
            ['@type' => 'BlogPosting', 'headline' => 'Rodinné focení: jak dostat přirozené snímky i s malými dětmi', 'url' => 'http://localhost:8000/blog/rodinne-foceni-s-detmi'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
]);
echo "✅ Blog index stránka vytvořena (ID: $blogPageId).\n";

// ═══════════════════════════════════════════════════════════════════════════════
// 5. PUBLIKOVAT VŠE
// ═══════════════════════════════════════════════════════════════════════════════
$service = new App\Services\PublishService();

$pages = [
    [1,           'Homepage'],
    [2,           'Blog post 1 (příprava na svatební focení)'],
    [3,           'Blog post 2 (rodinné focení s dětmi)'],
    [$blogPageId, 'Blog index (/blog)'],
];

echo "\n--- Publikuji stránky ---\n";
foreach ($pages as [$pid, $label]) {
    $r = $service->publishPage($pid, 1);
    echo ($r['success'] ? '✅' : '❌') . " $label: " . $r['message'] . "\n";
}

echo "\n--- Hotovo ---\n";
echo "Přístupné URL:\n";
echo "  http://localhost:8000/\n";
echo "  http://localhost:8000/blog\n";
echo "  http://localhost:8000/blog/priprava-na-svatebni-foceni\n";
echo "  http://localhost:8000/blog/rodinne-foceni-s-detmi\n";
