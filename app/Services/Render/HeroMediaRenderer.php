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
        // Rendered by app/Views/partials/hero.phtml (empty imageUrl => empty output).
        return \App\View\ViewFactory::forTheme(null)->render('partials/hero', [
            'imageUrl' => $imageUrl,
        ]);
    }

    public static function buildHeroVideoHtml(string $videoUrl, string $posterUrl = '', bool $autoplay = false, string $tabletUrl = '', string $mobileUrl = '', array $ratios = [], string $videoPosterUrl = ''): string
    {
        return self::buildResponsiveHeroVideoHtml(
            ['desktop' => $videoUrl, 'tablet' => $tabletUrl, 'mobile' => $mobileUrl],
            $posterUrl,
            $autoplay,
            $ratios,
            $videoPosterUrl
        );
    }

    /**
     * Hero video with an independent source per screen width. A width whose field is empty
     * has no video at all: nothing is inherited from another width, and the browser shows
     * only the poster there. Every width keeps its own kind of source, so Vimeo on desktop
     * and a plain file on mobile work together: such a mixed set renders both players, each
     * tagged with the widths it serves, and a small stylesheet shows only the one that has a
     * video at the current width.
     *
     * $ratios (width => "W / H") lets the page reserve the video's box before its metadata arrives;
     * $videoPosterUrl is a lighter poster used only by the plain <video> element.
     *
     * @param array{desktop?:string,tablet?:string,mobile?:string} $urls
     * @param array<string,string> $ratios
     */
    public static function buildResponsiveHeroVideoHtml(array $urls, string $posterUrl = '', bool $autoplay = false, array $ratios = [], string $videoPosterUrl = ''): string
    {
        // Embed URL resolution stays here (logic); markup is app/Views/partials/hero-video.phtml.
        $sources = [
            'desktop' => self::resolveHeroVideoSource((string)($urls['desktop'] ?? '')),
            'tablet' => self::resolveHeroVideoSource((string)($urls['tablet'] ?? '')),
            'mobile' => self::resolveHeroVideoSource((string)($urls['mobile'] ?? '')),
        ];
        $kinds = [];
        foreach (['embed', 'direct'] as $kind) {
            foreach ($sources as $source) {
                if ($source[$kind] !== '') {
                    $kinds[] = $kind;
                    break;
                }
            }
        }
        if ($kinds === []) {
            return '';
        }
        $mixed = count($kinds) > 1;

        $html = '';
        foreach ($kinds as $kind) {
            $pick = static fn(string $width): string => $sources[$width][$kind];
            $embed = $kind === 'embed';
            $html .= \App\View\ViewFactory::forTheme(null)->render('partials/hero-video', [
                'embedUrl' => $embed ? $pick('desktop') : '',
                'directUrl' => $embed ? '' : $pick('desktop'),
                'kind' => $kind,
                'posterUrl' => $posterUrl,
                'videoPosterUrl' => $videoPosterUrl,
                'autoplay' => $autoplay,
                'tabletEmbedUrl' => $embed ? $pick('tablet') : '',
                'mobileEmbedUrl' => $embed ? $pick('mobile') : '',
                'tabletDirectUrl' => $embed ? '' : $pick('tablet'),
                'mobileDirectUrl' => $embed ? '' : $pick('mobile'),
                'widths' => $mixed
                    ? array_keys(array_filter($sources, static fn(array $source): bool => $source[$kind] !== ''))
                    : [],
            ]);
        }

        $style = ($mixed ? self::mixedWidthsStyle() : '') . (in_array('direct', $kinds, true) ? self::ratioStyle($sources, $ratios) : '');

        return $style . $html;
    }

    /**
     * Reserves the plain video's box per width from the stored dimensions. "auto W / H" uses the
     * file's own ratio once known and this one before that, so nothing moves when it arrives.
     *
     * @param array<string,array{embed:string,direct:string}> $sources
     * @param array<string,string> $ratios
     */
    private static function ratioStyle(array $sources, array $ratios): string
    {
        $queries = [
            'mobile' => '(max-width:700px)',
            'tablet' => '(min-width:700.02px) and (max-width:1050px)',
            'desktop' => '(min-width:1050.02px)',
        ];
        $rules = '';
        foreach ($queries as $width => $query) {
            $ratio = (string)($ratios[$width] ?? '');
            if (($sources[$width]['direct'] ?? '') === '' || !preg_match('~^\d{1,5} / \d{1,5}$~', $ratio)) {
                continue;
            }
            $rules .= '@media ' . $query . '{video.theme-hero-video--native{aspect-ratio:auto ' . $ratio . '}}';
        }

        return $rules === '' ? '' : '<style data-hero-video-ratio>' . $rules . '</style>';
    }

    /** Shows only the player that has a video at the current width (mixed Vimeo/file sets). */
    private static function mixedWidthsStyle(): string
    {
        return '<style data-hero-video-widths>'
            . '@media (max-width:700px){.theme-hero-video[data-hero-widths]:not([data-hero-widths~="mobile"]){display:none}}'
            . '@media (min-width:700.02px) and (max-width:1050px){.theme-hero-video[data-hero-widths]:not([data-hero-widths~="tablet"]){display:none}}'
            . '@media (min-width:1050.02px){.theme-hero-video[data-hero-widths]:not([data-hero-widths~="desktop"]){display:none}}'
            . '</style>';
    }

    /**
     * Normalises one set of three hero video fields (the page's own, or the site default
     * from Settings). Inside a set there is no fallback between widths: an empty field
     * means no video at that width. Which set wins (page first, then the site default) is
     * decided by the callers, live and published alike, in the same order.
     *
     * @return array{desktop:string,tablet:string,mobile:string}
     */
    public static function pickHeroVideoUrls(string $pageDesktop, string $pageTablet, string $pageMobile): array
    {
        return [
            'desktop' => trim($pageDesktop),
            'tablet' => trim($pageTablet),
            'mobile' => trim($pageMobile),
        ];
    }

    public static function hasHeroVideo(array $urls): bool
    {
        foreach (['desktop', 'tablet', 'mobile'] as $width) {
            $source = self::resolveHeroVideoSource((string)($urls[$width] ?? ''));
            if ($source['embed'] !== '' || $source['direct'] !== '') {
                return true;
            }
        }

        return false;
    }

    /** @return array{embed:string,direct:string} */
    private static function resolveHeroVideoSource(string $videoUrl): array
    {
        $embed = self::buildVideoEmbedUrl($videoUrl);
        $direct = $embed === '' && preg_match('~\.(?:mp4|webm|mov)(?:\?.*)?$~i', trim($videoUrl))
            ? trim($videoUrl)
            : '';

        return ['embed' => $embed, 'direct' => $direct];
    }

    public static function buildVideoEmbedUrl(string $videoUrl): string
{
    $url = trim($videoUrl);
    if ($url === '') {
        return '';
    }

    if (preg_match('~vimeo\.com/(?:video/)?([0-9]+)~i', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1] . '?background=1&autoplay=1&loop=1&muted=1&playsinline=1&dnt=1';
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
        return 'https://www.youtube-nocookie.com/embed/' . $youtubeId . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . $youtubeId . '&playsinline=1&modestbranding=1&rel=0&iv_load_policy=3&disablekb=1';
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
.theme-hero-video--facade{pointer-events:none}
.theme-hero-video__poster{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.theme-hero-video__play{position:absolute;top:50%;left:50%;z-index:3;display:grid;width:56px;height:56px;padding:0;place-items:center;border:1px solid rgba(255,255,255,.78);border-radius:50%;background:rgba(5,5,5,.72);color:#fff;cursor:pointer;pointer-events:auto;transform:translate(-50%,-50%)}
.theme-hero-video__play:focus-visible{outline:3px solid #fff;outline-offset:4px}
.theme-hero-video::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.62),rgba(0,0,0,.22),rgba(0,0,0,.58))}
.hp-hero .theme-hero-video::after{content:none}
.hp-hero>.container,.hero>.container,.page-hero>.container,.pf-hero>.container,.bl-hero>.container,.kt-hero>.container,.fq-hero>.container,.bp-hero>.container,.pf-detail-hero>.container,.bp-detail-hero>.container,.hero-content{position:relative;z-index:3;pointer-events:none}
.hp-hero>.container a,.hp-hero>.container button,.hp-hero>.container input,.hp-hero>.container select,.hp-hero>.container textarea,.hero>.container a,.hero>.container button,.hero>.container input,.hero>.container select,.hero>.container textarea,.page-hero>.container a,.page-hero>.container button,.hero-content a,.hero-content button{pointer-events:auto}
</style>
HTML;

    if (str_contains($html, '</head>')) {
        return str_replace('</head>', "\n" . $style . "\n</head>", $html);
    }

    return $style . $html;
}

    public static function hasHeroVideoElement(string $html): bool
{
    // The class has to be a WHOLE token. \b was not enough: it also matches inside
    // "theme-hero-video--sourceless", between the o and the hyphen, so an empty video
    // marked as sourceless was reported as an existing hero and the real one was never
    // injected - the homepage lost its video and its LCP poster.
    if (!preg_match_all('/<[^>]+\bclass=(["\'])([^"\']*)\1/i', $html, $matches)) {
        return false;
    }
    foreach ($matches[2] as $classAttribute) {
        if (in_array('theme-hero-video', preg_split('/\s+/', trim($classAttribute)) ?: [], true)) {
            return true;
        }
    }

    return false;
}
}
