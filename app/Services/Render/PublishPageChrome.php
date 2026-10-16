<?php

declare(strict_types=1);

namespace App\Services\Render;

/**
 * Publish-mode page chrome injections that remain intentionally divergent from
 * the live render path in phase 2 (logo, page hero image/video, video overlay
 * styles).
 *
 * These are NOT duplicated helpers - the live path has different, richer
 * implementations (webp <picture>, optimized media resolution, full overlay
 * CSS). Unifying them is scheduled for phases 3/6 (layout partials + theme
 * hack migration). Until then PublishService owns the publish variants and
 * exposes them to PageComposer through this contract so the publish cascade
 * has a single orchestration home.
 *
 * Phase 6: the former injectEditorialGoldOverrides() left this contract - the
 * editorial-gold CTA fixes now live in Templates/editorial-gold/assets/overrides.css
 * (a normal theme asset), removing the theme-specific branch from core.
 */
interface PublishPageChrome
{
    public function injectSiteLogo(string $html, array $site): string;

    public function injectPageHeroImage(string $html, int $pageId, array $site = []): string;

    public function injectPageHeroVideo(string $html, array $site, int $pageId): string;

    public function injectGlobalVideoOverlayStyles(string $html, array $site): string;
}
