<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\BlockRepository;
use App\Repositories\BlogRepository;
use App\Repositories\ContactRepository;
use App\Repositories\FaqRepository;
use App\Repositories\PageRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TemplateRepository;
use PDO;

class DemoContentSeeder
{
    private PDO $db;
    private MediaIngestionService $media;
    private BlogRepository $blog;
    private PortfolioRepository $portfolio;
    private PageRepository $pages;
    private BlockRepository $blocks;
    private TemplateRepository $templates;

    /** filename (demo_assets) => media_id — naplneno v seedMediaLibrary(). */
    private array $demoMedia = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->media = new MediaIngestionService();
        $this->blog = new BlogRepository();
        $this->portfolio = new PortfolioRepository();
        $this->pages = new PageRepository();
        $this->blocks = new BlockRepository();
        $this->templates = new TemplateRepository();
    }

    public function run(?int $siteId = null, ?int $userId = null, bool $publish = true): array
    {
        $site = $siteId ? (new SiteRepository())->findById($siteId) : (new SiteRepository())->findFirst();
        if (!$site) {
            throw new \RuntimeException('Web nenalezen. Nejdřív spusť počáteční seed.');
        }

        $siteId = (int)$site['id'];
        $mediaCount = $this->seedMediaLibrary($siteId);
        $blogCount = $this->seedBlogPosts($siteId);
        $portfolioCount = $this->seedPortfolioEntries($siteId);
        $this->resetHomepageAutofillBlocks();
        $reviewCount = $this->seedReviewRows($siteId);
        $this->seedCenikCards($siteId);
        $this->seedDemoContactInfo($siteId);
        $faqCount = $this->seedFaqItems($siteId);
        $this->seedHeroBackgrounds($siteId);
        $this->seedServiceItems($siteId);
        $this->resetServiceBlocks($siteId);
        $this->seedServiceVisuals($siteId);
        $servicePageCount = $this->seedServicePages($siteId, $site);
        $this->seedPageSeo($siteId, $site);

        $published = false;
        if ($publish && $userId) {
            $result = (new PublishService())->publishSite($siteId, $userId);
            $published = (bool)($result['success'] ?? false);
        }

        return [
            'blog_posts' => $blogCount,
            'portfolio_entries' => $portfolioCount,
            'reviews' => $reviewCount,
            'faq_items' => $faqCount,
            'media' => $mediaCount,
            'service_pages' => $servicePageCount,
            'published' => $published,
        ];
    }

    /**
     * Nahraje vsechny demo fotky (database/seeds/demo_assets) do media knihovny,
     * aby je uzivatel mel v adminu k dispozici. Naplni $this->demoMedia mapou
     * filename => media_id. Idempotentni (dedup podle original_filename).
     */
    private function seedMediaLibrary(int $siteId): int
    {
        $dir = dirname(__DIR__, 2) . '/database/seeds/demo_assets';
        if (!is_dir($dir)) {
            return 0;
        }

        $count = 0;
        $paths = [];
        foreach (['webp', 'png', 'jpg', 'jpeg'] as $ext) {
            $paths = array_merge($paths, glob($dir . '/*.' . $ext) ?: []);
        }
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($paths as $path) {
            $filename = basename($path);
            $alt = $this->altFromFilename($filename);
            $id = $this->ingestDemoAsset($siteId, $filename, $alt);
            if ($id > 0) {
                $this->demoMedia[$filename] = $id;
                $count++;
            }
        }

        return $count;
    }

    private function ingestDemoAsset(int $siteId, string $filename, string $alt): int
    {
        // Dedup: pokud uz medium s timto puvodnim nazvem existuje, znovu pouzij.
        $stmt = $this->db->prepare('SELECT id FROM media WHERE site_id = ? AND original_filename = ? LIMIT 1');
        $stmt->execute([$siteId, $filename]);
        $row = $stmt->fetch();
        if ($row) {
            return (int)$row['id'];
        }

        $path = dirname(__DIR__, 2) . '/database/seeds/demo_assets/' . $filename;
        if (!is_file($path)) {
            return 0;
        }

        try {
            $result = $this->media->ingestUploadedFile($siteId, $path, $filename, $alt, $alt, false);
            return (int)($result['media_id'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function altFromFilename(string $filename): string
    {
        $base = (string) pathinfo($filename, PATHINFO_FILENAME);
        return ucfirst(trim(str_replace('-', ' ', $base)));
    }

    /** Vrati media_id pro dany seznam nazvu souboru (jen existujici). */
    private function pickMedia(array $filenames): array
    {
        $ids = [];
        foreach ($filenames as $f) {
            if (!empty($this->demoMedia[$f])) {
                $ids[] = (int) $this->demoMedia[$f];
            }
        }
        return $ids;
    }

    private function mediaPathById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT storage_path FROM media WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $path = (string) ($stmt->fetchColumn() ?: '');
        return $path !== '' ? '/' . ltrim($path, '/') : '';
    }

    /**
     * Nastavi hero foto (hero_bg) na strankach, kde chybi hero pozadi. Foto se
     * zobrazi jako hero pozadi vsude, kde neni hero video (viz priorita
     * video -> foto -> default).
     */
    private function seedHeroBackgrounds(int $siteId): void
    {
        $map = [
            'sluzby' => 'sluzby.png',
            'cenik' => 'cenik.png',
            'faq' => 'faq.png',
            'nase-prace' => 'nase-prace.png',
            'blog' => 'blog.png',
            'kontakt' => 'kontakt.png',
            'podminky-uziti' => 'podminky-uziti.png',
        ];

        foreach ($map as $urlPath => $filename) {
            if (empty($this->demoMedia[$filename])) {
                continue;
            }
            $page = $this->pages->findByUrlPath($urlPath);
            if (!$page) {
                continue;
            }
            $bgUrl = $this->mediaPathById((int) $this->demoMedia[$filename]);
            if ($bgUrl === '') {
                continue;
            }
            $this->blocks->upsert((int) $page['id'], 'hero_bg', [
                'block_type' => 'image',
                'label' => 'Hero foto',
                'content' => $bgUrl,
                'sort_order' => 5,
                'autofill_disabled' => 1,
            ]);
        }
    }

    /**
     * Seed sluzeb (service_items) pro /sluzby i homepage grid. Builder cte tato
     * data primarne ze service_items (ServiceRepository), takze obsah patri sem.
     */
    private function seedServiceItems(int $siteId): void
    {
        $items = [];
        foreach ($this->servicePages() as $i => $svc) {
            $imageUrl = !empty($this->demoMedia[$svc['image']]) ? $this->mediaPathById((int) $this->demoMedia[$svc['image']]) : '';
            $items[] = [
                'title' => $svc['title'],
                'description' => $svc['lead'],
                'image_url' => $imageUrl,
                'button_text' => 'Více informací',
                // "Více informací" teď vede na detailní stránku služby (viz seedServicePages).
                'button_url' => '/' . $svc['slug'],
                'sort_order' => $i + 1,
                'is_active' => 1,
            ];
        }

        (new ServiceRepository())->upsertMany($siteId, $items);
    }

    /**
     * Vymaze ulozene service bloky (homepage grid i /sluzby stranka), aby se pri
     * publikaci znovu vygenerovaly z aktualnich service_items — jinak zustanou
     * stara tlacitka (napr. odkaz na /kontakt misto na detailni stranku sluzby).
     */
    private function resetServiceBlocks(int $siteId): void
    {
        $keys = ['sluzby_items', 'sluzby_grid', 'service_1', 'service_2', 'service_3', 'service_4'];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        foreach (['', 'sluzby'] as $urlPath) {
            $page = $this->pages->findByUrlPath($urlPath);
            if (!$page) {
                continue;
            }
            $stmt = $this->db->prepare(
                "UPDATE content_blocks SET content = '', autofill_disabled = 0 WHERE page_id = ? AND block_key IN ({$placeholders})"
            );
            $stmt->execute([(int) $page['id'], ...$keys]);
        }
    }

    /**
     * Kanonický seznam 4 služeb. Sdílený mezi service_items (homepage grid + /sluzby)
     * a detailními stránkami služeb (seedServicePages), aby titulky, popisy, obrázky
     * a slugy zůstaly konzistentní na jednom místě.
     */
    private function servicePages(): array
    {
        return [
            [
                'slug' => 'svatebni-foto-video',
                'title' => 'Svatební foto & video',
                'image' => 'svatba-nevesta-zenich-polibek-kamenna-zed.webp',
                'lead' => 'Zachytíme každý dotek, každý pohled i každou slzu štěstí. Přirozené reportážní fotografie a filmové video, které vám budou váš den připomínat celý život.',
                'sections' => [
                    ['Váš den, vyprávěný přirozeně', 'Svatbu fotím i natáčím reportážně — bez zbytečného pózování a režírování. Většinu dne jsem nenápadně poblíž a zachytávám skutečné momenty: přípravy, obřad, gratulace, smích hostů i tiché detaily, kterých si v tom shonu možná ani nevšimnete.'],
                    ['Foto i video pohromadě', 'Fotografie zastaví konkrétní okamžik, video k němu přidá pohyb, hlasy a atmosféru. Dohromady vznikne vzpomínka, ke které se budete vracet celý život. Vše ladím tak, aby výstupy působily jednotně a opravdově.'],
                    ['Co je součástí', '<ul><li>celodenní nebo půldenní focení dle balíčku</li><li>filmový sestřih / svatební video</li><li>online galerie a předání ke stažení</li><li>archivace hotových výstupů</li></ul>'],
                ],
                'seo_title' => 'Svatební foto & video Olomouc | RB Photos & Videos',
                'seo_desc' => 'Svatební fotografie a filmové video v Olomouci a po celé ČR. Přirozené reportážní záběry vašeho dne. Nezávazná poptávka — 792 483 583.',
                'seo_kw' => 'svatební foto video Olomouc, svatební fotograf, svatební kameraman, svatební video',
            ],
            [
                'slug' => 'rodinne-a-portretni-foceni',
                'title' => 'Rodinné & portrétní focení',
                'image' => 'rodinne-foceni-rodina-dite-louka.webp',
                'lead' => 'Rodinné vzpomínky, těhotenské focení, křtiny i portréty. Přirozené, nenucené a krásné snímky, ke kterým se budete rádi vracet.',
                'sections' => [
                    ['Focení bez stresu', 'Rodinné a portrétní focení vedu tak, aby bylo příjemné i pro ty, kdo se neradi fotí. Děti nemusí sedět jako v katalogu a vy nemusíte umět pózovat — navedu vás jednoduše a nechám prostor přirozenosti.'],
                    ['Vzpomínky, ke kterým se vracíte', 'Těhotenské focení, křtiny, rodinné portréty i osobní fotky na míru tomu, k čemu je potřebujete. Společně vybereme místo i styl tak, aby snímky působily uvolněně a opravdově.'],
                    ['Kde fotíme', '<ul><li>v přírodě, parku nebo na místě, které máte rádi</li><li>ateliérové i venkovní portréty</li><li>kratší session vhodná i pro malé děti</li></ul>'],
                ],
                'seo_title' => 'Rodinné & portrétní focení Olomouc | RB Photos & Videos',
                'seo_desc' => 'Rodinné, těhotenské a portrétní focení v Olomouci. Přirozené a nenucené snímky bez stresu, ke kterým se budete rádi vracet.',
                'seo_kw' => 'rodinné focení Olomouc, portrétní focení, těhotenské focení, focení dětí',
            ],
            [
                'slug' => 'reklamni-foto-video',
                'title' => 'Reklamní foto & video',
                'image' => 'sportovni-auto-nissan-silnice-les-pohyb.webp',
                'lead' => 'Profesionální vizuální obsah pro firmy, produkty, kapely i značky. Obsah, který buduje důvěru, odlišuje a prodává.',
                'sections' => [
                    ['Obsah, který buduje důvěru', 'Pomáhám firmám, kapelám i značkám ukázat jejich práci tak, aby působila profesionálně a důvěryhodně. Žádné prázdné snímky z fotobanky — reálné prostředí, lidé a produkty v dobrém světle.'],
                    ['Podle toho, kde se obsah použije', 'Web, sociální sítě, reklama nebo Google profil — každé využití potřebuje trochu jiný přístup. Když to promyslíme dopředu, vznikne materiál, se kterým se dá dlouhodobě pracovat.'],
                    ['Pro koho', '<ul><li>firmy, e-shopy a lokální značky</li><li>produktové a reklamní foto i video</li><li>kapely, klipy a firemní prezentace</li></ul>'],
                ],
                'seo_title' => 'Reklamní foto & video pro firmy | RB Photos & Videos',
                'seo_desc' => 'Profesionální reklamní foto a video pro firmy, produkty i značky. Vizuální obsah, který buduje důvěru a prodává. Olomouc a celá ČR.',
                'seo_kw' => 'reklamní foto video, firemní focení, produktové foto, video pro firmy Olomouc',
            ],
            [
                'slug' => 'akce-a-udalosti',
                'title' => 'Akce & události',
                'image' => 'koncert-zpevak-mikrofon-cernobila-fotografie.webp',
                'lead' => 'Konference, kulturní akce, plesy, koncerty i firemní večery. Každá událost si zaslouží profesionální dokumentaci bez kompromisů.',
                'sections' => [
                    ['Dokumentace bez kompromisů', 'Konference, kulturní akce, plesy, koncerty i firemní večery zachytím tak, aby byla vidět atmosféra i důležité momenty. Pohybuji se nenápadně a soustředím se na to podstatné.'],
                    ['Rychlé a použitelné výstupy', 'Z akce dostanete fotografie i případný videosestřih ve formě, kterou rovnou využijete na web i sociální sítě. Termín, rozsah i způsob předání domluvíme podle konkrétní události.'],
                    ['Co nafotím', '<ul><li>konference, semináře a firemní akce</li><li>plesy, koncerty a kulturní události</li><li>reportážní foto i krátké video</li></ul>'],
                ],
                'seo_title' => 'Foto & video akcí a událostí | RB Photos & Videos',
                'seo_desc' => 'Profesionální dokumentace akcí — konference, plesy, koncerty i firemní večery. Foto i video bez kompromisů. Olomouc a celá ČR.',
                'seo_kw' => 'focení akcí Olomouc, fotograf konference, foto plesy koncerty, dokumentace události',
            ],
        ];
    }

    /**
     * Vytvoří (nebo aktualizuje) 4 detailní stránky služeb — jednu pro každou službu
     * z servicePages(). Každá stránka = pages řádek (published) se sdílenou šablonou
     * "Služba detail" + content_blocks + SEO. Publish (na konci run()) je pak vyrenderuje
     * do storage/published/<slug>/index.html. Idempotentní podle url_path.
     */
    private function seedServicePages(int $siteId, array $site): int
    {
        $templateId = $this->ensureServiceTemplate($siteId);

        $seoStmt = $this->db->prepare(
            'REPLACE INTO seo_settings
               (page_id, meta_title, meta_description, meta_keywords, canonical_url, robots_index, robots_follow,
                og_title, og_description, og_image_id, twitter_title, twitter_description, twitter_image_id, schema_type, schema_json)
             VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $count = 0;
        foreach ($this->servicePages() as $i => $svc) {
            $slug = $svc['slug'];
            $urlPath = '/' . $slug;
            $pageTitle = $svc['title'] . ' | RB Photos & Videos';

            $existing = $this->pages->findByUrlPath($slug);
            if ($existing) {
                $pageId = (int) $existing['id'];
                $this->pages->update($pageId, [
                    'title' => $pageTitle,
                    'slug' => $slug,
                    'url_path' => $urlPath,
                    'template_id' => $templateId,
                    'status' => 'published',
                ]);
            } else {
                $pageId = $this->pages->create([
                    'site_id' => $siteId,
                    'template_id' => $templateId,
                    'title' => $pageTitle,
                    'slug' => $slug,
                    'url_path' => $urlPath,
                    'language' => 'cs',
                    'status' => 'published',
                ]);
            }

            $imageId = !empty($this->demoMedia[$svc['image']]) ? (int) $this->demoMedia[$svc['image']] : 0;
            $imageUrl = $imageId ? $this->mediaPathById($imageId) : '';

            $this->blocks->upsert($pageId, 'hero_tag', [
                'block_type' => 'text', 'label' => 'Hero — štítek',
                'content' => 'Služba ' . sprintf('%02d', $i + 1),
                'sort_order' => 1, 'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'hero_heading', [
                'block_type' => 'text', 'label' => 'Hero — nadpis',
                'content' => $svc['title'], 'sort_order' => 2, 'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'hero_sub', [
                'block_type' => 'textarea', 'label' => 'Hero — podnadpis',
                'content' => $svc['lead'], 'sort_order' => 3, 'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'body', [
                'block_type' => 'html', 'label' => 'Obsah služby',
                'content' => $this->buildServiceBody($imageUrl, $svc['title'], $svc['sections']),
                'sort_order' => 4, 'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'cta_heading', [
                'block_type' => 'text', 'label' => 'CTA — nadpis',
                'content' => 'Pojďme naplánovat vaše focení', 'sort_order' => 5, 'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'cta_sub', [
                'block_type' => 'textarea', 'label' => 'CTA — podnadpis',
                'content' => 'Nezávazně se ozvěte — domluvíme termín, rozsah i výstupy přesně podle vašich představ.',
                'sort_order' => 6, 'autofill_disabled' => 1,
            ]);

            $schemaJson = $this->buildSchemaJson('Service', $svc['seo_title'], $svc['seo_desc'], $urlPath, $imageUrl, $site);
            $seoStmt->execute([
                $pageId, $svc['seo_title'], $svc['seo_desc'], $svc['seo_kw'], $urlPath,
                $svc['seo_title'], $svc['seo_desc'], $imageId ?: null,
                $svc['seo_title'], $svc['seo_desc'], $imageId ?: null,
                'Service', $schemaJson,
            ]);

            $count++;
        }

        return $count;
    }

    /** Sestaví HTML obsah detailní stránky služby (úvodní foto + sekce h2/odstavce). */
    private function buildServiceBody(string $imageUrl, string $title, array $sections): string
    {
        $html = '';
        if ($imageUrl !== '') {
            $html .= '<img class="svc-detail__img" src="' . $this->h($imageUrl) . '" alt="' . $this->h($title) . '" loading="lazy">';
        }
        foreach ($sections as [$heading, $bodyHtml]) {
            $html .= '<h2>' . $this->h($heading) . '</h2>';
            $trimmed = trim((string) $bodyHtml);
            $html .= str_starts_with($trimmed, '<') ? $trimmed : '<p>' . $this->h($trimmed) . '</p>';
        }
        return $html;
    }

    /**
     * Zajistí sdílenou šablonu "Služba detail" (hero + obsah + CTA). Obsahuje jen obsah
     * stránky — nav, patičku a obal doplní site layout při publish. CSS je samonosné,
     * aby stránka vypadala dobře i bez aktivního tématu.
     */
    private function ensureServiceTemplate(int $siteId): int
    {
        $html = <<<'HTML'
<header class="hero svc-hero">
  <div class="container">
    <span class="hero-tag" data-block="hero_tag"></span>
    <h1 data-block="hero_heading"></h1>
    <p data-block="hero_sub"></p>
    <div class="svc-actions">
      <a href="/kontakt" class="btn btn-primary">Nezávazná poptávka</a>
      <a href="/sluzby" class="btn btn-secondary">Zpět na služby</a>
    </div>
  </div>
</header>
<section class="section">
  <div class="container svc-detail" data-block="body"></div>
</section>
<section class="section-dark">
  <div class="container">
    <h2 data-block="cta_heading"></h2>
    <p data-block="cta_sub"></p>
    <div class="svc-actions">
      <a href="tel:792483583" class="btn btn-primary">792&#160;483&#160;583</a>
      <a href="/sluzby" class="btn btn-secondary">Zpět na služby</a>
    </div>
  </div>
</section>
HTML;

        $css = <<<'CSS'
.hero{background:linear-gradient(135deg,#241a13 0%,#161311 62%,#30241b 100%);color:#fff;padding:92px 0 104px}
.svc-hero .container,.section-dark .container{max-width:980px;margin-left:auto;margin-right:auto;text-align:center;display:flex;flex-direction:column;align-items:center}
.hero .hero-tag{display:inline-flex;border:1px solid rgba(214,174,105,.55);padding:9px 18px;font-size:11px;color:#f1cf8b;text-transform:uppercase;letter-spacing:.18em;margin-bottom:24px;background:rgba(255,255,255,.04)}
.hero h1{color:#fff !important;font-size:clamp(2.4rem,4vw,4.4rem);font-weight:900;line-height:1;margin:0 0 20px;text-shadow:0 12px 34px rgba(0,0,0,.32)}
.hero p{color:#fff !important;font-size:1.06rem;line-height:1.75;max-width:720px;margin:0 auto}
.section{padding:64px 0}
.section-dark{background:linear-gradient(135deg,#241a13 0%,#151311 100%);padding:72px 0;border-top:1px solid rgba(214,174,105,.22);border-bottom:1px solid rgba(214,174,105,.22)}
.section-dark h2{color:#fff !important;font-size:clamp(1.8rem,3vw,3rem);line-height:1.12;margin:0 0 14px}
.section-dark p{color:#fff !important;font-size:1.02rem;line-height:1.75;max-width:720px;margin:0 auto}
.svc-detail__img{width:100%;height:auto;margin-bottom:28px;border-radius:6px}
.svc-detail h2{font-size:1.4rem;margin:30px 0 10px;color:#222}
.svc-detail p{color:#555;line-height:1.8;margin-bottom:1rem}
.svc-detail ul{margin:0 0 1rem 1.2rem;color:#555;line-height:1.8}
.svc-actions{display:flex;flex-wrap:wrap;justify-content:center;gap:14px;margin-top:30px}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:14px 24px;font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;text-decoration:none}
.btn-primary{background:#d2a13f;color:#17110c !important;border:1px solid #d2a13f;box-shadow:0 18px 34px rgba(0,0,0,.22)}
.btn-primary:hover{background:#e2b85e;border-color:#e2b85e}
.btn-secondary{background:transparent;color:#fff7ed !important;border:1px solid rgba(255,247,237,.45)}
.btn-secondary:hover{border-color:#d2a13f;color:#f1cf8b !important}
@media(max-width:720px){.hero{padding:72px 0 82px}.svc-actions{flex-direction:column}.btn{width:100%}}
CSS;

        foreach ($this->templates->allBySite($siteId) as $t) {
            if (($t['name'] ?? '') === 'Služba detail') {
                $this->templates->update((int) $t['id'], [
                    'name' => 'Služba detail',
                    'html_source' => $html,
                    'css_source' => $css,
                    'js_source' => '',
                ]);
                return (int) $t['id'];
            }
        }

        return $this->templates->create([
            'site_id' => $siteId,
            'name' => 'Služba detail',
            'html_source' => $html,
            'css_source' => $css,
            'js_source' => '',
        ]);
    }

    private function seedServiceVisuals(int $siteId): void
    {
        if (empty($this->demoMedia['weare2.png'])) {
            return;
        }

        $imageUrl = $this->mediaPathById((int) $this->demoMedia['weare2.png']);
        if ($imageUrl === '') {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE service_items
             SET image_url = ?
             WHERE site_id = ?
             ORDER BY sort_order ASC, id ASC
             LIMIT 1'
        );
        $stmt->execute([$imageUrl, $siteId]);
    }

    /**
     * Doplni kompletni SEO (seo_settings) pro hlavni stranky webu:
     * meta (title/description/keywords/canonical), Open Graph, Twitter Card
     * (vc. og/twitter image ID z media) a Schema.org (type + JSON-LD).
     */
    private function seedPageSeo(int $siteId, array $site): void
    {
        $brand = 'RB Videos & Photos';
        // [title, desc, canonical, keywords, hero foto (pro OG/Twitter image), schema_type]
        $seo = [
            '' => ['Svatební fotograf a kameraman Olomouc | ' . $brand, 'Svatební fotografie a video v Olomouci a po celé ČR. Přirozené záběry, filmové video a profesionální přístup pro váš den.', '/', 'svatební fotograf Olomouc, svatební video Olomouc, svatební kameraman, fotograf Olomouc, foto a video svatba', 'svatba-nevesta-zenich-polibek-kamenna-zed.webp', 'LocalBusiness'],
            'sluzby' => ['Služby — foto a video na míru | ' . $brand, 'Svatební, rodinné, portrétní i reklamní foto a video. Profesionální výsledky a přátelský přístup. Olomouc a celá ČR.', '/sluzby', 'svatební focení, rodinné focení, portrétní focení, reklamní video, fotograf Olomouc', 'svatba-nevesta-zavoj-cernobily-portret.webp', 'WebPage'],
            'cenik' => ['Ceník svatebního focení a videa — Olomouc | ' . $brand, 'Transparentní ceník svatebních balíčků od 14 900 Kč. Foto i video, online galerie a archivace výstupů. Nabídka na míru.', '/cenik', 'ceník svatebního focení, cena svatební fotograf, svatební balíčky, fotograf Olomouc cena', 'svatba-nevesta-kytice-zapad-slunce-u-vody.webp', 'WebPage'],
            'faq' => ['Často kladené otázky — svatební focení | ' . $brand, 'Odpovědi na nejčastější dotazy o rezervaci termínu, dodání fotografií, videu, archivaci i průběhu svatebního focení.', '/faq', 'svatební focení otázky, rezervace fotografa, dodání fotografií, svatební video, archivace', 'svatba-nevesta-zenich-objeti-kytice-les.webp', 'WebPage'],
            'nase-prace' => ['Naše práce — svatební foto a video | ' . $brand, 'Galerie svatebních fotografií a videí z Olomouce a celé ČR. Reálné realizace, párové i rodinné focení.', '/nase-prace', 'svatební fotografie, portfolio fotograf Olomouc, svatební video ukázky, naše práce', 'svatba-nevesta-zenich-zavoj-tanec-priroda.webp', 'WebPage'],
            'blog' => ['Blog — tipy a inspirace pro focení | ' . $brand, 'Rady od profesionálního fotografa: příprava na focení, svatební video, výběr místa a tipy pro klienty.', '/blog', 'tipy svatební focení, příprava na focení, svatební video, fotograf blog, inspirace', 'par-v-prirode-romanticky-portret-zapad-slunce.webp', 'WebPage'],
            'kontakt' => ['Kontakt — svatební fotograf Olomouc | ' . $brand, 'Ozvěte se nám — Olomouc a celá ČR. Volejte nebo napište na sociální sítě, odpovídáme rychle.', '/kontakt', 'kontakt svatební fotograf Olomouc, rezervace termínu, fotograf telefon', 'zamilovany-par-objeti-zapad-slunce-podzim.webp', 'LocalBusiness'],
        ];

        $stmt = $this->db->prepare(
            'REPLACE INTO seo_settings
               (page_id, meta_title, meta_description, meta_keywords, canonical_url, robots_index, robots_follow,
                og_title, og_description, og_image_id, twitter_title, twitter_description, twitter_image_id, schema_type, schema_json)
             VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $seoImageOverrides = [
            'sluzby' => 'sluzby.png',
            'cenik' => 'cenik.png',
            'faq' => 'faq.png',
            'nase-prace' => 'nase-prace.png',
            'blog' => 'blog.png',
            'kontakt' => 'kontakt.png',
            'podminky-uziti' => 'podminky-uziti.png',
        ];

        foreach ($seo as $urlPath => [$title, $desc, $canonical, $keywords, $imageFile, $schemaType]) {
            $imageFile = $seoImageOverrides[$urlPath] ?? $imageFile;
            $page = $this->pages->findByUrlPath($urlPath);
            if (!$page) {
                continue;
            }
            $imageId = !empty($this->demoMedia[$imageFile]) ? (int) $this->demoMedia[$imageFile] : null;
            $imageUrl = $imageId ? $this->mediaPathById($imageId) : '';
            $schemaJson = $this->buildSchemaJson($schemaType, $title, $desc, $canonical, $imageUrl, $site);

            $stmt->execute([
                (int) $page['id'], $title, $desc, $keywords, $canonical,
                $title, $desc, $imageId,
                $title, $desc, $imageId,
                $schemaType, $schemaJson,
            ]);
        }
    }

    private function buildSchemaJson(string $type, string $title, string $desc, string $url, string $imageUrl, array $site): string
    {
        $base = rtrim((string) ($site['domain'] ?? ''), '/');
        $data = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $title,
            'description' => $desc,
            'url' => ($base !== '' ? $base : '') . $url,
        ];
        if ($imageUrl !== '') {
            $data['image'] = ($base !== '' ? $base : '') . $imageUrl;
        }
        if ($type === 'LocalBusiness') {
            $data['telephone'] = '+420 792 483 583';
            $data['areaServed'] = 'Olomouc a celá ČR';
            $data['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Olomouc',
                'addressCountry' => 'CZ',
            ];
        }

        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Svatebni cenik (3 baliky) — prepise cenik_card_N na strance Cenik.
     * Zdroj pro homepage teaser i /cenik. Bez toho zustanou genericke hodnoty
     * z migrace 014.
     */
    private function seedCenikCards(int $siteId): void
    {
        $page = $this->pages->findByUrlPath('cenik');
        if (!$page) {
            return;
        }
        $pageId = (int)$page['id'];
        $templateId = $this->ensurePricingTemplate($siteId);

        $this->pages->update($pageId, [
            'template_id' => $templateId,
            'status' => 'published',
        ]);

        $this->blocks->upsert($pageId, 'hero_heading', [
            'block_type' => 'text',
            'label' => 'Hero - nadpis',
            'content' => 'Cenik fotografickych a video sluzeb - Olomouc',
            'sort_order' => 1,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'hero_intro', [
            'block_type' => 'textarea',
            'label' => 'Hero - intro',
            'content' => 'Transparentni ceny bez prekvapeni. Kazda zakazka je individualni - vzdy se domluvime na balicku presne pro vas. Pres 150 spokojenych klientu, 8 let praxe.',
            'sort_order' => 2,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'pricing_weddings_heading', [
            'block_type' => 'text',
            'label' => 'Cenik svatby - nadpis',
            'content' => 'Svatebni balicky',
            'sort_order' => 3,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'pricing_weddings_sub', [
            'block_type' => 'textarea',
            'label' => 'Cenik svatby - intro',
            'content' => 'Vyberte si balicek podle rozsahu svatebniho dne. Kazda zakazka je individualni - finalni nabidku pripravime vzdy na miru. Ceny jsou orientacni, od.',
            'sort_order' => 4,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'pricing_other_heading', [
            'block_type' => 'text',
            'label' => 'Cenik ostatni - nadpis',
            'content' => 'Rodinne, portretni a firemni foceni',
            'sort_order' => 5,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'pricing_other_sub', [
            'block_type' => 'textarea',
            'label' => 'Cenik ostatni - intro',
            'content' => 'Rodinne foceni od 4 500 Kc - Portretni foceni od 3 000 Kc - Firemni foceni od 6 000 Kc - Zasnuby a maternity od 3 500 Kc - Videografie od 8 000 Kc.',
            'sort_order' => 6,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'cta_heading', [
            'block_type' => 'text',
            'label' => 'CTA - nadpis',
            'content' => 'Rezervujte termin jeste dnes',
            'sort_order' => 7,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'cta_sub', [
            'block_type' => 'textarea',
            'label' => 'CTA - podnadpis',
            'content' => 'Oblibene soboty se obsazuji rok dopredu - cim drive, tim vetsi vyber. Zavolejte nebo napiste, odpovime do 24 hodin.',
            'sort_order' => 8,
            'autofill_disabled' => 1,
        ]);

        // Vicerradkovy popis: 1. radek = souhrn (homepage teaser bere jen prvni
        // radek), zbytek = seznam (.cenik-card-desc ma white-space:pre-line,
        // takze se na /cenik vykresli jako odrazky vc. archivace).
        // Popis = prehledovy odstavec (zobrazi se i na homepage, bez ceny) +
        // prazdny radek + "Obsahuje" seznam (jen na /cenik diky pre-line).
        $cards = [
            1 => [
                'Základní',
                "Pro menší svatby a samotný obřad – obřad, gratulace, skupinové fotografie a krátké focení novomanželů. Přibližně 3 hodiny focení a nejméně 100 upravených fotografií.\n\n"
                . "Obsahuje:\n"
                . "• focení cca 3 hodiny\n"
                . "• obřad, gratulace, skupinové fotografie\n"
                . "• krátké párové focení novomanželů\n"
                . "• minimálně 100 upravených fotografií\n"
                . "• online galerie\n"
                . "• předání fotek ke stažení\n"
                . "• archivace hotových výstupů na 2 roky",
                'od 14 900 Kč',
            ],
            2 => [
                'Standardní',
                "Nejoblíbenější volba pro hlavní část svatebního dne, od příprav po první tanec nebo večerní program. Přibližně 8 hodin focení, 300–500 upravených fotografií a svatební video v ceně.\n\n"
                . "Obsahuje:\n"
                . "• focení cca 8 hodin\n"
                . "• přípravy, obřad, gratulace, skupinky\n"
                . "• párové focení novomanželů\n"
                . "• hostina, detaily a atmosféra dne\n"
                . "• minimálně 300–500 upravených fotografií\n"
                . "• svatební video / sestřih dne\n"
                . "• online galerie\n"
                . "• konzultace před svatbou\n"
                . "• archivace hotových výstupů na 2 roky",
                'od 24 900 Kč',
            ],
            3 => [
                'Prémiový',
                "Kompletní zachycení celého svatebního dne bez spěchu a kompromisů. Přibližně 10–12 hodin focení, prioritní zpracování a svatební video / reels v ceně. Delší archivace výstupů.\n\n"
                . "Obsahuje:\n"
                . "• focení cca 10–12 hodin\n"
                . "• celý den od příprav až po večerní zábavu\n"
                . "• větší počet upravených fotografií\n"
                . "• detailní zachycení atmosféry, hostů a momentů\n"
                . "• svatební video + krátký sestřih / reels v ceně\n"
                . "• online galerie\n"
                . "• konzultace před svatbou\n"
                . "• prioritní zpracování\n"
                . "• archivace hotových výstupů na 5 let",
                'od 34 900 Kč',
            ],
        ];

        $pricingPackages = <<<'HTML'
<div class="ckp-grid">
  <div class="ckp-card">
    <h3 class="ckp-title">Zakladni svatba</h3>
    <div class="ckp-price">od 14 900 Kc</div>
    <p class="ckp-desc">Pro mensi svatby, obrad, gratulace, skupinove fotografie a kratke foceni novomanzelu.</p>
    <ul class="ckp-feats">
      <li>foceni cca 3 hodiny</li>
      <li>obrad, gratulace, skupinove fotografie</li>
      <li>kratke parove foceni novomanzelu</li>
      <li>minimalne 100 upravenych fotografii</li>
      <li>online galerie</li>
      <li>predani fotek ke stazeni</li>
      <li>archivace hotovych vystupu na 2 roky</li>
    </ul>
  </div>
  <div class="ckp-card ckp-card--featured">
    <span class="ckp-badge">Nejoblibenejsi</span>
    <h3 class="ckp-title">Standardni svatba</h3>
    <div class="ckp-price">od 24 900 Kc</div>
    <p class="ckp-desc">Nejoblibenejsi volba pro vetsinu paru. Pokryje hlavni cast svatebniho dne od priprav po prvni tanec nebo vecerni program.</p>
    <ul class="ckp-feats">
      <li>foceni cca 8 hodin</li>
      <li>pripravy, obrad, gratulace, skupinky</li>
      <li>parove foceni novomanzelu</li>
      <li>hostina, detaily a atmosfera dne</li>
      <li>minimalne 300 az 500 upravenych fotografii</li>
      <li>svatebni video nebo sestrih dne</li>
      <li>online galerie</li>
      <li>konzultace pred svatbou</li>
      <li>archivace hotovych vystupu na 2 roky</li>
    </ul>
  </div>
  <div class="ckp-card">
    <h3 class="ckp-title">Premiova svatba</h3>
    <div class="ckp-price">od 34 900 Kc</div>
    <p class="ckp-desc">Kompletni zachyceni celeho svatebniho dne bez spechu a kompromisu.</p>
    <ul class="ckp-feats">
      <li>foceni cca 10 az 12 hodin</li>
      <li>cely den od priprav az po vecerni zabavu</li>
      <li>vetsi pocet upravenych fotografii</li>
      <li>detailni zachyceni atmosfery, hostu a momentu</li>
      <li>svatebni video a kratky sestrih nebo reels v cene</li>
      <li>online galerie a video ke stazeni</li>
      <li>konzultace pred svatbou</li>
      <li>prioritni zpracovani</li>
      <li>archivace hotovych vystupu na 5 let</li>
    </ul>
  </div>
</div>
HTML;

        $this->blocks->upsert($pageId, 'pricing_packages', [
            'block_type' => 'html',
            'label' => 'Cenik svatby - balicky',
            'content' => $pricingPackages,
            'sort_order' => 5,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'pricing_body', [
            'block_type' => 'html',
            'label' => 'Cenik svatby - legacy balicky',
            'content' => $pricingPackages,
            'sort_order' => 5,
            'autofill_disabled' => 1,
        ]);

        $order = 10;
        foreach ($cards as $i => [$title, $desc, $price]) {
            $this->blocks->upsert($pageId, 'cenik_card_' . $i . '_title', [
                'block_type' => 'text',
                'label' => 'Karta ' . $i . ' — nadpis',
                'content' => $title,
                'sort_order' => $order++,
                'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'cenik_card_' . $i . '_desc', [
                'block_type' => 'textarea',
                'label' => 'Karta ' . $i . ' — popis',
                'content' => $desc,
                'sort_order' => $order++,
                'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'cenik_card_' . $i . '_price', [
                'block_type' => 'text',
                'label' => 'Karta ' . $i . ' — cena',
                'content' => $price,
                'sort_order' => $order++,
                'autofill_disabled' => 1,
            ]);
        }

        // Poznamka pod cenik (do CTA bloku) + zachovani tlacitka poptavky.
        $note = '<div class="cenik-note">'
            . '<p>Hotové výstupy archivuji podle zvoleného balíčku po dobu 2 nebo 5 let. '
            . 'Díky tomu je možné fotografie nebo video v případě ztráty znovu obnovit.</p>'
            . '<p class="cenik-note__fine">Archivace se vztahuje na finálně předané výstupy, '
            . 'nikoliv na všechny surové záznamy.</p>'
            . '</div>'
            . '<a href="/kontakt" class="btn-primary">Nezávazná poptávka</a>';

        $this->blocks->upsert($pageId, 'cta_wrap', [
            'block_type' => 'html',
            'label' => 'Ceník — poznámka a CTA',
            'content' => $note,
            'sort_order' => 90,
            'autofill_disabled' => 1,
        ]);
    }

    /**
     * Svatebni FAQ (12 otazek) do tabulky faq_items — zdroj pro /faq stranku.
     */
    private function ensurePricingTemplate(int $siteId): int
    {
        $html = <<<'HTML'
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
<section class="section-dark">
  <div class="container">
    <h2 data-block="cta_heading"></h2>
    <p data-block="cta_sub"></p>
    <div class="cenik-cta-wrap" data-block="cta_wrap"></div>
  </div>
</section>
HTML;

        $css = <<<'CSS'
.page-hero{background:#1a1a1a;color:#fff;padding:104px 0 96px;position:relative;overflow:hidden}
.page-hero .container{position:relative;z-index:3;max-width:920px;margin:0 auto;text-align:center;display:flex;flex-direction:column;align-items:center}
.page-hero h1{color:#fff;font-size:clamp(2.6rem,5vw,4.7rem);font-weight:700;line-height:1.03;margin:0 0 1rem;max-width:760px}
.page-hero .hero-sub{color:rgba(255,255,255,.82);max-width:720px;margin:0;line-height:1.78}
.section{background:#f7f2ea;padding:76px 0 84px}
.section .container,.section-dark .container{max-width:1060px;margin:0 auto;padding:0 24px}
.section h2{color:#241914;font-family:Georgia,serif;font-size:clamp(1.8rem,3vw,2.7rem);line-height:1.08;margin:0 0 14px}
.section p{max-width:860px;color:#6b5948;line-height:1.75}
.ckp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px;align-items:stretch;margin:26px 0 180px}
.ckp-grid+h2{margin-top:0}
.ckp-card{position:relative;display:flex;flex-direction:column;gap:18px;height:100%;padding:30px 26px;background:#fff;border:1px solid #e7ddd0;border-radius:22px;box-shadow:0 18px 48px rgba(39,27,18,.08)}
.ckp-card--featured{border-color:#c79a49;box-shadow:0 24px 58px rgba(199,154,73,.18)}
.ckp-badge{position:absolute;top:-12px;left:24px;padding:5px 12px;background:#c79a49;color:#fff;font-size:.66rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase;border-radius:999px}
.ckp-title{margin:0;color:#241914;font-family:Georgia,serif;font-size:1.9rem;line-height:1.02}
.ckp-price{margin:0;color:#c4963c;font-size:1.45rem;font-weight:900}
.ckp-desc{margin:0;color:#5f5143;line-height:1.72}
.ckp-feats{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
.ckp-feats li{position:relative;padding-left:20px;color:#5f5143;line-height:1.6}
.ckp-feats li::before{content:"\2713";position:absolute;left:0;top:0;color:#c79a49;font-weight:900}
.section-dark{background:radial-gradient(circle at top,rgba(214,170,95,.12),transparent 45%),linear-gradient(135deg,#1c140f,#120d09);padding:88px 0 92px;border-top:1px solid rgba(214,170,95,.16)}
.section-dark .container{display:flex;flex-direction:column;align-items:center;text-align:center}
.section-dark h2{max-width:760px;color:#fff7ec;font-family:Georgia,serif;font-size:clamp(2rem,3.8vw,3.4rem);line-height:1.04;margin:0 0 16px}
.section-dark p{max-width:760px;color:rgba(247,239,227,.78);line-height:1.78;margin:0 auto 14px}
.cenik-cta-wrap{display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;max-width:760px;gap:18px}
.cenik-note{max-width:760px;margin:6px auto 8px;text-align:center}
.cenik-note p{color:rgba(247,239,227,.76);line-height:1.72;margin:0 0 8px}
.cenik-note__fine{font-size:.82rem;color:rgba(247,239,227,.52)}
.btn-primary{display:inline-flex;align-items:center;justify-content:center;min-width:260px;min-height:52px;padding:14px 26px;background:#d6aa5f;color:#1b120d!important;border:1px solid #d6aa5f;border-radius:0;text-decoration:none;font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;box-shadow:0 18px 36px rgba(0,0,0,.22)}
.btn-primary:hover{background:#e0b866;border-color:#e0b866;color:#120d09!important}
@media(max-width:720px){.section .container,.section-dark .container{padding:0 18px}.ckp-grid{grid-template-columns:1fr}.ckp-card{padding:24px 20px}.btn-primary{width:min(100%,320px)}}
CSS;

        foreach ($this->templates->allBySite($siteId) as $template) {
            if (($template['name'] ?? '') === 'Demo cenik') {
                $this->templates->update((int)$template['id'], [
                    'name' => 'Demo cenik',
                    'html_source' => $html,
                    'css_source' => $css,
                    'js_source' => '',
                ]);
                return (int)$template['id'];
            }
        }

        return $this->templates->create([
            'site_id' => $siteId,
            'name' => 'Demo cenik',
            'html_source' => $html,
            'css_source' => $css,
            'js_source' => '',
        ]);
    }

    private function seedDemoContactInfo(int $siteId): void
    {
        $phone1 = '+420 792 483 583';
        $phone2 = '+420 604 225 032';
        $email = 'rb_vp@volny.cz';
        $formEmail = ContactRepository::DEFAULT_FORM_EMAIL;

        (new ContactRepository())->upsert($siteId, [
            'phone_1' => $phone1,
            'phone_2' => $phone2,
            'phone_3' => '',
            'email' => $email,
            'form_email' => $formEmail,
            'social_1_type' => 'instagram',
            'social_1_url' => 'https://www.instagram.com/rb_videos_photos/',
            'social_2_type' => 'facebook',
            'social_2_url' => 'https://www.facebook.com/rbvideosandphotos',
            'social_3_type' => 'youtube',
            'social_3_url' => 'https://www.youtube.com/channel/UC5miHVzPxIs2MjEsoPAzGCw',
            'social_4_type' => '',
            'social_4_url' => '',
            'social_5_type' => '',
            'social_5_url' => '',
        ]);

        $keys = ['kontakt_info', 'contact_info', 'contact_card'];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        foreach (['', 'kontakt'] as $urlPath) {
            $page = $this->pages->findByUrlPath($urlPath);
            if (!$page) {
                continue;
            }

            $stmt = $this->db->prepare(
                "UPDATE content_blocks SET content = '', autofill_disabled = 0 WHERE page_id = ? AND block_key IN ({$placeholders})"
            );
            $stmt->execute([(int)$page['id'], ...$keys]);
        }
    }

    private function seedFaqItems(int $siteId): int
    {
        $faqs = [
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

        $rows = [];
        $order = 0;
        foreach ($faqs as [$q, $a]) {
            $rows[] = ['question' => $q, 'answer' => $a, 'sort_order' => ++$order, 'is_active' => 1];
        }

        (new FaqRepository())->replaceAllBySite($siteId, $rows);

        return count($rows);
    }

    private function seedBlogPosts(int $siteId): int
    {
        $posts = [
            [
                'slug' => 'jak-vybrat-misto-na-foceni',
                'title' => 'Jak vybrat místo na focení',
                'excerpt' => 'Místo na focení nemusí být slavné ani dokonalé. Důležité je, aby vám sedělo, bylo příjemné a pomohlo vytvořit fotky, které nebudou působit strojeně.',
                'intro' => 'Místo na focení nemusí být slavné ani dokonalé. Důležité je, aby vám sedělo, bylo příjemné a pomohlo vytvořit fotky, které nebudou působit strojeně.',
                'paragraphs' => [
                    'Když se domlouvám na focení, výběr místa řeším vždycky hned na začátku. Ne proto, že bych potřeboval za každou cenu nějakou „instagramovou“ lokalitu, ale proto, že místo hodně ovlivní náladu celého focení.',
                    'Někdy stačí obyčejná louka, klidná ulička, park, zahrada nebo místo, kde to máte rádi. Nemusí to být nic velkého. Důležitější je světlo, klid a to, abyste se tam necítili divně.',
                    'U svatby se snažím vybírat místa hlavně prakticky. Nechci vás tahat na hodinu pryč od hostů jen kvůli fotkám. Většinou se dá najít hezké místo někde poblíž, jen je potřeba se na něj podívat správně.',
                    'U rodinného nebo portrétního focení zase řeším, aby se tam dobře fungovalo vám. Pokud máte děti, je lepší místo, kde se můžou trochu hýbat. Pokud chcete klidnější portréty, hledám prostředí, které nebude rušit.',
                    'Za mě je nejlepší místo takové, kde se cítíte přirozeně. Fotky pak nejsou jen hezké, ale mají v sobě i něco vašeho.',
                ],
                'conclusion' => 'Jestli nevíte, kde fotit, klidně to nechte na mě. Navrhnu pár míst podle toho, co spolu budeme fotit a jakou atmosféru chcete.',
                'topic' => 'Svatebni misto na foceni',
                'color' => ['151515', 'E6B45D'],
            ],
            [
                'slug' => 'proc-mit-svatebni-video',
                'title' => 'Proč mít svatební video',
                'excerpt' => 'Fotky jsou krásná vzpomínka. Video ale zachytí i hlas, pohyb, smích a atmosféru, která se do jedné fotky prostě nevejde.',
                'intro' => 'Fotky jsou krásná vzpomínka. Video ale zachytí i hlas, pohyb, smích a atmosféru, která se do jedné fotky prostě nevejde.',
                'paragraphs' => [
                    'Svatební video podle mě není jen něco navíc. Je to jiný způsob, jak si ten den uchovat.',
                    'Fotka zastaví jeden okamžik. Video vám ho po čase připomene trochu jinak. Uslyšíte sliby, smích hostů, hudbu, gratulace, nervozitu před obřadem i malé věci, kterých si v den svatby možná ani nevšimnete.',
                    'Nesnažím se ze svatby dělat hraný film. Mám raději, když video působí opravdově. Když je v něm vidět, jaký ten den skutečně byl, kdo tam byl s vámi a co se dělo kolem.',
                    'Hodně lidí si řekne, že fotky stačí. A ano, fotky jsou základ. Ale video má sílu hlavně v čase. Po pár letech si nepustíte jen obraz, ale i zvuk a atmosféru. A to je něco, co časem získává mnohem větší hodnotu.',
                    'Nejde o to mít video za každou cenu. Jde o to, jestli chcete mít ze svatby vzpomínku, která bude živější než samotné fotky.',
                ],
                'conclusion' => 'Pokud nad videem váháte, za mě dává smysl hlavně tehdy, když chcete zachytit nejen to, jak svatba vypadala, ale i jak zněla a jaká byla.',
                'topic' => 'Svatebni video',
                'color' => ['211811', 'F1D19A'],
            ],
            [
                'slug' => 'svatebni-fotograf-olomouc',
                'title' => 'Svatební fotograf Olomouc: jak probíhá focení',
                'excerpt' => 'Svatební focení nemusí narušit celý den. Když se dobře naplánuje, proběhne v klidu a vy si ho nemusíte zbytečně hlídat.',
                'intro' => 'Svatební focení nemusí narušit celý den. Když se dobře naplánuje, proběhne v klidu a vy si ho nemusíte zbytečně hlídat.',
                'paragraphs' => [
                    'Svatba není focení v ateliéru. Je to živý den, kde se pořád něco děje. Proto se snažím být připravený, ale zároveň do všeho zbytečně nezasahovat.',
                    'Před svatbou si projdu harmonogram. Zajímá mě obřad, místo hostiny, důležití lidé, skupinové fotky a čas, kdy bude prostor na portréty. Díky tomu pak v den svatby nemusíte všechno vysvětlovat znovu.',
                    'Během dne fotím hlavně přirozené momenty. Přípravy, obřad, gratulace, hosty, detaily, smích, nervozitu i malé situace kolem. To jsou věci, které podle mě ke svatbě patří stejně jako portréty.',
                    'Když přijde čas na společné focení novomanželů, povedu vás. Nemusíte vědět, jak stát nebo co dělat s rukama. Řeknu vám to jednoduše a bez zbytečného pózování.',
                    'V Olomouci a okolí se dá najít hodně hezkých míst, ale vždycky řeším hlavně to, aby to zapadlo do vašeho dne. Fotky jsou důležité, ale nechci, abyste kvůli nim přišli o půlku svatby.',
                ],
                'conclusion' => 'Pokud chcete svatební focení v Olomouci v klidu a bez strojeného pózování, můžeme si projít váš den a vymyslet, jak to udělat přirozeně.',
                'topic' => 'Svatebni fotograf Olomouc',
                'color' => ['111827', 'D9A441'],
            ],
            [
                'slug' => 'rodinne-foceni-olomouc',
                'title' => 'Rodinné focení v Olomouci bez stresu',
                'excerpt' => 'Rodinné focení nemusí být o nucených úsměvech. Může to být klidná procházka, pár společných momentů a fotky, na kterých se opravdu poznáte.',
                'intro' => 'Rodinné focení nemusí být o nucených úsměvech. Může to být klidná procházka, pár společných momentů a fotky, na kterých se opravdu poznáte.',
                'paragraphs' => [
                    'U rodinného focení často slyším: „Děti asi nevydrží,“ nebo „My se neumíme fotit.“ To je úplně normální. A upřímně, ani nechci, aby děti stály půl hodiny na místě a usmívaly se na povel.',
                    'Nejhezčí rodinné fotky většinou vzniknou ve chvíli, kdy se trochu zapomene, že se fotí. Když se děti smějí, běží, drží se vás, něco ukazují nebo se prostě chovají tak, jak je znáte.',
                    'Focení vedu tak, aby bylo jednoduché. Řeknu vám, kam si stoupnout, co udělat a jak se natočit. Zároveň ale nechávám prostor tomu, co se děje samo.',
                    'V Olomouci a okolí je dost míst, kde se dá fotit příjemně a bez velkého stresu. Parky, příroda, klidnější zákoutí nebo místa, kam chodíte rádi. Nemusíme hledat nic složitého. Hlavní je, aby vám tam bylo dobře.',
                    'Cílem není vytvořit dokonale naaranžovanou rodinu. Cílem je zachytit vás tak, jak spolu opravdu jste.',
                ],
                'conclusion' => 'Jestli máte z rodinného focení trochu obavy, vůbec nevadí. Provedu vás tím tak, aby to bylo co nejpřirozenější.',
                'topic' => 'Rodinne foceni Olomouc',
                'color' => ['2B2118', 'FFE2A8'],
            ],
            [
                'slug' => 'reklamni-foto-video-pro-firmy',
                'title' => 'Reklamní foto a video pro firmy',
                'excerpt' => 'Dobré fotky a video nejsou jen výplň webu. Často rozhodují o tom, jestli firma působí důvěryhodně, nebo obyčejně.',
                'intro' => 'Dobré fotky a video nejsou jen výplň webu. Často rozhodují o tom, jestli firma působí důvěryhodně, nebo obyčejně.',
                'paragraphs' => [
                    'Když přijdu na firemní web a vidím tam náhodné ilustrační fotky, většinou mi tam něco chybí. Nevidím lidi, prostředí, práci ani skutečnou atmosféru firmy.',
                    'A přesně proto má smysl mít vlastní fotky a videa.',
                    'Nejde jen o to, aby výstup hezky vypadal. Dobrý vizuální obsah má lidem rychle ukázat, kdo jste, co děláte a proč vám mají věřit. U firmy to může být provozovna, tým, výrobek, služba, práce v terénu, zákulisí nebo konkrétní proces.',
                    'Před focením nebo natáčením se vždycky ptám, kde se budou výstupy používat. Web potřebuje něco jiného než sociální sítě. Reklama zase něco jiného než profil na Googlu. Když se tohle promyslí dopředu, vzniknou materiály, které mají mnohem větší využití.',
                    'Nemám rád obsah, který působí jako prázdná reklama. Lepší je ukázat firmu čistě, hezky, ale pořád lidsky. Aby zákazník neměl pocit, že se dívá na něco umělého.',
                ],
                'conclusion' => 'Pokud chcete, aby vaše firma působila lépe a důvěryhodněji, vlastní foto a video je jedna z nejpraktičtějších věcí, kterou můžete udělat.',
                'topic' => 'Reklamni foto video',
                'color' => ['0D1117', '9FDBFF'],
            ],
        ];

        $blogImages = [
            'jak-vybrat-misto-na-foceni' => ['svatba-nevesta-kytice-zapad-slunce-u-vody.webp', 'par-v-prirode-romanticky-portret-zapad-slunce.webp', 'svatba-nevesta-zenich-objeti-kytice-les.webp'],
            'proc-mit-svatebni-video' => ['svatba-nevesta-zenich-zavoj-tanec-priroda.webp', 'svatba-nevesta-zenich-polibek-kamenna-zed.webp', 'svatba-nevesta-zavoj-cernobily-portret.webp'],
            'svatebni-fotograf-olomouc' => ['svatba-nevesta-zenich-polibek-kamenna-zed.webp', 'snubni-prsteny-listy-zapad-slunce-detail.webp', 'svatba-nevesta-kytice-zapad-slunce-u-vody.webp'],
            'rodinne-foceni-olomouc' => ['rodinne-foceni-rodina-dite-louka.webp', 'rodinne-tehotenske-foceni-rodina-pole.webp', 'detske-foceni-chlapec-odrazedlo-zapad-slunce.webp'],
            'reklamni-foto-video-pro-firmy' => ['sportovni-auto-nissan-silnice-les-pohyb.webp', 'koncert-zpevak-mikrofon-cernobila-fotografie.webp'],
        ];

        foreach ($posts as $index => $post) {
            $mediaIds = $this->pickMedia($blogImages[$post['slug']] ?? []);
            if ($mediaIds === []) {
                $mediaIds = $this->seedImageSet($siteId, 'blog-' . $post['slug'], $post['topic'], $post['color'][0], $post['color'][1], 7);
            }
            $featured = $mediaIds[0] ?? null;
            $id = $this->upsertBlogPost($siteId, $post, $featured, $index);
            $this->blog->setGallery($id, $mediaIds);
        }

        return count($posts);
    }

    private function seedPortfolioEntries(int $siteId): int
    {
        $entries = [
            [
                'slug' => 'svatebni-foto-video-olomouc',
                'title' => 'Svatební foto a video Olomouc',
                'excerpt' => 'Svatbu fotím a natáčím tak, aby vzpomínky působily opravdově. Ne jako póza, ale jako váš den se vším, co k němu patřilo.',
                'intro' => 'Svatbu fotím a natáčím tak, aby vzpomínky působily opravdově. Ne jako póza, ale jako váš den se vším, co k němu patřilo.',
                'paragraphs' => [
                    'Každá svatba má vlastní tempo. Někde je hodně emocí, jinde víc smíchu, někde klidná rodinná atmosféra a jinde velká párty. Mojí prací je to vnímat a zachytit tak, aby výsledek odpovídal právě vám.',
                    'Při focení a natáčení se snažím být spíš nenápadný. Nechci vás pořád zastavovat a říkat, co máte dělat. Když je potřeba, pomůžu a navedu vás. Ale když se děje něco přirozeného, nechám tomu prostor.',
                    'Fotky vám zachytí konkrétní momenty. Video k tomu přidá pohyb, hlasy, hudbu a atmosféru. Dohromady to dává vzpomínku, ke které se dá vracet úplně jinak.',
                    'V Olomouci a okolí znám dost míst, kde se dá udělat hezké portrétní focení, aniž byste museli na dlouho odjíždět od hostů. Vždycky hledám řešení, které bude hezké, ale zároveň praktické.',
                    'Nejde mi o to udělat svatbu efektnější, než byla. Jde mi o to zachytit ji tak, abyste se v těch fotkách a videu poznali.',
                ],
                'conclusion' => 'Pokud chcete ze svatby fotky i video v osobním a přirozeném stylu, rád s vámi projdu plán dne a navrhnu, jak vše zachytit co nejlépe.',
                'topic' => 'Svatebni foto video Olomouc',
                'color' => ['17110B', 'E7B75E'],
                'video_url' => 'https://vimeo.com/1122538281',
            ],
            [
                'slug' => 'svatebni-fotograf-brno',
                'title' => 'Svatební fotograf Brno',
                'excerpt' => 'Svatební focení v Brně dělám tak, aby bylo přirozené, klidné a zapadlo do vašeho dne bez zbytečného stresu.',
                'intro' => 'Svatební focení v Brně dělám tak, aby bylo přirozené, klidné a zapadlo do vašeho dne bez zbytečného stresu.',
                'paragraphs' => [
                    'Při svatebním focení se nesnažím z lidí dělat někoho jiného. Chci, aby fotky působily jako vy. Jen v dobrém světle, ve správný moment a s trochou vedení tam, kde je potřeba.',
                    'Brno má spoustu krásných míst, ale vždycky se dívám hlavně na to, co je reálně praktické. Někdy je lepší krátké focení kousek od místa svatby než složitý přejezd na známou lokalitu. V den svatby je čas cenný.',
                    'Předem si s vámi projdu, co je důležité. Kde bude obřad, kolik chcete skupinových fotek, kdy bude prostor na portréty a jestli máte nějaké konkrétní přání.',
                    'Během dne fotím reportážně, takže zachytím i momenty, které se nedají naplánovat. Pohledy, reakce rodičů, smích u stolu, objetí, detaily a atmosféru.',
                    'U portrétů vás navedu jednoduše. Nemusíte mít žádnou zkušenost s focením. Stačí mi věřit a nechat se vést.',
                ],
                'conclusion' => 'Pokud hledáte svatebního fotografa v Brně a chcete fotky bez přehnaného pózování, můžeme se domluvit a projít vaši představu.',
                'topic' => 'Svatebni fotograf Brno',
                'color' => ['111111', 'FFFFFF'],
                'video_url' => 'https://vimeo.com/1133996815',
            ],
            [
                'slug' => 'rodinne-portretni-foceni',
                'title' => 'Rodinné a portrétní focení',
                'excerpt' => 'Fotím lidi tak, aby se na fotkách poznali. Přirozeně, jednoduše a bez pocitu, že musí před foťákem něco hrát.',
                'intro' => 'Fotím lidi tak, aby se na fotkách poznali. Přirozeně, jednoduše a bez pocitu, že musí před foťákem něco hrát.',
                'paragraphs' => [
                    'Ne každý se rád fotí. To chápu. Právě proto se snažím focení vést tak, aby bylo co nejpříjemnější a aby člověk neměl pocit, že musí umět pózovat.',
                    'U portrétů řeším hlavně to, k čemu fotky potřebujete. Jinak se fotí pracovní portrét, jinak fotka na sociální sítě a jinak osobní portrét jen pro radost. Podle toho vybírám místo, styl i atmosféru.',
                    'U rodinného focení nechávám víc prostoru přirozenosti. Děti nemusí sedět jako v katalogu. Rodiče nemusí dokonale pózovat. Stačí být spolu, trochu se uvolnit a nechat focení plynout.',
                    'Samozřejmě vás během focení povedu. Řeknu vám, co dělat, kam se podívat, jak se postavit nebo jak se k sobě přiblížit. Jen to nechci dělat tvrdě a strojeně.',
                    'Dobrá fotka podle mě nemusí být dokonalá. Musí být uvěřitelná.',
                ],
                'conclusion' => 'Pokud chcete rodinné nebo portrétní fotky, které budou působit přirozeně a ne jako nucená póza, rád vám s tím pomůžu.',
                'topic' => 'Rodinne portretni foceni',
                'color' => ['2A2018', 'F5D6A1'],
                'video_url' => null,
            ],
            [
                'slug' => 'reklamni-foto-video-olomouc',
                'title' => 'Reklamní foto a video Olomouc',
                'excerpt' => 'Pomáhám firmám ukázat jejich práci tak, aby působila dobře, srozumitelně a důvěryhodně. Bez zbytečně umělé reklamy.',
                'intro' => 'Pomáhám firmám ukázat jejich práci tak, aby působila dobře, srozumitelně a důvěryhodně. Bez zbytečně umělé reklamy.',
                'paragraphs' => [
                    'Firemní fotky a videa by neměly být jen hezká dekorace na webu. Měly by lidem ukázat, kdo jste, co děláte a proč vám můžou věřit.',
                    'Když fotím nebo natáčím pro firmu, nejdřív mě zajímá, kde se bude obsah používat. Web, reklama, sociální sítě, Google profil, prezentace nebo nábor. Každé použití potřebuje trochu jiný přístup.',
                    'Můžeme zachytit provozovnu, tým, produkty, službu, práci v praxi nebo atmosféru firmy. Důležité je, aby výsledek nepůsobil jako náhodný obrázek z fotobanky. Lidé chtějí vidět realitu, jen dobře zpracovanou.',
                    'Snažím se, aby výstupy byly čisté, použitelné a dlouhodobě využitelné. Ne jen jedna fotka na jeden příspěvek, ale materiál, se kterým se dá dál pracovat.',
                    'V Olomouci a okolí můžeme připravit obsah pro menší firmy, služby, řemeslníky, restaurace, lokální značky i e-shopy.',
                ],
                'conclusion' => 'Pokud chcete, aby vaše firma působila na webu a sociálních sítích lépe, můžeme společně vymyslet obsah, který bude dávat smysl pro vaše zákazníky.',
                'topic' => 'Reklamni foto video Olomouc',
                'color' => ['0C1118', '78D7FF'],
                'video_url' => null,
            ],
            [
                'slug' => 'portretni-foceni-olomouc',
                'title' => 'Portrétní focení Olomouc',
                'excerpt' => 'Portrétní focení nemusí být nepříjemné. Pomůžu vám s pózou, výrazem i místem, aby fotky působily přirozeně a dobře vás vystihly.',
                'intro' => 'Portrétní focení nemusí být nepříjemné. Pomůžu vám s pózou, výrazem i místem, aby fotky působily přirozeně a dobře vás vystihly.',
                'paragraphs' => [
                    'Portrét je často první věc, kterou o vás někdo uvidí. Na webu, v profilu, na sociálních sítích nebo v pracovním kontaktu. Proto by měl působit dobře, ale zároveň ne falešně.',
                    'Před focením se vždycky ptám, k čemu fotky potřebujete. Chcete působit profesionálně, přátelsky, kreativně, elegantně nebo civilně? Podle toho zvolíme místo, oblečení i celkový styl.',
                    'Během focení vás povedu. Nemusíte řešit, jak se tvářit nebo kam dát ruce. To je moje práce. Budu vás navádět tak, aby fotky vypadaly dobře, ale nepůsobily křečovitě.',
                    'V Olomouci můžeme fotit ve městě, v přírodě, u jednoduchého pozadí nebo na místě, které má souvislost s tím, co děláte.',
                    'Mám raději portréty, které jsou čisté a přirozené, než fotky, které se snaží být za každou cenu efektní.',
                ],
                'conclusion' => 'Pokud chcete portrétní focení v Olomouci a nechcete u toho působit strojeně, domluvíme styl, místo a všechno projdeme v klidu.',
                'topic' => 'Portretni foceni Olomouc',
                'color' => ['1B1410', 'FFD2BA'],
                'video_url' => null,
            ],
        ];

        $portfolioImages = [
            'svatebni-foto-video-olomouc' => ['svatba-nevesta-zenich-polibek-kamenna-zed.webp', 'svatba-nevesta-zenich-objeti-kytice-les.webp', 'svatba-nevesta-kytice-zapad-slunce-u-vody.webp', 'svatba-nevesta-zavoj-cernobily-portret.webp', 'svatba-nevesta-zenich-zavoj-tanec-priroda.webp', 'snubni-prsteny-listy-zapad-slunce-detail.webp'],
            'svatebni-fotograf-brno' => ['svatba-nevesta-zenich-objeti-kytice-les.webp', 'svatba-nevesta-zenich-zavoj-tanec-priroda.webp', 'svatba-nevesta-zavoj-cernobily-portret.webp', 'svatba-nevesta-kytice-zapad-slunce-u-vody.webp', 'snubni-prsteny-listy-zapad-slunce-detail.webp', 'svatba-nevesta-zenich-polibek-kamenna-zed.webp'],
            'rodinne-portretni-foceni' => ['rodinne-foceni-rodina-dite-louka.webp', 'rodinne-tehotenske-foceni-rodina-pole.webp', 'tehotenske-foceni-par-lavicka-park.webp', 'portret-zeny-letni-saty-listi-priroda.webp', 'detske-foceni-chlapec-odrazedlo-zapad-slunce.webp'],
            'reklamni-foto-video-olomouc' => ['sportovni-auto-nissan-silnice-les-pohyb.webp', 'koncert-zpevak-mikrofon-cernobila-fotografie.webp'],
            'portretni-foceni-olomouc' => ['portret-divka-kytice-kvetinova-celenka-slunce.webp', 'portret-zeny-letni-saty-listi-priroda.webp', 'par-v-prirode-romanticky-portret-zapad-slunce.webp', 'zamilovany-par-objeti-zapad-slunce-podzim.webp'],
        ];

        foreach ($entries as $index => $entry) {
            $mediaIds = $this->pickMedia($portfolioImages[$entry['slug']] ?? []);
            if ($mediaIds === []) {
                $mediaIds = $this->seedImageSet($siteId, 'portfolio-' . $entry['slug'], $entry['topic'], $entry['color'][0], $entry['color'][1], 8);
            }
            $id = $this->upsertPortfolioEntry($siteId, $entry, $index);
            $this->portfolio->setMedia($id, $mediaIds);
        }

        return count($entries);
    }

    private function seedHomepageReviews(int $siteId): int
    {
        $reviews = [
            ['Jan Jüngling', 'Svatba M+H', 'Kluci jsou naprostí profesionálové. Jsou pečliví a zachytili přesně atmosféru našeho dne.'],
            ['Václav Konopka', 'Svatební focení', 'Super domluva, milý přístup a fotky dodané dříve, než jsme čekali. Velká spokojenost.'],
            ['Jana Dudková', 'Rodinné focení', 'Moc doporučuji. Děti se cítily přirozeně a fotky mají krásné barvy i emoce.'],
            ['Markéta a Tomáš', 'Svatební video', 'Video vystihlo atmosféru dne do posledního detailu. Vracíme se k němu pořád dokola.'],
            ['Lucie Nováková', 'Portrétní focení', 'Focení bylo uvolněné a bez stresu. Výsledek působí přirozeně a profesionálně.'],
            ['Petr Malý', 'Firemní akce', 'Rychlá domluva, jistota na místě a výstupy použitelné rovnou na web i sociální sítě.'],
            ['Anna a Michal', 'Svatba', 'Fotky mají nádhernou atmosféru. Oceňujeme hlavně klidný přístup během celého dne.'],
            ['Kateřina D.', 'Rodinné focení', 'Děti nebyly strojené, všechno proběhlo hravě a výsledek je přesně náš.'],
            ['Dominik S.', 'Reklamní video', 'Skvělá práce se světlem, obrazem i střihem. Výstup působí draze a čistě.'],
            ['Veronika a Pavel', 'Svatba', 'Výborná komunikace před svatbou i během dne. Fotky i video jsou krásná vzpomínka.'],
            ['Ellie Lachnitova', 'Foceni s Bannym', 'Foceni s Bannym me moc bavilo. Super pristup, atmosfera i fotky. Rozhodne doporucuji! :)'],
            ['Michael Latal', 'Foceni', 'Velmi super pristup i fotky.'],
            ['Roman Vrana', 'Svatebni foceni', 'Rychlost, super provedeni, nadherne fotky, ktere rychle dodal upravene. 11/10, jeste jednou moc dekujeme <3'],
            ['Tomas Pavlat', 'Portretni foceni', 'Profesionalita, ochota, vstricnost a hlavne poradi i se scenou. Chces mit profi foto a nevis komu rict a poradit s kompozici? Tak ted uz vis.'],
            ['Lucie Fedorova', 'Foceni', 'Lepsiho fotografa neznam. :) Uzasny pristup, nadherny fotky. Doporucuji.'],
            ['Zuzana Horakova', 'Rodinne foceni', 'Perfektni. Foceni velmi prijemne a fotky krasne. Doporucuji vsemi deseti.'],
            ['Martina Bobkova', 'Foceni', 'Super fotky a super pokec :) doporucuji moc moc.'],
            ['Darca Polaskova', 'Svatebni foceni', 'Super domluva, zabava a vtipky pri samotnem foceni, fotky skvele a je jich hodne :3 Rozhodne doporucuji :)'],
            ['Dana Kneblova', 'Foceni', 'Bylo to zabavne a uplne pohodove foceni ;)'],
            ['Veronika Bokova', 'Rodinne foceni', 'Domluva skvela, foceni zabavne, takze z cloveka hned odpadne nervozita a fotky paradni...Doporucuji! :)'],
            ['Michal Kucera', 'Foceni', 'Skvela komunikace, super fotky jen doporucuji.'],
            ['Dominika Jecminkova', 'Foceni', 'Skvela komunikace, skvele fotky ^^ Kdykoliv se jde domluvit, aby to vyhovovalo obema stranam :) Muzu jen doporucit.'],
            ['Rose De Clermont', 'Foceni', 'Velka spokojenost! Dvakrat nam foceni zrusilo spatne pocasi, ale diky dobre komunikaci s fotografem se foceni fakt uskutecnilo :) I po foceni je paradni domluva, kdyz chci jeste nejakou dalsi z fotek upravit. Doporucuji :)'],
            ['Aneta Jenesova', 'Foceni', 'Super fotky a skvela domluva s panem fotografem doporucuji :)'],
            ['Jenny Molatova', 'Foceni', 'Super fotky ^^'],
            ['Martin Jenes', 'Foceni', 'Vrele doporucuji. Perfektni prace pana fotografa.'],
            ['Vojtech Vrana', 'Hudebni klip', 'Cerstvy klip z nove desky WE ARE HERE. Jsme nataceli s RB - Videos & Photos, jsou to sikovni panove.'],
            ['Veronika Saskova', 'Celodenni foceni', 'Diky klukum z rbvideosphotos jsem mela moznost vyzkouset si celodenni foceni v pronajatem apartmanu, kde se vystridalo nekolik modelek. Byla to fajn zkusenost :)'],
            ['Diana Smidova', 'Foceni', 'Dekujeme za nejkrasnejsi vzpominku! Super prace.'],
            ['Clarissa cosplayer', 'Photo shoot', 'Today is nice day. I already have photos from the photo shoot. Thank you so much to the photographer for the photos and video.'],
            ['Tomas Fyfty Fiala', 'Hudebni klip', 'A je to venku. Klip k novemu songu SVETLO. Velke diky klukum z RB - Videos & Photos.'],
            ['Katerina Vaclavkova', 'Foceni', 'Super :) :)'],
            ['Radka Polynkova', 'Foceni', 'Moc dekujeme za uzasne fotky <3'],
        ];

        $homepage = $this->pages->findByUrlPath('');
        if (!$homepage) {
            return 0;
        }

        $this->ensureReviewsBlockInHomepageTemplate($homepage);

        $html = '<div class="hp-reviews-grid">';
        foreach ($reviews as $review) {
            [$author, $context, $text] = $review;
            $html .= '<article class="hp-review-card">'
                . '<div class="hp-review-stars">★★★★★</div>'
                . '<p>„' . $this->h($text) . '“</p>'
                . '<strong>' . $this->h($author) . '</strong>'
                . '<span>' . $this->h($context) . '</span>'
                . '</article>';
        }
        $html .= '</div>';

        $this->blocks->upsert((int)$homepage['id'], 'reviews_demo', [
            'block_type' => 'html',
            'label' => 'Demo recenze',
            'content' => $this->reviewsCss() . $html,
            'sort_order' => 70,
            'autofill_disabled' => 1,
        ]);

        return count($reviews);
    }

    private function seedReviewRows(int $siteId): int
    {
        $reviews = [
            ['author_name' => 'Jan Jungling', 'context_label' => 'Svatba M+H', 'review_text' => 'Kluci jsou naprosti profesionalove. Jsou peclivi a zachytili presne atmosferu naseho dne.', 'rating' => 5, 'sort_order' => 1, 'is_active' => 1],
            ['author_name' => 'Vaclav Konopka', 'context_label' => 'Svatebni foceni', 'review_text' => 'Super domluva, mily pristup a fotky dodane drive, nez jsme cekali. Velka spokojenost.', 'rating' => 5, 'sort_order' => 2, 'is_active' => 1],
            ['author_name' => 'Jana Dudkova', 'context_label' => 'Rodinne foceni', 'review_text' => 'Moc doporucuji. Deti se citily prirozene a fotky maji krasne barvy i emoce.', 'rating' => 5, 'sort_order' => 3, 'is_active' => 1],
            ['author_name' => 'Marketa a Tomas', 'context_label' => 'Svatebni video', 'review_text' => 'Video vystihlo atmosferu dne do posledniho detailu. Vracime se k nemu porad dokola.', 'rating' => 5, 'sort_order' => 4, 'is_active' => 1],
            ['author_name' => 'Lucie Novakova', 'context_label' => 'Portretni foceni', 'review_text' => 'Foceni bylo uvolnene a bez stresu. Vysledek pusobi prirozene a profesionalne.', 'rating' => 5, 'sort_order' => 5, 'is_active' => 1],
            ['author_name' => 'Petr Maly', 'context_label' => 'Firemni akce', 'review_text' => 'Rychla domluva, jistota na miste a vystupy pouzitelne rovnou na web i socialni site.', 'rating' => 5, 'sort_order' => 6, 'is_active' => 1],
            ['author_name' => 'Anna a Michal', 'context_label' => 'Svatba', 'review_text' => 'Fotky maji nadhernou atmosferu. Ocenujeme hlavne klidny pristup behem celeho dne.', 'rating' => 5, 'sort_order' => 7, 'is_active' => 1],
            ['author_name' => 'Katerina D.', 'context_label' => 'Rodinne foceni', 'review_text' => 'Deti nebyly strojene, vsechno probehlo hrave a vysledek je presne nas.', 'rating' => 5, 'sort_order' => 8, 'is_active' => 1],
            ['author_name' => 'Dominik S.', 'context_label' => 'Reklamni video', 'review_text' => 'Skvela prace se svetlem, obrazem i strihem. Vystup pusobi draze a ciste.', 'rating' => 5, 'sort_order' => 9, 'is_active' => 1],
            ['author_name' => 'Veronika a Pavel', 'context_label' => 'Svatba', 'review_text' => 'Vyborna komunikace pred svatbou i behem dne. Fotky i video jsou krasna vzpominka.', 'rating' => 5, 'sort_order' => 10, 'is_active' => 1],
            ['author_name' => 'Ellie Lachnitova', 'context_label' => 'Foceni s Bannym', 'review_text' => 'Foceni s Bannym me moc bavilo. Super pristup, atmosfera i fotky. Rozhodne doporucuji! :)', 'rating' => 5, 'sort_order' => 11, 'is_active' => 1],
            ['author_name' => 'Michael Latal', 'context_label' => 'Foceni', 'review_text' => 'Velmi super pristup i fotky.', 'rating' => 5, 'sort_order' => 12, 'is_active' => 1],
            ['author_name' => 'Roman Vrana', 'context_label' => 'Svatebni foceni', 'review_text' => 'Rychlost, super provedeni, nadherne fotky, ktere rychle dodal upravene. 11/10, jeste jednou moc dekujeme <3', 'rating' => 5, 'sort_order' => 13, 'is_active' => 1],
            ['author_name' => 'Tomas Pavlat', 'context_label' => 'Portretni foceni', 'review_text' => 'Profesionalita, ochota, vstricnost a hlavne poradi i se scenou. Chces mit profi foto a nevis komu rict a poradit s kompozici? Tak ted uz vis.', 'rating' => 5, 'sort_order' => 14, 'is_active' => 1],
            ['author_name' => 'Lucie Fedorova', 'context_label' => 'Foceni', 'review_text' => 'Lepsiho fotografa neznam. :) Uzasny pristup, nadherny fotky. Doporucuji.', 'rating' => 5, 'sort_order' => 15, 'is_active' => 1],
            ['author_name' => 'Zuzana Horakova', 'context_label' => 'Rodinne foceni', 'review_text' => 'Perfektni. Foceni velmi prijemne a fotky krasne. Doporucuji vsemi deseti.', 'rating' => 5, 'sort_order' => 16, 'is_active' => 1],
            ['author_name' => 'Martina Bobkova', 'context_label' => 'Foceni', 'review_text' => 'Super fotky a super pokec :) doporucuji moc moc.', 'rating' => 5, 'sort_order' => 17, 'is_active' => 1],
            ['author_name' => 'Darca Polaskova', 'context_label' => 'Svatebni foceni', 'review_text' => 'Super domluva, zabava a vtipky pri samotnem foceni, fotky skvele a je jich hodne :3 Rozhodne doporucuji :)', 'rating' => 5, 'sort_order' => 18, 'is_active' => 1],
            ['author_name' => 'Dana Kneblova', 'context_label' => 'Foceni', 'review_text' => 'Bylo to zabavne a uplne pohodove foceni ;)', 'rating' => 5, 'sort_order' => 19, 'is_active' => 1],
            ['author_name' => 'Veronika Bokova', 'context_label' => 'Rodinne foceni', 'review_text' => 'Domluva skvela, foceni zabavne, takze z cloveka hned odpadne nervozita a fotky paradni...Doporucuji! :)', 'rating' => 5, 'sort_order' => 20, 'is_active' => 1],
            ['author_name' => 'Michal Kucera', 'context_label' => 'Foceni', 'review_text' => 'Skvela komunikace, super fotky jen doporucuji.', 'rating' => 5, 'sort_order' => 21, 'is_active' => 1],
            ['author_name' => 'Dominika Jecminkova', 'context_label' => 'Foceni', 'review_text' => 'Skvela komunikace, skvele fotky ^^ Kdykoliv se jde domluvit, aby to vyhovovalo obema stranam :) Muzu jen doporucit.', 'rating' => 5, 'sort_order' => 22, 'is_active' => 1],
            ['author_name' => 'Rose De Clermont', 'context_label' => 'Foceni', 'review_text' => 'Velka spokojenost! Dvakrat nam foceni zrusilo spatne pocasi, ale diky dobre komunikaci s fotografem se foceni fakt uskutecnilo :) I po foceni je paradni domluva, kdyz chci jeste nejakou dalsi z fotek upravit. Doporucuji :)', 'rating' => 5, 'sort_order' => 23, 'is_active' => 1],
            ['author_name' => 'Aneta Jenesova', 'context_label' => 'Foceni', 'review_text' => 'Super fotky a skvela domluva s panem fotografem doporucuji :)', 'rating' => 5, 'sort_order' => 24, 'is_active' => 1],
            ['author_name' => 'Jenny Molatova', 'context_label' => 'Foceni', 'review_text' => 'Super fotky ^^', 'rating' => 5, 'sort_order' => 25, 'is_active' => 1],
            ['author_name' => 'Martin Jenes', 'context_label' => 'Foceni', 'review_text' => 'Vrele doporucuji. Perfektni prace pana fotografa.', 'rating' => 5, 'sort_order' => 26, 'is_active' => 1],
            ['author_name' => 'Vojtech Vrana', 'context_label' => 'Hudebni klip', 'review_text' => 'Cerstvy klip z nove desky WE ARE HERE. Jsme nataceli s RB - Videos & Photos, jsou to sikovni panove.', 'rating' => 5, 'sort_order' => 27, 'is_active' => 1],
            ['author_name' => 'Veronika Saskova', 'context_label' => 'Celodenni foceni', 'review_text' => 'Diky klukum z rbvideosphotos jsem mela moznost vyzkouset si celodenni foceni v pronajatem apartmanu, kde se vystridalo nekolik modelek. Byla to fajn zkusenost :)', 'rating' => 5, 'sort_order' => 28, 'is_active' => 1],
            ['author_name' => 'Diana Smidova', 'context_label' => 'Foceni', 'review_text' => 'Dekujeme za nejkrasnejsi vzpominku! Super prace.', 'rating' => 5, 'sort_order' => 29, 'is_active' => 1],
            ['author_name' => 'Clarissa cosplayer', 'context_label' => 'Photo shoot', 'review_text' => 'Today is nice day. I already have photos from the photo shoot. Thank you so much to the photographer for the photos and video.', 'rating' => 5, 'sort_order' => 30, 'is_active' => 1],
            ['author_name' => 'Tomas Fyfty Fiala', 'context_label' => 'Hudebni klip', 'review_text' => 'A je to venku. Klip k novemu songu SVETLO. Velke diky klukum z RB - Videos & Photos.', 'rating' => 5, 'sort_order' => 31, 'is_active' => 1],
            ['author_name' => 'Katerina Vaclavkova', 'context_label' => 'Foceni', 'review_text' => 'Super :) :)', 'rating' => 5, 'sort_order' => 32, 'is_active' => 1],
            ['author_name' => 'Radka Polynkova', 'context_label' => 'Foceni', 'review_text' => 'Moc dekujeme za uzasne fotky <3', 'rating' => 5, 'sort_order' => 33, 'is_active' => 1],
        ];

        (new ReviewRepository())->replaceAllBySite($siteId, $reviews);
        (new ReviewContentService())->ensureHomepageSection($siteId);

        return count($reviews);
    }

    private function resetHomepageAutofillBlocks(): void
    {
        $homepage = $this->pages->findByUrlPath('');
        if (!$homepage) {
            return;
        }

        $keys = [
            'services_title',
            'services_intro',
            'sluzby_title',
            'sluzby_intro',
            'portfolio_title',
            'portfolio_intro',
            'sluzby_items',
            'sluzby_grid',
            'service_1',
            'service_2',
            'service_3',
            'service_4',
            'portfolio-tiles',
            'portfolio_btns',
            'blog_title',
            'blog_intro',
            'blog_1',
            'blog_2',
            'blog_3',
            'blog_cta',
            'cenik_title',
            'cenik_intro',
            'pricing_title',
            'pricing_intro',
            'cenik_body',
            'pricing_body',
            'pricing_cta',
            'cenik_cta',
            'reviews_title',
            'reviews_intro',
            'reviews_demo',
            'reviews_cta',
            'kontakt_title',
            'kontakt_sub',
            'cta_title',
            'cta_sub',
            'kontakt_info',
            'contact_info',
            'contact_card',
            'portfolio_cta',
        ];

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->db->prepare(
            "UPDATE content_blocks SET content = '', autofill_disabled = 0 WHERE page_id = ? AND block_key IN ({$placeholders})"
        );
        $stmt->execute([(int)$homepage['id'], ...$keys]);

        $this->normalizeHomepageTemplateLabels($homepage);
    }

    private function normalizeHomepageTemplateLabels(array $homepage): void
    {
        $templateId = (int)($homepage['template_id'] ?? 0);
        if ($templateId <= 0) {
            return;
        }

        $template = $this->templates->findById($templateId);
        if (!$template) {
            return;
        }

        $html = (string)($template['html_source'] ?? '');
        if ($html === '') {
            return;
        }

        $updated = preg_replace_callback(
            '~(<section\b[^>]*\bid=(["\'])nase-prace\2[^>]*>(?:(?!</section>).)*?)(<p\b[^>]*\bclass=(["\'])section-label\4[^>]*>)(.*?)(</p>)~is',
            static fn(array $m): string => $m[1] . $m[3] . 'Naše práce' . $m[6],
            $html,
            1
        ) ?? $html;

        if ($updated === $html) {
            return;
        }

        $this->templates->update($templateId, [
            'name' => $template['name'],
            'html_source' => $updated,
            'css_source' => $template['css_source'] ?? '',
            'js_source' => $template['js_source'] ?? '',
        ]);
    }

    private function seedImageSet(int $siteId, string $prefix, string $topic, string $bg, string $fg, int $count): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $isMain = $i === 1;
            $size = $isMain ? '1400x933' : ($i % 2 === 0 ? '1200x800' : '900x1200');
            $label = $topic . ($isMain ? ' hlavni fotka' : ' galerie ' . $i);
            $url = 'https://placehold.co/' . $size . '/' . $bg . '/' . $fg . '/png?text=' . rawurlencode($label);
            $result = $this->media->ingestRemoteImage($siteId, $url, $prefix . '-' . $i, $label, $label);
            $ids[] = (int)$result['media_id'];
        }
        return $ids;
    }

    private function upsertBlogPost(int $siteId, array $post, ?int $featuredImageId, int $index): int
    {
        $existing = $this->fetchId('blog_posts', $siteId, $post['slug']);
        $content = $this->longformContent($post);
        $publishedAt = date('Y-m-d H:i:s', strtotime('-' . (6 - $index) . ' days'));
        $data = [
            $post['title'],
            $post['excerpt'],
            $content,
            $featuredImageId,
            'published',
            $publishedAt,
            $post['title'] . ' | RB Photos & Videos',
            $post['excerpt'],
        ];

        if ($existing) {
            $this->db->prepare('UPDATE blog_posts SET title=?, excerpt=?, content=?, featured_image_id=?, status=?, published_at=?, meta_title=?, meta_description=?, updated_at=NOW() WHERE id=?')
                ->execute([...$data, $existing]);
            return $existing;
        }

        $this->db->prepare('INSERT INTO blog_posts (site_id, title, slug, excerpt, content, featured_image_id, status, published_at, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$siteId, $post['title'], $post['slug'], $post['excerpt'], $content, $featuredImageId, 'published', $publishedAt, $post['title'] . ' | RB Photos & Videos', $post['excerpt']]);
        return (int)$this->db->lastInsertId();
    }

    private function upsertPortfolioEntry(int $siteId, array $entry, int $index): int
    {
        $existing = $this->fetchId('portfolio_entries', $siteId, $entry['slug']);
        $description = $this->longformContent($entry);
        $publishedAt = date('Y-m-d H:i:s', strtotime('-' . (10 - $index) . ' days'));
        $data = [
            $entry['title'],
            $description,
            $entry['video_url'],
            'published',
            $publishedAt,
            $index + 1,
            $entry['title'] . ' | Portfolio',
            $entry['excerpt'] ?? ('Ukázková práce fotografa: ' . $entry['title']),
        ];

        if ($existing) {
            $this->db->prepare('UPDATE portfolio_entries SET title=?, description=?, video_url=?, status=?, published_at=?, sort_order=?, meta_title=?, meta_description=?, updated_at=NOW() WHERE id=?')
                ->execute([...$data, $existing]);
            return $existing;
        }

        $this->db->prepare('INSERT INTO portfolio_entries (site_id, title, slug, description, video_url, status, published_at, sort_order, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$siteId, $entry['title'], $entry['slug'], $description, $entry['video_url'], 'published', $publishedAt, $index + 1, $entry['title'] . ' | Portfolio', 'Ukázková práce fotografa: ' . $entry['title']]);
        return (int)$this->db->lastInsertId();
    }

    private function ensureReviewsBlockInHomepageTemplate(array $homepage): void
    {
        $templateId = (int)($homepage['template_id'] ?? 0);
        if ($templateId <= 0) {
            return;
        }

        $template = $this->templates->findById($templateId);
        if (!$template) {
            return;
        }

        $html = (string)($template['html_source'] ?? '');
        if (str_contains($html, 'reviews_demo')) {
            return;
        }

        $section = <<<'HTML'

<section class="section section--dark hp-reviews-demo" id="hodnoceni">
  <div class="container">
    <p class="section-label">Co o nás říkají</p>
    <h2 class="section-title">Hodnocení našich párů</h2>
    <div data-block="reviews_demo"><!-- Demo recenze --></div>
    <div data-block="reviews_cta" class="hp-section-cta"><a href="/hodnoceni" class="btn-secondary">Vsechny recenze</a></div>
  </div>
</section>
HTML;

        $updated = preg_replace('~(<section\b[^>]*id=(["\'])kontakt\2[^>]*>)~i', $section . "\n$1", $html, 1, $count);
        if (!$updated || $count === 0) {
            $updated = $html . $section;
        }

        $this->templates->update($templateId, [
            'name' => $template['name'],
            'html_source' => $updated,
            'css_source' => $template['css_source'] ?? '',
            'js_source' => $template['js_source'] ?? '',
        ]);
    }

    private function fetchId(string $table, int $siteId, string $slug): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM `{$table}` WHERE site_id = ? AND slug = ? LIMIT 1");
        $stmt->execute([$siteId, $slug]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function longformContent(array $item): string
    {
        // Bez nadpisu sekci (Krátký úvod / Text / Závěr) — jen odstavce s mezerou.
        $html = '';
        $intro = trim((string)($item['intro'] ?? ''));
        if ($intro !== '') {
            $html .= '<p>' . $this->h($intro) . '</p>';
        }

        $paragraphs = $item['paragraphs'] ?? [];
        if (is_array($paragraphs)) {
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim((string)$paragraph);
                if ($paragraph === '') {
                    continue;
                }
                $html .= '<p>' . $this->h($paragraph) . '</p>';
            }
        }

        $conclusion = trim((string)($item['conclusion'] ?? ''));
        if ($conclusion !== '') {
            $html .= '<p>' . $this->h($conclusion) . '</p>';
        }

        return $html;
    }

    private function reviewsCss(): string
    {
        return '<style>'
            . '.hp-reviews-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;margin-top:28px}'
            . '.hp-review-card{background:rgba(255,255,255,.04);border:1px solid rgba(196,150,60,.32);padding:22px;min-height:190px}'
            . '.hp-review-stars{color:#d9a441;letter-spacing:.16em;margin-bottom:12px;font-size:.9rem}'
            . '.hp-review-card p{color:rgba(255,255,255,.72);font-style:italic;line-height:1.7;margin:0 0 18px}'
            . '.hp-review-card strong{display:block;color:#fff;font-size:.95rem;margin-bottom:2px}'
            . '.hp-review-card span{color:rgba(255,255,255,.48);font-size:.82rem}'
            . '</style>';
    }

    private function h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
