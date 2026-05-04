<?php

namespace App\Services;

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

                while ($node->firstChild) {
                    $node->removeChild($node->firstChild);
                }
                // Use text node for plain text, fragment for HTML content
                if ($content === '' || $content === null) {
                    continue;
                }
                // Try as XML fragment first (for HTML content), fall back to text node
                $fragment = $dom->createDocumentFragment();
                // Encode non-ASCII as numeric entities so appendXML handles UTF-8 safely
                $encoded = mb_encode_numericentity($content, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
                $ok      = @$fragment->appendXML($encoded);
                if ($ok && $fragment->hasChildNodes()) {
                    $node->appendChild($fragment);
                } else {
                    $node->appendChild($dom->createTextNode($content));
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
        if ($css !== '') {
            $html = str_replace('</head>', "\n<style>\n{$css}\n</style>\n</head>", $html);
        }
        if ($js !== '') {
            $html = str_replace('</body>', "\n<script>\n{$js}\n</script>\n</body>", $html);
        }
        return $html;
    }
}
