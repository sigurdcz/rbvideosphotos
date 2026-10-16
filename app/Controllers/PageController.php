<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SlugTrait;
use App\Repositories\BlockRepository;
use App\Repositories\ContactRepository;
use App\Repositories\FaqRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TemplateRepository;
use App\Services\HomepageContentService;
use App\Services\PublishService;
use App\Services\TemplateParser;

class PageController extends Controller
{
    use SlugTrait;

    /**
     * Every editable text of the o-nas page: [block_key, block_type, label,
     * default content, sort_order]. The defaults are the literals the theme
     * template falls back to, so materialising a row never changes the page.
     */
    private const ABOUT_COPY_BLOCKS = [
        ['about_hero_cta_primary', 'text', 'Hero - hlavní tlačítko', 'Získat nabídku', 10],
        ['about_hero_cta_secondary', 'text', 'Hero - druhé tlačítko', 'Podívat se na ukázky', 11],
        ['about_team_heading', 'text', 'Tým - nadpis sekce', 'Kdo stojí za RB Videos & Photos', 12],
        ['about_photographer_name', 'text', 'Fotograf - jméno', 'Zdeněk Černohlávek', 13],
        ['about_photographer_role', 'text', 'Fotograf - role', 'Fotograf', 14],
        ['about_photographer_quote', 'textarea', 'Fotograf - citát', 'Zachycuji emoce a přirozenost tak, abyste se k nim mohli vracet i po letech a znovu je prožívat. Věnuji se focení od roku 2015.', 15],
        ['about_photographer_point_1', 'text', 'Fotograf - odrážka 1', 'Stovky spokojených klientů', 16],
        ['about_photographer_point_2', 'text', 'Fotograf - odrážka 2', 'Cit pro detail a světlo', 17],
        ['about_photographer_point_3', 'text', 'Fotograf - odrážka 3', 'Individuální přístup ke každému páru', 18],
        ['about_photographer_cta_primary', 'text', 'Fotograf - tlačítko 1', 'Ukázky práce', 19],
        ['about_photographer_cta_secondary', 'text', 'Fotograf - tlačítko 2', 'Spojit se', 20],
        ['about_photographer_photo_alt', 'text', 'Fotograf - popis fotky (alt)', 'Zdeněk Černohlávek, fotograf', 21],
        ['about_photographer_video_label', 'text', 'Fotograf - popis tlačítka přehrát video', 'Přehrát video se Zdeňkem Černohlávkem', 22],
        ['about_cameraman_name', 'text', 'Kameraman - jméno', 'Tomáš Šincl', 23],
        ['about_cameraman_role', 'text', 'Kameraman - role', 'Kameraman a editor', 24],
        ['about_cameraman_quote', 'textarea', 'Kameraman - citát', 'Vyprávím vaše příběhy pohyblivým obrazem, který dojme a zůstane navždy.', 25],
        ['about_cameraman_point_1', 'text', 'Kameraman - odrážka 1', 'Filmové svatební video', 26],
        ['about_cameraman_point_2', 'text', 'Kameraman - odrážka 2', 'Dynamické střihy a emoce', 27],
        ['about_cameraman_point_3', 'text', 'Kameraman - odrážka 3', 'Příběh, který má začátek i konec', 28],
        ['about_cameraman_cta_primary', 'text', 'Kameraman - tlačítko 1', 'Ukázky práce', 29],
        ['about_cameraman_cta_secondary', 'text', 'Kameraman - tlačítko 2', 'Spojit se', 30],
        ['about_cameraman_photo_alt', 'text', 'Kameraman - popis fotky (alt)', 'Tomáš Šincl, kameraman', 31],
        ['about_cameraman_video_label', 'text', 'Kameraman - popis tlačítka přehrát video', 'Přehrát video s Tomášem Šinclem', 32],
        ['about_values_heading', 'text', 'Hodnoty - nadpis sekce', 'Naše zkušenosti a přístup', 33],
        ['about_value_1_title', 'text', 'Hodnota 1 - nadpis', 'Profesionální technika', 34],
        ['about_value_2_title', 'text', 'Hodnota 2 - nadpis', 'Individuální přístup', 35],
        ['about_value_3_title', 'text', 'Hodnota 3 - nadpis', 'Důvěra a diskrétnost', 36],
        ['about_value_4_title', 'text', 'Hodnota 4 - nadpis', 'Vkus a cit pro detail', 37],
        ['about_reviews_heading', 'text', 'Reference - nadpis sekce', 'Reference klientů', 38],
        ['about_trust_heading', 'text', 'Důvěra - nadpis sekce', 'Důvěra a transparentnost', 39],
        ['about_trust_link_1', 'text', 'Důvěra - odkaz 1', 'IČO a fakturační údaje', 40],
        ['about_trust_link_2', 'text', 'Důvěra - odkaz 2', 'Obchodní podmínky', 41],
        ['about_trust_link_3', 'text', 'Důvěra - odkaz 3', 'GDPR ochrana', 42],
        ['about_trust_link_4', 'text', 'Důvěra - odkaz 4', 'Orientační ceník', 43],
        ['about_trust_link_5', 'text', 'Důvěra - odkaz 5', 'Dodací termíny', 44],
        ['about_trust_link_6', 'text', 'Důvěra - odkaz 6', 'Rezervační postup', 45],
        ['about_stories_heading', 'text', 'Příběhy - nadpis sekce', 'Příběhy z praxe / případové studie', 46],
        ['about_stories_kicker', 'text', 'Příběhy - štítek karty', 'Blog', 47],
        ['about_stories_link_label', 'text', 'Příběhy - text odkazu', 'Zobrazit příběh ›', 48],
        ['about_cta_heading', 'text', 'Závěrečné CTA - nadpis', 'Pojďme si vytvořit vzpomínky na celý život', 49],
        ['about_cta_primary', 'text', 'Závěrečné CTA - tlačítko 1', 'Získat nabídku', 50],
        ['about_cta_secondary', 'text', 'Závěrečné CTA - tlačítko 2', 'Napište nám', 51],
    ];

