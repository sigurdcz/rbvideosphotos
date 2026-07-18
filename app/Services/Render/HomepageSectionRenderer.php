<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Core\Logger;
use App\Repositories\BlockRepository;
use App\Services\HomepageContentService;

/**
 * Canonical homepage section autofill / data-source injection.
 *
 * Extracted in phase 2 of the templating refactor as the single source of truth
 * shared by the live render path (public/index.php) and the publish path
 * (App\Services\PublishService). Do not fork these implementations.
 */
final class HomepageSectionRenderer
{
    public static function blockContent(array $blocks, string $key): string
{
    foreach ($blocks as $block) {
        if (($block['block_key'] ?? '') === $key) {
            return trim((string)($block['content'] ?? ''));
        }
    }

    return '';
}

    public static function injectHomepageMissingSections(string $html, array $blocks, bool $isHome): string
{
    if (!$isHome) {
        return $html;
    }

    $sections = '';
    if (!preg_match('~<section\b[^>]*\bid=(["\'])cenik\1~i', $html)) {
        $pricingBody = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'pricing_body') ?: \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'cenik_body');
        if ($pricingBody !== '') {
            $pricingTitle = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'pricing_title') ?: \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'cenik_title') ?: 'Cenik';
            $pricingIntro = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'pricing_intro') ?: \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'cenik_intro');
            $pricingCta = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'pricing_cta') ?: \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'cenik_cta');
            $sections .= '<section class="section ew-pricing hp-pricing" id="cenik"><div class="container">'
                . '<p class="section-label">Cenik</p>'
                . '<h2 class="section-title">' . $pricingTitle . '</h2>'
                . ($pricingIntro !== '' ? '<p class="section-intro">' . $pricingIntro . '</p>' : '')
                . $pricingBody
                . $pricingCta
                . '</div></section>';
        }
    }

    if (!preg_match('~<section\b[^>]*\bid=(["\'])blog\1~i', $html)) {
        $blogCards = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'blog_1') . \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'blog_2') . \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'blog_3');
        if (trim($blogCards) !== '') {
            $blogTitle = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'blog_title') ?: 'Blog a clanky';
            $blogIntro = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'blog_intro');
            $blogCta = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'blog_cta');
            $sections .= '<section class="section ew-blog hp-blog-section" id="blog"><div class="container">'
                . '<p class="section-label">Blog</p>'
                . '<h2 class="section-title">' . $blogTitle . '</h2>'
                . ($blogIntro !== '' ? '<p class="section-intro">' . $blogIntro . '</p>' : '')
                . '<div class="hp-blog-grid">' . $blogCards . '</div>'
                . $blogCta
                . '</div></section>';
        }
    }

    if (preg_match('~\btheme-elegant-white2?\b~', $html) && !preg_match('~<section\b[^>]*\bid=(["\'])faq\1~i', $html)) {
        $faqItems = '';
        for ($i = 1; $i <= 4; $i++) {
            $faqItem = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'faq_' . $i);
            if (trim(strip_tags($faqItem)) !== '') {
                $faqItems .= '<article class="hp-faq-item">' . $faqItem . '</article>';
            }
        }
        if ($faqItems !== '') {
            $faqTitle = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'faq_title') ?: 'Časté dotazy';
            $faqCta = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'faq_cta');
            $sections .= '<section class="section" id="faq"><div class="container">'
                . '<p class="section-label">Otázky</p>'
                . '<h2 class="section-title">' . $faqTitle . '</h2>'
                . '<div class="hp-faq">' . $faqItems . '</div>'
                . $faqCta
                . '</div></section>';
        }
    }

    if (!preg_match('~<section\b[^>]*\bid=(["\'])proces\1~i', $html)) {
        $processBody = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'process_body');
        if ($processBody === '') {
            $processBody = '<div class="ew-process-list">'
                . '<article><span>01</span><i class="ew-process-icon ew-process-icon--form"></i><h3>Nezavazna poptavka</h3><p>Ozvete se nam a probereme vase predstavy a pozadavky.</p></article>'
                . '<article><span>02</span><i class="ew-process-icon ew-process-icon--plan"></i><h3>Konzultace s planem</h3><p>Spolecne naplanujeme prubeh a detaily.</p></article>'
                . '<article><span>03</span><i class="ew-process-icon ew-process-icon--camera"></i><h3>Foceni &amp; nataceni</h3><p>Zachytime prirozene momenty bez zbytecnych zasahu.</p></article>'
                . '<article><span>04</span><i class="ew-process-icon ew-process-icon--edit"></i><h3>Upravy &amp; vyber</h3><p>Peclive upravime a pripravime ty nejlepsi zabery.</p></article>'
                . '<article><span>05</span><i class="ew-process-icon ew-process-icon--send"></i><h3>Dodani &amp; sdileni</h3><p>Predame hotove fotografie a video v nejvyssi kvalite.</p></article>'
                . '</div>';
        }
        $processLabel = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'process_label') ?: 'Jak to probiha';
        $processTitle = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'process_title') ?: 'Jednoduchy proces, skvely vysledek';
        $processIntro = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'process_intro');
        $sections .= '<section class="section hp-process" id="proces"><div class="container">'
            . '<p class="section-label">' . $processLabel . '</p>'
            . '<h2 class="section-title">' . $processTitle . '</h2>'
            . ($processIntro !== '' ? '<p class="section-intro">' . $processIntro . '</p>' : '')
            . $processBody
            . '</div></section>';
    }

    if ($sections === '') {
        return $html;
    }

    $inserted = preg_replace('~(<section\b[^>]*\bid=(["\'])kontakt\2[^>]*>)~i', $sections . '$1', $html, 1, $count);
    if ($count > 0 && is_string($inserted)) {
        return $inserted;
    }

    return preg_replace('~(</main>)~i', $sections . '$1', $html, 1) ?? $html;
}

    public static function injectHomepageThemeDataSources(string $html, array $blocks, bool $isHome): string
{
    if (!$isHome) {
        return $html;
    }

    if (preg_match('~\btheme-bezova-lens-tale\b~', $html) && !str_contains($html, 'data-blt-home-sources')) {
        $faqItems = '';
        for ($i = 1; $i <= 5; $i++) {
            $faqItem = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'faq_' . $i);
            if (trim(strip_tags($faqItem)) !== '') {
                $faqItems .= '<article class="hp-faq-item">' . $faqItem . '</article>';
            }
        }

        $contact = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'contact_info') ?: \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'kontakt_info');
        $source = '<div data-blt-home-sources hidden>'
            . '<div data-blt-contact-source>' . $contact . '</div>'
            . '<div data-blt-faq-source>' . $faqItems . '</div>'
            . '</div>';

        return preg_replace('~(<main\b[^>]*>)~i', '$1' . $source, $html, 1) ?? $html;
    }

    if (!preg_match('~\btheme-elegant-white2?\b~', $html) || str_contains($html, 'data-ew-home-faq-source')) {
        return $html;
    }

    $faqItem = \App\Services\Render\HomepageSectionRenderer::blockContent($blocks, 'faq_4');
    if (trim(strip_tags($faqItem)) === '') {
        return $html;
    }

    $source = '<div data-ew-home-faq-source hidden><article class="hp-faq-item">'
        . $faqItem
        . '</article></div>';

    return preg_replace('~(<main\b[^>]*>)~i', '$1' . $source, $html, 1) ?? $html;
}

    public static function injectHomepageAboutVisualSource(string $html, int $pageId, int $siteId, bool $isHome): string
{
    if (!$isHome) {
        return $html;
    }

    try {
        $block = (new BlockRepository())->findByKey($pageId, 'jb-about-visual__media');
        $url = $block ? (new HomepageContentService())->resolveOptimizedMediaUrl($siteId, (string)($block['content'] ?? ''), 102400) : '';
        if ($url === '') {
            $url = \App\Services\Render\MediaUrlResolver::resolveDemoMediaUrlByFilename($siteId, 'weare2.png');
        }
    } catch (\Throwable $e) {
        Logger::infoTo('theme-debug', 'theme.about.visual.error', 'Failed to resolve homepage about visual', [
            'page_id' => $pageId,
            'error' => $e->getMessage(),
        ]);
        return $html;
    }

    $sourcePattern = '~<(?:img|span)\b[^>]*\bclass=(["\'])[^"\']*\bjb-about-visual__source\b[^"\']*\1[^>]*(?:>.*?</span>|>)~is';
    if ($url === '') {
        return preg_replace($sourcePattern, '', $html) ?? $html;
    }

    $source = '<span class="jb-about-visual__source" data-src="' . htmlspecialchars($url, ENT_QUOTES) . '" hidden></span>';
    $updated = preg_replace($sourcePattern, $source, $html, 1, $count);
    if ($count > 0 && is_string($updated)) {
        return $updated;
    }

    $html = preg_replace('/(<main\b[^>]*>)/i', '$1' . $source, $html, 1, $count) ?? $html;
    if ($count > 0) {
        return $html;
    }

    return preg_replace('/(<body\b[^>]*>)/i', '$1' . $source, $html, 1) ?? $html;
}
}
