<?php

namespace App\Services;

use App\Support\FrontendAssetManager;
use DOMDocument;
use DOMXPath;

class PageRenderer
{
    /**
     * Inject content blocks and media into template HTML.
     */
    public function render(string $templateHtml, array $blocks, array $mediaMap, bool $editMode = false): string
    {
        if (trim($templateHtml) === '') {
            return $templateHtml;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $templateHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // In edit mode: tag all data-block elements with inline-editor attributes
        if ($editMode) {
            $typeMap = [];
            foreach ($blocks as $b) {
                $typeMap[$b['block_key']] = $b['block_type'] ?? 'text';
            }
            foreach ($xpath->query('//*[@data-block]') as $node) {
                $k = $node->getAttribute('data-block');
                $node->setAttribute('data-inline-key', $k);
                $node->setAttribute('data-inline-type', $typeMap[$k] ?? 'text');
            }
        }

        // Replace data-block content
        foreach ($blocks as $block) {
            $key     = $block['block_key'];
            $content = $block['content'] ?? '';
            $nodes   = $xpath->query('//*[@data-block="' . $key . '"]');

            foreach ($nodes as $node) {
                // Image blocks: set src attribute directly
                if (($block['block_type'] ?? '') === 'image' && $node->nodeName === 'img') {
                    if ($content !== '') {
                        $node->setAttribute('src', $content);
                    }
                    continue;
                }

                $isVideoBlock = (($block['block_type'] ?? '') === 'video') || $key === 'hero_video';
                if ($isVideoBlock && $node->nodeName === 'video') {
                    $embedUrl = $this->buildVideoEmbedUrl((string)$content);
                    if ($embedUrl !== '') {
                        $wrapper = $dom->createElement('div');
                        $wrapper->setAttribute('class', trim(($node->getAttribute('class') ?: '') . ' theme-hero-video theme-hero-video--embed'));
                        $wrapper->setAttribute('aria-hidden', 'true');

                        $iframe = $dom->createElement('iframe');
                        $iframe->setAttribute('src', $embedUrl);
                        $iframe->setAttribute('title', 'Hero video');
                        $iframe->setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
                        $iframe->setAttribute('loading', 'eager');
                        $wrapper->appendChild($iframe);

                        if ($node->parentNode) {
                            $node->parentNode->replaceChild($wrapper, $node);
                        }
                        continue;
                    }

                    foreach (['autoplay' => 'autoplay', 'muted' => 'muted', 'loop' => 'loop', 'playsinline' => 'playsinline'] as $attr => $value) {
                        if (!$node->hasAttribute($attr)) {
                            $node->setAttribute($attr, $value);
                        }
                    }
                    if (!$node->hasAttribute('preload')) {
                        $node->setAttribute('preload', 'metadata');
                    }
                    if ($content !== '' && !preg_match('~^https?://~i', (string)$content)) {
                        $node->setAttribute('src', $content);
                    }
                    continue;
                }

                while ($node->firstChild) {
                    $node->removeChild($node->firstChild);
                }
                if ($content === '' || $content === null) {
                    continue;
                }

                if (($block['block_type'] ?? 'text') === 'html') {
                    // HTML blocks: parse via a temp DOMDocument so &nbsp; and all
                    // named HTML entities are handled correctly (appendXML rejects them
                    // because they are not valid XML entity references).
                    $tmpDoc = new DOMDocument('1.0', 'UTF-8');
                    libxml_use_internal_errors(true);
                    $tmpDoc->loadHTML(
                        '<?xml encoding="UTF-8"><html><body>' . $content . '</body></html>',
                        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
                    );
                    libxml_clear_errors();
                    $body = $tmpDoc->getElementsByTagName('body')->item(0);
                    if ($body) {
                        foreach (iterator_to_array($body->childNodes) as $child) {
                            $node->appendChild($dom->importNode($child, true));
                        }
                    }
                } else {
                    // text / textarea / link — plain text, encode non-ASCII for XML safety
                    $encoded  = mb_encode_numericentity($content, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
                    $fragment = $dom->createDocumentFragment();
                    $ok       = @$fragment->appendXML($encoded);
                    if ($ok && $fragment->hasChildNodes()) {
                        $node->appendChild($fragment);
                    } else {
                        $node->appendChild($dom->createTextNode($content));
                    }
                }
            }
        }

        // Replace data-media src
        foreach ($mediaMap as $key => $mediaInfo) {
            $nodes = $xpath->query('//*[@data-media="' . $key . '"]');
            foreach ($nodes as $node) {
                if ($node->tagName === 'img') {
                    $node->setAttribute('src', $mediaInfo['url']);
                    if (!empty($mediaInfo['alt'])) {
                        $node->setAttribute('alt', $mediaInfo['alt']);
                    }
                } elseif ($node->tagName === 'video') {
                    $node->setAttribute('poster', $mediaInfo['url']);
                }
            }
        }

        $html = $dom->saveHTML();
        $html = preg_replace('/^<\?xml[^?]*\?>\s*/i', '', $html);
        // Remove leftover data-block and data-media attributes from output
        $html = preg_replace('/ data-block="[^"]*"/', '', $html);
        $html = preg_replace('/ data-media="[^"]*"/', '', $html);

        return $html;
    }

    /**
     * Remove inline style blocks from rendered HTML so active themes can fully control presentation.
     */
    public function stripInlineStyles(string $html): string
    {
        return preg_replace('~<style\b[^>]*>.*?</style>~is', '', $html) ?? $html;
    }

    /**
     * Wrap rendered page content with the site-level layout.
     *
     * Supported placeholders in $layoutHtml:
     *   {page_content} — replaced with $content
     *   {site_name}    — replaced with escaped site name
     *   {nav}          — replaced with $nav HTML (pass '' to leave unreplaced)
     */
    public function applyLayout(string $layoutHtml, string $content, string $siteName, string $nav = ''): string
    {
        $safeName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
        $result = str_replace('{page_content}', $content, $layoutHtml);
        $result = str_replace('{nav}', $nav, $result);
        $result = str_replace('{site_name}', $safeName, $result);
        return $result;
    }

    /**
     * Inject SEO tags into <head> and body scripts before </body>.
     */
    public function injectSeo(string $html, string $seoHead, string $bodyScripts): string
    {
        // Insert before </head>
        if ($seoHead !== '') {
            $html = str_replace('</head>', "\n" . $seoHead . "\n</head>", $html);
        }
        // Insert before </body>
        if ($bodyScripts !== '') {
            $html = str_replace('</body>', "\n" . $bodyScripts . "\n</body>", $html);
        }
        return $html;
    }

    /**
     * Inject CSS and JS from template.
     */
    public function injectAssets(string $html, string $css, string $js): string
    {
        return FrontendAssetManager::injectInlineAssets($html, $css, $js);
    }

    private function buildVideoEmbedUrl(string $videoUrl): string
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

        return $youtubeId !== ''
            ? 'https://www.youtube.com/embed/' . $youtubeId . '?autoplay=1&mute=1&controls=0&loop=1&playlist=' . $youtubeId . '&playsinline=1&modestbranding=1&rel=0'
            : '';
    }
}
