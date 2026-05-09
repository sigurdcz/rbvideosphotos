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
        if ($css !== '') {
            $html = str_replace('</head>', "\n<style>\n{$css}\n</style>\n</head>", $html);
        }
        if ($js !== '') {
            $html = str_replace('</body>', "\n<script>\n{$js}\n</script>\n</body>", $html);
        }
        return $html;
    }
}
