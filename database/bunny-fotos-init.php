<?php
/**
 * RB Photos & Videos — kompletní inicializace s reálnými daty z rbvideosfotos.cz
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

$db = new PDO('sqlite:' . BASE_PATH . '/storage/database.sqlite', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA foreign_keys=ON');

// ─── SITE ────────────────────────────────────────────────────────────────────
$db->prepare("UPDATE sites SET name=?, domain=?, google_analytics_id=?, google_tag_manager_id=? WHERE id=1")
   ->execute(['RB Photos & Videos', 'http://localhost:8000', 'G-BD4BS9R4JZ', 'GTM-542MMSL']);

// ─── ŠABLONA ─────────────────────────────────────────────────────────────────
$html = <<<'HTML'
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RB Photos &amp; Videos</title>
</head>
<body>

  <!-- NAV -->
  <header class="hero" id="hero">
    <div id="hero-video-bg" class="hero-video-bg"></div>
    <div class="hero-overlay"></div>
    <span id="hero-vid" data-block="hero_video_id" style="display:none">1133996815</span>
    <nav class="navbar">
      <a href="/" class="logo">RB<span class="logo-sep">&nbsp;&middot;&nbsp;</span>Photos &amp; Videos</a>
      <ul class="nav-links">
        <li><a href="#sluzby">Služby</a></li>
        <li><a href="#videa">Videa</a></li>
        <li><a href="#reference">Reference</a></li>
        <li><a href="#kontakt" class="btn-nav">Kontakt</a></li>
      </ul>
      <a href="#kontakt" class="btn-nav btn-nav-mobile">Kontakt</a>
    </nav>

    <!-- HERO -->
    <div class="hero-content">
      <span class="hero-tag" data-block="hero_tag">Fotograf &amp; Videograf — Olomouc</span>
      <h1 data-block="hero_title">Zachytíme váš příběh. Na vždy.</h1>
      <p class="hero-sub" data-block="hero_subtitle">Profesionální svatební fotografie a videografie v Olomouci a celé České republice. Každý okamžik si zaslouží být nezapomenutelný.</p>
      <div class="hero-btns">
        <a href="tel:792483583" class="btn btn-primary"><span data-block="hero_tel">792 483 583</span></a>
        <a href="#sluzby" class="btn btn-outline" data-block="hero_cta">Naše služby</a>
      </div>
    </div>
    <div class="hero-scroll">&#8595;</div>
  </header>

  <!-- STATS -->
  <section class="stats-bar">
    <div class="container stats-grid">
      <div class="stat"><strong data-block="stat1_num">150+</strong><span data-block="stat1_lbl">spokojených klientů</span></div>
      <div class="stat"><strong data-block="stat2_num">8+</strong><span data-block="stat2_lbl">let zkušeností</span></div>
      <div class="stat"><strong data-block="stat3_num">5 let</strong><span data-block="stat3_lbl">zálohy fotek zdarma</span></div>
      <div class="stat"><strong data-block="stat4_num">2 fotografové</strong><span data-block="stat4_lbl">tým profesionálů</span></div>
    </div>
  </section>

  <!-- SLUŽBY -->
  <section class="section" id="sluzby">
    <div class="container">
      <div class="section-header centered">
        <h2 data-block="services_title">Co pro vás uděláme</h2>
        <p data-block="services_sub">Ať jde o svatbu, portrét nebo firemní projekt — jsme tu pro vás.</p>
      </div>
      <div class="services-grid">
        <div class="service-card featured">
          <div class="service-num">01</div>
          <h3 data-block="s1_title">Svatební foto &amp; video</h3>
          <p data-block="s1_text">Zachytíme každý dotek, každý pohled, každou slzu štěstí. Přirozené reportážní fotografie a filmové video, které vám budou vyprávět příběh vašeho dne po celý život.</p>
        </div>
        <div class="service-card">
          <div class="service-num">02</div>
          <h3 data-block="s2_title">Rodinné &amp; portrétní focení</h3>
          <p data-block="s2_text">Rodinné vzpomínky, těhotenské focení, křtiny nebo portréty. Přirozené, nenucené a krásné snímky, na které budete hrdí.</p>
        </div>
        <div class="service-card">
          <div class="service-num">03</div>
          <h3 data-block="s3_title">Reklamní foto &amp; video</h3>
          <p data-block="s3_text">Profesionální vizuální obsah pro firmy, kapely, produkty nebo brand. Videoklipy, firemní prezentace a obsah, který prodává.</p>
        </div>
        <div class="service-card">
          <div class="service-num">04</div>
          <h3 data-block="s4_title">Akce &amp; události</h3>
          <p data-block="s4_text">Konference, kulturní akce, plesy, narozeniny. Každá událost si zaslouží být profesionálně zdokumentovaná.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- PROČ MY -->
  <section class="section section-dark" id="proc-my">
    <div class="container">
      <div class="section-header centered light">
        <h2 data-block="why_title">Proč si vybrat RB Photos &amp; Videos?</h2>
        <p data-block="why_sub">Nejsme jen fotografové. Jsme vypravěči příběhů.</p>
      </div>
      <div class="why-grid">
        <div class="why-item">
          <span class="why-num">01</span>
          <h4 data-block="why1_title">Osobní přístup</h4>
          <p data-block="why1_text">Každý klient je pro nás jedinečný. Přátelský tým, který naslouchá vašim přáním a pomůže i s kompozicí.</p>
        </div>
        <div class="why-item">
          <span class="why-num">02</span>
          <h4 data-block="why2_title">Zkušený tým</h4>
          <p data-block="why2_text">Více než 8 let zkušeností, profesionální vybavení a neustálé vzdělávání. Investujeme do kvality pro vás.</p>
        </div>
        <div class="why-item">
          <span class="why-num">03</span>
          <h4 data-block="why3_title">5 let zálohy zdarma</h4>
          <p data-block="why3_text">Vaše fotografie a videa bezpečně zálohujeme po dobu 5 let. Nikdy o vzpomínky nepřijdete.</p>
        </div>
        <div class="why-item">
          <span class="why-num">04</span>
          <h4 data-block="why4_title">Celá ČR i zahraničí</h4>
          <p data-block="why4_text">Sídlíme v Olomouci, ale fotografujeme po celé České republice i v zahraničí. Přijedeme za vámi.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- VIDEA -->
  <section class="section" id="videa">
    <div class="container">
      <div class="section-header centered">
        <h2 data-block="videos_title">Ukázka naší práce</h2>
        <p data-block="videos_sub">Svatební filmy, klipy a reportáže — podívejte se jak pracujeme.</p>
      </div>
      <div class="videos-grid">
        <div class="video-wrap">
          <iframe src="https://player.vimeo.com/video/1133996815?wmode=opaque" allowfullscreen loading="lazy" title="Svatební video"></iframe>
        </div>
        <div class="video-wrap">
          <iframe src="https://player.vimeo.com/video/1122538281?wmode=opaque" allowfullscreen loading="lazy" title="Svatební video"></iframe>
        </div>
        <div class="video-wrap">
          <iframe src="https://player.vimeo.com/video/1144185057?wmode=opaque" allowfullscreen loading="lazy" title="Svatební video"></iframe>
        </div>
        <div class="video-wrap">
          <iframe src="https://www.youtube.com/embed/O5i9TzCms5c?wmode=opaque" allowfullscreen loading="lazy" title="Video"></iframe>
        </div>
      </div>
      <div class="gallery-cta">
        <a href="https://www.youtube.com/channel/UC5miHVzPxIs2MjEsoPAzGCw/videos" target="_blank" rel="noopener" class="btn btn-primary">Všechna videa na YouTube</a>
        <a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener" class="btn btn-outline-dark">Instagram portfolio</a>
      </div>
    </div>
  </section>

  <!-- REFERENCE -->
  <section class="section section-light" id="reference">
    <div class="container">
      <div class="section-header centered">
        <h2 data-block="reviews_title">Co říkají naši klienti</h2>
        <p data-block="reviews_sub">Přes 150 spokojených klientů — jejich slova mluví za nás.</p>
      </div>
      <div class="reviews-grid">
        <div class="review-card">
          <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
          <p data-block="r1_text">"Kluci jsou naprostí profesionálové. Jsou to opravdu přátelští borci, kteří vám udělají fotky a videa podle vašich představ a ještě líp. Za mě TOP ve svém oboru. Děkujeme, že jste mohli být na naší svatbě!"</p>
          <strong data-block="r1_name">Jan Jüngling</strong>
          <span data-block="r1_event">Svatba M+H</span>
        </div>
        <div class="review-card">
          <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
          <p data-block="r2_text">"Super domluva, milý a profesionální přístup k focení. Zdena nám nafotil svatební obřad s poobřadním focením v zámeckém parku. Z kvality fotek jsme nadšení a byly dodány dříve než jsme byli domluveni. Velká spokojenost. Doporučuju."</p>
          <strong data-block="r2_name">Václav Konopka</strong>
          <span data-block="r2_event">Svatební focení</span>
        </div>
        <div class="review-card">
          <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
          <p data-block="r3_text">"Moc doporučuji, pan fotograf nám vyčaroval ty nejkrásnější rodinné fotky i s dětmi, co neměly zrovna fotící náladu. Profesionální přístup a špičková kvalita. Už se těším na další focení!"</p>
          <strong data-block="r3_name">Jana Dudková</strong>
          <span data-block="r3_event">Rodinné focení</span>
        </div>
        <div class="review-card">
          <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
          <p data-block="r4_text">"Rychlost, super provedení, nádherné fotky, které rychle dodal upravené. 11/10, ještě jednou moc děkujeme."</p>
          <strong data-block="r4_name">Roman Vrána</strong>
          <span data-block="r4_event">Focení</span>
        </div>
        <div class="review-card">
          <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
          <p data-block="r5_text">"Focení s Bannym mě moc bavilo. Super přístup, atmosféra i fotky. Rozhodně doporučuji!"</p>
          <strong data-block="r5_name">Ellie Lachnitová</strong>
          <span data-block="r5_event">Portrétní focení</span>
        </div>
        <div class="review-card">
          <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
          <p data-block="r6_text">"Profesionalita, ochota, vstřícnost a hlavně poradí i se scénou. Chceš mít profi foto a nevíš komu říct a poradit s kompozicí? Tak teď už víš."</p>
          <strong data-block="r6_name">Tomáš Pavlat</strong>
          <span data-block="r6_event">Profesionální focení</span>
        </div>
      </div>
      <div class="reviews-more">
        <a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener" class="btn btn-outline-dark">Všechny recenze na Facebooku</a>
      </div>
    </div>
  </section>

  <!-- KONTAKT -->
  <section class="section" id="kontakt">
    <div class="container contact-wrap">
      <div class="contact-text">
        <h2 data-block="contact_title">Pojďme vytvořit něco krásného</h2>
        <p data-block="contact_intro">Nezávazně se nám ozvěte — rádi odpovíme na vaše otázky a připravíme nabídku na míru pro váš den.</p>
        <ul class="contact-list">
          <li><a href="tel:792483583" data-block="contact_phone1">792 483 583</a></li>
          <li><a href="tel:604225032" data-block="contact_phone2">604 225 032</a></li>
          <li><span data-block="contact_city">Olomouc a celá ČR</span></li>
          <li><a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener" data-block="contact_fb">Facebook: RB Videos &amp; Photos</a></li>
          <li><a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener" data-block="contact_ig">Instagram: @rb_videos_photos</a></li>
          <li><a href="https://www.youtube.com/channel/UC5miHVzPxIs2MjEsoPAzGCw/videos" target="_blank" rel="noopener" data-block="contact_yt">YouTube kanál</a></li>
        </ul>
      </div>
      <div class="contact-card">
        <h3 data-block="contact_card_title">Ozvěte se nám</h3>
        <p data-block="contact_card_text">Zavolejte nebo napište na sociální sítě. Odpovídáme rychle!</p>
        <a href="tel:792483583" class="btn btn-primary" style="display:block;text-align:center;margin-top:24px">Zavolat: 792 483 583</a>
        <a href="tel:604225032" class="btn btn-outline-light" style="display:block;text-align:center;margin-top:10px">Zavolat: 604 225 032</a>
        <a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener" class="btn btn-outline-light" style="display:block;text-align:center;margin-top:10px">Instagram</a>
        <a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener" class="btn btn-outline-light" style="display:block;text-align:center;margin-top:10px">Facebook</a>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="container footer-inner">
      <div class="footer-brand">
        <span class="logo">RB<span class="logo-sep">&nbsp;&middot;&nbsp;</span>Photos &amp; Videos</span>
        <p data-block="footer_text">Profesionální fotografie a videografie — Olomouc</p>
      </div>
      <div class="footer-links">
        <a href="https://www.facebook.com/rbvideosandphotos" target="_blank" rel="noopener">Facebook</a>
        <a href="https://www.instagram.com/rb_videos_photos/" target="_blank" rel="noopener">Instagram</a>
        <a href="https://www.youtube.com/channel/UC5miHVzPxIs2MjEsoPAzGCw/videos" target="_blank" rel="noopener">YouTube</a>
        <a href="tel:792483583">792 483 583</a>
      </div>
      <p class="footer-copy" data-block="footer_copy">&copy; 2025 RB Photos &amp; Videos. Všechna práva vyhrazena.</p>
    </div>
  </footer>

</body>
</html>
HTML;

$css = <<<'CSS'
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --gold:    #c4963c;
  --gold-d:  #a67c2a;
  --dark:    #0c0c0e;
  --dark2:   #141416;
  --dark3:   #1e1e22;
  --light:   #f9f8f6;
  --text:    #1c1c1e;
  --muted:   #6b7280;
  --border:  #e4e4e7;
  --radius:  3px;
  --shadow:  0 2px 16px rgba(0,0,0,.07);
}

body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; color: var(--text); background: #fff; line-height: 1.65; -webkit-font-smoothing: antialiased; }
h1, h2, h3, h4 { letter-spacing: -.02em; line-height: 1.2; }
a { color: inherit; text-decoration: none; }
.container { max-width: 1100px; margin: 0 auto; padding: 0 28px; }

/* BUTTONS */
.btn { display: inline-block; padding: 13px 28px; border-radius: var(--radius); font-weight: 600; font-size: 13px; cursor: pointer; transition: all .22s; letter-spacing: .06em; text-transform: uppercase; }
.btn-primary      { background: var(--gold); color: #fff; }
.btn-primary:hover { background: var(--gold-d); }
.btn-outline      { background: transparent; border: 1px solid rgba(255,255,255,.35); color: #fff; }
.btn-outline:hover { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.65); }
.btn-outline-dark { background: transparent; border: 1px solid var(--gold); color: var(--gold); }
.btn-outline-dark:hover { background: var(--gold); color: #fff; }
.btn-outline-light { background: transparent; border: 1px solid rgba(255,255,255,.2); color: rgba(255,255,255,.75); }
.btn-outline-light:hover { border-color: rgba(255,255,255,.6); color: #fff; }
.btn-nav { background: var(--gold); color: #fff !important; padding: 9px 22px; border-radius: var(--radius); font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.btn-nav-mobile { display: none; }

/* NAVBAR */
.navbar { display: flex; align-items: center; justify-content: space-between; padding: 26px 48px; position: absolute; top: 0; left: 0; right: 0; z-index: 10; border-bottom: 1px solid rgba(255,255,255,.05); }
.logo { font-size: 16px; font-weight: 700; color: #fff; letter-spacing: .08em; text-transform: uppercase; }
.logo-sep { color: var(--gold); font-weight: 300; }
.nav-links { display: flex; list-style: none; gap: 32px; align-items: center; }
.nav-links a { color: rgba(255,255,255,.55); font-size: 12px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; transition: color .2s; }
.nav-links a:hover { color: #fff; }

/* HERO */
.hero { min-height: 100vh; background: var(--dark); display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; position: relative; overflow: hidden; }
.hero-video-bg { position: absolute; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
.hero-video-bg iframe { position: absolute; top: 50%; left: 50%; min-width: 100%; min-height: 100%; width: 177.78vh; height: 56.25vw; transform: translate(-50%,-50%); border: none; }
.hero-overlay { position: absolute; inset: 0; z-index: 1; background: rgba(0,0,0,.65); }
.hero-overlay::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 55%, rgba(196,150,60,.07) 0%, transparent 65%); }
.hero-overlay::after  { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(196,150,60,.25), transparent); }
.hero-content { position: relative; z-index: 2; padding: 150px 28px 64px; max-width: 780px; }
.hero-tag { display: inline-block; border: 1px solid rgba(196,150,60,.35); color: var(--gold); padding: 6px 18px; font-size: 11px; font-weight: 600; letter-spacing: .18em; margin-bottom: 28px; text-transform: uppercase; }
.hero h1 { font-size: clamp(2.4rem, 5vw, 4rem); font-weight: 800; color: #fff; margin-bottom: 20px; }
.hero-sub { font-size: 1.05rem; color: rgba(255,255,255,.48); max-width: 560px; margin: 0 auto 44px; line-height: 1.8; }
.hero-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
.hero-scroll { position: relative; z-index: 2; color: rgba(255,255,255,.25); font-size: 1.1rem; margin-bottom: 44px; animation: bounce 2.2s ease-in-out infinite; }
@keyframes bounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(7px)} }

/* STATS */
.stats-bar { background: var(--dark2); padding: 36px 0; border-bottom: 1px solid rgba(196,150,60,.12); }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); }
.stat { text-align: center; padding: 10px 16px; border-right: 1px solid rgba(255,255,255,.05); }
.stat:last-child { border-right: none; }
.stat strong { display: block; font-size: 2rem; font-weight: 800; color: var(--gold); letter-spacing: -.02em; }
.stat span { display: block; font-size: 10.5px; color: rgba(255,255,255,.35); letter-spacing: .1em; text-transform: uppercase; margin-top: 4px; }

/* SECTIONS */
.section { padding: 100px 0; }
.section-dark  { background: var(--dark2); }
.section-light { background: var(--light); }
.section-header { margin-bottom: 60px; }
.section-header.centered { text-align: center; }
.section-header h2 { font-size: clamp(1.9rem, 3vw, 2.5rem); font-weight: 800; margin-bottom: 14px; }
.section-header h2::after { content: ''; display: block; width: 36px; height: 2px; background: var(--gold); margin-top: 16px; }
.section-header.centered h2::after { margin-left: auto; margin-right: auto; }
.section-header.light h2 { color: #fff; }
.section-header.light h2::after { background: var(--gold); }
.section-header.light p  { color: rgba(255,255,255,.42); }
.section-header p { color: var(--muted); font-size: .97rem; margin-top: 10px; }

/* SERVICES */
.services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1px; background: var(--border); border: 1px solid var(--border); }
.service-card { background: #fff; padding: 36px 28px; transition: background .22s; }
.service-card:hover { background: var(--light); }
.service-card.featured { background: var(--dark); }
.service-card.featured:hover { background: var(--dark3); }
.service-card.featured h3 { color: #fff; }
.service-card.featured p  { color: rgba(255,255,255,.42); }
.service-num { font-size: 10.5px; font-weight: 700; color: var(--gold); letter-spacing: .18em; text-transform: uppercase; margin-bottom: 20px; }
.service-card h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 10px; }
.service-card p  { color: var(--muted); font-size: .9rem; line-height: 1.75; }

/* WHY */
.why-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 48px; }
.why-item { }
.why-num { font-size: 10.5px; font-weight: 700; color: var(--gold); letter-spacing: .18em; text-transform: uppercase; display: block; margin-bottom: 18px; }
.why-item h4 { font-size: 1rem; font-weight: 700; color: #fff; margin-bottom: 10px; }
.why-item p  { color: rgba(255,255,255,.4); font-size: .9rem; line-height: 1.75; }

/* VIDEOS */
.videos-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.video-wrap { position: relative; padding-bottom: 56.25%; overflow: hidden; background: #000; }
.video-wrap iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
.gallery-cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 40px; }

/* REVIEWS */
.reviews-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.review-card { background: #fff; padding: 28px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 10px; }
.stars { color: var(--gold); font-size: .85rem; letter-spacing: 3px; }
.review-card p { color: var(--muted); font-style: italic; font-size: .88rem; line-height: 1.78; flex: 1; }
.review-card strong { font-weight: 700; color: var(--text); font-size: .88rem; }
.review-card span { font-size: .78rem; color: var(--muted); }
.reviews-more { text-align: center; margin-top: 36px; }

/* CONTACT */
.contact-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: start; }
.contact-text h2 { font-size: 2rem; font-weight: 800; margin-bottom: 16px; }
.contact-text p  { color: var(--muted); margin-bottom: 28px; line-height: 1.75; }
.contact-list { list-style: none; display: flex; flex-direction: column; gap: 12px; }
.contact-list li { font-size: .95rem; color: var(--muted); }
.contact-list a  { color: var(--text); font-weight: 600; transition: color .2s; }
.contact-list a:hover { color: var(--gold); }
.contact-card { background: var(--dark); padding: 36px; }
.contact-card h3 { font-size: 1.15rem; font-weight: 700; color: #fff; margin-bottom: 10px; }
.contact-card p  { color: rgba(255,255,255,.4); font-size: .9rem; line-height: 1.7; }

/* FOOTER */
.footer { background: var(--dark); padding: 56px 0 32px; border-top: 1px solid rgba(196,150,60,.1); }
.footer-inner { display: flex; flex-direction: column; align-items: center; gap: 24px; text-align: center; }
.footer-brand .logo { font-size: 15px; }
.footer-brand p { color: rgba(255,255,255,.28); font-size: .82rem; margin-top: 8px; letter-spacing: .04em; }
.footer-links { display: flex; gap: 28px; flex-wrap: wrap; justify-content: center; }
.footer-links a { color: rgba(255,255,255,.3); font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; transition: color .2s; }
.footer-links a:hover { color: var(--gold); }
.footer-copy { color: rgba(255,255,255,.18); font-size: .72rem; letter-spacing: .05em; }

/* RESPONSIVE */
@media (max-width: 900px) {
  .reviews-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  .navbar { padding: 18px 20px; }
  .nav-links { display: none; }
  .btn-nav-mobile { display: block; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .stat { border-right: none; border-bottom: 1px solid rgba(255,255,255,.05); padding: 14px; }
  .videos-grid { grid-template-columns: 1fr; }
  .reviews-grid { grid-template-columns: 1fr; }
  .contact-wrap { grid-template-columns: 1fr; gap: 36px; }
  .hero-content { padding: 120px 20px 52px; }
}
CSS;

$js = <<<'JS'
(function() {
  var el = document.getElementById('hero-vid');
  if (!el) return;
  var id = el.textContent.trim();
  if (!id) return;
  var bg = document.getElementById('hero-video-bg');
  if (!bg) return;
  var iframe = document.createElement('iframe');
  iframe.src = 'https://player.vimeo.com/video/' + encodeURIComponent(id) + '?autoplay=1&loop=1&muted=1&background=1';
  iframe.allow = 'autoplay; fullscreen';
  iframe.setAttribute('frameborder', '0');
  bg.appendChild(iframe);
})();
JS;

$db->prepare('UPDATE templates SET name=?, html_source=?, css_source=?, js_source=? WHERE id=1')
   ->execute(['RB Photos & Videos — Homepage', $html, $css, $js]);
echo "Šablona uložena.\n";

// ─── STRÁNKA ─────────────────────────────────────────────────────────────────
$db->prepare("UPDATE pages SET title=?, slug=?, url_path='', language='cs', template_id=1 WHERE id=1")
   ->execute(['RB Photos & Videos — Fotograf & Videograf Olomouc', 'homepage']);
echo "Stránka nastavena.\n";

// ─── BLOKY ───────────────────────────────────────────────────────────────────
$db->prepare('DELETE FROM content_blocks WHERE page_id=1')->execute();

$blocks = [
  ['hero_tag',          'text',     'Fotograf & Videograf — Olomouc',        0],
  ['hero_title',        'text',     'Zachytíme váš příběh. Na vždy.',        1],
  ['hero_subtitle',     'textarea', 'Profesionální svatební fotografie a videografie v Olomouci a celé České republice. Každý okamžik si zaslouží být nezapomenutelný.', 2],
  ['hero_tel',          'text',     '792 483 583',                           3],
  ['hero_cta',          'text',     'Naše služby',                           4],
  ['stat1_num',         'text',     '150+',                                  5],
  ['stat1_lbl',         'text',     'spokojených klientů',                   6],
  ['stat2_num',         'text',     '8+',                                    7],
  ['stat2_lbl',         'text',     'let zkušeností',                        8],
  ['stat3_num',         'text',     '5 let',                                 9],
  ['stat3_lbl',         'text',     'zálohy fotek zdarma',                  10],
  ['stat4_num',         'text',     '2 fotografové',                        11],
  ['stat4_lbl',         'text',     'tým profesionálů',                     12],
  ['services_title',    'text',     'Co pro vás uděláme',                   13],
  ['services_sub',      'text',     'Ať jde o svatbu, portrét nebo firemní projekt — jsme tu pro vás.', 14],
  ['s1_title',          'text',     'Svatební foto & video',                15],
  ['s1_text',           'textarea', 'Zachytíme každý dotek, každý pohled, každou slzu štěstí. Přirozené reportážní fotografie a filmové video, které vám budou vyprávět příběh vašeho dne po celý život.', 16],
  ['s2_title',          'text',     'Rodinné & portrétní focení',           17],
  ['s2_text',           'textarea', 'Rodinné vzpomínky, těhotenské focení, křtiny nebo portréty. Přirozené, nenucené a krásné snímky, na které budete hrdí.', 18],
  ['s3_title',          'text',     'Reklamní foto & video',                19],
  ['s3_text',           'textarea', 'Profesionální vizuální obsah pro firmy, kapely, produkty nebo brand. Videoklipy, firemní prezentace a obsah, který prodává.', 20],
  ['s4_title',          'text',     'Akce & události',                      21],
  ['s4_text',           'textarea', 'Konference, kulturní akce, plesy, narozeniny. Každá událost si zaslouží být profesionálně zdokumentovaná.', 22],
  ['why_title',         'text',     'Proč si vybrat RB Photos & Videos?',   23],
  ['why_sub',           'text',     'Nejsme jen fotografové. Jsme vypravěči příběhů.',  24],
  ['why1_title',        'text',     'Osobní přístup',                       25],
  ['why1_text',         'textarea', 'Každý klient je pro nás jedinečný. Přátelský tým, který naslouchá vašim přáním a pomůže i s kompozicí.', 26],
  ['why2_title',        'text',     'Zkušený tým',                          27],
  ['why2_text',         'textarea', 'Více než 8 let zkušeností, profesionální vybavení a neustálé vzdělávání. Investujeme do kvality pro vás.', 28],
  ['why3_title',        'text',     '5 let zálohy zdarma',                  29],
  ['why3_text',         'textarea', 'Vaše fotografie a videa bezpečně zálohujeme po dobu 5 let. Nikdy o vzpomínky nepřijdete.', 30],
  ['why4_title',        'text',     'Celá ČR i zahraničí',                  31],
  ['why4_text',         'textarea', 'Sídlíme v Olomouci, ale fotografujeme po celé České republice i v zahraničí. Přijedeme za vámi.', 32],
  ['videos_title',      'text',     'Ukázka naší práce',                    33],
  ['videos_sub',        'text',     'Svatební filmy, klipy a reportáže — podívejte se jak pracujeme.', 34],
  ['reviews_title',     'text',     'Co říkají naši klienti',               35],
  ['reviews_sub',       'text',     'Přes 150 spokojených klientů — jejich slova mluví za nás.', 36],
  ['r1_text',           'textarea', '"Kluci jsou naprostí profesionálové. Jsou to opravdu přátelští borci, kteří vám udělají fotky a videa podle vašich představ a ještě líp. Za mě TOP ve svém oboru. Děkujeme, že jste mohli být na naší svatbě!"', 37],
  ['r1_name',           'text',     'Jan Jüngling',                         38],
  ['r1_event',          'text',     'Svatba M+H',                           39],
  ['r2_text',           'textarea', '"Super domluva, milý a profesionální přístup k focení. Zdena nám nafotil svatební obřad s poobřadním focením v zámeckém parku. Z kvality fotek jsme nadšení a byly dodány dříve než jsme byli domluveni. Velká spokojenost. Doporučuju."', 40],
  ['r2_name',           'text',     'Václav Konopka',                       41],
  ['r2_event',          'text',     'Svatební focení',                      42],
  ['r3_text',           'textarea', '"Moc doporučuji, pan fotograf nám vyčaroval ty nejkrásnější rodinné fotky i s dětmi, co neměly zrovna fotící náladu. Profesionální přístup a špičková kvalita. Už se těším na další focení!"', 43],
  ['r3_name',           'text',     'Jana Dudková',                         44],
  ['r3_event',          'text',     'Rodinné focení',                       45],
  ['r4_text',           'textarea', '"Rychlost, super provedení, nádherné fotky, které rychle dodal upravené. 11/10, ještě jednou moc děkujeme."', 46],
  ['r4_name',           'text',     'Roman Vrána',                          47],
  ['r4_event',          'text',     'Focení',                               48],
  ['r5_text',           'textarea', '"Focení s Bannym mě moc bavilo. Super přístup, atmosféra i fotky. Rozhodně doporučuji!"', 49],
  ['r5_name',           'text',     'Ellie Lachnitová',                     50],
  ['r5_event',          'text',     'Portrétní focení',                     51],
  ['r6_text',           'textarea', '"Profesionalita, ochota, vstřícnost a hlavně poradí i se scénou. Chceš mít profi foto a nevíš komu říct a poradit s kompozicí? Tak teď už víš."', 52],
  ['r6_name',           'text',     'Tomáš Pavlat',                         53],
  ['r6_event',          'text',     'Profesionální focení',                 54],
  ['contact_title',     'text',     'Pojďme vytvořit něco krásného',        55],
  ['contact_intro',     'textarea', 'Nezávazně se nám ozvěte — rádi odpovíme na vaše otázky a připravíme nabídku na míru pro váš den.', 56],
  ['contact_phone1',    'text',     '792 483 583',                          57],
  ['contact_phone2',    'text',     '604 225 032',                          58],
  ['contact_city',      'text',     'Olomouc a celá ČR',                    59],
  ['contact_fb',        'text',     'Facebook: RB Videos & Photos',         60],
  ['contact_ig',        'text',     'Instagram: @rb_videos_photos',         61],
  ['contact_yt',        'text',     'YouTube kanál',                        62],
  ['contact_card_title','text',     'Ozvěte se nám',                        63],
  ['contact_card_text', 'textarea', 'Zavolejte nebo napište na sociální sítě. Odpovídáme rychle!', 64],
  ['footer_text',       'text',     'Profesionální fotografie a videografie — Olomouc', 65],
  ['footer_copy',       'text',     '© 2025 RB Photos & Videos. Všechna práva vyhrazena.', 66],
  ['hero_video_id',     'text',     '1133996815',                                           67],
];

$stmt = $db->prepare('INSERT INTO content_blocks (page_id,block_key,block_type,label,content,sort_order) VALUES (1,?,?,?,?,?)');
foreach ($blocks as [$key, $type, $content, $order]) {
    $stmt->execute([$key, $type, ucfirst(str_replace('_', ' ', $key)), $content, $order]);
}
echo "Bloků: " . count($blocks) . "\n";

// ─── SEO ─────────────────────────────────────────────────────────────────────
$db->prepare('DELETE FROM seo_settings WHERE page_id=1')->execute();
$db->prepare("INSERT INTO seo_settings (page_id,meta_title,meta_description,meta_keywords,canonical_url,robots_index,robots_follow,og_title,og_description,twitter_title,twitter_description,schema_type,schema_json)
VALUES (1,?,?,?,?,1,1,?,?,?,?,?,?)")
->execute([
  'RB Photos & Videos — Fotograf & Videograf Olomouc | Svatby, Rodiny, Akce',
  'Profesionální fotograf a videograf v Olomouci. Svatební fotografie a video, rodinné focení, portréty a reklamní obsah. Zálohy 5 let zdarma. Volejte: 792 483 583.',
  'fotograf Olomouc, videograf Olomouc, svatební fotograf Olomouc, kameraman Olomouc, rodinné focení, portrétní fotografie, reklamní video, RB Photos Videos',
  'http://localhost:8000/',
  'RB Photos & Videos — Fotograf & Videograf Olomouc | Svatby, Rodiny, Akce',
  'Profesionální fotograf a videograf v Olomouci. Zachytíme váš příběh — svatební foto a video, rodinné focení, portréty, reklama. Zálohy 5 let zdarma.',
  'RB Photos & Videos — Fotograf Olomouc',
  'Fotograf & videograf Olomouc. Svatby, rodiny, akce. 792 483 583.',
  'LocalBusiness',
  json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'LocalBusiness',
    'name'        => 'RB Photos & Videos',
    'description' => 'Profesionální fotografie a videografie v Olomouci. Svatby, rodinné focení, portréty, reklamní obsah.',
    'url'         => 'http://localhost:8000/',
    'telephone'   => ['+420792483583', '+420604225032'],
    'address'     => ['@type' => 'PostalAddress', 'addressLocality' => 'Olomouc', 'addressCountry' => 'CZ'],
    'sameAs'      => [
      'https://www.facebook.com/rbvideosandphotos',
      'https://www.instagram.com/rb_videos_photos/',
      'https://www.youtube.com/channel/UC5miHVzPxIs2MjEsoPAzGCw/videos',
    ],
    'priceRange'  => '$$',
    'areaServed'  => 'Česká republika',
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
]);
echo "SEO nastaveno.\n";

// ─── PUBLIKOVAT ──────────────────────────────────────────────────────────────
$service = new App\Services\PublishService();
$result  = $service->publishPage(1, 1);
echo $result['success'] ? "✅ " . $result['message'] . "\n" : "❌ " . $result['message'] . "\n";
