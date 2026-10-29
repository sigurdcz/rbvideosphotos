<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Services\ThemeTemplateRegistry;

/** Place the DB photograph in its final layout before the browser runs theme JS. */
final class GoldenBashAboutRenderer
{
    public static function compose(string $html, bool $isHome, ?string $themeKey, bool $editMode = false): string
    {
        if (!$isHome || $editMode || !ThemeTemplateRegistry::isGoldenBashFamily($themeKey)
            || str_contains($html, 'data-about-visual-ready="1"')) {
            return $html;
        }
        if (!preg_match('~<img\b(?=[^>]*\bclass=(["\'])[^"\']*\bjb-about-visual__source\b[^"\']*\1)[^>]*>~i', $html, $imageMatch)
            || !preg_match('~<section\b(?=[^>]*\bid=(["\'])sluzby\1)[^>]*>.*?</section>~is', $html, $sectionMatch)) {
            // No DB image/section to compose; existing theme diagnostics remain authoritative.
            return $html;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div id="about-fragment">' . $imageMatch[0] . $sectionMatch[0] . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) throw new \RuntimeException('Cannot parse Golden Bash about layout.');
        $xpath = new \DOMXPath($dom);
        $section = $xpath->query('//section[@id="sluzby"]')->item(0);
        $container = $xpath->query('//section[@id="sluzby"]/*[contains(concat(" ", normalize-space(@class), " "), " container ")]')->item(0);
        $image = $xpath->query('//div[@id="about-fragment"]/img')->item(0);
        if (!$section instanceof \DOMElement || !$container instanceof \DOMElement || !$image instanceof \DOMElement) {
            throw new \RuntimeException('Golden Bash about layout is missing its image or container.');
        }
        $section->setAttribute('class', trim($section->getAttribute('class') . ' gb-services gb-services-profile'));
        $section->setAttribute('data-about-visual-ready', '1');
        $image->removeAttribute('hidden');
        $image->setAttribute('class', 'jb-about-visual__source gb-services-visual__image');
        $image->setAttribute('data-eager-image', '');
        $image->setAttribute('data-mobile-lcp', '');

        $visual = $dom->createElement('figure');
        $visual->setAttribute('class', 'gb-services-visual');
        $visual->appendChild($image);
        $copy = $dom->createElement('div');
        $copy->setAttribute('class', 'gb-services-copy');
        while ($container->firstChild !== null) $copy->appendChild($container->firstChild);
        $container->appendChild($visual);
        $container->appendChild($copy);
        $composed = $dom->saveHTML($section);
        if ($composed === false) throw new \RuntimeException('Cannot serialize Golden Bash about layout.');

        $html = str_replace($imageMatch[0], '', $html);
        return str_replace($sectionMatch[0], $composed, $html);
    }
}
