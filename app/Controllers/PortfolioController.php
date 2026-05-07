<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\BlockRepository;
use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\SiteRepository;
use App\Repositories\TemplateRepository;

class PortfolioController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $site    = (new SiteRepository())->findFirst();
        $repo    = new PortfolioRepository();
        $entries = $site ? $repo->allBySite($site['id']) : [];

        // Attach first media item to each entry for preview
        foreach ($entries as &$e) {
            $e['_first_media'] = $repo->getFirstMedia($e['id']);
        }
        unset($e);

        $this->view('portfolio.index', [
            'entries' => $entries,
            'site'    => $site,
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->view('portfolio.form', [
            'entry'    => null,
            'media'    => $this->allMedia(),
            'selected' => [],
        ]);
    }

    public function store(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            $this->flash('error', 'Web nenalezen.');
            $this->redirect('/admin/portfolio');
        }

        $slug   = $this->makeSlug($_POST['slug'] ?? '', $_POST['title'] ?? '');
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        $repo = new PortfolioRepository();
        $id   = $repo->create([
            'site_id'          => $site['id'],
            'title'            => trim($_POST['title'] ?? ''),
            'slug'             => $slug,
            'description'      => $_POST['description'] ?? '',
            'video_url'        => trim($_POST['video_url'] ?? '') ?: null,
            'status'           => $status,
            'published_at'     => $status === 'published' ? date('Y-m-d H:i:s') : null,
            'sort_order'       => (int)($_POST['sort_order'] ?? 0),
            'meta_title'       => trim($_POST['meta_title']       ?? '') ?: null,
            'meta_description' => trim($_POST['meta_description'] ?? '') ?: null,
        ]);

        $repo->setMedia($id, $_POST['media_ids'] ?? []);

        if ($status === 'published') {
            $this->publishEntry($repo->findById($id), $repo, $site);
        }
        $this->syncHomepageTiles($repo, $site['id']);

        $this->flash('success', 'Práce byla uložena.');
        $this->redirect('/admin/portfolio/edit?id=' . $id);
    }

    public function edit(array $params): void
    {
        $this->requireAuth();
        $id    = (int)($_GET['id'] ?? 0);
        $repo  = new PortfolioRepository();
        $entry = $repo->findById($id);
        if (!$entry) {
            $this->flash('error', 'Práce nenalezena.');
            $this->redirect('/admin/portfolio');
        }

        $selected = array_column($repo->getMedia($id), 'id');

        $this->view('portfolio.form', [
            'entry'    => $entry,
            'media'    => $this->allMedia(),
            'selected' => $selected,
            'success'  => $this->getFlash('success'),
            'error'    => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = new PortfolioRepository();
        $entry = $repo->findById($id);
        if (!$entry) {
            $this->flash('error', 'Práce nenalezena.');
            $this->redirect('/admin/portfolio');
        }

        $slug   = $this->makeSlug($_POST['slug'] ?? '', $_POST['title'] ?? '');
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $publishedAt = $status === 'published'
            ? ($entry['published_at'] ?? date('Y-m-d H:i:s'))
            : null;

        $repo->update($id, [
            'title'            => trim($_POST['title'] ?? ''),
            'slug'             => $slug,
            'description'      => $_POST['description'] ?? '',
            'video_url'        => trim($_POST['video_url'] ?? '') ?: null,
            'status'           => $status,
            'published_at'     => $publishedAt,
            'sort_order'       => (int)($_POST['sort_order'] ?? 0),
            'meta_title'       => trim($_POST['meta_title']       ?? '') ?: null,
            'meta_description' => trim($_POST['meta_description'] ?? '') ?: null,
        ]);

        $repo->setMedia($id, $_POST['media_ids'] ?? []);

        $site = (new SiteRepository())->findFirst();
        if ($status === 'published' && $site) {
            $this->publishEntry($repo->findById($id), $repo, $site);
        }
        if ($site) {
            $this->syncHomepageTiles($repo, $site['id']);
        }

        $this->flash('success', 'Práce byla uložena.');
        $this->redirect('/admin/portfolio/edit?id=' . $id);
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $id   = (int)($_POST['id'] ?? 0);
        $repo = new PortfolioRepository();
        $e    = $repo->findById($id);
        $repo->delete($id);

        // Remove published static file
        if ($e) {
            $file = dirname(__DIR__, 2) . '/storage/published/nase-prace/' . $e['slug'] . '/index.html';
            @unlink($file);
            @rmdir(dirname($file));
        }

        $site = (new SiteRepository())->findFirst();
        if ($site) {
            $this->syncHomepageTiles($repo, $site['id']);
        }

        $this->flash('success', 'Práce smazána.');
        $this->redirect('/admin/portfolio');
    }

    // ── Publishing ────────────────────────────────────────────────────────────

    private function publishEntry(array $entry, PortfolioRepository $repo, array $site): void
    {
        $mediaItems = $repo->getMedia($entry['id']);
        $embedUrl   = $entry['video_url'] ? PortfolioRepository::embedUrl($entry['video_url']) : null;

        $h  = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $sn = $h($site['name'] ?? 'RB Photos & Videos');
        $title = $h($entry['title']);

        // Photo gallery HTML
        $galleryHtml = '';
        foreach ($mediaItems as $m) {
            $src = $h($this->publicMediaUrl((string)($m['storage_path'] ?? '')));
            $alt = $h($m['alt_text'] ?? $entry['title']);
            $galleryHtml .= "<div class=\"pf-photo\"><img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\"></div>";
        }

        // Video embed HTML
        $videoHtml = '';
        if ($embedUrl) {
            $eu = $h($embedUrl);
            $videoHtml = "<div class=\"pf-video-wrap\"><iframe src=\"{$eu}\" frameborder=\"0\" allow=\"autoplay; fullscreen; picture-in-picture\" allowfullscreen loading=\"lazy\" title=\"{$title}\"></iframe></div>";
        }

        $description = $entry['description'] ?? '';
        $metaDesc    = $h($entry['meta_description'] ?? strip_tags(substr($description, 0, 160)));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$title} — {$sn}</title>
  <meta name="description" content="{$metaDesc}">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,-apple-system,sans-serif;color:#1a1a1a;background:#fff;line-height:1.6}
    .pf-nav{background:#111;padding:14px 24px;display:flex;align-items:center;gap:16px}
    .pf-nav a{color:#fff;text-decoration:none;font-size:14px;opacity:.8}.pf-nav a:hover{opacity:1}
    .pf-nav-logo{font-weight:700;font-size:16px;opacity:1!important}
    .pf-hero{background:#111;color:#fff;padding:48px 24px 40px;text-align:center}
    .pf-hero h1{font-size:clamp(1.4rem,4vw,2.4rem);font-weight:700;max-width:700px;margin:0 auto}
    .pf-container{max-width:960px;margin:0 auto;padding:40px 24px}
    .pf-video-wrap{position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:10px;margin-bottom:40px;background:#000}
    .pf-video-wrap iframe{position:absolute;top:0;left:0;width:100%;height:100%}
    .pf-description{font-size:1rem;color:#444;margin-bottom:40px;max-width:760px}
    .pf-description h2,.pf-description h3{margin:1.2em 0 .5em;color:#111}
    .pf-description p{margin-bottom:.8em}
    .pf-description ul,.pf-description ol{margin:.6em 0 .8em 1.5em}
    .pf-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin-bottom:48px}
    .pf-photo img{width:100%;height:220px;object-fit:cover;border-radius:8px;display:block}
    .pf-back{text-align:center;padding:24px 0 48px}
    .pf-back a{background:#c4963c;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600}
    footer{background:#111;color:rgba(255,255,255,.4);text-align:center;padding:20px;font-size:13px}
  </style>
</head>
<body>
<nav class="pf-nav">
  <a class="pf-nav-logo" href="/">{$sn}</a>
  <a href="/nase-prace">← Naše práce</a>
</nav>
<div class="pf-hero"><h1>{$title}</h1></div>
<div class="pf-container">
  {$videoHtml}
  <div class="pf-description">{$description}</div>
  <div class="pf-gallery">{$galleryHtml}</div>
  <div class="pf-back"><a href="/nase-prace">← Zpět na Naše práce</a></div>
</div>
<footer>{$sn}</footer>
</body>
</html>
HTML;

        $dir = dirname(__DIR__, 2) . '/storage/published/nase-prace/' . $entry['slug'];
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '/index.html', $this->sanitizePortfolioHtml($html));

        // Regenerate listing page
        $this->regenerateListingPage(new PortfolioRepository(), $site);
    }

    private function regenerateListingPage(PortfolioRepository $repo, array $site): void
    {
        $entries = $repo->allPublished($site['id']);
        $h  = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $sn = $h($site['name'] ?? '');

        $tilesHtml = '';
        foreach ($entries as $e) {
            $media      = $repo->getFirstMedia($e['id']);
            $thumb      = $e['video_url'] ? PortfolioRepository::youtubeThumbnail($e['video_url']) : '';
            if (!$thumb && $media) {
                $thumb = $this->publicMediaUrl((string)($media['storage_path'] ?? ''));
            }
            $imgHtml = $thumb ? "<img src=\"{$h($thumb)}\" alt=\"{$h($e['title'])}\" loading=\"lazy\">" : "<div class=\"pl-no-thumb\">▶</div>";
            $tilesHtml .= "<a class=\"pl-tile\" href=\"/{$h($e['slug'])}\"><div class=\"pl-thumb\">{$imgHtml}</div><div class=\"pl-caption\">{$h($e['title'])}</div></a>";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Naše práce — {$sn}</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,-apple-system,sans-serif;color:#1a1a1a;background:#f8f6f2}
    .pl-nav{background:#111;padding:14px 24px;display:flex;align-items:center;gap:16px}
    .pl-nav a{color:#fff;text-decoration:none;font-size:14px;opacity:.8}.pl-nav a:hover{opacity:1}
    .pl-nav-logo{font-weight:700;font-size:16px;opacity:1!important}
    .pl-hero{background:#111;color:#fff;padding:56px 24px 48px;text-align:center}
    .pl-hero h1{font-size:clamp(1.6rem,4vw,2.6rem);font-weight:700}
    .pl-container{max-width:1100px;margin:0 auto;padding:48px 24px}
    .pl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px}
    .pl-tile{display:block;text-decoration:none;color:inherit;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);transition:transform .2s,box-shadow .2s}
    .pl-tile:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,.15)}
    .pl-thumb{height:210px;overflow:hidden;background:#111}
    .pl-thumb img{width:100%;height:100%;object-fit:cover;display:block}
    .pl-no-thumb{height:100%;display:flex;align-items:center;justify-content:center;font-size:3rem;color:rgba(255,255,255,.3)}
    .pl-caption{padding:14px 16px;font-weight:600;font-size:.95rem}
    footer{background:#111;color:rgba(255,255,255,.4);text-align:center;padding:20px;font-size:13px;margin-top:48px}
  </style>
</head>
<body>
<nav class="pl-nav">
  <a class="pl-nav-logo" href="/">{$sn}</a>
  <a href="/">← Domů</a>
</nav>
<div class="pl-hero"><h1>Naše práce</h1></div>
<div class="pl-container">
  <div class="pl-grid">{$tilesHtml}</div>
</div>
<footer>{$sn}</footer>
</body>
</html>
HTML;

        $dir = dirname(__DIR__, 2) . '/storage/published/nase-prace';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '/index.html', $this->sanitizePortfolioHtml($html));
    }

    // ── Homepage tiles sync ───────────────────────────────────────────────────

    private function syncHomepageTiles(PortfolioRepository $repo, int $siteId): void
    {
        $page = (new PageRepository())->findByUrlPath('');
        if (!$page) return;

        $entries  = $repo->allPublished($siteId);
        $tilesHtml = $this->buildTilesHtml($entries, $repo);

        // Upsert the portfolio-tiles content block on the homepage
        $blocks = new BlockRepository();
        $blocks->upsert($page['id'], 'portfolio-tiles', [
            'block_type' => 'html',
            'label'      => 'Naše práce — dlaždice',
            'content'    => $tilesHtml,
            'sort_order' => 38,
        ]);
    }

    private function buildTilesHtml(array $entries, PortfolioRepository $repo): string
    {
        if (empty($entries)) {
            return '<p style="color:#888;text-align:center;padding:40px 0">Zatím žádné publikované práce.</p>';
        }

        $h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

        $tiles = '';
        foreach ($entries as $e) {
            $photos   = $repo->getMedia($e['id']);
            $thumb    = $e['video_url'] ? PortfolioRepository::youtubeThumbnail($e['video_url']) : '';
            $hasVideo = (bool)$e['video_url'];
            if (!$thumb && !empty($photos)) {
                $thumb = $this->publicMediaUrl((string)($photos[0]['storage_path'] ?? ''));
            }

            $thumbHtml = $thumb
                ? "<img src=\"{$h($thumb)}\" alt=\"{$h($e['title'])}\" loading=\"lazy\">"
                : "<div style=\"height:100%;background:#222;display:flex;align-items:center;justify-content:center;font-size:2rem;color:rgba(255,255,255,.3)\">&#9654;</div>";
            $playIcon  = $hasVideo ? '<span class="pt-play">&#9654;</span>' : '';

            // Up to 3 extra photos
            $extraHtml = '';
            $extraPhotos = array_slice($photos, 0, 3);
            foreach ($extraPhotos as $p) {
                $psrc = $h($this->publicMediaUrl((string)($p['storage_path'] ?? '')));
                $palt = $h($p['alt_text'] ?? '');
                $extraHtml .= "<img src=\"{$psrc}\" alt=\"{$palt}\" loading=\"lazy\">";
            }

            $tiles .= <<<TILE
<a class="pt-tile" href="/{$h($e['slug'])}">
  <div class="pt-thumb">{$thumbHtml}{$playIcon}</div>
  <div class="pt-photos">{$extraHtml}</div>
  <div class="pt-caption">{$h($e['title'])}</div>
</a>
TILE;
        }

        return '<div class="pt-grid">' . $tiles . '</div>'
            . '<div style="text-align:center;margin-top:32px"><a href="/nase-prace" class="btn-g">Zobrazit všechny práce</a></div>';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function allMedia(): array
    {
        $site = (new SiteRepository())->findFirst();
        return $site ? (new MediaRepository())->allBySite($site['id']) : [];
    }

    private function makeSlug(string $slug, string $title): string
    {
        $src = trim($slug) !== '' ? $slug : $title;
        $map = ['á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z'];
        $s   = mb_strtolower(strtr($src, $map));
        $s   = preg_replace('/[^a-z0-9\s-]/', '', $s);
        $s   = preg_replace('/[\s-]+/', '-', trim($s));
        return $s;
    }
    private function publicMediaUrl(string $storagePath): string
    {
        $normalized = str_replace('\\', '/', trim($storagePath));
        if ($normalized === '') {
            return '';
        }

        if (preg_match('~https?://[^/]+(/storage/media/.*)$~i', $normalized, $m)) {
            return $m[1];
        }

        if (str_starts_with($normalized, '/storage/media/')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/media/')) {
            return '/' . $normalized;
        }

        return '/' . ltrim($normalized, '/');
    }

    private function sanitizePortfolioHtml(string $html): string
    {
        return preg_replace('~https?://localhost(?::\d+)?/storage/media/~i', '/storage/media/', $html) ?? $html;
    }
}
