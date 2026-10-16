<?php

namespace App\Services;

use App\Repositories\MediaRepository;
use App\Repositories\PageRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\UserRepository;

class PortfolioPublishService
{
    /**
     * Write the standalone static export for a single portfolio entry
     * (storage/published/nase-prace/{slug}/index.html). The /nase-prace LISTING
     * page is NOT written here anymore - see regenerateListing(), which delegates
     * to PublishService::publishPage() so live and publish share one themed
     * source of truth for the listing (TEMPLATE_REFACTOR_PLAN.md phase 6).
     */
    public function publishEntry(array $entry, PortfolioRepository $repo, array $site): void
    {
        $mediaItems = $repo->getMedia($entry['id']);
        // Both the first and the extra videos come from one list, so the export can
        // never disagree with itself if the video_url mirror ever drifts.
        $videos     = $repo->getVideos((int)$entry['id']);
        $embedUrl   = $videos[0]['embed_url'] ?? null;

        $h  = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $sn = $h($site['name'] ?? 'RB Videos & Photos');
        $title = $h($entry['title']);

        $galleryHtml = '';
        foreach ($mediaItems as $idx => $m) {
            $thumbSrc = $h($this->publicMediaUrl((string)($m['thumb_path'] ?? $m['medium_path'] ?? $m['storage_path'] ?? '')));
            $fullSrc  = $h($this->publicMediaUrl((string)($m['storage_path'] ?? '')));
            $alt      = $h($m['alt_text'] ?? $entry['title']);
            $galleryHtml .= "<button type=\"button\" class=\"pf-photo\" data-pf-full=\"{$fullSrc}\" data-pf-idx=\"{$idx}\" aria-label=\"Otevřít fotografii\"><img src=\"{$thumbSrc}\" alt=\"{$alt}\" loading=\"lazy\"></button>";
        }

        $videoHtml = '';
        if ($embedUrl) {
            $eu = $h($embedUrl);
            $videoHtml = "<div class=\"pf-video-wrap\"><iframe src=\"{$eu}\" frameborder=\"0\" allow=\"autoplay; fullscreen; picture-in-picture\" allowfullscreen loading=\"lazy\" title=\"{$title}\"></iframe></div>";
            // Further videos of the entry follow the first one; an entry with a single
            // video keeps exporting the very same markup it did before.
            foreach (array_slice($videos, 1) as $i => $video) {
                $eu = $h($video['embed_url']);
                $videoTitle = $title . ' &ndash; video ' . ($i + 2);
                $videoHtml .= "<div class=\"pf-video-wrap pf-video-wrap--extra\"><iframe src=\"{$eu}\" frameborder=\"0\" allow=\"autoplay; fullscreen; picture-in-picture\" allowfullscreen loading=\"lazy\" title=\"{$videoTitle}\"></iframe></div>";
            }
        }

        $description = $entry['description'] ?? '';
        $metaDesc    = $h($entry['meta_description'] ?? strip_tags(substr($description, 0, 160)));
        $pageUrl = rtrim((string)($site['domain'] ?? ''), '/') . '/' . ltrim((string)$entry['slug'], '/');
        $ogImageUrl = $this->absoluteMediaUrl((string)($mediaItems[0]['storage_path'] ?? ''), $site);
        $twitterImageUrl = $this->resolveSiteSocialImage(
            (int)($site['default_twitter_image_id'] ?? $site['default_og_image_id'] ?? 0),
            $site
        );
        if ($twitterImageUrl === '') {
            $twitterImageUrl = $ogImageUrl;
        }
        $metaTitle = trim((string)($entry['meta_title'] ?? '')) ?: (string)$entry['title'];
        $metaTitleEsc = $h($metaTitle);
        $socialHead = (new SeoRenderer())->renderHead([
            'meta_title' => $metaTitle,
            'meta_description' => html_entity_decode($metaDesc, ENT_QUOTES, 'UTF-8'),
            'canonical_url' => $pageUrl,
            'robots_index' => 1,
            'robots_follow' => 1,
            'og_type' => 'website',
        ], $site, $pageUrl, $ogImageUrl !== '' ? $ogImageUrl : null, $twitterImageUrl !== '' ? $twitterImageUrl : null);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$metaTitleEsc}</title>
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
    .pf-photo{padding:0;border:0;background:transparent;cursor:pointer}
    .pf-photo img{width:100%;height:220px;object-fit:cover;border-radius:8px;display:block}
    .pf-lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.92);align-items:center;justify-content:center}
    .pf-lightbox.active{display:flex}
    .pf-lb-img{max-width:92vw;max-height:88vh;object-fit:contain;border-radius:8px}
    .pf-lb-close{position:absolute;top:20px;right:24px;display:grid;width:50px!important;height:50px!important;min-width:50px!important;min-height:50px!important;aspect-ratio:1;padding:0!important;place-items:center;border:1px solid rgba(255,255,255,.55);border-radius:50%!important;background:transparent!important;box-shadow:none!important;font-size:2rem;color:#fff;cursor:pointer;line-height:1;opacity:.7}
    .pf-lb-close:hover{opacity:1}
    .pf-lb-prev,.pf-lb-next{position:absolute;top:50%;display:grid;width:52px!important;height:52px!important;min-width:52px!important;min-height:52px!important;aspect-ratio:1;padding:0!important;place-items:center;transform:translateY(-50%);border:1px solid rgba(255,255,255,.55);border-radius:50%!important;background:transparent!important;box-shadow:none!important;font-size:2.5rem;color:#fff;cursor:pointer;line-height:1;opacity:.6;user-select:none}
    .pf-lb-prev:hover,.pf-lb-next:hover{opacity:1}
    .pf-lb-prev{left:16px}
    .pf-lb-next{right:16px}
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
<div class="pf-lightbox" id="pf-lightbox">
  <span class="pf-lb-close" id="pf-lb-close">&times;</span>
  <span class="pf-lb-prev" id="pf-lb-prev">&#8249;</span>
  <img class="pf-lb-img" id="pf-lb-img" src="" alt="">
  <span class="pf-lb-next" id="pf-lb-next">&#8250;</span>
</div>
<footer>{$sn}</footer>
<script>
(function(){
  var lb=document.getElementById('pf-lightbox'),img=document.getElementById('pf-lb-img'),items=Array.from(document.querySelectorAll('.pf-photo')),idx=0;
  if(!lb||!items.length)return;
  function open(i){idx=i;img.src=items[i].dataset.pfFull||'';lb.classList.add('active');}
  function close(){lb.classList.remove('active');img.src='';}
  function prev(){open((idx-1+items.length)%items.length);}
  function next(){open((idx+1)%items.length);}
  items.forEach(function(el,i){el.addEventListener('click',function(){open(i);});});
  document.getElementById('pf-lb-close').addEventListener('click',close);
  document.getElementById('pf-lb-prev').addEventListener('click',prev);
  document.getElementById('pf-lb-next').addEventListener('click',next);
  lb.addEventListener('click',function(e){if(e.target===lb)close();});
  document.addEventListener('keydown',function(e){if(!lb.classList.contains('active'))return;if(e.key==='Escape')close();if(e.key==='ArrowLeft')prev();if(e.key==='ArrowRight')next();});
})();
</script>
</body>
</html>
HTML;

        $html = preg_replace('~<title\b[^>]*>.*?</title>\s*~is', '', $html, 1) ?? $html;
        $html = preg_replace('~<meta\s+name="description"[^>]*>\s*~i', '', $html, 1) ?? $html;
        $html = str_replace('</head>', $socialHead . "\n</head>", $html);

        $dir = dirname(__DIR__, 2) . '/storage/published/nase-prace/' . $entry['slug'];
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '/index.html', \App\Services\Render\MarkupNormalizer::injectLightOnlyColorScheme($this->sanitizeHtml($html)));
    }

    /**
     * Regenerate the static /nase-prace LISTING page after a portfolio CRUD
     * action, by delegating to the ONE themed publish pipeline
     * (PublishService::publishPage → renderArchetypeContent('nase-prace')).
     *
     * Phase 6 of the templating refactor: this replaces the former
     * regenerateListingPage() which wrote the same storage/published/nase-prace/
     * index.html with bare, unthemed pl-grid/pl-tile markup - a dual writer that
     * overwrote the themed listing on the first portfolio publish in the admin.
     * See UKOL.md "Fáze 6" BLOCKER.
     *
     * A guard on findPublishedByUrlPath() keeps this a no-op unless the
     * nase-prace page actually exists AND is published for this site, so a draft
     * listing page is never force-published as a side effect of portfolio edits.
     */
    public function regenerateListing(array $site, int $userId = 0): void
    {
        $page = (new PageRepository())->findPublishedByUrlPath('nase-prace');
        if (!$page || (int)($page['site_id'] ?? 0) !== (int)($site['id'] ?? 0)) {
            return;
        }

        (new PublishService())->publishPage((int)$page['id'], $this->resolvePublisherId($userId));
    }

    public function removePublishedFiles(string $slug): void
    {
        $file = dirname(__DIR__, 2) . '/storage/published/nase-prace/' . $slug . '/index.html';
        @unlink($file);
        @rmdir(dirname($file));
    }

    /**
     * A valid user id for the publish_history row publishPage() writes. Prefers
     * the caller-supplied id (the logged-in admin in the session/PortfolioController
     * context); falls back to the lowest existing user id for the API-key context,
     * which has no session user. Mirrors the golden runner's firstAdminUserId().
     */
    private function resolvePublisherId(int $userId): int
    {
        if ($userId > 0) {
            return $userId;
        }

        return (new UserRepository())->firstId() ?? 0;
    }

    private function publicMediaUrl(string $storagePath): string
    {
        return \App\Services\Render\MediaUrlResolver::publicMediaUrl($storagePath);
    }

    private function resolveSiteSocialImage(int $mediaId, array $site): string
    {
        if ($mediaId <= 0) {
            return '';
        }

        $mediaRepository = new MediaRepository();
        $media = $mediaRepository->findById($mediaId);
        if (!$media) {
            return '';
        }

        $path = (string)($media['storage_path'] ?? '');
        foreach ($mediaRepository->variantsByMediaId($mediaId) as $variant) {
            if (($variant['format'] ?? '') === 'og' && !empty($variant['path'])) {
                $path = (string)$variant['path'];
                break;
            }
        }

        return $this->absoluteMediaUrl($path, $site);
    }

    private function absoluteMediaUrl(string $path, array $site): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return rtrim((string)($site['domain'] ?? ''), '/') . '/' . ltrim($path, '/');
    }

    private function sanitizeHtml(string $html): string
    {
        $socialTags = [];
        $html = preg_replace_callback(
            '~<meta\s+(?:property="og:image"|name="twitter:image")[^>]*>~i',
            static function (array $match) use (&$socialTags): string {
                $token = '<!--cms-portfolio-social-' . count($socialTags) . '-->';
                $socialTags[$token] = $match[0];
                return $token;
            },
            $html
        ) ?? $html;
        $html = preg_replace('~https?://localhost(?::\d+)?/storage/media/~i', '/media/', $html) ?? $html;
        $html = str_replace('/storage/media/', '/media/', $html);
        return $socialTags === [] ? $html : strtr($html, $socialTags);
    }
}
