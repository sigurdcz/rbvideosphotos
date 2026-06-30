<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\BlockRepository;
use App\Repositories\BlogRepository;
use App\Repositories\FaqRepository;
use App\Repositories\PageRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\ReviewRepository;
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
        $blogCount = $this->seedBlogPosts($siteId);
        $portfolioCount = $this->seedPortfolioEntries($siteId);
        $this->resetHomepageAutofillBlocks();
        $reviewCount = $this->seedReviewRows($siteId);
        $this->seedCenikCards($siteId);
        $faqCount = $this->seedFaqItems($siteId);

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
            'published' => $published,
        ];
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

        $cards = [
            1 => ['Základní', 'Menší svatba, obřad, skupinky a krátké focení novomanželů.', 'od 14 900 Kč'],
            2 => ['Standardní', 'Nejoblíbenější balíček pro hlavní část svatebního dne.', 'od 24 900 Kč'],
            3 => ['Prémiový', 'Celodenní zachycení svatby s rozšířenou péčí a delší archivací.', 'od 34 900 Kč'],
        ];

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
    }

    /**
     * Svatebni FAQ (12 otazek) do tabulky faq_items — zdroj pro /faq stranku.
     */
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
                'excerpt' => 'Praktický průvodce výběrem lokality, světla a atmosféry pro svatební, portrétní i rodinné focení.',
                'topic' => 'Svatebni misto na foceni',
                'color' => ['151515', 'E6B45D'],
            ],
            [
                'slug' => 'proc-mit-svatebni-video',
                'title' => 'Proč mít svatební video',
                'excerpt' => 'Fotografie zastaví okamžik. Video k němu přidá hlas, hudbu, atmosféru a pohyb celého dne.',
                'topic' => 'Svatebni video',
                'color' => ['211811', 'F1D19A'],
            ],
            [
                'slug' => 'svatebni-fotograf-olomouc',
                'title' => 'Svatební fotograf Olomouc: jak probíhá focení',
                'excerpt' => 'Co čekat před svatbou, během dne a po předání galerie, aby vše působilo klidně a přirozeně.',
                'topic' => 'Svatebni fotograf Olomouc',
                'color' => ['111827', 'D9A441'],
            ],
            [
                'slug' => 'rodinne-foceni-olomouc',
                'title' => 'Rodinné focení v Olomouci bez stresu',
                'excerpt' => 'Jak se připravit, co si vzít na sebe a proč nejlepší fotky často vznikají mezi pózami.',
                'topic' => 'Rodinne foceni Olomouc',
                'color' => ['2B2118', 'FFE2A8'],
            ],
            [
                'slug' => 'reklamni-foto-video-pro-firmy',
                'title' => 'Reklamní foto a video pro firmy',
                'excerpt' => 'Kdy se vyplatí profesionální vizuální obsah a jak z něj vytěžit maximum pro web i sociální sítě.',
                'topic' => 'Reklamni foto video',
                'color' => ['0D1117', '9FDBFF'],
            ],
        ];

        foreach ($posts as $index => $post) {
            $mediaIds = $this->seedImageSet($siteId, 'blog-' . $post['slug'], $post['topic'], $post['color'][0], $post['color'][1], 7);
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
                'title' => 'Svatební foto & video Olomouc',
                'topic' => 'Svatebni foto video Olomouc',
                'color' => ['17110B', 'E7B75E'],
                'video_url' => 'https://vimeo.com/1122538281',
            ],
            [
                'slug' => 'svatebni-fotograf-brno',
                'title' => 'Svatební fotograf Brno',
                'topic' => 'Svatebni fotograf Brno',
                'color' => ['111111', 'FFFFFF'],
                'video_url' => 'https://vimeo.com/1133996815',
            ],
            [
                'slug' => 'rodinne-portretni-foceni',
                'title' => 'Rodinné & portrétní focení',
                'topic' => 'Rodinne portretni foceni',
                'color' => ['2A2018', 'F5D6A1'],
                'video_url' => null,
            ],
            [
                'slug' => 'reklamni-foto-video-olomouc',
                'title' => 'Reklamní foto & video Olomouc',
                'topic' => 'Reklamni foto video Olomouc',
                'color' => ['0C1118', '78D7FF'],
                'video_url' => null,
            ],
            [
                'slug' => 'portretni-foceni-olomouc',
                'title' => 'Portrétní focení Olomouc',
                'topic' => 'Portretni foceni Olomouc',
                'color' => ['1B1410', 'FFD2BA'],
                'video_url' => null,
            ],
        ];

        foreach ($entries as $index => $entry) {
            $mediaIds = $this->seedImageSet($siteId, 'portfolio-' . $entry['slug'], $entry['topic'], $entry['color'][0], $entry['color'][1], 8);
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
            'portfolio-tiles',
            'blog_1',
            'blog_2',
            'blog_3',
            'blog_cta',
            'portfolio_cta',
        ];

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->db->prepare(
            "UPDATE content_blocks SET content = '', autofill_disabled = 0 WHERE page_id = ? AND block_key IN ({$placeholders})"
        );
        $stmt->execute([(int)$homepage['id'], ...$keys]);
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
        $content = $this->blogContent($post['title'], $post['topic']);
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
        $description = '<p>' . $this->h($entry['title']) . ' je ukázková realizace pro naplnění webu reálnější strukturou obsahu.</p>'
            . '<p>Galerie obsahuje hlavní fotografii a sérii doplňkových záběrů, aby bylo vidět dlaždice, detail práce i automatické výpisy na homepage.</p>';
        $publishedAt = date('Y-m-d H:i:s', strtotime('-' . (10 - $index) . ' days'));
        $data = [
            $entry['title'],
            $description,
            $entry['video_url'],
            'published',
            $publishedAt,
            $index + 1,
            $entry['title'] . ' | Portfolio',
            'Ukázková práce fotografa: ' . $entry['title'],
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

    private function blogContent(string $title, string $topic): string
    {
        return '<p>Tenhle demo článek ukazuje, jak může vypadat obsah pro fotografa, když je web naplněný reálnými tématy místo prázdných placeholderů.</p>'
            . '<h2>1. Začněte atmosférou</h2><p>U focení nejde jen o místo, ale o světlo, náladu a příběh. Dobře zvolená lokalita pomůže vytvořit fotky, které působí přirozeně.</p>'
            . '<h2>2. Myslete na čas dne</h2><p>Nejhezčí světlo bývá ráno nebo večer. U svateb se proto vyplatí naplánovat krátké focení při západu slunce.</p>'
            . '<h2>3. Připravte si jednoduchý plán</h2><p>Stačí několik jasných bodů: kde začneme, co nesmí chybět a kolik času máme. Zbytek vzniká přirozeně během dne.</p>'
            . '<h2>4. Nechte prostor emocím</h2><p>Nejsilnější záběry často nejsou dokonale naaranžované. Vznikají mezi momenty, kdy lidé zapomenou, že se fotí.</p>'
            . '<p><strong>Téma ukázky:</strong> ' . $this->h($topic ?: $title) . '.</p>';
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
