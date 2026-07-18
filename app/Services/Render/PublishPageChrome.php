<?php

declare(strict_types=1);

namespace App\Services\Render;

/**
 * Publish-mode page chrome injections that remain intentionally divergent from
 * the live render path in phase 2 (logo, page hero image/video, video overlay
 * styles, editorial-gold overrides).
 *
 * These are NOT duplicated helpers — the live path has different, richer
 * implementations (webp <picture>, optimized media resolution, full overlay
 * CSS). Unifying them is scheduled for phases 3/6 (layout partials + theme
 * hack migration). Until then PublishService owns the publish variants and
 * exposes them to PageComposer through this contract so the publish cascade
 * has a single orchestration home.
 */
interface PublishPageChrome
{
    public function injectSiteLogo(string $html, array $site): string;

    public function injectPageHeroImage(string $html, int $pageId, array $site = []): string;

    public function injectPageHeroVideo(string $html, array $site, int $pageId): string;

    public function injectGlobalVideoOverlayStyles(string $html, array $site): string;

    public function injectEditorialGoldOverrides(string $html): string;
}
