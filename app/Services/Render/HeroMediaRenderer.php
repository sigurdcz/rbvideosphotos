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

    public static function buildHeroVideoHtml(string $videoUrl, string $posterUrl = '', bool $autoplay = false, string $tabletUrl = '', string $mobileUrl = ''): string
    {
        return self::buildResponsiveHeroVideoHtml(
            ['desktop' => $videoUrl, 'tablet' => $tabletUrl, 'mobile' => $mobileUrl],
            $posterUrl,
            $autoplay
        );
    }

    /**
     * Hero video with optional narrower-screen variants. The desktop URL is the one that
     * always renders and stays the fallback; a variant is emitted only when it resolves to
     * the same kind of source as the desktop one, so a Vimeo desktop video can never end up
     * with an .mp4 in the same attribute set. With both variants empty the markup is byte
     * for byte what it was before they existed.
     *
     * @param array{desktop?:string,tablet?:string,mobile?:string} $urls
     */
    public static function buildResponsiveHeroVideoHtml(array $urls, string $posterUrl = '', bool $autoplay = false): string
    {
        // Embed URL resolution stays here (logic); markup is app/Views/partials/hero-video.phtml.
        $desktop = self::resolveHeroVideoSource((string)($urls['desktop'] ?? ''));
        $variant = static function (string $url) use ($desktop): array {
            $resolved = self::resolveHeroVideoSource($url);
            if ($desktop['embed'] !== '') {
                return ['embed' => $resolved['embed'], 'direct' => ''];
            }
            if ($desktop['direct'] !== '') {
                return ['embed' => '', 'direct' => $resolved['direct']];
            }

            return ['embed' => '', 'direct' => ''];
        };
        $tablet = $variant((string)($urls['tablet'] ?? ''));
        $mobile = $variant((string)($urls['mobile'] ?? ''));

        return \App\View\ViewFactory::forTheme(null)->render('partials/hero-video', [
            'embedUrl' => $desktop['embed'],
            'directUrl' => $desktop['direct'],
            'posterUrl' => $posterUrl,
            'autoplay' => $autoplay,
            'tabletEmbedUrl' => $tablet['embed'],
            'mobileEmbedUrl' => $mobile['embed'],
            'tabletDirectUrl' => $tablet['direct'],
            'mobileDirectUrl' => $mobile['direct'],
        ]);
    }

    /**
     * Which trio of URLs the hero video uses: the page's own videos when the page has a
     * desktop one, otherwise the site defaults. Without this rule a page with its own
     * desktop video could inherit a mobile video belonging to a different one. Both the
     * live and the published render call this, so the two cannot drift apart.
     *
     * @return array{desktop:string,tablet:string,mobile:string}
     */
    public static function pickHeroVideoUrls(
        string $pageDesktop,
        string $pageTablet,
        string $pageMobile,
        string $siteDesktop,
        string $siteTablet,
        string $siteMobile
    ): array {
        if (trim($pageDesktop) !== '') {
            return ['desktop' => $pageDesktop, 'tablet' => $pageTablet, 'mobile' => $pageMobile];
        }

        return ['desktop' => $siteDesktop, 'tablet' => $siteTablet, 'mobile' => $siteMobile];
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
