<?php

declare(strict_types=1);

namespace App\Services\Render;

/**
 * Canonical hero media builders (image/video markup, embed URLs, hero style guard).
 *
 * Extracted in phase 2 of the templating refactor as the single source of truth
 * shared by the live render path (public/index.php) and the publish path
 * (App\Services\PublishService). Do not fork these implementations.
 */
final class HeroMediaRenderer
{
    public static function buildHeroImageHtml(string $imageUrl): string
{
    if ($imageUrl === '') {
        return '';
    }
    $safe = htmlspecialchars($imageUrl, ENT_QUOTES);
    return '<div class="theme-hero-video theme-hero-video--image" aria-hidden="true" style="background-image:url(\'' . $safe . '\')"></div>';
}

    public static function buildHeroVideoHtml(string $videoUrl, string $posterUrl = ''): string
{
    $embedUrl = \App\Services\Render\HeroMediaRenderer::buildVideoEmbedUrl($videoUrl);
    $posterStyle = $posterUrl !== '' ? ' style="background-image:url(\'' . htmlspecialchars($posterUrl, ENT_QUOTES) . '\')"' : '';
    if ($embedUrl !== '') {
        $safeEmbed = htmlspecialchars($embedUrl, ENT_QUOTES);
        return '<div class="theme-hero-video theme-hero-video--embed" aria-hidden="true"' . $posterStyle . '><iframe src="' . $safeEmbed . '" title="Hero video" allow="autoplay; fullscreen; picture-in-picture" loading="eager"></iframe></div>';
    }

    return '';
}

    public static function buildVideoEmbedUrl(string $videoUrl): string
{
    $url = trim($videoUrl);
    if ($url === '') {
        return '';
    }

    if (preg_match('~vimeo\.com/(?:video/)?([0-9]+)~i', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1] . '?background=1&autoplay=1&loop=1&muted=1&playsinline=1';
    }

    $youtubeId = '';
    if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~i', $url, $m)) {
        $youtubeId = $m[1];
    } elseif (preg_match('~youtube\.com/(?:embed|shorts)/([A-Za-z0-9_-]{6,})~i', $url, $m)) {
        $youtubeId = $m[1];
    } elseif (preg_match('~[?&]v=([A-Za-z0-9_-]{6,})~i', $url, $m)) {
        $youtubeId = $m[1];
    }

    if ($youtubeId !== '') {
        return 'https://www.youtube.com/embed/' . $youtubeId . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . $youtubeId . '&playsinline=1&modestbranding=1&rel=0';
    }

    return '';
}

    public static function ensureHeroVideoStyle(string $html): string
{
    if (str_contains($html, 'data-cms-hero-video')) {
        return $html;
    }

    $style = <<<'HTML'
<style data-cms-hero-video>
.hp-hero,.hero,.page-hero,.pf-hero,.bl-hero,.kt-hero,.fq-hero,.bp-hero,.pf-detail-hero,.bp-detail-hero{position:relative;overflow:hidden}
.hp-hero::before,.hero::before,.page-hero::before,.pf-hero::before,.bl-hero::before,.kt-hero::before,.fq-hero::before,.bp-hero::before,.pf-detail-hero::before,.bp-detail-hero::before{z-index:0!important}
.theme-hero-video{position:absolute;inset:0;z-index:1;width:100%;height:100%;object-fit:cover;pointer-events:none;background:#050505;background-size:cover;background-position:center}
.theme-hero-video--embed{overflow:hidden;background-size:cover;background-position:center}
.theme-hero-video--embed iframe{position:absolute;top:50%;left:50%;width:120vw;height:67.5vw;min-width:213.33vh;min-height:120vh;transform:translate(-50%,-50%);border:0;pointer-events:none}
.theme-hero-video::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.62),rgba(0,0,0,.22),rgba(0,0,0,.58))}
.hp-hero>.container,.hero>.container,.page-hero>.container,.pf-hero>.container,.bl-hero>.container,.kt-hero>.container,.fq-hero>.container,.bp-hero>.container,.pf-detail-hero>.container,.bp-detail-hero>.container,.hero-content{position:relative;z-index:3}
</style>
HTML;

    if (str_contains($html, '</head>')) {
        return str_replace('</head>', "\n" . $style . "\n</head>", $html);
    }

    return $style . $html;
}

    public static function hasHeroVideoElement(string $html): bool
{
    return (bool) preg_match('/<[^>]+\bclass=(["\'])[^"\']*\btheme-hero-video\b[^"\']*\1/i', $html);
}
}
