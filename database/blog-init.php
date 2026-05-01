<?php
/**
 * RB Photos & Videos — blog šablona + dva blogové články
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

$db = new PDO('sqlite:' . BASE_PATH . '/storage/database.sqlite', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA foreign_keys=ON');

// ─── BLOG ŠABLONA (ID 2) ──────────────────────────────────────────────────────
$blogHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blog</title>
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar-blog">
    <div class="nb-inner">
      <a href="/" class="logo">RB<span class="logo-sep">&nbsp;&middot;&nbsp;</span>Photos &amp; Videos</a>
      <ul class="nav-links">
        <li><a href="/#sluzby">Služby</a></li>
        <li><a href="/#videa">Videa</a></li>
        <li><a href="/#reference">Reference</a></li>
        <li><a href="/#kontakt" class="btn-nav">Kontakt</a></li>
      </ul>
    </div>
  </nav>

  <!-- ARTICLE HERO -->
  <header class="article-hero">
    <div class="container">
      <div class="article-breadcrumb">
        <a href="/">Domů</a>&nbsp;&rsaquo;&nbsp;<a href="/#reference">Blog</a>
      </div>
      <div class="article-meta">
        <span class="article-cat" data-block="article_cat">Focení</span>
        <span class="article-date" data-block="article_date">2025</span>
      </div>
      <h1 class="article-title" data-block="article_title">Název článku</h1>
      <p class="article-lead" data-block="article_lead">Perex článku.</p>
    </div>
  </header>

  <!-- THUMBNAIL -->
  <div class="article-thumb-wrap">
    <div class="container">
      <div class="article-thumb">
        <iframe data-block="article_video_embed" src="https://player.vimeo.com/video/1133996815?wmode=opaque" allowfullscreen loading="lazy" title="Ukázka naší práce"></iframe>
      </div>
    </div>
  </div>

  <!-- ARTICLE BODY + SIDEBAR -->
  <div class="container article-layout">
    <article class="article-body">

      <section class="article-section">
        <h2 data-block="sec1_title">Sekce 1</h2>
        <div data-block="sec1_text">Text sekce.</div>
      </section>

      <section class="article-section">
        <h2 data-block="sec2_title">Sekce 2</h2>
        <div data-block="sec2_text">Text sekce.</div>
      </section>

      <section class="article-section">
        <h2 data-block="sec3_title">Sekce 3</h2>
        <div data-block="sec3_text">Text sekce.</div>
      </section>

      <section class="article-section">
        <h2 data-block="sec4_title">Sekce 4</h2>
        <div data-block="sec4_text">Text sekce.</div>
      </section>

      <section class="article-section">
        <h2 data-block="sec5_title">Sekce 5</h2>
        <div data-block="sec5_text">Text sekce.</div>
      </section>

      <div class="article-tags">
        <a href="/#sluzby" data-block="tag1">Olomouc</a>
        <a href="/#sluzby" data-block="tag2">Fotografie</a>
        <a href="/#sluzby" data-block="tag3">RB Photos</a>
      </div>

      <div class="article-author">
        <div class="author-avatar">RB</div>
        <div>
          <strong>RB Photos &amp; Videos</strong>
          <p>Profesionální fotograf a videograf v Olomouci. Specializujeme se na svatební fotografie a videografie, rodinné focení a komerční obsah.</p>
        </div>
      </div>

    </article>

    <!-- SIDEBAR -->
    <aside class="article-sidebar">
      <div class="sidebar-card">
        <p class="sidebar-label">Fotograf &amp; Videograf</p>
        <h3>Rezervujte termín</h3>
        <p>Obsazujeme se rychle. Kontaktujte nás co nejdříve pro dostupné termíny.</p>
        <a href="tel:792483583" class="btn btn-primary" style="display:block;text-align:center;margin-top:20px">792 483 583</a>
        <a href="tel:604225032" class="btn btn-outline-sb" style="display:block;text-align:center;margin-top:10px">604 225 032</a>
        <div class="sidebar-social">
          <a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener">Instagram</a>
          <a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener">Facebook</a>
        </div>
      </div>
      <div class="sidebar-card">
        <p class="sidebar-label">Naše práce</p>
        <div class="sidebar-video">
          <iframe src="https://player.vimeo.com/video/1144185057?wmode=opaque" allowfullscreen loading="lazy" title="Video ukázka"></iframe>
        </div>
      </div>
    </aside>
  </div>

  <!-- CTA -->
  <section class="article-cta-section">
    <div class="container article-cta-inner">
      <div>
        <h2 data-block="cta_title">Rezervujte svůj termín</h2>
        <p data-block="cta_text">Zavolejte nám nebo napište na sociální sítě — rádi odpovíme na vaše otázky.</p>
      </div>
      <div class="cta-btns">
        <a href="tel:792483583" class="btn btn-primary">792 483 583</a>
        <a href="/#kontakt" class="btn btn-outline-light2">Napsat zprávu</a>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="container footer-inner">
      <div class="footer-brand">
        <span class="logo">RB<span class="logo-sep">&nbsp;&middot;&nbsp;</span>Photos &amp; Videos</span>
        <p>Profesionální fotografie a videografie — Olomouc</p>
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

$blogCss = <<<'CSS'
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
h1, h2, h3 { letter-spacing: -.02em; }
a { color: inherit; text-decoration: none; }
.container { max-width: 1100px; margin: 0 auto; padding: 0 28px; }

/* NAVBAR */
.navbar-blog { background: var(--dark); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid rgba(196,150,60,.12); }
.nb-inner { max-width: 1100px; margin: 0 auto; padding: 0 28px; display: flex; align-items: center; justify-content: space-between; height: 64px; }
.logo { font-size: 15px; font-weight: 700; color: #fff; letter-spacing: .08em; text-transform: uppercase; }
.logo-sep { color: var(--gold); font-weight: 300; }
.nav-links { display: flex; list-style: none; gap: 28px; align-items: center; }
.nav-links a { color: rgba(255,255,255,.5); font-size: 12px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; transition: color .2s; }
.nav-links a:hover { color: #fff; }
.btn-nav { background: var(--gold) !important; color: #fff !important; padding: 8px 20px; border-radius: var(--radius); font-size: 12px; }

/* ARTICLE HERO */
.article-hero { background: var(--dark); padding: 72px 0 60px; }
.article-breadcrumb { font-size: 11px; color: rgba(255,255,255,.3); letter-spacing: .08em; text-transform: uppercase; margin-bottom: 20px; }
.article-breadcrumb a { color: rgba(255,255,255,.35); transition: color .2s; }
.article-breadcrumb a:hover { color: var(--gold); }
.article-meta { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.article-cat { font-size: 11px; font-weight: 700; color: var(--gold); letter-spacing: .15em; text-transform: uppercase; border: 1px solid rgba(196,150,60,.35); padding: 4px 12px; }
.article-date { font-size: 12px; color: rgba(255,255,255,.3); letter-spacing: .06em; }
.article-title { font-size: clamp(1.8rem, 4vw, 3rem); font-weight: 800; color: #fff; max-width: 760px; margin-bottom: 20px; }
.article-lead { font-size: 1.08rem; color: rgba(255,255,255,.5); max-width: 680px; line-height: 1.75; }

/* THUMBNAIL */
.article-thumb-wrap { background: var(--dark2); padding: 0 0 48px; }
.article-thumb { position: relative; padding-bottom: 46.25%; border-radius: var(--radius); overflow: hidden; background: #000; max-width: 860px; }
.article-thumb iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }

/* LAYOUT */
.article-layout { display: grid; grid-template-columns: 1fr 300px; gap: 60px; padding-top: 56px; padding-bottom: 80px; align-items: start; }

/* ARTICLE BODY */
.article-body { min-width: 0; }
.article-section { margin-bottom: 40px; }
.article-section h2 { font-size: 1.25rem; font-weight: 700; margin-bottom: 12px; position: relative; padding-left: 16px; }
.article-section h2::before { content: ''; position: absolute; left: 0; top: 4px; bottom: 4px; width: 3px; background: var(--gold); border-radius: 2px; }
.article-section div { color: var(--muted); font-size: .97rem; line-height: 1.8; }

/* TAGS */
.article-tags { display: flex; gap: 8px; flex-wrap: wrap; margin: 40px 0 32px; }
.article-tags a { font-size: 11px; font-weight: 600; color: var(--muted); letter-spacing: .08em; text-transform: uppercase; border: 1px solid var(--border); padding: 5px 14px; border-radius: 2px; transition: all .2s; }
.article-tags a:hover { border-color: var(--gold); color: var(--gold); }

/* AUTHOR */
.article-author { display: flex; gap: 16px; align-items: flex-start; padding: 24px; background: var(--light); border-left: 3px solid var(--gold); }
.author-avatar { width: 48px; height: 48px; border-radius: 50%; background: var(--dark); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; letter-spacing: .04em; }
.article-author strong { font-size: .95rem; font-weight: 700; display: block; margin-bottom: 4px; }
.article-author p { font-size: .85rem; color: var(--muted); line-height: 1.6; }

/* SIDEBAR */
.article-sidebar { position: sticky; top: 80px; display: flex; flex-direction: column; gap: 20px; }
.sidebar-card { background: var(--light); padding: 24px; border: 1px solid var(--border); }
.sidebar-label { font-size: 10.5px; font-weight: 700; color: var(--gold); letter-spacing: .18em; text-transform: uppercase; margin-bottom: 10px; }
.sidebar-card h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 8px; }
.sidebar-card p { font-size: .88rem; color: var(--muted); line-height: 1.6; }
.sidebar-social { display: flex; gap: 12px; margin-top: 14px; }
.sidebar-social a { font-size: 11px; font-weight: 600; color: var(--muted); letter-spacing: .06em; text-transform: uppercase; transition: color .2s; }
.sidebar-social a:hover { color: var(--gold); }
.sidebar-video { position: relative; padding-bottom: 56.25%; background: #000; margin-top: 10px; }
.sidebar-video iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: none; }

/* BUTTONS */
.btn { display: inline-block; padding: 12px 24px; font-weight: 600; font-size: 12px; cursor: pointer; transition: all .22s; letter-spacing: .06em; text-transform: uppercase; border-radius: var(--radius); }
.btn-primary { background: var(--gold); color: #fff; }
.btn-primary:hover { background: var(--gold-d); }
.btn-outline-sb { background: transparent; border: 1px solid var(--border); color: var(--text); }
.btn-outline-sb:hover { border-color: var(--gold); color: var(--gold); }
.btn-outline-light2 { background: transparent; border: 1px solid rgba(255,255,255,.25); color: rgba(255,255,255,.8); }
.btn-outline-light2:hover { border-color: rgba(255,255,255,.6); color: #fff; }

/* CTA SECTION */
.article-cta-section { background: var(--dark); padding: 72px 0; border-top: 1px solid rgba(196,150,60,.1); }
.article-cta-inner { display: flex; align-items: center; justify-content: space-between; gap: 40px; flex-wrap: wrap; }
.article-cta-inner h2 { font-size: 1.8rem; font-weight: 800; color: #fff; margin-bottom: 8px; }
.article-cta-inner p { color: rgba(255,255,255,.45); font-size: .97rem; }
.cta-btns { display: flex; gap: 12px; flex-wrap: wrap; flex-shrink: 0; }

/* FOOTER */
.footer { background: var(--dark); padding: 48px 0 28px; border-top: 1px solid rgba(196,150,60,.08); }
.footer-inner { display: flex; flex-direction: column; align-items: center; gap: 20px; text-align: center; }
.footer-brand p { color: rgba(255,255,255,.28); font-size: .82rem; margin-top: 6px; }
.footer-links { display: flex; gap: 24px; flex-wrap: wrap; justify-content: center; }
.footer-links a { color: rgba(255,255,255,.3); font-size: .78rem; letter-spacing: .07em; text-transform: uppercase; transition: color .2s; }
.footer-links a:hover { color: var(--gold); }
.footer-copy { color: rgba(255,255,255,.18); font-size: .72rem; }

/* RESPONSIVE */
@media (max-width: 900px) {
  .article-layout { grid-template-columns: 1fr; }
  .article-sidebar { position: static; }
  .article-cta-inner { flex-direction: column; text-align: center; }
  .cta-btns { justify-content: center; }
}
@media (max-width: 640px) {
  .nav-links { display: none; }
  .article-hero { padding: 52px 0 40px; }
}
CSS;

$db->prepare('INSERT OR REPLACE INTO templates (id, site_id, name, html_source, css_source, js_source) VALUES (2, 1, ?, ?, ?, ?)')
   ->execute(['RB Photos & Videos — Blog Článek', $blogHtml, $blogCss, '']);
echo "Blog šablona uložena.\n";

// ─── POMOCNÁ FUNKCE ───────────────────────────────────────────────────────────
function insertBlogPage(PDO $db, array $pageData, array $blocks, array $seo): int {
    // Smazat existující stránku se stejným slugem
    $existing = $db->prepare('SELECT id FROM pages WHERE site_id=1 AND slug=?');
    $existing->execute([$pageData['slug']]);
    $row = $existing->fetch();
    if ($row) {
        $db->prepare('DELETE FROM content_blocks WHERE page_id=?')->execute([$row['id']]);
        $db->prepare('DELETE FROM seo_settings WHERE page_id=?')->execute([$row['id']]);
        $db->prepare('DELETE FROM pages WHERE id=?')->execute([$row['id']]);
    }

    $db->prepare("INSERT INTO pages (site_id, template_id, title, slug, url_path, language, status) VALUES (1, 2, ?, ?, ?, 'cs', 'published')")
       ->execute([$pageData['title'], $pageData['slug'], $pageData['url_path']]);
    $id = (int)$db->lastInsertId();

    $stmt = $db->prepare('INSERT INTO content_blocks (page_id,block_key,block_type,label,content,sort_order) VALUES (?,?,?,?,?,?)');
    foreach ($blocks as $i => [$key, $type, $content]) {
        $stmt->execute([$id, $key, $type, ucfirst(str_replace('_', ' ', $key)), $content, $i]);
    }

    $db->prepare("INSERT INTO seo_settings (page_id,meta_title,meta_description,meta_keywords,canonical_url,robots_index,robots_follow,og_title,og_description,twitter_title,twitter_description,schema_type,schema_json)
    VALUES (?,?,?,?,?,1,1,?,?,?,?,'Article',?)")
    ->execute([
        $id,
        $seo['title'], $seo['desc'], $seo['keywords'], $seo['url'],
        $seo['title'], $seo['desc'], $seo['title'], $seo['desc'],
        json_encode([
            '@context' => 'https://schema.org',
            '@type'    => 'BlogPosting',
            'headline' => $seo['title'],
            'author'   => ['@type' => 'Organization', 'name' => 'RB Photos & Videos'],
            'publisher'=> ['@type' => 'Organization', 'name' => 'RB Photos & Videos'],
            'url'      => $seo['url'],
        ], JSON_UNESCAPED_UNICODE),
    ]);

    return $id;
}

// ═══════════════════════════════════════════════════════════════════════════════
// BLOG POST 1 — Jak se připravit na dokonalé svatební focení
// ═══════════════════════════════════════════════════════════════════════════════
$id1 = insertBlogPage($db,
    [
        'title'    => '10 tipů, jak se připravit na dokonalé svatební focení',
        'slug'     => 'blog-priprava-na-svatebni-foceni',
        'url_path' => 'blog/priprava-na-svatebni-foceni',
    ],
    [
        ['article_cat',          'text',     'Svatební fotografie'],
        ['article_date',         'text',     '12. dubna 2025'],
        ['article_title',        'text',     '10 tipů, jak se připravit na dokonalé svatební focení'],
        ['article_lead',         'textarea', 'Váš svatební den je jedním z nejdůležitějších okamžiků života. S těmito tipy od profesionálního fotografa zajistíte, že každý záběr bude dokonalý — a vy si ten den budete užívat bez zbytečného stresu.'],
        ['article_video_embed',  'text',     'https://player.vimeo.com/video/1133996815?wmode=opaque'],
        ['sec1_title',           'text',     '1. Konzultace s fotografem — co nejdříve'],
        ['sec1_text',            'textarea', 'Osobní schůzka s fotografem ještě před svatbou je absolutní základ. Promluvte si o místě obřadu, harmonogramu dne a vašich přáních. Dobrý fotograf vám pomůže sestavit realistický časový plán a doporučí nejlepší místa pro párové fotografie. Na schůzce navíc zjistíte, zda si spolu sednete — a to je stejně důležité jako portfolio.'],
        ['sec2_title',           'text',     '2. Naplánujte harmonogram s rezervou'],
        ['sec2_text',            'textarea', 'Jednou z nejčastějších chyb je podceňování času. Přidejte ke každé části dne záložní čas — vlasy a make-up trvají déle, než čekáte, hosté přicházejí postupně a skupinové fotografie s celou rodinou zaberou aspoň 20–30 minut. Zlaté pravidlo: lepší mít čas navíc než fotit ve spěchu a pod tlakem.'],
        ['sec3_title',           'text',     '3. Připravte detaily s láskou'],
        ['sec3_text',            'textarea', 'Prsteny, kytice, pozvánky, šperky, parfém — to jsou detaily, které dělají snímky výjimečnými. Připravte je na pevnou podložku nebo neutrální povrch (dřevo, mramor, textil) ráno, než začne veškerý chaos příprav. Bílé i tmavé pozadí se hodí, přibalte obě varianty. Fotograf si s vámi pak vybere tu nejlepší.'],
        ['sec4_title',           'text',     '4. Využijte zlatou hodinu — tajemství krásného světla'],
        ['sec4_text',            'textarea', 'Hodina před západem slunce je čas, kdy je světlo nejkrásnější — teplé, měkké, kinematografické. Pokud je to možné, naplánujte párové focení právě na tento čas. Výsledky jsou vždy magické a odlišují průměrné snímky od těch, které vás popadnou za srdce. V Olomouci a okolí máme spoustu míst, kde zlatá hodinka vypadá úchvatně.'],
        ['sec5_title',           'text',     '5. Zůstaňte sami sebou — přirozenost je základ'],
        ['sec5_text',            'textarea', 'Nejkrásnější fotografie jsou ty přirozené. Nehledejte do objektivu, hýbejte se, smějte se, šeptejte si. Zkušený fotograf zachytí skutečné emoce — ty se nedají režírovat. Před obřadem si s partnerem domluvte, že budete moment prožívat naplno, a kameře nechte starosti na fotografovi. Výsledek vás překvapí.'],
        ['tag1',                 'text',     'Svatební focení'],
        ['tag2',                 'text',     'Olomouc'],
        ['tag3',                 'text',     'Tipy od fotografa'],
        ['cta_title',            'text',     'Chcete dokonalé svatební fotografie?'],
        ['cta_text',             'textarea', 'Oblíbené soboty se obsazují rok i více dopředu. Kontaktujte nás co nejdříve a zajistěte si váš termín.'],
    ],
    [
        'title'    => '10 tipů jak se připravit na dokonalé svatební focení | RB Photos & Videos',
        'desc'     => 'Profesionální fotograf z Olomouce sdílí 10 klíčových tipů, jak se připravit na svatební focení. Od harmonogramu až po zlatou hodinu — vše, co potřebujete vědět.',
        'keywords' => 'příprava na svatební focení, tipy od fotografa, svatební fotografie Olomouc, zlatá hodinka, harmonogram svatebního dne',
        'url'      => 'http://localhost:8000/blog/priprava-na-svatebni-foceni',
    ]
);
echo "Blog post 1 vložen (ID: $id1).\n";

// ═══════════════════════════════════════════════════════════════════════════════
// BLOG POST 2 — Rodinné focení s dětmi
// ═══════════════════════════════════════════════════════════════════════════════
$id2 = insertBlogPage($db,
    [
        'title'    => 'Rodinné focení: jak dostat přirozené snímky i s malými dětmi',
        'slug'     => 'blog-rodinne-foceni-s-detmi',
        'url_path' => 'blog/rodinne-foceni-s-detmi',
    ],
    [
        ['article_cat',          'text',     'Rodinné focení'],
        ['article_date',         'text',     '28. března 2025'],
        ['article_title',        'text',     'Rodinné focení: jak dostat přirozené snímky i s malými dětmi'],
        ['article_lead',         'textarea', 'Malé děti a fotoaparát? Zní to jako recept na chaos. Ale přesně v tom chaosu se skrývají ty nejkrásnější momenty. Přečtěte si, jak z rodinného focení udělat zábavný zážitek — a domů si odnést snímky, na které budete hrdí celý život.'],
        ['article_video_embed',  'text',     'https://player.vimeo.com/video/1122538281?wmode=opaque'],
        ['sec1_title',           'text',     '1. Správný čas je základ'],
        ['sec1_text',            'textarea', 'S malými dětmi je načasování vše. Naplánujte focení na dobu, kdy jsou děti nejvíce odpočaté a v dobré náladě — typicky dopoledne po snídani, nebo krátce po odpoledním spánku. Nikdy ne těsně před jídlem nebo spaním. Unavené a hladové dítě nevyfotíte do dobré nálady žádnými triky.'],
        ['sec2_title',           'text',     '2. Oblečení v harmonických barvách'],
        ['sec2_text',            'textarea', 'Nemusíte mít všichni stejné tričko. Stačí zvolit dva nebo tři harmonické tóny a kombinovat je volně. Vyhněte se agresivním vzorům a nápisům, které odvádějí pozornost. Neutrální barvy (béžová, terakota, modrá, zelená) ve spojení s přírodním prostředím fungují vždy skvěle.'],
        ['sec3_title',           'text',     '3. Hrajte si — ignorujte fotoaparát'],
        ['sec3_text',            'textarea', 'Nejhorší, co rodiče dělají, je říkat dětem: „Koukej na pana fotografa a usměj se." Výsledek je nucený úsměv a strnulé pózy. Místo toho se věnujte dětem přirozeně — šimrejte, honič, čtěte pohádku, házejte listy. Fotograf zachytí opravdové výrazy a smích, které jsou k nezaplacení.'],
        ['sec4_title',           'text',     '4. Příroda je nejlepší kulisa'],
        ['sec4_text',            'textarea', 'Parky, lesy, louky nebo zahrada — příroda je pro rodinné focení ideální prostředí. Děti mají prostor k pohybu, přirozené světlo je krásné a pozadí nevyžaduje žádnou postprodukci. V Olomouci a okolí máme nádherné lokace: Smetanovy sady, Tabulový vrch, Bystřice pod Hostýnem nebo lesní cesty na Svatém Kopečku.'],
        ['sec5_title',           'text',     '5. Krátká session — méně je více'],
        ['sec5_text',            'textarea', 'S malými dětmi plánujte focení na 45–60 minut, ne na celé odpoledne. Kratší čas znamená, že děti jsou svěží a spolupracují. Pokud máte více dětí různého věku, domluvte s fotografem pořadí — nejmenší nejdřív, protože jejich výdrž je nejkratší. A na závěr si nechte pár minut jen pro rodičovský pár.'],
        ['tag1',                 'text',     'Rodinné focení'],
        ['tag2',                 'text',     'Děti a fotografie'],
        ['tag3',                 'text',     'Olomouc a okolí'],
        ['cta_title',            'text',     'Rezervujte rodinné focení'],
        ['cta_text',             'textarea', 'Přirozené, krásné rodinné snímky z Olomouce a okolí. Zavolejte nebo napište — domluvíme termín i lokaci přesně podle vás.'],
    ],
    [
        'title'    => 'Rodinné focení s dětmi — tipy pro přirozené snímky | RB Photos & Videos',
        'desc'     => 'Jak nafotit přirozené rodinné fotografie i s malými dětmi? Tipy profesionálního fotografa z Olomouce — správný čas, lokace, oblečení a jak děti uvolnit.',
        'keywords' => 'rodinné focení Olomouc, focení s dětmi, přirozené rodinné fotografie, rodinný fotograf Olomouc, tipy pro rodinné focení',
        'url'      => 'http://localhost:8000/blog/rodinne-foceni-s-detmi',
    ]
);
echo "Blog post 2 vložen (ID: $id2).\n";

// ─── PUBLIKOVAT ───────────────────────────────────────────────────────────────
$service = new App\Services\PublishService();

$r1 = $service->publishPage($id1, 1);
echo $r1['success'] ? "✅ Blog 1: " . $r1['message'] . "\n" : "❌ Blog 1: " . $r1['message'] . "\n";

$r2 = $service->publishPage($id2, 1);
echo $r2['success'] ? "✅ Blog 2: " . $r2['message'] . "\n" : "❌ Blog 2: " . $r2['message'] . "\n";

// Publikovat i homepage (kvůli video update)
$r0 = $service->publishPage(1, 1);
echo $r0['success'] ? "✅ Homepage: " . $r0['message'] . "\n" : "❌ Homepage: " . $r0['message'] . "\n";
