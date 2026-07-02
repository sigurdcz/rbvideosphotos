<?php

namespace App\Services;

use App\Repositories\BlockRepository;
use App\Repositories\PageRepository;
use App\Repositories\PortfolioRepository;

class PortfolioPublishService
{
    public function publishEntry(array $entry, PortfolioRepository $repo, array $site): void
    {
        $mediaItems = $repo->getMedia($entry['id']);
        $embedUrl   = $entry['video_url'] ? PortfolioRepository::embedUrl($entry['video_url']) : null;

        $h  = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $sn = $h($site['name'] ?? 'RB Photos & Videos');
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
    .pf-photo{padding:0;border:0;background:transparent;cursor:pointer}
    .pf-photo img{width:100%;height:220px;object-fit:cover;border-radius:8px;display:block}
    .pf-lightbox{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.92);align-items:center;justify-content:center}
    .pf-lightbox.active{display:flex}
    .pf-lb-img{max-width:92vw;max-height:88vh;object-fit:contain;border-radius:8px}
    .pf-lb-close{position:absolute;top:20px;right:24px;font-size:2rem;color:#fff;cursor:pointer;line-height:1;opacity:.7}
    .pf-lb-close:hover{opacity:1}
    .pf-lb-prev,.pf-lb-next{position:absolute;top:50%;transform:translateY(-50%);font-size:2.5rem;color:#fff;cursor:pointer;padding:12px;opacity:.6;user-select:none}
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

        $dir = dirname(__DIR__, 2) . '/storage/published/nase-prace/' . $entry['slug'];
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '/index.html', $this->sanitizeHtml($html));

        $this->regenerateListingPage($repo, $site);
    }

    public function syncHomepageTiles(PortfolioRepository $repo, int $siteId): void
    {
        $page = (new PageRepository())->findByUrlPath('');
        if (!$page) return;

        $entries   = $repo->allPublished($siteId);
        $tilesHtml = $this->buildTilesHtml($entries, $repo);

        (new BlockRepository())->upsert($page['id'], 'portfolio-tiles', [
            'block_type' => 'html',
            'label'      => 'Naše práce — dlaždice',
            'content'    => $tilesHtml,
            'sort_order' => 38,
        ]);
    }

    public function removePublishedFiles(string $slug): void
    {
        $file = dirname(__DIR__, 2) . '/storage/published/nase-prace/' . $slug . '/index.html';
        @unlink($file);
        @rmdir(dirname($file));
    }

    private function regenerateListingPage(PortfolioRepository $repo, array $site): void
    {
        $entries = $repo->allPublished($site['id']);
        $h  = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $sn = $h($site['name'] ?? '');

        $tilesHtml = '';
        foreach ($entries as $e) {
            $media = $repo->getFirstMedia($e['id']);
            $thumb = $e['video_url'] ? PortfolioRepository::youtubeThumbnail($e['video_url']) : '';
            if (!$thumb && $media) {
                $thumb = $this->publicMediaUrl((string)($media['storage_path'] ?? ''));
            }
            $imgHtml    = $thumb ? "<img src=\"{$h($thumb)}\" alt=\"{$h($e['title'])}\" loading=\"lazy\">" : "<div class=\"pl-no-thumb\">▶</div>";
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
        file_put_contents($dir . '/index.html', $this->sanitizeHtml($html));
    }

    private function buildTilesHtml(array $entries, PortfolioRepository $repo): string
    {
        if (empty($entries)) {
            return '<p style="color:rgba(255,255,255,.45);text-align:center;padding:40px 0">Zatím žádné publikované práce.</p>';
        }

        $h     = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
        $tiles = '';

        foreach ($entries as $e) {
            $photos   = $repo->getMedia($e['id']);
            $thumb    = $e['video_url'] ? PortfolioRepository::youtubeThumbnail($e['video_url']) : '';
            $hasVideo = (bool)$e['video_url'];
            if (!$thumb && !empty($photos)) {
                $thumbFile = $photos[0]['thumb_path'] ?? $photos[0]['medium_path'] ?? $photos[0]['storage_path'] ?? '';
                $thumb = $this->publicMediaUrl((string)$thumbFile);
            }

            $thumbHtml = $thumb
                ? "<img src=\"{$h($thumb)}\" alt=\"{$h($e['title'])}\" loading=\"lazy\">"
                : '<div style="height:100%;background:#222;display:flex;align-items:center;justify-content:center;font-size:2rem;color:rgba(255,255,255,.3)">&#9654;</div>';
            $playIcon = $hasVideo ? '<span class="pt-play">&#9654;</span>' : '';

            $extraHtml   = '';
            $stripPhotos = $hasVideo ? array_slice($photos, 0, 3) : array_slice($photos, 1, 3);
            foreach ($stripPhotos as $p) {
                $pfile      = $p['thumb_path'] ?? $p['storage_path'] ?? '';
                $psrc       = $h($this->publicMediaUrl((string)$pfile));
                $palt       = $h($p['alt_text'] ?? '');
                $extraHtml .= "<img src=\"{$psrc}\" alt=\"{$palt}\" loading=\"lazy\">";
            }

            $tiles .= <<<TILE
<a class="pt-tile" href="/nase-prace">
  <div class="pt-thumb">{$thumbHtml}{$playIcon}</div>
  <div class="pt-photos">{$extraHtml}</div>
  <div class="pt-caption">{$h($e['title'])}</div>
</a>
TILE;
        }

        $css = '<style>'
            . '.pt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px}'
            . '.pt-tile{display:block;text-decoration:none;color:inherit;background:#1b1b1f;border-radius:14px;overflow:hidden;box-shadow:0 14px 30px rgba(0,0,0,.25);transition:transform .2s,box-shadow .2s}'
            . '.pt-tile:hover{transform:translateY(-3px);box-shadow:0 20px 40px rgba(0,0,0,.4)}'
            . '.pt-thumb{position:relative;height:200px;background:#0e0e11;overflow:hidden}'
            . '.pt-thumb img{width:100%;height:100%;object-fit:cover;display:block}'
            . '.pt-play{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:56px;height:56px;border-radius:50%;background:rgba(196,150,60,.92);display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff}'
            . '.pt-photos{display:flex;gap:3px;height:72px;background:#17171a;padding:3px}'
            . '.pt-photos img{flex:1;min-width:0;height:100%;object-fit:cover;display:block}'
            . '.pt-caption{padding:14px 16px 16px;font-weight:700;font-size:.95rem;color:#fff;background:#17171a}'
            . '@media(max-width:600px){.pt-grid{grid-template-columns:1fr}}'
            . '</style>';

        return $css . '<div class="pt-grid">' . $tiles . '</div>';
    }

    private function publicMediaUrl(string $storagePath): string
    {
        $normalized = str_replace('\\', '/', trim($storagePath));
        if ($normalized === '') return '';

        if (preg_match('~https?://[^/]+(/storage/media/.*)$~i', $normalized, $m)) {
            return '/media/' . ltrim(substr($m[1], strlen('/storage/media/')), '/');
        }
        if (str_starts_with($normalized, '/media/')) return $normalized;
        if (str_starts_with($normalized, '/storage/media/')) return '/media/' . ltrim(substr($normalized, strlen('/storage/media/')), '/');
        if (str_starts_with($normalized, 'storage/media/')) return '/media/' . ltrim(substr($normalized, strlen('storage/media/')), '/');

        return '/' . ltrim($normalized, '/');
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('~https?://localhost(?::\d+)?/storage/media/~i', '/media/', $html) ?? $html;
        return str_replace('/storage/media/', '/media/', $html);
    }
}
