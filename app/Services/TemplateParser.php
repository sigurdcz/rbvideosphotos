<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class TemplateParser
{
    /**
     * Parse HTML template and return discovered blocks.
     * Returns array of ['key' => '...', 'type' => '...', 'label' => '...', 'default_content' => '...']
     */
    public function parseBlocks(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = $this->loadDom($html);
        $xpath = new DOMXPath($dom);
        $blocks = [];
        $order = 0;

        // data-block elements
        $nodes = $xpath->query('//*[@data-block]');
        foreach ($nodes as $node) {
            $key = $node->getAttribute('data-block');
            if ($key === '') continue;

            $tag  = strtolower($node->tagName);
            $type = $this->guessType($tag, $node);

            $blocks[$key] = [
                'block_key'  => $key,
                'block_type' => $type,
                'label'      => ucfirst(str_replace('_', ' ', $key)),
                'content'    => $dom->saveHTML($node->firstChild) !== false
                    ? $this->innerHtml($dom, $node)
                    : '',
                'sort_order' => $order++,
            ];
        }

        // data-media elements (image blocks)
        $mediaNodes = $xpath->query('//*[@data-media]');
        foreach ($mediaNodes as $node) {
            $key = $node->getAttribute('data-media');
            if ($key === '' || isset($blocks[$key])) continue;

            $blocks[$key] = [
                'block_key'  => $key,
                'block_type' => 'image',
                'label'      => ucfirst(str_replace('_', ' ', $key)),
                'content'    => $node->getAttribute('src') ?: '',
                'sort_order' => $order++,
            ];
        }

        return array_values($blocks);
    }

    private function guessType(string $tag, \DOMElement $node): string
    {
        if ($tag === 'img') return 'image';
        if ($tag === 'a')   return 'link';
        if (in_array($tag, ['div','section','article'])) return 'html';
        if (in_array($tag, ['textarea','p'])) return 'textarea';
        return 'text';
    }

    private function loadDom(string $html): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return $dom;
    }

    private function innerHtml(DOMDocument $dom, \DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }
        return trim($html);
    }
}
