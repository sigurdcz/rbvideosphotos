<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\SlugTrait;
use App\Repositories\BlockRepository;
use App\Repositories\ContactRepository;
use App\Repositories\FaqRepository;
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
        if ($isHomepage) {
            $this->ensureHomepageHeroVideoBlock($id);
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
        $serviceItems = $layout === 'services' ? (new ServiceRepository())->allBySite((int)$page['site_id']) : [];

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
        if ($isHomepage) {
            $this->ensureHomepageHeroVideoBlock($id);
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

        if ($layoutType === 'services') {
            $this->saveSubmittedServiceItems((int)$page['site_id']);
            $this->refreshServicesPageBlock((int)$page['site_id']);
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

    private function ensureHomepageHeroVideoBlock(int $pageId): void
    {
        $blockRepo = new BlockRepository();
        if ($blockRepo->findByKey($pageId, 'hero_video')) {
            return;
        }

        $blockRepo->upsert($pageId, 'hero_video', [
            'block_type' => 'link',
            'label' => 'Hero — video (URL)',
            'content' => '',
            'sort_order' => 2,
            'autofill_disabled' => 0,
        ]);
    }

    private function ensureServicesHeroImageBlock(int $pageId): void
    {
        $blockRepo = new BlockRepository();
        if ($blockRepo->findByKey($pageId, 'hero_bg')) {
            return;
        }

        $blockRepo->upsert($pageId, 'hero_bg', [
            'block_type' => 'image',
            'label' => 'Hero — obrazek / medium',
            'content' => '',
            'sort_order' => 3,
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

        $items  = array_slice($repo->allActive($siteId), 0, 3);
        $blocks = new BlockRepository();
        $h      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

        for ($i = 0; $i < 3; $i++) {
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

        $contact = (new ContactRepository())->findBySiteSafe($siteId);
        $content = $this->buildHomepageContactHtml($contact);

        (new BlockRepository())->upsert((int)$page['id'], 'kontakt_info', [
            'block_type' => 'html',
            'label'      => 'Kontakt — info',
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
            'label' => 'Služby — karty',
            'content' => $content,
            'sort_order' => 3,
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
