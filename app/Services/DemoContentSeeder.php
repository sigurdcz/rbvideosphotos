<?php

namespace App\Services;

use App\Content\CustomerReviewDataset;
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
    public const DEFAULT_THEME_KEY = ThemeTemplateRegistry::DEFAULT_THEME_KEY;
    public const DEFAULT_HERO_POSTER_ASSET = 'svatba-nevesta-zenich-polibek-kamenna-zed.webp';
    /** First /sluzby card; seed and migration 097 resolve it by title or detail URL, never by id. */
    public const FIRST_SERVICE_TITLE = 'Svatební den - foto + video';
    public const FIRST_SERVICE_DETAIL_URL = '/svatebni-foto-video';
    /** Demo asset shown on the first service card (seed = migration 097). */
    public const FIRST_SERVICE_VISUAL = 'weare5.jpg';
    /** Poster/fallback shown before Tomas's About video facade is activated. */
    public const ABOUT_CAMERAMAN_FALLBACK_ASSET = 'fallback tomáš.png';
    /** Hero photograph for the /nase-prace portfolio listing. */
    public const WORK_LISTING_HERO_ASSET = 'Snímek obrazovky 2026-10-06 132148.png';

    private PDO $db;
    private MediaIngestionService $media;
    private BlogRepository $blog;
    private PortfolioRepository $portfolio;
    private PageRepository $pages;
    private BlockRepository $blocks;
    private TemplateRepository $templates;

    /** filename (demo_assets) => media_id - naplneno v seedMediaLibrary(). */
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
        $themeRegistry = new ThemeTemplateRegistry();
        if ($themeRegistry->find(self::DEFAULT_THEME_KEY) === null) {
            throw new \RuntimeException('Výchozí demo motiv nebyl nalezen: ' . self::DEFAULT_THEME_KEY);
        }

        $mediaCount = $this->seedMediaLibrary($siteId);
        $this->applyApprovedSiteDefaultsAndAboutProfile($siteId);
        $defaultHeroPosterUrl = $this->seedDefaultHeroPoster($siteId);
        $blogCount = $this->seedBlogPosts($siteId);
        $weddingBlog = (new WeddingBlogDemoContentImporter())->import($siteId);
        $blogCount += $weddingBlog['articles'];
        $portfolioResult = (new PortfolioDemoContentImporter())->import($siteId);
        $portfolioCount = $portfolioResult['entries'];
        $this->resetHomepageAutofillBlocks();
        $this->seedHomepageElegantWhiteBlocks($siteId);
        $reviewCount = $this->seedReviewRows($siteId);
        $this->seedCenikCards($siteId);
        $this->seedRealPricingContent($siteId);
        $this->seedDemoContactInfo($siteId);
        $faqCount = $this->seedFaqItems($siteId);
        $this->seedHeroBackgrounds($siteId);
        $this->seedServiceItems($siteId);
        $this->resetServiceBlocks($siteId);
        $this->seedServiceVisuals($siteId);
        $servicePageCount = $this->seedServicePages($siteId, $site);
        $this->seedPageSeo($siteId, $site);
        $this->seedPageHeadings();

        (new SiteRepository())->update($siteId, ['active_theme_key' => self::DEFAULT_THEME_KEY]);
        $themeRegistry->persistActiveThemeKey(self::DEFAULT_THEME_KEY);

        $published = false;
        if ($publish && $userId) {
            $result = (new PublishService())->publishSite($siteId, $userId);
            $published = (bool)($result['success'] ?? false);
        }

        return [
            'blog_posts' => $blogCount,
            'portfolio_entries' => $portfolioCount,
            'portfolio_media' => $portfolioResult['media'],
            'wedding_blog_media' => $weddingBlog['media'],
            'wedding_blog_warnings' => $weddingBlog['warnings'],
            'reviews' => $reviewCount,
            'faq_items' => $faqCount,
            'media' => $mediaCount,
            'service_pages' => $servicePageCount,
            'active_theme' => self::DEFAULT_THEME_KEY,
            'default_hero_poster_url' => $defaultHeroPosterUrl,
            'published' => $published,
        ];
    }

    /** Number of individually processed base assets in the HTTP batch pipeline. */
    public function demoAssetBatchCount(): int
    {
        return count($this->demoAssetPaths());
    }

    /** @return array{label:string,media_id:int} */
    public function seedDemoAssetBatchItem(int $siteId, int $index): array
    {
        $paths = $this->demoAssetPaths();
        if (!isset($paths[$index])) {
            throw new \RuntimeException('Neplatný index dávky základních médií: ' . $index);
        }
        $filename = basename($paths[$index]);
        $id = $this->ingestDemoAsset($siteId, $filename, $this->altFromFilename($filename));
        if ($id <= 0) {
            throw new \RuntimeException('Demo médium se nepodařilo importovat: ' . $filename);
        }
        return ['label' => $filename, 'media_id' => $id];
    }

    /** Execute a short, named content phase after base media have been ingested. */
    public function runBatchContentPhase(string $phase, int $siteId, ?int $userId): array
    {
        $site = (new SiteRepository())->findById($siteId);
        if (!$site) {
            throw new \RuntimeException('Web pro Demo seed nebyl nalezen.');
        }
        $this->hydrateDemoMediaMap($siteId);

        switch ($phase) {
            case 'default_poster':
                return ['label' => 'Hlavní obrázek webu', 'value' => $this->seedDefaultHeroPoster($siteId)];
            case 'base_blog':
                return ['label' => 'Základní blogové články', 'count' => $this->seedBlogPosts($siteId)];
            case 'homepage':
                $this->resetHomepageAutofillBlocks();
                $this->seedHomepageElegantWhiteBlocks($siteId);
                return ['label' => 'Obsah homepage'];
            case 'business_content':
                $this->applyApprovedSiteDefaultsAndAboutProfile($siteId);
                $reviews = $this->seedReviewRows($siteId);
                $this->seedCenikCards($siteId);
                $this->seedRealPricingContent($siteId);
                $this->seedDemoContactInfo($siteId);
                $faq = $this->seedFaqItems($siteId);
                return ['label' => 'Recenze, ceník, kontakt a FAQ', 'reviews' => $reviews, 'faq' => $faq];
            case 'services':
                $this->seedHeroBackgrounds($siteId);
                $this->seedServiceItems($siteId);
                $this->resetServiceBlocks($siteId);
                $this->seedServiceVisuals($siteId);
                $count = $this->seedServicePages($siteId, $site);
                return ['label' => 'Služby a jejich média', 'count' => $count];
            case 'seo_theme':
                $this->seedPageSeo($siteId, $site);
                $this->seedPageHeadings();
                (new SiteRepository())->update($siteId, ['active_theme_key' => self::DEFAULT_THEME_KEY]);
                (new ThemeTemplateRegistry())->persistActiveThemeKey(self::DEFAULT_THEME_KEY);
                return ['label' => 'SEO metadata a motiv', 'theme' => self::DEFAULT_THEME_KEY];
            case 'publish':
                if (!$userId) {
                    throw new \RuntimeException('Publikování vyžaduje přihlášeného administrátora.');
                }
                $result = (new PublishService())->publishSite($siteId, $userId);
                if (empty($result['success'])) {
                    throw new \RuntimeException((string)($result['message'] ?? 'Publikování webu selhalo.'));
                }
                return ['label' => 'Publikování webu', 'published' => true];
        }
        throw new \RuntimeException('Neznámá fáze Demo seedu: ' . $phase);
    }

    /**
     * Restore only the client-approved pricing blocks in an existing database.
     * Used by restricted-hosting migration compatibility; it does not reset any
     * other demo content and does not publish the site.
     */
    public function restoreRealPricingContent(int $siteId): void
    {
        $this->seedRealPricingContent($siteId);
    }

    /**
     * Restore only public service pages and page-level SEO settings required by
     * the production smoke contract. No portfolio, blog, review, media, contact
     * or theme reset is performed.
     */
    public function restoreProductionSeoContent(int $siteId): void
    {
        $site = (new SiteRepository())->findById($siteId);
        if (!$site) {
            throw new \RuntimeException('Web pro obnovu SEO obsahu nebyl nalezen.');
        }

        $this->seedServicePages($siteId, $site);
        $this->seedPageSeo($siteId, $site);
        $this->seedPageHeadings();
    }

    /**
     * Apply the approved content/intent remediation to an existing site without
     * resetting unrelated reviews, pricing, contact details, theme or media.
     */
    public function restoreContentSeoRemediation(int $siteId): void
    {
        $site = (new SiteRepository())->findById($siteId);
        if (!$site) {
            throw new \RuntimeException('Web pro obsahovou SEO opravu nebyl nalezen.');
        }

        $this->hydrateDemoMediaMap($siteId);
        $this->assertContentSeoMediaAvailable();

        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->seedServicePages($siteId, $site);
            $this->seedPageSeo($siteId, $site);
            $this->seedPageHeadings();
            $this->seedBlogPosts($siteId, false);
            (new WeddingBlogDemoContentImporter())->migrateLegacySlugs($siteId);
            (new PortfolioDemoContentImporter())->refreshSeoMetadata($siteId);
            if ($startedTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Restore the client-approved homepage team photograph without resetting
     * any unrelated homepage content. The source is ingested through the same
     * Media Library pipeline as an admin upload.
     */
    public function restoreHomepageAboutVisual(int $siteId): void
    {
        $this->replaceHomepageAboutVisual($siteId, 'weare4.jpg');
    }

    public function replaceHomepageAboutVisualWithWeare5(int $siteId): void
    {
        $this->replaceHomepageAboutVisual($siteId, 'weare5.jpg');
    }

    public function applyApprovedSiteDefaultsAndAboutProfile(int $siteId): void
    {
        (new SiteRepository())->update($siteId, [
            'company_registration_id' => '29719194',
            'default_hero_video_url' => 'https://vimeo.com/1229273025',
            // Tablet plays the desktop video: an empty variant falls back to it (migration 100).
            'default_hero_video_url_tablet' => null,
            'default_hero_video_url_mobile' => 'https://vimeo.com/1228952734',
        ]);

        $aboutPage = null;
        foreach ($this->pages->allBySite($siteId) as $page) {
            if (trim((string)($page['url_path'] ?? ''), '/') === 'o-nas') {
                $aboutPage = $page;
                break;
            }
        }
        if (!$aboutPage) {
            throw new \RuntimeException('Stranka O nas pro obnovu profilu nebyla nalezena.');
        }

        $mediaId = $this->ingestDemoAsset(
            $siteId,
            'aboutuszdenek.jpg',
            'Zdenek Cernohlavek, fotograf'
        );
        if ($mediaId <= 0) {
            throw new \RuntimeException('Fotografii aboutuszdenek.jpg se nepodarilo nahrat do Media Library.');
        }
        $photoUrl = $this->mediaPathById($mediaId);
        if ($photoUrl === '') {
            throw new \RuntimeException('Medium aboutuszdenek.jpg nema platnou cestu v Media Library.');
        }

        $pageId = (int)$aboutPage['id'];
        $this->blocks->upsert($pageId, 'about_photographer_image', [
            'block_type' => 'image',
            'label' => 'Zdenek Cernohlavek - fotograf',
            'content' => $photoUrl,
            'sort_order' => 4,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'about_cameraman_video', [
            'block_type' => 'video',
            'label' => 'Tomas Sincl - video misto fotky (nepovinne)',
            'content' => 'https://vimeo.com/1228181473',
            'sort_order' => 6,
            'autofill_disabled' => 1,
        ]);
        $this->replaceAboutCameramanFallback($siteId);
    }

    public function replaceAboutCameramanFallback(int $siteId): void
    {
        $aboutPage = null;
        foreach ($this->pages->allBySite($siteId) as $page) {
            if (trim((string)($page['url_path'] ?? ''), '/') === 'o-nas') {
                $aboutPage = $page;
                break;
            }
        }
        if (!$aboutPage) {
            throw new \RuntimeException('Stranka O nas pro obnovu Tomasovy fotografie nebyla nalezena.');
        }

        $mediaId = $this->ingestDemoAsset(
            $siteId,
            self::ABOUT_CAMERAMAN_FALLBACK_ASSET,
            'Tomas Sincl, kameraman'
        );
        if ($mediaId <= 0) {
            throw new \RuntimeException(
                'Fotografii ' . self::ABOUT_CAMERAMAN_FALLBACK_ASSET . ' se nepodarilo nahrat do Media Library.'
            );
        }
        $photoUrl = $this->mediaPathById($mediaId);
        if ($photoUrl === '') {
            throw new \RuntimeException(
                'Medium ' . self::ABOUT_CAMERAMAN_FALLBACK_ASSET . ' nema platnou cestu v Media Library.'
            );
        }

        $this->blocks->upsert((int)$aboutPage['id'], 'about_cameraman_image', [
            'block_type' => 'image',
            'label' => 'Tomas Sincl - kameraman',
            'content' => $photoUrl,
            'sort_order' => 3,
            'autofill_disabled' => 1,
        ]);
    }

    private function replaceHomepageAboutVisual(int $siteId, string $filename): void
    {
        $homepage = null;
        foreach ($this->pages->allBySite($siteId) as $page) {
            if (trim((string)($page['url_path'] ?? ''), '/') === '') {
                $homepage = $page;
                break;
            }
        }
        if (!$homepage) {
            throw new \RuntimeException('Homepage pro obnovu fotografie O nas nebyla nalezena.');
        }

        $mediaId = $this->ingestDemoAsset($siteId, $filename, 'RB Videos & Photos - fotograf a kameraman');
        if ($mediaId <= 0) {
            throw new \RuntimeException('Fotografii ' . $filename . ' se nepodarilo nahrat do Media Library.');
        }

        $visual = $this->mediaPathById($mediaId);
        if ($visual === '') {
            throw new \RuntimeException('Medium ' . $filename . ' nema platnou cestu v Media Library.');
        }

        $this->blocks->upsert((int)$homepage['id'], 'jb-about-visual__media', [
            'block_type' => 'image',
            'label' => 'Homepage O nas - tym',
            'content' => $visual,
            'sort_order' => 86,
            'autofill_disabled' => 1,
        ]);
    }

    /** Re-encode only the existing homepage team photograph before publish. */
    public function optimizeHomepageLcpMedia(int $siteId): int
    {
        $homepage = null;
        foreach ($this->pages->allBySite($siteId) as $page) {
            if (trim((string)($page['url_path'] ?? ''), '/') === '') {
                $homepage = $page;
                break;
            }
        }
        if (!$homepage) {
            throw new \RuntimeException('Homepage pro optimalizaci LCP média nebyla nalezena.');
        }
        $visual = $this->blocks->findByKey((int)$homepage['id'], 'jb-about-visual__media');
        $path = trim((string)($visual['content'] ?? ''));
        if ($path === '') {
            throw new \RuntimeException('Homepage LCP médium není nastaveno v bloku jb-about-visual__media.');
        }
        return $this->media->regenerateImageVariantsByStoragePath($siteId, $path);
    }

    /**
     * Restore the historically approved sixth service and regenerate only the
     * two service-list blocks. Existing services and unrelated content remain
     * untouched.
     */
    public function restoreSixthService(int $siteId): void
    {
        $filename = 'svatba-nevesta-zenich-polibek-kamenna-zed.webp';
        $mediaId = $this->ingestDemoAsset($siteId, $filename, 'Svatebni foto a video z dronu');
        if ($mediaId <= 0) {
            throw new \RuntimeException('Obrazek pro sluzbu Letecke zabery dronem se nepodarilo nahrat do Media Library.');
        }

        $imageUrl = $this->mediaPathById($mediaId);
        if ($imageUrl === '') {
            throw new \RuntimeException('Obrazek pro sluzbu Letecke zabery dronem nema platnou cestu.');
        }

        $values = [
            'Letecké záběry dronem',
            'Letecké záběry místa, příjezdu novomanželů i okolní krajiny dodají svatebnímu filmu působivou perspektivu a atmosféru.',
            $imageUrl,
            'Chci vědět víc',
            '/kontakt',
            6,
            1,
        ];
        $stmt = $this->db->prepare(
            'SELECT id FROM service_items
             WHERE site_id = ? AND (sort_order = 6 OR title = ?)
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([$siteId, $values[0]]);
        $serviceId = (int)($stmt->fetchColumn() ?: 0);

        if ($serviceId > 0) {
            $this->db->prepare(
                'UPDATE service_items
                 SET title = ?, description = ?, image_url = ?, button_text = ?, button_url = ?, sort_order = ?, is_active = ?
                 WHERE id = ? AND site_id = ?'
            )->execute([...$values, $serviceId, $siteId]);
        } else {
            $this->db->prepare(
                'INSERT INTO service_items
                    (site_id, title, description, image_url, button_text, button_url, sort_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$siteId, ...$values]);
        }

        $homepage = null;
        $servicesPage = null;
        foreach ($this->pages->allBySite($siteId) as $page) {
            $path = trim((string)($page['url_path'] ?? ''), '/');
            if ($path === '') {
                $homepage = $page;
            } elseif ($path === 'sluzby') {
                $servicesPage = $page;
            }
        }
        if (!$homepage || !$servicesPage) {
            throw new \RuntimeException('Homepage nebo stranka Sluzby nebyla nalezena pro obnovu seste sluzby.');
        }

        $this->blocks->upsert((int)$homepage['id'], 'sluzby_items', [
            'block_type' => 'html',
            'label' => 'Služby - karty',
            'content' => (new HomepageContentService())->buildServicesHtml($siteId, 6),
            'sort_order' => 12,
            'autofill_disabled' => 0,
        ]);
        $this->blocks->upsert((int)$servicesPage['id'], 'sluzby_grid', [
            'block_type' => 'html',
            'label' => 'Služby - karty',
            'content' => (new HomepageContentService())->buildServicesHtml($siteId, 0),
            'sort_order' => 3,
            'autofill_disabled' => 0,
        ]);
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
        $paths = $this->demoAssetPaths();

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

    /** @return list<string> */
    private function demoAssetPaths(): array
    {
        $dir = dirname(__DIR__, 2) . '/database/seeds/demo_assets';
        if (!is_dir($dir)) {
            throw new \RuntimeException('Chybí adresář základních Demo médií: ' . $dir);
        }
        $paths = [];
        foreach (['webp', 'png', 'jpg', 'jpeg'] as $ext) {
            $paths = array_merge($paths, glob($dir . '/*.' . $ext) ?: []);
        }
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($paths);
    }

    /** Populate the filename map from already-ingested Media Library rows. */
    private function hydrateDemoMediaMap(int $siteId): void
    {
        $stmt = $this->db->prepare('SELECT id, original_filename FROM media WHERE site_id = ?');
        $stmt->execute([$siteId]);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $filename = trim((string)($row['original_filename'] ?? ''));
            if ($filename !== '') {
                $this->demoMedia[$filename] = (int)$row['id'];
            }
        }
    }

    /** Fail before writes instead of replacing missing real photos with placeholders. */
    private function assertContentSeoMediaAvailable(): void
    {
        $required = [];
        foreach ($this->servicePages() as $service) {
            $required[] = (string)$service['image'];
        }
        foreach ([
            ['svatba-nevesta-kytice-zapad-slunce-u-vody.webp', 'par-v-prirode-romanticky-portret-zapad-slunce.webp', 'svatba-nevesta-zenich-objeti-kytice-les.webp'],
            ['svatba-nevesta-zenich-zavoj-tanec-priroda.webp', 'svatba-nevesta-zenich-polibek-kamenna-zed.webp', 'svatba-nevesta-zavoj-cernobily-portret.webp'],
            ['svatba-nevesta-zenich-polibek-kamenna-zed.webp', 'snubni-prsteny-listy-zapad-slunce-detail.webp', 'svatba-nevesta-kytice-zapad-slunce-u-vody.webp'],
            ['rodinne-foceni-rodina-dite-louka.webp', 'rodinne-tehotenske-foceni-rodina-pole.webp', 'detske-foceni-chlapec-odrazedlo-zapad-slunce.webp'],
            ['sportovni-auto-nissan-silnice-les-pohyb.webp', 'koncert-zpevak-mikrofon-cernobila-fotografie.webp'],
        ] as $filenames) {
            array_push($required, ...$filenames);
        }

        $missing = array_values(array_filter(
            array_unique($required),
            fn(string $filename): bool => empty($this->demoMedia[$filename])
        ));
        if ($missing !== []) {
            throw new \RuntimeException(
                'Obsahovou SEO opravu nelze provest: v Media Library chybi ' . implode(', ', $missing) . '.'
            );
        }
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

    private function requiredMediaVariantPathById(int $id, string $format): string
    {
        $stmt = $this->db->prepare(
            'SELECT path FROM media_variants WHERE media_id = ? AND format = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$id, $format]);
        $path = (string)($stmt->fetchColumn() ?: '');
        if ($path === '') {
            throw new \RuntimeException(sprintf(
                'Medium #%d nema povinnou optimalizovanou variantu %s.',
                $id,
                $format
            ));
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * Nastavi hero foto (hero_bg) na strankach, kde chybi hero pozadi. Foto se
     * zobrazi jako hero pozadi vsude, kde neni hero video (viz priorita
     * video -> foto -> default).
     */
    private function seedHeroBackgrounds(int $siteId): void
    {
        $map = [
            'sluzby' => 'hero-sluzby-cream.png',
            'cenik' => 'hero-cenik-cream.png',
            'faq' => 'hero-faq-cream.png',
            'nase-prace' => self::WORK_LISTING_HERO_ASSET,
            'blog' => 'hero-blog-cream.png',
            'kontakt' => 'hero-kontakt-cream.png',
            'o-nas' => 'hero-o-nas-cream.png',
            'hodnoceni' => 'hero-reference-cream.png',
            'podminky-uziti' => 'rodinne-foceni-rodina-dite-louka.webp',
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

    public function replaceWorkListingHero(int $siteId): void
    {
        $page = $this->pages->findByUrlPath('nase-prace');
        if (!$page) {
            throw new \RuntimeException('Stranka Nase prace pro obnovu hero fotografie nebyla nalezena.');
        }

        $mediaId = $this->ingestDemoAsset(
            $siteId,
            self::WORK_LISTING_HERO_ASSET,
            'Svatebni par v krajine - Nase prace'
        );
        if ($mediaId <= 0) {
            throw new \RuntimeException(
                'Hero fotografii ' . self::WORK_LISTING_HERO_ASSET . ' se nepodarilo nahrat do Media Library.'
            );
        }
        $heroUrl = $this->mediaPathById($mediaId);
        if ($heroUrl === '') {
            throw new \RuntimeException(
                'Hero medium ' . self::WORK_LISTING_HERO_ASSET . ' nema platnou cestu v Media Library.'
            );
        }

        $this->blocks->upsert((int)$page['id'], 'hero_bg', [
            'block_type' => 'image',
            'label' => 'Hero foto',
            'content' => $heroUrl,
            'sort_order' => 5,
            'autofill_disabled' => 1,
        ]);
    }

    /**
     * Seed sluzeb (service_items) pro /sluzby i homepage grid. Builder cte tato
     * data primarne ze service_items (ServiceRepository), takze obsah patri sem.
     */
    private function seedServiceItems(int $siteId): void
    {
        $servicePages = $this->servicePages();
        $image = function (int $index) use ($servicePages): string {
            $filename = (string)($servicePages[$index]['image'] ?? '');
            return !empty($this->demoMedia[$filename])
                ? $this->mediaPathById((int)$this->demoMedia[$filename])
                : '';
        };

        // Copy supplied with the Premium Photo design. It now lives in the
        // canonical demo dataset instead of being embedded in theme markup.
        $items = [
            [
                'title' => self::FIRST_SERVICE_TITLE,
                'description' => 'Celodenní reportáž od příprav po večerní zábavu. Fotografie i svatební film v jednom balíčku, jeden sehraný tým.',
                'image_url' => $image(0), 'button_text' => 'Více informací',
                'button_url' => self::FIRST_SERVICE_DETAIL_URL, 'sort_order' => 1, 'is_active' => 1,
            ],
            [
                'title' => 'Svatební klipy',
                'description' => 'Tří až pětiminutový filmový sestřih s vaší hudbou. Emoce dne v jednom klipu, který si pustíte i za dvacet let.',
                'image_url' => $image(0), 'button_text' => 'Ukázky',
                'button_url' => '/nase-prace', 'sort_order' => 2, 'is_active' => 1,
            ],
            [
                'title' => 'Zásnuby & páry',
                'description' => 'Focení před svatbou - poznáme se, vy si zvyknete na objektiv a v den D už jsme staří známí.',
                'image_url' => $image(3), 'button_text' => 'Více informací',
                'button_url' => '/rande-a-zasnuby', 'sort_order' => 3, 'is_active' => 1,
            ],
            [
                'title' => 'Oslavy & akce',
                'description' => 'Narozeniny, křtiny, výročí a plesy. Reportážní fotografie i krátká videa z rodinných a společenských událostí.',
                'image_url' => $image(1), 'button_text' => 'Ukázky',
                'button_url' => '/nase-prace', 'sort_order' => 4, 'is_active' => 1,
            ],
            [
                'title' => 'Firemní natáčení',
                'description' => 'Promo videa, konference a firemní večírky. Stejná filmová kvalita, jen v kravatě.',
                'image_url' => $image(2), 'button_text' => 'Více informací',
                'button_url' => '/reklamni-foto-video', 'sort_order' => 5, 'is_active' => 1,
            ],
            [
                'title' => 'Letecké záběry dronem',
                'description' => 'Letecké záběry místa, příjezdu novomanželů i okolní krajiny dodají svatebnímu filmu působivou perspektivu a atmosféru.',
                'image_url' => $image(0), 'button_text' => 'Chci vědět víc',
                'button_url' => '/kontakt', 'sort_order' => 6, 'is_active' => 1,
            ],
        ];

        (new ServiceRepository())->upsertMany($siteId, $items);
    }

    /**
     * Vymaze ulozene service bloky (homepage grid i /sluzby stranka), aby se pri
     * publikaci znovu vygenerovaly z aktualnich service_items - jinak zustanou
     * stara tlacitka (napr. odkaz na /kontakt misto na detailni stranku sluzby).
     */
    private function resetServiceBlocks(int $siteId): void
    {
        $keys = ['sluzby_items', 'sluzby_grid', 'service_1', 'service_2', 'service_3', 'service_4', 'service_5', 'service_6'];
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
     * Kanonický seznam detailních služeb. Homepage navíc obsahuje DB položku Další projekty.
     * a detailními stránkami služeb (seedServicePages), aby titulky, popisy, obrázky
     * a slugy zůstaly konzistentní na jednom místě.
     */
    private function servicePages(): array
    {
        return [
            [
                'slug' => 'svatebni-foto-video',
                'title' => 'Svatební fotografie a video v Olomouci',
                'image' => 'svatba-nevesta-zenich-polibek-kamenna-zed.webp',
                'lead' => 'Fotografie i svatební film od jednoho týmu. Přirozeně zachytíme průběh dne, důležité lidi, pohyb, hlasy i atmosféru v Olomouci, na Moravě a po celé České republice.',
                'sections' => [
                    ['Váš svatební den vyprávíme přirozeně', 'Svatbu fotografujeme a natáčíme reportážně, bez zbytečného zastavování programu. Sledujeme přípravy, obřad, gratulace, rodinu, přátele, výzdobu i večerní atmosféru. Do dění vstupujeme jen tehdy, když je potřeba poradit se světlem, místem nebo jednoduchým postavením. Výsledkem má být souvislý příběh vašeho dne, ne série strojených scén.'],
                    ['Fotograf a kameraman v jednom týmu', 'Fotografie zachytí konkrétní okamžik, svatební video k němu vrátí pohyb, hlasy a hudbu. Harmonogram a důležité záběry plánujeme společně, takže si při práci nepřekážíme a výstupy působí jednotně. Před svatbou projdeme místo, přesuny, důležité osoby i situace, které si přejete zachovat.'],
                    ['Jak spolupráce probíhá', 'Po nezávazné poptávce ověříme termín a společně vybereme vhodný rozsah. Při konzultaci projdeme harmonogram, styl a podobu výsledných fotografií nebo filmu. Ve svatební den přijedeme s předstihem, pracujeme nenápadně a portrétní část vedeme pomocí jednoduchých pokynů. Přesný termín předání vždy potvrdíme v nabídce.'],
                    ['Co dostanete', '<ul><li>pečlivě vybrané a upravené fotografie v online galerii,</li><li>svatební film nebo kratší sestřih podle zvolené varianty,</li><li>fotografie připravené pro tisk i běžné sdílení,</li><li>jasně domluvený rozsah, termín předání a archivaci hotových výstupů.</li></ul>'],
                    ['Kde působíme', 'Nejčastěji pracujeme v Olomouci a Olomouckém kraji, ale na svatby vyjíždíme také po Moravě a po celé České republice. Místo není překážkou; před potvrzením nabídky vždy domluvíme dopravu, čas příjezdu a případné přesuny během dne.'],
                    ['Ukázky, reference a cena', '<p>Skutečné svatby najdete v našem <a href="/nase-prace">svatebním portfoliu</a> a zkušenosti párů na stránce <a href="/hodnoceni">Reference a hodnocení klientů</a>. Rozsah jednotlivých variant je uveden v <a href="/cenik">ceníku focení a svatebního videa</a>. Pro ověření volného termínu použijte <a href="/kontakt">nezávaznou poptávku</a>.</p>'],
                ],
                'seo_title' => 'Svatební foto a video Olomouc | RB Videos & Photos',
                'seo_desc' => 'Fotografie i svatební film od jednoho týmu. Podívejte se na průběh spolupráce, ukázky práce a možnosti focení v Olomouci i po celé ČR.',
                'seo_kw' => 'svatební foto a video Olomouc, svatební fotograf, svatební kameraman, svatební video Olomouc',
            ],
            [
                'slug' => 'rodinne-a-portretni-foceni',
                'title' => 'Rodinné a portrétní focení v Olomouci',
                'image' => 'rodinne-foceni-rodina-dite-louka.webp',
                'lead' => 'Rodinné vzpomínky, těhotenské focení, křtiny i portréty. Přirozené, nenucené a krásné snímky, ke kterým se budete rádi vracet.',
                'sections' => [
                    ['Focení bez stresu a nucených úsměvů', 'Rodinné a portrétní focení vedeme tak, aby bylo příjemné i lidem, kteří se před fotoaparátem necítí jistě. Děti nemusí stát na místě a dospělí nemusí znát žádné pózy. Dáváme jednoduché pokyny a necháváme prostor pro pohyb, blízkost a situace, které jsou pro vaši rodinu přirozené.'],
                    ['Pro koho je focení vhodné', 'Fotíme rodiny s dětmi, páry, nastávající rodiče, křtiny i samostatné portréty. Předem si ujasníme, pro koho fotografie vznikají a jak je chcete používat. Jinak připravíme klidné těhotenské focení, jinak svižnou rodinnou procházku s malými dětmi a jinak pracovní nebo osobní portrét.'],
                    ['Kde fotografujeme', 'V Olomouci a okolí můžeme vybrat park, přírodu, městské prostředí nebo místo, ke kterému máte vztah. Podle domluvy je možné také ateliérové focení. Při výběru řešíme dostupnost, světlo, počasí a dostatek prostoru pro děti, nikoli jen vzhled lokality na jedné fotografii.'],
                    ['Jak se připravit', 'Doporučíme oblečení, jehož barvy spolu přirozeně ladí a ve kterém se můžete volně pohybovat. Není potřeba pořizovat zvláštní kostýmy ani nutit děti k dlouhé přípravě. Pokud si nejste jistí výběrem oblečení nebo místa, můžete nám před focením poslat fotografie variant a vše společně projdeme.'],
                    ['Průběh a předání fotografií', 'Na začátku si necháme čas na seznámení a vyzkoušení světla. Během focení střídáme vedené portréty s volnějšími situacemi. Po focení vybereme a upravíme výslednou sérii a předáme ji v online galerii. Konkrétní počet fotografií a termín dodání potvrdíme podle vybrané varianty.'],
                    ['Ukázky a rezervace', '<p>Prohlédněte si <a href="/nase-prace">ukázky rodinných a portrétních fotografií</a>, přečtěte si <a href="/hodnoceni">reference klientů</a> a zkontrolujte <a href="/cenik">ceník focení</a>. Volný termín ověříte pomocí <a href="/kontakt">nezávazné poptávky</a>.</p>'],
                ],
                'seo_title' => 'Rodinné a portrétní focení Olomouc | RB Videos & Photos',
                'seo_desc' => 'Rodinné, těhotenské a portrétní focení v Olomouci. Přirozené snímky bez stresu, ke kterým se budete rádi vracet.',
                'seo_kw' => 'rodinné focení Olomouc, portrétní focení, těhotenské focení, focení dětí',
            ],
            [
                'slug' => 'reklamni-foto-video',
                'title' => 'Firemní fotografie a reklamní video',
                'image' => 'sportovni-auto-nissan-silnice-les-pohyb.webp',
                'lead' => 'Profesionální vizuální obsah pro firmy, produkty, kapely i značky. Obsah, který buduje důvěru, odlišuje a prodává.',
                'sections' => [
                    ['Vlastní obsah místo anonymní fotobanky', 'Pomáháme firmám, e-shopům, kapelám a značkám ukázat skutečné lidi, produkty, prostředí a způsob práce. Fotografie a video připravujeme podle toho, co má návštěvník pochopit a kde se s obsahem setká. Výsledkem nemá být jen hezký obrázek, ale srozumitelná prezentace firmy.'],
                    ['Nejdříve řešíme použití výstupů', 'Web, sociální sítě, reklamní kampaň, nábor nebo firemní profil potřebují odlišné formáty a kompozice. Před produkcí si proto ujasníme cílové stránky, poměry stran, potřebné varianty a prostor pro text. Díky tomu lze jednu realizaci využít na více místech bez dodatečného ořezávání důležitých částí.'],
                    ['Co můžeme fotografovat a natáčet', '<ul><li>tým, provozovnu a práci v reálném prostředí,</li><li>produkty, služby a jednotlivé kroky výroby,</li><li>firemní akce, koncerty a prezentace,</li><li>krátká videa, rozhovory a materiál pro sociální sítě.</li></ul>'],
                    ['Příprava zadání', 'Stačí popsat, co nabízíte, komu je obsah určený a kde jej chcete použít. Společně sestavíme seznam záběrů, časový plán a priority. U lidí před kamerou počítáme s časem na základní vedení; není nutná předchozí zkušenost s focením nebo natáčením.'],
                    ['Licence, formáty a předání', 'Předem si potvrdíme rozsah použití, požadované rozměry a formát výsledných souborů. Cena se odvíjí od rozsahu produkce, počtu míst, délky natáčení a množství výstupů. Konkrétní nabídku připravíme až po upřesnění zadání, aby odpovídala skutečné potřebě projektu.'],
                    ['Ukázky a poptávka', '<p>Realizované fotografie a videa najdete na stránce <a href="/nase-prace">Naše práce</a>. Zkušenosti zákazníků jsou na stránce <a href="/hodnoceni">Reference</a>. Pro návrh rozsahu a termínu pošlete <a href="/kontakt">nezávaznou firemní poptávku</a>.</p>'],
                ],
                'seo_title' => 'Firemní foto a reklamní video Olomouc | RB Videos & Photos',
                'seo_desc' => 'Produktové, firemní a reklamní fotografie i video pro web, sociální sítě a kampaně. Domluvte si rozsah, výstupy a termín realizace.',
                'seo_kw' => 'firemní fotograf Olomouc, reklamní video Olomouc, produktové fotografie, video pro firmy',
            ],
            [
                'slug' => 'rande-a-zasnuby',
                'title' => 'Párové a zásnubní focení v Olomouci',
                'image' => 'par-v-prirode-romanticky-portret-zapad-slunce.webp',
                'lead' => 'Přirozené párové focení, žádost o ruku i předsvatební focení v Olomouci. Pomůžeme s místem, světlem a nenápadným plánem zásnub.',
                'sections' => [
                    ['Jak focení probíhá', 'Předem si upřesníme, zda chcete společné fotografie, předsvatební focení nebo nenápadně zachytit žádost o ruku. Na místě vás jednoduše vedeme, ale necháváme dost prostoru pro blízkost, pohyb a přirozené reakce. Běžné párové focení trvá přibližně hodinu; konkrétní délku vždy potvrdíme podle zvoleného místa a záměru.'],
                    ['Výběr lokality', 'V Olomouci a okolí vybereme místo podle světla, roční doby, dostupnosti a atmosféry, kterou chcete na fotografiích zachovat. Může to být park, příroda, městská ulice nebo místo, ke kterému máte vlastní vztah.'],
                    ['Přirozené fotografie bez nuceného pózování', 'Nemusíte mít zkušenosti před fotoaparátem. Dostanete jednoduché pokyny, kam se postavit nebo kudy se projít, ale výsledkem mají být skutečné společné momenty, ne strojené pózy.'],
                    ['Co si vzít na párové focení', 'Doporučíme oblečení, ve kterém se cítíte dobře a jehož barvy spolu přirozeně ladí. Vhodné jsou čisté střihy bez výrazných nápisů; konkrétní volbu můžeme před focením společně projít podle lokality a počasí.'],
                    ['Zásnubní a předsvatební focení', 'Při plánované žádosti o ruku pomůžeme zvolit místo, čas i nenápadný scénář. Fotograf zůstane před samotným okamžikem v pozadí a po prvních emocích může následovat krátké párové focení. Předsvatební focení vám zároveň umožní poznat způsob práce před svatebním dnem.'],
                    ['Výsledné fotografie a jejich předání', '<p>Po focení vybereme a upravíme výslednou sérii. Hotové fotografie předáme v online galerii ke stažení a sdílení; přesný rozsah a termín předání potvrdíme v nabídce.</p><p>Prohlédněte si <a href="/nase-prace">ukázky realizací</a>, <a href="/hodnoceni">reference klientů</a> a orientační <a href="/cenik">ceník focení</a>. Pro konkrétní termín použijte <a href="/kontakt">nezávaznou poptávku</a>.</p>'],
                    ['Kdy je vhodné focení naplánovat', 'Pro běžné párové focení vybíráme čas podle světla, počasí a zvolené lokality. U žádosti o ruku navíc domluvíme přesný bod setkání a nenápadný způsob komunikace. Pokud má focení navazovat na svatbu, můžeme výsledné fotografie využít také pro oznámení nebo výzdobu.'],
                    ['Co když se před fotoaparátem necítíte jistě', 'To je běžné a počítáme s tím. Začínáme jednoduchou chůzí nebo rozhovorem, při kterém nemusíte sledovat objektiv. Postupně přidáme několik vedených portrétů, ale zachováme prostor pro vaše vlastní reakce. Cílem není sehrát cizí scénář, ale vytvořit fotografie, ve kterých se poznáte.'],
                ],
                'seo_title' => 'Párové a zásnubní focení Olomouc | RB Videos & Photos',
                'seo_desc' => 'Přirozené párové a zásnubní focení v Olomouci. Zjistěte, jak focení probíhá, jak vybrat lokalitu a jak se připravit.',
                'seo_kw' => 'párové focení Olomouc, fotograf zásnub, rande focení',
            ],
        ];
    }

    /**
     * Vytvoří (nebo aktualizuje) detailní stránky služeb - jednu pro každou službu
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
                og_title, og_description, og_image_id, twitter_title, twitter_description, twitter_image_id, schema_type, schema_name, schema_description)
             VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $count = 0;
        foreach ($this->servicePages() as $i => $svc) {
            $slug = $svc['slug'];
            $urlPath = '/' . $slug;
            $pageTitle = $svc['title'] . ' | RB Videos & Photos';

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
                'block_type' => 'text', 'label' => 'Hero - štítek',
                'content' => 'Služba ' . sprintf('%02d', $i + 1),
                'sort_order' => 1, 'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'hero_heading', [
                'block_type' => 'text', 'label' => 'Hero - nadpis',
                'content' => $svc['title'], 'sort_order' => 2, 'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'hero_sub', [
                'block_type' => 'textarea', 'label' => 'Hero - podnadpis',
                'content' => $svc['lead'], 'sort_order' => 3, 'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'body', [
                'block_type' => 'html', 'label' => 'Obsah služby',
                'content' => $this->buildServiceBody($imageUrl, $svc['title'], $svc['sections']),
                'sort_order' => 4, 'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'cta_heading', [
                'block_type' => 'text', 'label' => 'CTA - nadpis',
                'content' => 'Pojďme naplánovat vaše focení', 'sort_order' => 5, 'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'cta_sub', [
                'block_type' => 'textarea', 'label' => 'CTA - podnadpis',
                'content' => 'Nezávazně se ozvěte - domluvíme termín, rozsah i výstupy přesně podle vašich představ.',
                'sort_order' => 6, 'autofill_disabled' => 1,
            ]);

            $seoStmt->execute([
                $pageId, $svc['seo_title'], $svc['seo_desc'], $svc['seo_kw'], $urlPath,
                $svc['seo_title'], $svc['seo_desc'], $imageId ?: null,
                $svc['seo_title'], $svc['seo_desc'], $imageId ?: null,
                'Service', $svc['seo_title'], $svc['seo_desc'],
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
     * stránky - nav, patičku a obal doplní site layout při publish. CSS je samonosné,
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
      <a href="/kontakt" class="btn btn-primary">Nezávazná poptávka</a>
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
        if (empty($this->demoMedia[self::FIRST_SERVICE_VISUAL])) {
            return;
        }

        $imageUrl = $this->mediaPathById((int) $this->demoMedia[self::FIRST_SERVICE_VISUAL]);
        if ($imageUrl === '') {
            return;
        }

        $this->assignFirstServiceVisual($siteId, $imageUrl);
    }

    /**
     * Migration 097: put weare5.jpg on the first service card ("Svatební den -
     * foto + video") of an existing site. The photo is reused from the Media
     * Library by original_filename (its alt/title stay untouched) or ingested
     * from demo_assets through the upload pipeline. The card <img> alt is the
     * service title, so no shared media metadata changes.
     */
    public function replaceFirstServiceVisualWithWeare5(int $siteId): void
    {
        $mediaId = $this->ingestDemoAsset($siteId, self::FIRST_SERVICE_VISUAL, self::FIRST_SERVICE_TITLE);
        if ($mediaId <= 0) {
            throw new \RuntimeException('Fotografii ' . self::FIRST_SERVICE_VISUAL . ' se nepodarilo najit ani nahrat do Media Library.');
        }
        $imageUrl = $this->mediaPathById($mediaId);
        if ($imageUrl === '') {
            throw new \RuntimeException('Medium ' . self::FIRST_SERVICE_VISUAL . ' nema platnou cestu v Media Library.');
        }

        $this->assignFirstServiceVisual($siteId, $imageUrl);

        // Stored service-list HTML would keep the old card image. Like the Demo
        // seed (resetServiceBlocks), clear it so render/publish rebuilds the
        // cards from service_items - only where a block has content and the
        // editor kept autofill enabled.
        foreach (['', 'sluzby'] as $urlPath) {
            $page = $this->pages->findByUrlPath($urlPath);
            if (!$page) {
                continue;
            }
            $this->db->prepare(
                "UPDATE content_blocks SET content = ''
                 WHERE page_id = ? AND block_key IN ('sluzby_items', 'sluzby_grid', 'service_1')
                   AND autofill_disabled = 0 AND content IS NOT NULL AND content <> ''"
            )->execute([(int)$page['id']]);
        }
    }

    private function assignFirstServiceVisual(int $siteId, string $imageUrl): void
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM service_items
             WHERE site_id = ? AND (title = ? OR button_url = ?)
             ORDER BY (title = ?) DESC, sort_order ASC, id ASC
             LIMIT 1'
        );
        $stmt->execute([$siteId, self::FIRST_SERVICE_TITLE, self::FIRST_SERVICE_DETAIL_URL, self::FIRST_SERVICE_TITLE]);
        $serviceId = (int)($stmt->fetchColumn() ?: 0);
        if ($serviceId <= 0) {
            throw new \RuntimeException('Sluzba "' . self::FIRST_SERVICE_TITLE . '" nebyla nalezena.');
        }

        $this->db->prepare('UPDATE service_items SET image_url = ? WHERE id = ? AND site_id = ?')
            ->execute([$imageUrl, $serviceId, $siteId]);
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
            '' => ['Svatební fotograf a kameraman Olomouc | ' . $brand, 'Svatební fotografie a video v Olomouci a po celé ČR. Přirozené záběry, filmové video a profesionální přístup pro váš den.', '/', 'svatební fotograf Olomouc, svatební video Olomouc, svatební kameraman Olomouc', 'svatba-nevesta-zenich-polibek-kamenna-zed.webp', 'LocalBusiness'],
            'sluzby' => ['Služby - foto a video na míru | ' . $brand, 'Svatební, rodinné, portrétní i reklamní focení a video v Olomouci a po celé ČR. Vyberte si službu a domluvte si nezávaznou konzultaci.', '/sluzby', 'svatební focení, rodinné focení, portrétní focení, reklamní video, fotograf Olomouc', 'svatba-nevesta-zavoj-cernobily-portret.webp', 'WebPage'],
            'cenik' => ['Ceník svatebního focení a videa - Olomouc | ' . $brand, 'Transparentní ceník svatebních balíčků od 14 900 Kč. Foto i video, online galerie a archivace výstupů. Nabídka na míru.', '/cenik', 'ceník svatebního focení, cena svatební fotograf, svatební balíčky, fotograf Olomouc cena', 'svatba-nevesta-kytice-zapad-slunce-u-vody.webp', 'WebPage'],
            'faq' => ['Časté otázky ke svatebnímu focení a videu | ' . $brand, 'Odpovědi na nejčastější dotazy o rezervaci termínu, dodání fotografií, videu, archivaci i průběhu svatebního focení.', '/faq', 'svatební focení otázky, rezervace fotografa, dodání fotografií, svatební video, archivace', 'svatba-nevesta-zenich-objeti-kytice-les.webp', 'WebPage'],
            'nase-prace' => ['Svatební portfolio - fotografie a video | ' . $brand, 'Prohlédněte si skutečné svatební, párové a portrétní galerie z Olomouce i celé ČR. Fotografie a videa ukazují náš přirozený styl práce.', '/nase-prace', 'svatební portfolio, svatební fotografie, svatební video ukázky', 'svatba-nevesta-zenich-zavoj-tanec-priroda.webp', 'WebPage'],
            'blog' => ['Tipy pro svatební a rodinné focení | ' . $brand, 'Praktické rady k přípravě na svatební, párové i rodinné focení, výběru místa a videu. Inspirace od fotografa a kameramana z Olomouce.', '/blog', 'tipy pro focení, příprava na focení, svatební video, rodinné focení', 'par-v-prirode-romanticky-portret-zapad-slunce.webp', 'WebPage'],
            'hodnoceni' => ['Reference na focení a video | ' . $brand, 'Přečtěte si zkušenosti párů, rodin a firem s focením a videem od RB Videos & Photos. Skutečné reference klientů z Olomouce i celé ČR.', '/hodnoceni', 'reference fotograf Olomouc, hodnocení focení, zkušenosti klientů', 'svatba-nevesta-zenich-objeti-kytice-les.webp', 'WebPage'],
            'kontakt' => ['Kontakt - svatební fotograf Olomouc | ' . $brand, 'Rezervujte si svatební focení nebo video v Olomouci a po celé ČR. Zavolejte, napište e-mail nebo pošlete nezávaznou poptávku přes formulář.', '/kontakt', 'kontakt svatební fotograf Olomouc, rezervace termínu, fotograf telefon', 'zamilovany-par-objeti-zapad-slunce-podzim.webp', 'LocalBusiness'],
            'o-nas' => ['O nás - fotograf a kameraman Olomouc | ' . $brand, 'Poznejte fotografa Zdeňka Černohlávka a kameramana Tomáše Šincla z RB Videos & Photos. Sehraná dvojice pro přirozené fotografie a filmové video.', '/o-nas', 'fotograf a kameraman Olomouc, RB Videos & Photos, Zdeněk Černohlávek, Tomáš Šincl', 'hero-o-nas-cream.png', 'AboutPage'],
            'podminky-uziti' => ['Podmínky užití a ochrana obsahu | ' . $brand, 'Pravidla používání webu RB Videos & Photos, informace o cookies, autorských právech, ochraně fotografií a možnostech kontaktu.', '/podminky-uziti', 'podmínky užití, cookies, autorská práva, ochrana fotografií', 'rodinne-foceni-rodina-dite-louka.webp', 'WebPage'],
        ];

        $stmt = $this->db->prepare(
            'REPLACE INTO seo_settings
               (page_id, meta_title, meta_description, meta_keywords, canonical_url, robots_index, robots_follow,
                og_title, og_description, og_image_id, twitter_title, twitter_description, twitter_image_id, schema_type, schema_name, schema_description)
             VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $seo['cenik'] = [
            'Ceník focení a svatebního videa | ' . $brand,
            'Přehledný ceník svatebního focení, celodenního videa, portrétního, rodinného a ateliérového focení. Vyberte si foto, video nebo společný balíček.',
            '/cenik',
            'ceník focení, ceník svatebního focení, svatební fotograf, svatební video, foto a video na svatbu, portrétní focení, rodinné focení, ateliérové focení',
            'svatba-nevesta-kytice-zapad-slunce-u-vody.webp',
            'WebPage',
        ];

        $seoImageOverrides = [
            'sluzby' => 'svatba-nevesta-zenich-polibek-kamenna-zed.webp',
            'cenik' => 'snubni-prsteny-listy-zapad-slunce-detail.webp',
            'faq' => 'svatba-nevesta-kytice-zapad-slunce-u-vody.webp',
            'nase-prace' => 'par-v-prirode-romanticky-portret-zapad-slunce.webp',
            'blog' => 'koncert-zpevak-mikrofon-cernobila-fotografie.webp',
            'hodnoceni' => 'svatba-nevesta-zenich-objeti-kytice-les.webp',
            'kontakt' => 'zamilovany-par-objeti-zapad-slunce-podzim.webp',
            'podminky-uziti' => 'rodinne-foceni-rodina-dite-louka.webp',
        ];

        foreach ($seo as $urlPath => [$title, $desc, $canonical, $keywords, $imageFile, $schemaType]) {
            $imageFile = $seoImageOverrides[$urlPath] ?? $imageFile;
            $page = $this->pages->findByUrlPath($urlPath);
            if (!$page) {
                continue;
            }
            $imageId = !empty($this->demoMedia[$imageFile]) ? (int) $this->demoMedia[$imageFile] : null;

            $stmt->execute([
                (int) $page['id'], $title, $desc, $keywords, $canonical,
                $title, $desc, $imageId,
                $title, $desc, null,
                $schemaType, $title, $desc,
            ]);
        }
    }

    /**
     * Svatebni cenik (3 baliky) - prepise cenik_card_N na strance Cenik.
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
            'content' => 'Ceník',
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
            'content' => 'Domluvme termín',
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
                "Pro menší svatby a samotný obřad - obřad, gratulace, skupinové fotografie a krátké focení novomanželů. Přibližně 3 hodiny focení a nejméně 100 upravených fotografií.\n\n"
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
                "Nejoblíbenější volba pro hlavní část svatebního dne, od příprav po první tanec nebo večerní program. Přibližně 8 hodin focení, 300-500 upravených fotografií a svatební video v ceně.\n\n"
                . "Obsahuje:\n"
                . "• focení cca 8 hodin\n"
                . "• přípravy, obřad, gratulace, skupinky\n"
                . "• párové focení novomanželů\n"
                . "• hostina, detaily a atmosféra dne\n"
                . "• minimálně 300-500 upravených fotografií\n"
                . "• svatební video / sestřih dne\n"
                . "• online galerie\n"
                . "• konzultace před svatbou\n"
                . "• archivace hotových výstupů na 2 roky",
                'od 24 900 Kč',
            ],
            3 => [
                'Prémiový',
                "Kompletní zachycení celého svatebního dne bez spěchu a kompromisů. Přibližně 10-12 hodin focení, prioritní zpracování a svatební video / reels v ceně. Delší archivace výstupů.\n\n"
                . "Obsahuje:\n"
                . "• vše ze Standardního balíčku\n"
                . "• focení cca 10-12 hodin\n"
                . "• větší počet upravených fotografií\n"
                . "• detailní zachycení atmosféry, hostů a momentů\n"
                . "• svatební video + krátký sestřih / reels v ceně\n"
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
                'label' => 'Karta ' . $i . ' - nadpis',
                'content' => $title,
                'sort_order' => $order++,
                'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'cenik_card_' . $i . '_desc', [
                'block_type' => 'textarea',
                'label' => 'Karta ' . $i . ' - popis',
                'content' => $desc,
                'sort_order' => $order++,
                'autofill_disabled' => 1,
            ]);
            $this->blocks->upsert($pageId, 'cenik_card_' . $i . '_price', [
                'block_type' => 'text',
                'label' => 'Karta ' . $i . ' - cena',
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
            'label' => 'Ceník - poznámka a CTA',
            'content' => $note,
            'sort_order' => 90,
            'autofill_disabled' => 1,
        ]);
    }

    /**
     * Svatebni FAQ (12 otazek) do tabulky faq_items - zdroj pro /faq stranku.
     */
    /**
     * Presny verejny cenik RB Videos & Photos dodany klientem.
     * Data zustavaji v content_blocks, nikoli v theme.
     */
    private function seedRealPricingContent(int $siteId): void
    {
        $page = $this->pages->findByUrlPath('cenik');
        if (!$page || (int)($page['site_id'] ?? 0) !== $siteId) {
            return;
        }
        $pageId = (int)$page['id'];
        $copy = [
            'hero_heading' => ['text', 'Hero - nadpis', 'Ceník focení a svatebního videa', 1],
            'hero_intro' => ['textarea', 'Hero - intro', 'Přehledné balíčky pro svatby, portréty, rodiny i ateliérové focení. Vyberte si rozsah podle toho, zda potřebujete zachytit pouze obřad, celý svatební den, video nebo kompletní foto + video.', 2],
            'pricing_weddings_heading' => ['text', 'Ceník - nadpis balíčků', 'Vyberte si balíček', 3],
            'pricing_weddings_sub' => ['textarea', 'Ceník - úvod balíčků', 'Každý balíček má jasně danou cenu a stručný popis, aby bylo hned zřejmé, pro jaký typ focení nebo natáčení se hodí. Konkrétní rozsah vždy doladíme podle místa, délky akce a vašich představ.', 4],
            'pricing_transport_heading' => ['text', 'Ceník - doprava nadpis', 'Doprava a individuální domluva', 70],
            'pricing_transport_text' => ['textarea', 'Ceník - doprava text', 'U kompletního svatebního balíčku foto + video je doprava do 80 km zdarma. U balíčku se dvěma kameramany počítáme dopravu 7 Kč / km. Finální rozsah služeb vždy ladíme podle místa, délky akce a konkrétní představy klienta.', 71],
            'cta_heading' => ['text', 'CTA - nadpis', 'Chcete ověřit volný termín?', 90],
            'cta_sub' => ['textarea', 'CTA - text', 'Napište nám datum, místo konání a službu, o kterou máte zájem. Ozveme se vám s možnostmi a doporučením vhodného balíčku.', 91],
            'cta_wrap' => ['html', 'CTA - tlačítko', '<a href="/kontakt" class="btn-primary">Nezávazně poptat termín</a>', 92],
        ];
        foreach ($copy as $key => [$type, $label, $content, $sortOrder]) {
            $this->blocks->upsert($pageId, $key, [
                'block_type' => $type, 'label' => $label, 'content' => $content,
                'sort_order' => $sortOrder, 'autofill_disabled' => 1,
            ]);
        }

        $cards = [
            ['Svatební focení', 'Celodenní svatební focení', '15 000 Kč', 'nad 8 hodin / celý den', 'Celodenní focení pro páry, které chtějí mít zdokumentovaný celý svatební den od příprav přes obřad až po večerní zábavu.', ['focení nad 8 hodin', 'přípravy, obřad, portréty i večer', 'reportážní fotografie celého dne', 'detaily, hosté, emoce a atmosféra'], '', 'Poptat celodenní focení', '/kontakt?sluzba=foto-cely-den'],
            ['Kompletní balíček', 'Kompletní balíček foto + video', '32 000 Kč', 'celý den', 'Kompletní celodenní balíček pro páry, které chtějí mít svatební den zachycený fotograficky i ve videu. Ideální řešení, pokud chcete sjednotit styl, komunikaci i výsledek.', ['celodenní svatební focení', 'celodenní svatební video', 'obřad, portréty, hostina i atmosféra dne', 'doprava do 80 km zdarma'], 'Nejžádanější', 'Nezávazně poptat termín', '/kontakt?sluzba=foto-video-komplet'],
            ['Svatební video', 'Celodenní svatební video', '17 000 Kč', 'celý den', 'Celodenní svatební video bez focení zachytí atmosféru celého dne od důležitých okamžiků až po přirozené emoce hostů. Vhodné pro páry, které chtějí mít ze svatby živou vzpomínku v pohybu.', ['natáčení celého svatebního dne', 'zachycení obřadu, hostů a atmosféry', 'reportážní styl videa'], '', 'Poptat natáčení svatby', '/kontakt?sluzba=jen-natoceni-svatby'],
            ['Svatební video', 'Dva kameramani', '25 000 Kč', 'celý den', 'Dva kameramani zachytí svatební den z více úhlů a umožní současně pokrýt hlavní okamžiky i přirozené reakce hostů.', ['dva kameramani po celý svatební den', 'více úhlů obřadu a důležitých okamžiků', 'zachycení hostů, detailů a atmosféry', 'doprava za 7 Kč / km'], '', 'Poptat dva kameramany', '/kontakt?sluzba=svatebni-video'],
            ['Svatební focení', 'Focení obřadu a portrétů', '7 000 Kč', 'balíček', 'Kratší svatební focení pro páry, které chtějí zachytit hlavně samotný obřad, společné fotografie a portréty novomanželů.', ['focení svatebního obřadu', 'portréty novomanželů', 'základní skupinové fotografie', 'vhodné pro menší svatby'], '', 'Poptat focení obřadu', '/kontakt?sluzba=obrad-portrety'],
            ['Svatební focení', 'Svatební focení do 8 hodin', '10 000 Kč', 'do 8 hodin', 'Praktický balíček pro svatby, kde chcete zachytit obřad, portréty, rodinu, hosty i část hostiny. Dobrá volba pro menší a středně velké svatby.', ['focení do 8 hodin', 'obřad, portréty a reportáž', 'fotografie rodiny a hostů', 'zachycení přirozené atmosféry'], '', 'Ověřit termín', '/kontakt?sluzba=foto-do-8-hodin'],
            ['Portrétní a rodinné focení', 'Portrétní a rodinné focení', '2 000 Kč', 'focení', 'Přirozené portrétní, párové nebo rodinné focení vhodné pro rodiny, děti, páry, těhotenské fotografie i osobní prezentaci.', ['portrétní nebo rodinné focení', 'přirozený styl fotografií', 'vhodné pro rodiny, páry i jednotlivce', 'focení podle domluvy'], '', 'Domluvit focení', '/kontakt?sluzba=portretni-rodinne-foceni'],
            ['Ateliérové focení', 'Fotografování v ateliéru', '3 000 Kč', 'focení', 'Ateliérové focení pro portréty, rodiny, děti, páry nebo osobní fotografie. Vhodné pro čistý vizuální styl, kontrolované světlo a elegantní výsledek.', ['focení v ateliéru', 'portréty, rodinné i osobní fotografie', 'kontrolované světlo', 'profesionální prostředí'], '', 'Poptat ateliérové focení', '/kontakt?sluzba=atelierove-foceni'],
        ];

        $legacyCards = '<div class="ckp-grid">';
        $order = 10;
        foreach ($cards as $index => [$category, $title, $price, $unit, $summary, $features, $badge, $ctaLabel, $ctaUrl]) {
            $i = $index + 1;
            $description = $summary . "\n\nObsahuje:\n• " . implode("\n• ", $features);
            foreach ([
                'title' => ['text', $title], 'desc' => ['textarea', $description],
                'price' => ['text', $price], 'unit' => ['text', $unit],
                'category' => ['text', $category], 'badge' => ['text', $badge],
                'cta_label' => ['text', $ctaLabel], 'cta_url' => ['text', $ctaUrl],
            ] as $suffix => [$type, $content]) {
                $this->blocks->upsert($pageId, 'cenik_card_' . $i . '_' . $suffix, [
                    'block_type' => $type, 'label' => 'Karta ' . $i . ' - ' . $suffix,
                    'content' => $content, 'sort_order' => $order++, 'autofill_disabled' => 1,
                ]);
            }
            $featuredClass = $badge !== '' ? ' ckp-card--featured' : '';
            $badgeHtml = $badge !== '' ? '<span class="ckp-badge">' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</span>' : '';
            $featureHtml = '<li>' . implode('</li><li>', array_map(
                static fn(string $feature): string => htmlspecialchars($feature, ENT_QUOTES, 'UTF-8'),
                $features
            )) . '</li>';
            $legacyCards .= '<article class="ckp-card' . $featuredClass . '">' . $badgeHtml
                . '<span class="ckp-category">' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . '</span>'
                . '<h3 class="ckp-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>'
                . '<div class="ckp-price">' . htmlspecialchars($price, ENT_QUOTES, 'UTF-8') . '</div>'
                . '<p class="ckp-desc">' . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<ul class="ckp-feats">' . $featureHtml . '</ul>'
                . '<a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '" class="btn-primary">'
                . htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') . '</a></article>';
        }
        $legacyCards .= '</div>';
        foreach (['pricing_packages', 'pricing_body'] as $key) {
            $this->blocks->upsert($pageId, $key, [
                'block_type' => 'html', 'label' => 'Ceník - osm reálných balíčků',
                'content' => $legacyCards, 'sort_order' => 60, 'autofill_disabled' => 1,
            ]);
        }

        $faq = [
            ['Kolik stojí svatební focení?', 'Svatební focení začíná od 7 000 Kč za obřad a portréty. Focení do 8 hodin stojí 10 000 Kč a celodenní svatební focení nad 8 hodin stojí 15 000 Kč.'],
            ['Kolik stojí svatební video se dvěma kameramany?', 'Balíček se dvěma kameramany stojí 25 000 Kč. K ceně se připočítává doprava 7 Kč / km.'],
            ['Nabízíte foto a video dohromady?', 'Ano. Kompletní balíček foto + video na celý den stojí 32 000 Kč a doprava do 80 km je zdarma.'],
            ['Fotíte i portrétní a rodinné fotografie?', 'Ano. Portrétní a rodinné focení stojí 2 000 Kč. Fotografování v ateliéru stojí 3 000 Kč.'],
            ['Je doprava v ceně?', 'U kompletního balíčku foto + video je doprava do 80 km zdarma. U balíčku se dvěma kameramany je doprava 7 Kč / km.'],
        ];
        $faqOrder = 80;
        foreach ($faq as $index => [$question, $answer]) {
            $i = $index + 1;
            foreach (['question' => $question, 'answer' => $answer] as $suffix => $content) {
                $this->blocks->upsert($pageId, 'cenik_faq_' . $i . '_' . $suffix, [
                    'block_type' => $suffix === 'question' ? 'text' : 'textarea',
                    'label' => 'Ceník FAQ ' . $i . ' - ' . $suffix,
                    'content' => $content, 'sort_order' => $faqOrder++, 'autofill_disabled' => 1,
                ]);
            }
        }
    }

    /** Keep listing-page H1 copy aligned with its unique search intent. */
    private function seedPageHeadings(): void
    {
        $headings = [
            'blog' => ['Tipy pro svatební, párové a rodinné focení', 'Tipy pro svatební a rodinné focení'],
            'nase-prace' => ['Svatební fotografie a videa - naše práce', 'Svatební portfolio'],
            'hodnoceni' => ['Reference a hodnocení klientů', 'Reference na focení a video'],
            'faq' => ['Časté otázky ke svatebnímu focení a videu', 'Časté otázky'],
            'o-nas' => ['Fotograf a kameraman z Olomouce.', 'O nás'],
        ];

        foreach ($headings as $path => [$heading, $pageTitle]) {
            $page = $this->pages->findByUrlPath($path);
            if (!$page) {
                continue;
            }
            $this->pages->update((int)$page['id'], ['title' => $pageTitle]);
            $existing = $this->blocks->findByKey((int)$page['id'], 'hero_h1')
                ?: $this->blocks->findByKey((int)$page['id'], 'hero_heading');
            $blockKey = is_array($existing) ? (string)($existing['block_key'] ?? 'hero_h1') : 'hero_h1';
            $blockType = is_array($existing) ? (string)($existing['block_type'] ?? 'text') : 'text';
            $blockLabel = is_array($existing) ? (string)($existing['label'] ?? 'Hero - nadpis') : 'Hero - nadpis';
            $sortOrder = is_array($existing) ? (int)($existing['sort_order'] ?? 1) : 1;
            $this->blocks->upsert((int)$page['id'], $blockKey, [
                'block_type' => $blockType,
                'label' => $blockLabel,
                'content' => $heading,
                'sort_order' => $sortOrder,
                'autofill_disabled' => 1,
            ]);
        }
    }

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
        $address1 = 'Mozartova 1113/65';
        $address2 = '779 00 Olomouc, Nová Ulice';
        $formEmail = ContactRepository::DEFAULT_FORM_EMAIL;

        (new ContactRepository())->upsert($siteId, [
            'phone_1' => $phone1,
            'phone_2' => $phone2,
            'phone_3' => '',
            'email' => $email,
            'address_1' => $address1,
            'address_2' => $address2,
            'reviews_url' => ContactRepository::DEFAULT_REVIEWS_URL,
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
        ], true);

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

    private function seedBlogPosts(int $siteId, bool $allowGeneratedFallback = true): int
    {
        $posts = [
            [
                'slug' => 'jak-vybrat-misto-na-foceni',
                'title' => 'Jak vybrat místo na focení - praktický průvodce',
                'excerpt' => 'Jak vybrat místo na svatební, párové nebo rodinné focení podle světla, dostupnosti, počasí a atmosféry, která vám bude přirozená.',
                'intro' => 'Dobré místo na focení nemusí být slavné ani dokonalé. Mělo by vám být příjemné, odpovídat typu focení a fungovat ve světle i čase, který máme k dispozici.',
                'sections' => [
                    ['Začněte tím, co budete fotografovat', ['Jinou lokalitu potřebuje krátké svatební portrétní focení, jinou rodina s malými dětmi a jinou samostatný portrét. U svatby hlídáme hlavně vzdálenost od obřadu nebo hostiny, aby focení nenarušilo program. U rodinného focení potřebujeme bezpečný prostor pro pohyb a místo, kde se děti nebudou muset neustále hlídat.']],
                    ['Světlo je důležitější než známá adresa', ['Park, louka, zahrada nebo klidná městská ulice mohou fungovat lépe než vyhlášená lokalita v nevhodnou denní dobu. Sledujeme směr světla, stín, otevřený prostor a možnost změnit úhel. Proto před focením potvrzujeme také konkrétní čas, ne pouze adresu.']],
                    ['Myslete na dostupnost a počasí', ['Ověřte parkování, délku chůze, případné vstupné a možnost ukrýt se při dešti. U svatby je praktické mít náhradní místo v interiéru nebo alespoň krátkou variantu focení poblíž programu. U rodin s kočárkem nebo malými dětmi vybíráme trasu, kterou lze bez stresu zkrátit.']],
                    ['Vyberte prostředí, které k vám patří', ['Místo může mít osobní význam, ale není to podmínka. Důležité je, abyste se v něm necítili jako na cizí scéně. Pokud máte rádi přírodu, nemusíme vás nutit do rušného centra. Pokud vám vyhovuje architektura a město, není důvod hledat louku jen proto, že se často objevuje ve svatebních galeriích.']],
                    ['Co nám poslat předem', ['Pomůže typ focení, počet lidí, věk dětí, přibližný termín a informace o dopravě. U svatby potřebujeme znát místo obřadu, hostiny a čas vyhrazený na portréty. Na základě toho navrhneme několik realistických možností a vysvětlíme jejich výhody i omezení.']],
                ],
                'conclusion' => 'Jestli nevíte, kde fotit, klidně to nechte na mě. Navrhnu pár míst podle toho, co spolu budeme fotit a jakou atmosféru chcete.',
                'related_links' => [['/rodinne-a-portretni-foceni', 'rodinné a portrétní focení'], ['/rande-a-zasnuby', 'párové a zásnubní focení'], ['/kontakt', 'konzultace vhodného místa']],
                'topic' => 'Svatebni misto na foceni',
                'color' => ['151515', 'E6B45D'],
            ],
            [
                'slug' => 'proc-mit-svatebni-video',
                'title' => 'Proč mít svatební video a co zachytí navíc',
                'excerpt' => 'Svatební video uchová pohyb, hlasy, hudbu i atmosféru dne. Zjistěte, v čem se liší od fotografií a kdy pro vás dává smysl.',
                'intro' => 'Fotografie zastaví jednotlivé okamžiky. Svatební video k nim přidá pohyb, hlasy, hudbu a souvislost mezi událostmi.',
                'sections' => [
                    ['Co video zachytí jinak než fotografie', ['Ve filmu znovu uslyšíte sliby, gratulace, smích i hlasy blízkých. Uvidíte příchod k obřadu, pohyb šatů, tanec a drobné reakce, které se do jedné fotografie nevejdou. Fotografie a video si proto nekonkurují; každé uchovává jinou část vzpomínky.']],
                    ['Nemusíte celý den hrát před kamerou', ['Svatební den natáčíme převážně reportážně. Nepřerušujeme důležité situace kvůli opakování záběru a většinu programu necháváme přirozeně plynout. Krátké vedení využíváme při portrétech nebo tehdy, když je potřeba upravit světlo a postavení.']],
                    ['Krátký sestřih nebo delší film', ['Vhodná podoba výstupu záleží na tom, k čemu se chcete vracet. Krátký sestřih soustředí hlavní emoce a snadno se sdílí. Delší film může zachovat větší část obřadu, proslovy a návaznost celého dne. Přesný obsah vždy vychází ze zvolené varianty a předem potvrzeného rozsahu.']],
                    ['Zvuk potřebuje stejnou pozornost jako obraz', ['Kvalitu filmu netvoří pouze pěkné záběry. Před obřadem proto řešíme také místo, ozvučení a možnosti zachycení mluveného slova. U proslovů nebo živé hudby se domlouváme podle konkrétního programu a podmínek na místě.']],
                    ['Kdy video nemusí být pro vás', ['Pokud chcete pouze několik portrétů nebo velmi krátký obřad bez dalšího programu, může vám fotografická galerie stačit. Smyslem není přidávat službu za každou cenu. Při konzultaci si otevřeně řekneme, jaký rozsah odpovídá vašemu dni a očekávání.']],
                ],
                'conclusion' => 'Pokud nad videem váháte, za mě dává smysl hlavně tehdy, když chcete zachytit nejen to, jak svatba vypadala, ale i jak zněla a jaká byla.',
                'related_links' => [['/svatebni-foto-video', 'svatební fotografie a video'], ['/nase-prace', 'ukázky skutečných svateb'], ['/cenik', 'varianty a ceník']],
                'topic' => 'Svatebni video',
                'color' => ['211811', 'F1D19A'],
            ],
            [
                'slug' => 'svatebni-fotograf-olomouc',
                'title' => 'Jak probíhá svatební focení v Olomouci',
                'excerpt' => 'Praktický průběh svatebního focení od plánování harmonogramu přes reportáž a portréty až po výběr a předání hotových fotografií.',
                'intro' => 'Svatební focení nemusí narušit celý den. Když předem známe harmonogram, důležité lidi a vaše priority, může proběhnout klidně a bez zbytečného hlídání.',
                'sections' => [
                    ['Před svatbou projdeme harmonogram', ['Potřebujeme znát místo příprav, obřadu a hostiny, přibližné časy přesunů a seznam důležitých skupinových fotografií. Upozorníme na místa, kde se hodí časová rezerva, a společně vybereme vhodný okamžik pro portréty. Díky tomu nemusíte v den svatby vše vysvětlovat znovu.']],
                    ['Reportáž během dne', ['Přípravy, obřad, gratulace, hosté, výzdoba a drobné reakce fotografujeme převážně bez zásahu. Sledujeme světlo a dění kolem, ale nepřerušujeme skutečné situace jen proto, abychom je znovu naaranžovali. Pokud je potřeba něco organizačně upřesnit, domlouváme se stručně se svědky nebo koordinátorem.']],
                    ['Portréty bez složitého pózování', ['Při společném focení vás jednoduše navedeme, kam se postavit nebo kudy se projít. Není potřeba vědět, co dělat s rukama ani trénovat pózy. Portrétní část plánujeme tak, aby zapadla do programu a neodvedla vás na dlouhou dobu od hostů.']],
                    ['Místa pro focení v Olomouci a okolí', ['Lokalitu vybíráme podle místa svatby, světla, počasí a času na přesun. Známé místo není automaticky nejlepší. Často lépe funguje klidný park, zahrada nebo krátká cesta poblíž areálu, kde můžeme fotografovat bez stresu a složité logistiky.']],
                    ['Výběr, úpravy a předání', ['Po svatbě fotografie vytřídíme, barevně upravíme a připravíme do online galerie. Přesný rozsah, termín předání a dobu archivace potvrzujeme podle vybrané varianty. Neupravené záběry nejsou běžnou součástí výsledného předání.']],
                ],
                'conclusion' => 'Pokud chcete svatební focení v Olomouci v klidu a bez strojeného pózování, můžeme si projít váš den a vymyslet, jak to udělat přirozeně.',
                'related_links' => [
                    ['/svatebni-foto-video', 'svatební foto a video služba'],
                    ['/cenik', 'balíčky a ceník svatebního focení'],
                    ['/kontakt', 'nezávazná poptávka termínu'],
                ],
                'topic' => 'Svatebni fotograf Olomouc',
                'color' => ['111827', 'D9A441'],
            ],
            [
                'slug' => 'rodinne-foceni-olomouc',
                'title' => 'Jak probíhá rodinné focení v Olomouci',
                'excerpt' => 'Jak se připravit na rodinné focení v Olomouci, vybrat místo a oblečení a zvládnout focení s dětmi bez nucených úsměvů.',
                'intro' => 'Rodinné focení může být klidná procházka a několik společných situací, ne dlouhé stání v řadě před fotoaparátem.',
                'sections' => [
                    ['Děti nemusí stát na místě', ['Počítáme s tím, že malé děti potřebují pohyb, pauzu nebo čas na seznámení. Začínáme jednoduše a střídáme krátké vedené fotografie s volnějšími situacemi. Když děti běží, smějí se nebo se přijdou obejmout, často vzniknou přirozenější snímky než při úsměvu na povel.']],
                    ['Jak vybrat místo', ['V Olomouci a okolí vybíráme parky, přírodu, městská zákoutí nebo místo, které máte rádi. Sledujeme dostupnost, bezpečí, světlo a prostor pro pohyb. S kočárkem nebo malými dětmi dává větší smysl snadno přístupná lokalita než složitá cesta za jediným výhledem.']],
                    ['Co si vzít na sebe', ['Oblečení nemusí být stejné, mělo by ale barevně a stylem přirozeně ladit. Doporučujeme pohodlné věci bez výrazných nápisů, ve kterých se můžete posadit, obejmout nebo jít s dětmi. Pokud váháte mezi několika variantami, můžete je předem poslat ke konzultaci.']],
                    ['Jak focení vedeme', ['Řekneme vám, kde je dobré světlo, kam se postavit a co zkusit. Nečekáme, že budete umět pózovat. Začínáme jednoduchou chůzí, rozhovorem nebo společnou činností a teprve potom přidáme klidnější portréty. Cílem je zachytit vztah mezi vámi, ne vytvořit obraz cizí rodiny.']],
                    ['Co se děje po focení', ['Vybereme výslednou sérii, upravíme barvy a připravíme fotografie do online galerie. Počet snímků a termín předání se odvíjí od zvolené varianty a potvrzujeme je před focením. Galerie umožní fotografie stáhnout a jednoduše sdílet s rodinou.']],
                ],
                'conclusion' => 'Jestli máte z rodinného focení trochu obavy, vůbec nevadí. Provedu vás tím tak, aby to bylo co nejpřirozenější.',
                'related_links' => [['/rodinne-a-portretni-foceni', 'rodinné a portrétní focení'], ['/cenik', 'ceník focení'], ['/kontakt', 'rezervace termínu']],
                'topic' => 'Rodinne foceni Olomouc',
                'color' => ['2B2118', 'FFE2A8'],
            ],
            [
                'slug' => 'reklamni-foto-video-pro-firmy',
                'title' => 'Jak připravit reklamní focení a video pro firmu',
                'excerpt' => 'Co připravit před firemním focením a natáčením, jak vytvořit zadání a naplánovat fotografie a video pro web, sociální sítě nebo reklamu.',
                'intro' => 'Nejlepší firemní fotografie a video nevznikají bez zadání. Před produkcí je potřeba vědět, komu má obsah sloužit, kde se použije a co má zákazník pochopit.',
                'sections' => [
                    ['Určete účel a cílovou skupinu', ['Jiné záběry potřebuje úvodní stránka webu, náborová kampaň, produktový detail nebo krátké video na sociální sítě. Sepište hlavní službu, publikum a jednu věc, kterou si má člověk po zhlédnutí odnést. Tento základ pomůže rozhodnout, co skutečně fotografovat a co by bylo jen dekorací.']],
                    ['Připravte seznam potřebných výstupů', ['U každého použití si poznamenejte formát, přibližný počet fotografií nebo délku videa a místo, kde bude kolem obrazu text. Pro web se hodí vodorovné i svislé varianty, pro sociální sítě často potřebujete kratší formáty. Plán předem šetří čas a omezuje dodatečné ořezy.']],
                    ['Vyberte lidi, produkty a prostředí', ['Rozhodněte, kdo bude firmu představovat, které činnosti lze během produkce skutečně ukázat a zda je prostor připravený. Produkty očistěte a seřaďte, u lidí potvrďte čas a vhodné oblečení. Není potřeba vytvářet umělé situace, pokud lze srozumitelně zachytit reálnou práci.']],
                    ['Sestavte realistický harmonogram', ['Počítejte s přípravou světla, přesuny mezi místy a opakováním důležitých situací z několika úhlů. U rozhovorů je nutný klid na zvuk. U provozu je potřeba domluvit bezpečný pohyb techniky a souhlas lidí, kteří budou rozpoznatelní.']],
                    ['Potvrďte použití a předání', ['Předem si ujasníme, kde budou fotografie a video zveřejněny, jaké varianty souborů potřebujete a kdo bude výstupy schvalovat. Rozsah licence, počet výstupů, termín a cenu uvedeme v konkrétní nabídce podle skutečného zadání.']],
                ],
                'conclusion' => 'Pokud chcete, aby vaše firma působila lépe a důvěryhodněji, vlastní foto a video je jedna z nejpraktičtějších věcí, kterou můžete udělat.',
                'related_links' => [['/reklamni-foto-video', 'firemní fotografie a reklamní video'], ['/nase-prace', 'ukázky realizací'], ['/kontakt', 'nezávazná firemní poptávka']],
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
                if (!$allowGeneratedFallback) {
                    throw new \RuntimeException('Pro clanek chybi schvalena media: ' . $post['slug']);
                }
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
                'title' => 'Realizace svatebního foto a videa v Olomouci',
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
                'related_links' => [
                    ['/svatebni-foto-video', 'rozsah svatební foto a video služby'],
                    ['/cenik', 'svatební balíčky a orientační ceny'],
                    ['/kontakt', 'poptávka dostupného termínu'],
                ],
                'topic' => 'Svatebni foto video Olomouc',
                'color' => ['17110B', 'E7B75E'],
                'video_url' => 'https://vimeo.com/1122538281',
            ],
            [
                'slug' => 'svatebni-fotograf-brno',
                'title' => 'Svatební focení v Brně - ukázka realizace',
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
                'title' => 'Rodinné a portrétní focení - ukázka realizace',
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
                'title' => 'Reklamní foto a video v Olomouci - realizace',
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
                'title' => 'Portrétní focení v Olomouci - ukázka realizace',
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
        $reviews = CustomerReviewDataset::all();
        $homepage = $this->pages->findByUrlPath('');
        if (!$homepage) {
            return 0;
        }

        $this->ensureReviewsBlockInHomepageTemplate($homepage);

        $html = '<div class="hp-reviews-grid">';
        foreach ($reviews as $review) {
            $author = $review['author_name'];
            $context = $review['context_label'];
            $text = $review['review_text'];
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
        $reviews = CustomerReviewDataset::all();
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
            'service_5',
            'portfolio-tiles',
            'portfolio_btns',
            'blog_title',
            'blog_intro',
            'blog_1',
            'blog_2',
            'blog_3',
            'blog_cta',
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
            'contact_visual',
            'jb-about-visual__media',
            'jb-about-photographer__media',
            'jb-about-cameraman__media',
            'process_label',
            'process_title',
            'process_intro',
            'process_body',
            'portfolio_cta',
        ];

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->db->prepare(
            "UPDATE content_blocks SET content = '', autofill_disabled = 0 WHERE page_id = ? AND block_key IN ({$placeholders})"
        );
        $stmt->execute([(int)$homepage['id'], ...$keys]);

        $this->normalizeHomepageTemplateLabels($homepage);
        $this->removeHomepageHeroLabel($homepage);
    }

    private function seedDefaultHeroPoster(int $siteId): string
    {
        $mediaId = (int)($this->demoMedia[self::DEFAULT_HERO_POSTER_ASSET] ?? 0);
        if ($mediaId <= 0) {
            throw new \RuntimeException('Chybi povinna demo fotografie pro vychozi hero pozadi: ' . self::DEFAULT_HERO_POSTER_ASSET);
        }

        $url = $this->requiredMediaVariantPathById($mediaId, 'medium');

        (new SiteRepository())->update($siteId, ['default_hero_poster_url' => $url]);
        return $url;
    }

    private function seedHomepageElegantWhiteBlocks(int $siteId): void
    {
        $homepage = $this->pages->findByUrlPath('');
        if (!$homepage) {
            return;
        }

        $this->ensureElegantWhiteHomepageSources($homepage);
        $pageId = (int) $homepage['id'];

        $premiumHomepageCopy = [
            'sluzby_title' => ['Služby - nadpis', 'Jedna kamera, jeden objektiv, celý váš příběh', 10],
            'sluzby_intro' => ['Služby - úvod', 'Nejsme agentura. Jsme dva lidé, kteří u vás budou od ranních příprav po poslední tanec.', 11],
            'portfolio_title' => ['Portfolio - nadpis', 'Vybráno z posledních sezón', 30],
            'portfolio_intro' => ['Portfolio - úvod', 'Skutečné zakázky z naší databáze Naše práce.', 31],
            'blog_title' => ['Blog - nadpis', 'Rady před svatbou', 50],
            'blog_intro' => ['Blog - úvod', 'Praktické rady z focení a natáčení skutečných svateb.', 51],
            'faq_title' => ['FAQ - nadpis', 'Časté dotazy', 60],
            'reviews_title' => ['Recenze - nadpis', 'Slova novomanželů', 70],
            'reviews_intro' => ['Recenze - úvod', 'Zkušenosti klientů po předání fotografií a filmů.', 71],
            'kontakt_title' => ['Kontakt - nadpis', 'Napište nám, termíny mizí rychle', 90],
            'kontakt_sub' => ['Kontakt - úvod', 'Ozvěte se s termínem a místem. Odpovíme co nejdříve.', 91],
        ];
        foreach ($premiumHomepageCopy as $key => [$label, $content, $sortOrder]) {
            $this->blocks->upsert($pageId, $key, [
                'block_type' => 'text',
                'label' => $label,
                'content' => $content,
                'sort_order' => $sortOrder,
                'autofill_disabled' => 1,
            ]);
        }

        $this->blocks->upsert($pageId, 'process_label', [
            'block_type' => 'text',
            'label' => 'Proces - stitek',
            'content' => 'Jak vypadá den s námi',
            'sort_order' => 82,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'process_title', [
            'block_type' => 'text',
            'label' => 'Proces - nadpis',
            'content' => 'Svatební den snímek po snímku',
            'sort_order' => 83,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'process_intro', [
            'block_type' => 'textarea',
            'label' => 'Proces - uvod',
            'content' => '',
            'sort_order' => 84,
            'autofill_disabled' => 1,
        ]);
        $this->blocks->upsert($pageId, 'process_body', [
            'block_type' => 'html',
            'label' => 'Proces - kroky',
            'content' => $this->homepageProcessHtml(),
            'sort_order' => 85,
            'autofill_disabled' => 1,
        ]);

        foreach ([
            'jb-about-visual__media' => ['weare5.jpg', 'Homepage O nas - tym', 86],
        ] as $blockKey => [$filename, $label, $sortOrder]) {
            if (!empty($this->demoMedia[$filename])) {
                $visual = $this->mediaPathById((int)$this->demoMedia[$filename]);
                if ($visual === '') {
                    continue;
                }
                $this->blocks->upsert($pageId, $blockKey, [
                    'block_type' => 'image',
                    'label' => $label,
                    'content' => $visual,
                    'sort_order' => $sortOrder,
                    'autofill_disabled' => 1,
                ]);
            }
        }

        if (!empty($this->demoMedia['kontakt.png'])) {
            $visual = $this->mediaPathById((int) $this->demoMedia['kontakt.png']);
            if ($visual !== '') {
                $this->blocks->upsert($pageId, 'contact_visual', [
                    'block_type' => 'image',
                    'label' => 'Kontaktní vizuál',
                    'content' => $visual,
                    'sort_order' => 90,
                    'autofill_disabled' => 1,
                ]);
            }
        }
    }

    private function homepageProcessHtml(): string
    {
        $steps = [
            ['01', 'ew-process-icon--form', 'Nezavazna poptavka', 'Ozvete se nam a probereme vase predstavy a pozadavky.'],
            ['02', 'ew-process-icon--plan', 'Konzultace s planem', 'Spolecne naplanujeme prubeh a detaily.'],
            ['03', 'ew-process-icon--camera', 'Foceni & nataceni', 'Zachytime prirozene momenty bez zbytecnych zasahu.'],
            ['04', 'ew-process-icon--edit', 'Upravy & vyber', 'Peclive upravime a pripravime ty nejlepsi zabery.'],
            ['05', 'ew-process-icon--send', 'Dodani & sdileni', 'Predame hotove fotografie a video v nejvyssi kvalite.'],
        ];

        $html = '<div class="ew-process-list">';
        foreach ($steps as [$num, $icon, $title, $text]) {
            $html .= '<article>'
                . '<span>' . $this->h($num) . '</span>'
                . '<i class="ew-process-icon ' . $this->h($icon) . '"></i>'
                . '<h3>' . $this->h($title) . '</h3>'
                . '<p>' . $this->h($text) . '</p>'
                . '</article>';
        }
        return $html . '</div>';
    }

    private function ensureElegantWhiteHomepageSources(array $homepage): void
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

        $updated = $html;

        if (!str_contains($updated, 'contact_visual')) {
            $contactSource = "\n    <div class=\"ew-data-source\" data-ew-contact-visual hidden><img class=\"ew-contact-visual-source\" data-block=\"contact_visual\" alt=\"\"></div>\n";
            $updatedWithContact = preg_replace_callback(
                '~(<section\b[^>]*\bid=(["\'])kontakt\2[^>]*>\s*<div\b[^>]*\bclass=(["\'])(?=[^"\']*\bcontainer\b)[^"\']*\3[^>]*>)~i',
                static fn(array $m): string => $m[1] . $contactSource,
                $updated,
                1,
                $count
            );
            if ($updatedWithContact && $count > 0) {
                $updated = $updatedWithContact;
            } else {
                $updated .= $contactSource;
            }
        }

        if (!str_contains($updated, 'process_body')) {
            $processSection = <<<'HTML'

<section class="section hp-process" id="proces">
  <div class="container">
    <p class="section-label" data-block="process_label"></p>
    <h2 class="section-title" data-block="process_title"></h2>
    <p class="section-intro" data-block="process_intro"></p>
    <div data-block="process_body"></div>
  </div>
</section>
HTML;

            $updatedWithProcess = preg_replace('~(<section\b[^>]*id=(["\'])kontakt\2[^>]*>)~i', $processSection . "\n$1", $updated, 1, $count);
            if ($updatedWithProcess && $count > 0) {
                $updated = $updatedWithProcess;
            } else {
                $updated .= $processSection;
            }
        }

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

    private function removeHomepageHeroLabel(array $homepage): void
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
        $updated = preg_replace(
            '~(<section\b[^>]*\bid=(["\'])uvod\2[^>]*>\s*<div\b[^>]*\bclass=(["\'])container\3[^>]*>\s*)<p\b[^>]*\bclass=(["\'])section-label\4[^>]*>.*?</p>\s*~is',
            '$1',
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
            $post['title'] . ' | RB Videos & Photos',
            $post['excerpt'],
        ];

        if ($existing) {
            $this->db->prepare('UPDATE blog_posts SET title=?, excerpt=?, content=?, featured_image_id=?, status=?, meta_title=?, meta_description=?, updated_at=NOW() WHERE id=?')
                ->execute([$data[0], $data[1], $data[2], $data[3], $data[4], $data[6], $data[7], $existing]);
            return $existing;
        }

        $this->db->prepare('INSERT INTO blog_posts (site_id, title, slug, excerpt, content, featured_image_id, status, published_at, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$siteId, $post['title'], $post['slug'], $post['excerpt'], $content, $featuredImageId, 'published', $publishedAt, $post['title'] . ' | RB Videos & Photos', $post['excerpt']]);
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
            !empty($entry['video_url']) ? 'video' : 'photo',
            'published',
            $publishedAt,
            $index + 1,
            $entry['title'] . ' | RB Videos & Photos',
            $entry['excerpt'] ?? ('Ukázková práce fotografa: ' . $entry['title']),
        ];

        if ($existing) {
            $this->db->prepare('UPDATE portfolio_entries SET title=?, description=?, video_url=?, content_type=?, status=?, published_at=?, sort_order=?, meta_title=?, meta_description=?, updated_at=NOW() WHERE id=?')
                ->execute([...$data, $existing]);
            $this->syncPortfolioVideos($existing, (string)($entry['video_url'] ?? ''));
            return $existing;
        }

        $this->db->prepare('INSERT INTO portfolio_entries (site_id, title, slug, description, video_url, status, published_at, sort_order, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$siteId, $entry['title'], $entry['slug'], $description, $entry['video_url'], 'published', $publishedAt, $index + 1, $entry['title'] . ' | RB Videos & Photos', $entry['excerpt'] ?? ('Ukázka realizace: ' . $entry['title'])]);
        $createdId = (int)$this->db->lastInsertId();
        $this->db->prepare('UPDATE portfolio_entries SET content_type = ? WHERE id = ?')
            ->execute([!empty($entry['video_url']) ? 'video' : 'photo', $createdId]);
        $this->syncPortfolioVideos($createdId, (string)($entry['video_url'] ?? ''));
        return $createdId;
    }

    /**
     * Demo content owns a single video per entry and writes it straight into the
     * portfolio_entries.video_url mirror, so the portfolio_videos rows have to be
     * rewritten with it - otherwise a reseed would leave the mirror and the list
     * disagreeing, and the listing and the detail page would show different videos.
     */
    private function syncPortfolioVideos(int $portfolioId, string $videoUrl): void
    {
        $this->db->prepare('DELETE FROM portfolio_videos WHERE portfolio_id = ?')->execute([$portfolioId]);
        $videoUrl = trim($videoUrl);
        if ($videoUrl === '') {
            return;
        }
        $this->db->prepare('INSERT INTO portfolio_videos (portfolio_id, video_url, sort_order) VALUES (?, ?, 0)')
            ->execute([$portfolioId, $videoUrl]);
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
    <div data-block="reviews_cta" class="hp-section-cta"><a href="/hodnoceni" class="btn-secondary">Všechny recenze</a></div>
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
        // Bez nadpisu sekci (Krátký úvod / Text / Závěr) - jen odstavce s mezerou.
        $html = '';
        $intro = trim((string)($item['intro'] ?? ''));
        $excerpt = trim((string)($item['excerpt'] ?? ''));
        if ($intro !== '' && $intro !== $excerpt) {
            $html .= '<p>' . $this->h($intro) . '</p>';
        }

        $sections = $item['sections'] ?? [];
        if (is_array($sections)) {
            foreach ($sections as $section) {
                if (!is_array($section) || count($section) < 2) {
                    continue;
                }
                $heading = trim((string)$section[0]);
                if ($heading !== '') {
                    $html .= '<h2>' . $this->h($heading) . '</h2>';
                }
                $sectionParagraphs = is_array($section[1]) ? $section[1] : [$section[1]];
                foreach ($sectionParagraphs as $paragraph) {
                    $paragraph = trim((string)$paragraph);
                    if ($paragraph !== '') {
                        $html .= '<p>' . $this->h($paragraph) . '</p>';
                    }
                }
            }
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

        $relatedLinks = $item['related_links'] ?? [];
        if (is_array($relatedLinks) && $relatedLinks !== []) {
            $links = [];
            foreach ($relatedLinks as $link) {
                if (!is_array($link) || count($link) < 2) {
                    continue;
                }
                $links[] = '<a href="' . $this->h((string)$link[0]) . '">' . $this->h((string)$link[1]) . '</a>';
            }
            if ($links !== []) {
                $html .= '<p class="content-related-links">Související informace: ' . implode(', ', $links) . '.</p>';
            }
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