    /**
     * Optional team videos of the o-nas page (same shape for both people): an
     * empty block keeps the portrait photo, a filled YouTube/Vimeo URL turns the
     * card into the video variant. Created by migration 068 and seed.php; here
     * only materialised when missing, always empty, so the page never changes
     * and an existing (admin-edited) row is never touched. The portraits
     * themselves (about_*_image) are mandatory media and stay with 037/087.
     */
    private const ABOUT_TEAM_VIDEO_BLOCKS = [
        ['about_photographer_video', 'video', 'Zdeněk Černohlávek - video místo fotky (nepovinné)', '', 5],
        ['about_cameraman_video', 'video', 'Tomáš Šincl - video místo fotky (nepovinné)', '', 6],
    ];

    public function index(array $params): void
    {
        $this->requireAuth();
        $sites = new SiteRepository();
        $site  = $sites->findFirst();
        $repo  = new PageRepository();

        $this->view('pages.index', [
            'pages'   => $repo->allForAdmin(),
            'site'    => $site,
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $sites     = new SiteRepository();
        $site      = $sites->findFirst();
        $templates = (new TemplateRepository())->allForAdmin();

        $this->view('pages.form', [
            'page'       => null,
            'site'       => $site,
            'templates'  => $templates,
            'template'   => null,
            'layoutType' => 'generic',
            'faqItems'   => [],
            'contact'    => [],
            'serviceItems' => [],
            'isHomepage' => false,
            'homepageAutofillBlocks' => [],
        ]);
    }

    public function store(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $sites = new SiteRepository();
        $site  = $sites->findFirst();
        if (!$site) {
            $this->flash('error', 'Nejprve nastavte web.');
            $this->redirect('/admin/pages');
        }

        $slug    = $this->makeSlug($_POST['slug'] ?? '', $_POST['title'] ?? '', allowSlash: true);
        $rawUrl  = trim($_POST['url_path'] ?? '');
        $urlPath = $rawUrl !== '' ? $this->makeSlug($rawUrl, '', allowSlash: true) : $slug;
        $repo    = new PageRepository();

        if ($repo->slugExists($site['id'], $slug)) {
            $this->flash('error', 'Slug již existuje.');
            $this->redirect('/admin/pages/create');
        }

        $pageId = $repo->create([
            'site_id'     => $site['id'],
            'template_id' => (int)($_POST['template_id'] ?? 0) ?: null,
            'title'       => trim($_POST['title'] ?? ''),
            'slug'        => $slug,
            'url_path'    => $urlPath,
            'language'    => $_POST['language'] ?? 'cs',
            'status'      => 'draft',
        ]);

        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateId) {
            $this->initBlocksFromTemplate($pageId, $templateId);
        }

        if ($urlPath === '') {
            $this->ensureHomepageAboutVisualMediaBlock($pageId);
        }

        $this->flash('success', 'Stránka vytvořena.');
        $this->redirect('/admin/pages/edit?id=' . $pageId);
    }

    public function edit(array $params): void
    {
        $this->requireAuth();
        $id   = (int)($_GET['id'] ?? 0);
        $repo = new PageRepository();
        $page = $repo->findById($id);
        if (!$page) {
            $this->flash('error', 'Stránka nenalezena.');
            $this->redirect('/admin/pages');
        }

        $sites     = new SiteRepository();
        $isHomepage = trim((string)($page['url_path'] ?? ''), '/') === '';
        $this->ensurePageHeroVideoBlock($id);
        if ($isHomepage) {
            $this->ensureHomepageAboutVisualMediaBlock($id);
        }
        if (trim((string)($page['url_path'] ?? ''), '/') === 'o-nas') {
            $this->ensureAboutCopyBlocks($id);
        }
        $templates = (new TemplateRepository())->allForAdmin();
        $template  = $page['template_id'] ? (new TemplateRepository())->findById((int)$page['template_id']) : null;
        $layout    = $this->resolveLayoutType($template['name'] ?? '');
        if ($layout === 'services') {
            $this->ensureServicesHeroImageBlock($id);
        }
        $blocks    = (new BlockRepository())->allByPage($id);
        $homepageAutofillBlocks = $isHomepage
            ? (new HomepageContentService())->getAutofillDefinitions()
            : [];
        $serviceItems = ($layout === 'services' || $isHomepage) ? (new ServiceRepository())->allBySite((int)$page['site_id']) : [];

        $this->view('pages.form', [
            'page'       => $page,
            'blocks'     => $blocks,
            'templates'  => $templates,
            'template'   => $template,
            'layoutType' => $layout,
            'faqItems'   => $layout === 'faq' ? (new FaqRepository())->allBySite((int)$page['site_id']) : [],
            'contact'    => $layout === 'contact' ? (new ContactRepository())->findBySiteSafe((int)$page['site_id']) : [],
            'serviceItems' => $serviceItems,
            'site'       => $sites->findById($page['site_id']),
            'isHomepage' => $isHomepage,
            'homepageAutofillBlocks' => $homepageAutofillBlocks,
            'success'    => $this->getFlash('success'),
            'error'      => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = new PageRepository();
        $page = $repo->findById($id);
        if (!$page) {
            $this->redirect('/admin/pages');
        }

        $slug       = $this->makeSlug($_POST['slug'] ?? '', '', allowSlash: true);
        $rawUrl     = trim($_POST['url_path'] ?? '');
        $urlPath    = $rawUrl !== '' ? $this->makeSlug($rawUrl, '', allowSlash: true) : '';
        $templateId = (int)($_POST['template_id'] ?? 0) ?: null;
        $template   = $templateId ? (new TemplateRepository())->findById($templateId) : null;
        $layoutType = $this->resolveLayoutType($template['name'] ?? '');

        if ($repo->slugExists($page['site_id'], $slug, $id)) {
            $this->flash('error', 'Slug již existuje.');
            $this->redirect('/admin/pages/edit?id=' . $id);
        }

        $repo->update($id, [
            'title'       => trim($_POST['title'] ?? ''),
            'slug'        => $slug,
            'url_path'    => $urlPath,
            'language'    => $_POST['language'] ?? 'cs',
            'template_id' => $templateId,
        ]);

        if ($templateId && (int)$page['template_id'] !== $templateId) {
            $this->initBlocksFromTemplate($id, $templateId);
        }

        $isHomepage = trim((string)($page['url_path'] ?? ''), '/') === '';
        $this->ensurePageHeroVideoBlock($id);
        if ($isHomepage) {
            $this->ensureHomepageAboutVisualMediaBlock($id);
        }
        if (trim((string)($page['url_path'] ?? ''), '/') === 'o-nas') {
            $this->ensureAboutCopyBlocks($id);
        }
        if ($layoutType === 'services') {
            $this->ensureServicesHeroImageBlock($id);
        }
        $this->saveSubmittedBlocks($id, $isHomepage);

        if ($layoutType === 'faq') {
            $faqRepo = new FaqRepository();
            $faqRepo->replaceAllBySite((int)$page['site_id'], $_POST['faq_items'] ?? []);
            $this->syncHomepageFaqBlocks($faqRepo, (int)$page['site_id']);
        }

        if ($layoutType === 'contact') {
            (new ContactRepository())->upsert((int)$page['site_id'], $_POST);
            $this->syncHomepageContactBlocks((int)$page['site_id']);
        }

        if ($layoutType === 'services' || $isHomepage) {
            $this->saveSubmittedServiceItems((int)$page['site_id']);
            $this->refreshHomepageServicesBlock((int)$page['site_id']);
            $this->refreshServicesPageBlock((int)$page['site_id']);
        }

        if ($isHomepage) {
            $this->syncHomepageAboutVisualBlock($id);
        }

        if (isset($_POST['publish_after_save'])) {
            $user = \App\Core\Auth::user();
            $result = (new PublishService())->publishPage($id, (int)($user['id'] ?? 0));

            if ($result['success']) {
                $this->flash('success', 'Stránka byla uložena a publikována.');
            } else {
                $this->flash('error', $result['message']);
            }

            $this->redirect('/admin/pages/edit?id=' . $id);
        }

        $this->flash('success', 'Stránka uložena.');
        $this->redirect('/admin/pages/edit?id=' . $id);
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();
        $id = (int)($_POST['id'] ?? 0);
        (new PageRepository())->delete($id);
        $this->flash('success', 'Stránka smazána.');
        $this->redirect('/admin/pages');
    }

    private function initBlocksFromTemplate(int $pageId, int $templateId): void
    {
        $tmpl = (new TemplateRepository())->findById($templateId);
        if (!$tmpl) {
            return;
        }

        $parser = new TemplateParser();
        $blocks = $parser->parseBlocks($tmpl['html_source']);
        $repo   = new BlockRepository();
        foreach ($blocks as $block) {
            $repo->upsert($pageId, $block['block_key'], $block);
        }
    }

    private function ensurePageHeroVideoBlock(int $pageId): void
    {
        $blockRepo = new BlockRepository();
        // Tablet and mobile are optional overrides of the desktop video; a page that has no
        // row for them simply falls back to the desktop one.
        $blocks = [
            'hero_video' => 'Hero - video pozadi (Vimeo/YouTube URL)',
            'hero_video_tablet' => 'Hero - video pro tablet (do 1050 px)',
            'hero_video_mobile' => 'Hero - video pro mobil (do 700 px)',
        ];
        foreach ($blocks as $key => $label) {
            if ($blockRepo->findByKey($pageId, $key)) {
                continue;
            }

            $blockRepo->upsert($pageId, $key, [
                'block_type' => 'link',
                'label' => $label,
                'content' => '',
                'sort_order' => 2,
                'autofill_disabled' => 0,
            ]);
        }
    }

    private function ensureServicesHeroImageBlock(int $pageId): void
    {
        $blockRepo = new BlockRepository();
        if ($blockRepo->findByKey($pageId, 'hero_bg')) {
            return;
        }

        $blockRepo->upsert($pageId, 'hero_bg', [
            'block_type' => 'image',
            'label' => 'Hero - obrazek / medium',
            'content' => '',
            'sort_order' => 3,
            'autofill_disabled' => 0,
        ]);
    }

    private function ensureHomepageAboutVisualMediaBlock(int $pageId): void
    {
        $blockRepo = new BlockRepository();
        if ($blockRepo->findByKey($pageId, 'jb-about-visual__media')) {
            return;
        }

        $blockRepo->upsert($pageId, 'jb-about-visual__media', [
            'block_type' => 'image',
            'label' => 'Homepage - vizual sekce Naše služby',
            'content' => '',
            'sort_order' => 13,
            'autofill_disabled' => 0,
        ]);
    }

    /**
     * Safety net for the o-nas copy blocks created by migrations 074/075.
     *
     * The theme template renders every one of these texts from its block with a
     * hardcoded fallback, so a missing row is invisible on the page - and also
     * invisible in the admin, because the generic "Bloky obsahu" tab only lists
     * rows that exist. A site that skipped the migration would therefore never
     * be able to edit the About copy. Opening or saving the page materialises
     * the rows with exactly the template's own defaults; an existing row is
     * never touched.
     *
     * Keep in sync with database/migrations/074_add_about_team_copy_blocks.sql,
     * database/migrations/075_add_about_rest_copy_blocks.sql and
     * database/seeds/seed.php.
     */
    private function ensureAboutCopyBlocks(int $pageId): void
    {
        $blockRepo = new BlockRepository();
        foreach (array_merge(self::ABOUT_COPY_BLOCKS, self::ABOUT_TEAM_VIDEO_BLOCKS) as [$key, $type, $label, $content, $order]) {
            if ($blockRepo->findByKey($pageId, $key)) {
                continue;
            }

            $blockRepo->upsert($pageId, $key, [
                'block_type' => $type,
                'label' => $label,
                'content' => $content,
                'sort_order' => $order,
                'autofill_disabled' => 0,
            ]);
        }
    }

    private function syncHomepageAboutVisualBlock(int $pageId): void
    {
        $blockRepo = new BlockRepository();
        $page = (new PageRepository())->findById($pageId);
        $siteId = (int)($page['site_id'] ?? 0);
        $submittedUrl = trim((string)($_POST['homepage_about_visual_url'] ?? ''));
        $content = $siteId > 0
            ? (new HomepageContentService())->resolveOptimizedMediaUrl($siteId, $submittedUrl, 102400)
            : $submittedUrl;
        $blockRepo->upsert($pageId, 'jb-about-visual__media', [
            'block_type' => 'image',
            'label' => 'Homepage - vizual sekce Naše služby',
            'content' => $content,
            'sort_order' => 13,
            'autofill_disabled' => 0,
        ]);
    }

    private function saveSubmittedBlocks(int $pageId, bool $isHomepage = false): void
    {
        $blockRepo = new BlockRepository();
        $keys      = $_POST['block_key'] ?? [];
        $contents  = $_POST['block_content'] ?? [];
        $autofillEnabled = $_POST['block_autofill_enabled'] ?? [];
        $autofillKeys = $isHomepage
            ? array_fill_keys(array_keys((new HomepageContentService())->getAutofillDefinitions()), true)
            : [];

        foreach ($keys as $i => $key) {
            $key = trim((string)$key);
            if ($key === '') {
                continue;
            }

            $existing = $blockRepo->findByKey($pageId, $key);
            $isAutofillBlock = $isHomepage && isset($autofillKeys[$key]);
            $enabled = $isAutofillBlock ? !empty($autofillEnabled[$key]) : true;
            $disabled = $isAutofillBlock ? ($enabled ? 0 : 1) : (int)($existing['autofill_disabled'] ?? 0);
            $content = (string)($contents[$i] ?? '');
            if ($isAutofillBlock) {
                $content = '';
            }

            $blockRepo->upsert($pageId, $key, [
                'block_type' => $existing['block_type'] ?? 'textarea',
                'label'      => $existing['label'] ?? $key,
                'content'    => $content,
                'sort_order' => $existing['sort_order'] ?? ($i + 1),
                'autofill_disabled' => $disabled,
            ]);
        }
    }

    private function resolveLayoutType(string $templateName): string
    {
        $name = mb_strtolower($templateName);

        return match (true) {
            str_contains($name, 'faq') => 'faq',
            str_contains($name, 'kontakt') => 'contact',
            str_contains($name, 'ceník'), str_contains($name, 'cenik') => 'pricing',
            str_contains($name, 'služby'), str_contains($name, 'sluzby') => 'services',
            default => 'generic',
        };
    }

    private function syncHomepageFaqBlocks(FaqRepository $repo, int $siteId): void
    {
        $page = (new PageRepository())->findByUrlPath('');
        if (!$page) {
            return;
        }

        $items  = array_slice($repo->allActive($siteId), 0, 4);
        $blocks = new BlockRepository();
        $h      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

        for ($i = 0; $i < 4; $i++) {
            $key = 'faq_' . ($i + 1);
            if (isset($items[$i])) {
                $item    = $items[$i];
                $content = '<strong style="display:block;color:#fff;margin-bottom:8px">'
                    . $h($item['question']) . '</strong>'
                    . '<p style="color:rgba(255,255,255,.72);line-height:1.6;margin:0">'
                    . nl2br($h($item['answer'])) . '</p>';
            } else {
                $content = '';
            }

            $blocks->upsert((int)$page['id'], $key, [
                'block_type' => 'html',
                'label'      => 'FAQ ' . ($i + 1),
                'content'    => $content,
                'sort_order' => 51 + $i,
            ]);
        }
    }

    private function syncHomepageContactBlocks(int $siteId): void
    {
        $page = (new PageRepository())->findByUrlPath('');
        if (!$page) {
            return;
        }

        $content = (new HomepageContentService())->buildContactHtml($siteId);

        (new BlockRepository())->upsert((int)$page['id'], 'kontakt_info', [
            'block_type' => 'html',
            'label'      => 'Kontakt - info',
            'content'    => $content,
            'sort_order' => 62,
        ]);
    }

    private function saveSubmittedServiceItems(int $siteId): void
    {
        $items = $_POST['service_items'] ?? [];
        (new ServiceRepository())->upsertMany($siteId, $items);
    }

    private function syncServicesGridBlock(int $siteId): void
    {
        $page = (new PageRepository())->findByUrlPath('sluzby');
        if (!$page) {
            return;
        }

        $serviceItems = (new ServiceRepository())->allActive($siteId);
        $serviceHtml  = (new HomepageContentService())->applyFallbacks(
            $page,
            [['block_key' => 'sluzby_grid', 'block_type' => 'html', 'label' => 'Služby', 'content' => '', 'sort_order' => 0]]
        );

        $content = '';
        foreach ($serviceHtml as $block) {
            if (($block['block_key'] ?? '') === 'sluzby_grid') {
                $content = (string)($block['content'] ?? '');
                break;
            }
        }

        if ($content === '' && $serviceItems !== []) {
            $content = (new HomepageContentService())->applyFallbacks(
                ['url_path' => '', 'site_id' => $siteId],
                [['block_key' => 'sluzby_items', 'block_type' => 'html', 'label' => 'Služby', 'content' => '', 'sort_order' => 0]]
            )[0]['content'] ?? '';
        }

        (new BlockRepository())->upsert((int)$page['id'], 'sluzby_grid', [
            'block_type' => 'html',
            'label'      => 'Služby â€” karty',
            'content'    => $content,
            'sort_order' => 3,
        ]);
    }

    private function refreshServicesPageBlock(int $siteId): void
    {
        $page = (new PageRepository())->findByUrlPath('sluzby');
        if (!$page) {
            return;
        }

        $content = (new HomepageContentService())->buildServicesHtml($siteId, 0);
        (new BlockRepository())->upsert((int)$page['id'], 'sluzby_grid', [
            'block_type' => 'html',
            'label' => 'Služby - karty',
            'content' => $content,
            'sort_order' => 3,
        ]);
    }

    private function refreshHomepageServicesBlock(int $siteId): void
    {
        $page = (new PageRepository())->findByUrlPath('');
        if (!$page) {
            return;
        }

        $content = (new HomepageContentService())->buildServicesHtml($siteId, 6);
        (new BlockRepository())->upsert((int)$page['id'], 'sluzby_items', [
            'block_type' => 'html',
            'label' => 'Služby - karty',
            'content' => $content,
            'sort_order' => 12,
        ]);
    }

    private function buildHomepageContactHtml(array $contact): string
    {
        $h     = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $lines = [];

        foreach (['phone_1', 'phone_2', 'phone_3'] as $phoneKey) {
            if (!empty($contact[$phoneKey])) {
                $phone   = (string)$contact[$phoneKey];
                $telLink = preg_replace('/\s+/', '', $phone);
                $lines[] = '<p><a href="tel:' . $h((string)$telLink) . '">' . $h($phone) . '</a></p>';
            }
        }

        if (!empty($contact['email'])) {
            $lines[] = '<p><a href="mailto:' . $h($contact['email']) . '">' . $h($contact['email']) . '</a></p>';
        }

        foreach (['address_1', 'address_2'] as $addressKey) {
            if (!empty($contact[$addressKey])) {
                $lines[] = '<p>' . nl2br($h($contact[$addressKey])) . '</p>';
            }
        }

        for ($i = 1; $i <= 5; $i++) {
            $type = trim((string)($contact['social_' . $i . '_type'] ?? ''));
            $url  = trim((string)($contact['social_' . $i . '_url'] ?? ''));

            if ($type !== '' && $url !== '') {
                $lines[] = '<p><a href="' . $h($url) . '" target="_blank" rel="noopener">'
                    . ucfirst($h($type)) . '</a></p>';
            }
        }

        if ($lines === []) {
            return '<p style="color:rgba(255,255,255,.45)">Doplňte kontaktní informace v administraci.</p>';
        }

        return '<div class="cta-info">' . implode('', $lines) . '</div>';
    }
}
