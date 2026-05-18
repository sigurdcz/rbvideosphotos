<?php

namespace App\Services;

/**
 * Generates /llms.txt following the llmstxt.org specification.
 *
 * The file is a Markdown map of the site's most important resources
 * for large language models — analogous to robots.txt for crawlers.
 *
 * Spec: https://llmstxt.org/
 */
class LlmsTxtGenerator
{
    public function generate(array $site, array $pages): string
    {
        $domain      = rtrim((string)($site['domain'] ?? ''), '/');
        $siteName    = (string)($site['name'] ?? 'Web');
        $description = (string)($site['meta_description'] ?? $site['description'] ?? '');

        $lines   = [];
        $lines[] = '# ' . $siteName;
        $lines[] = '';

        if ($description !== '') {
            $lines[] = '> ' . $description;
        } else {
            $lines[] = '> Webové stránky ' . $siteName . '.';
        }

        $lines[] = '';
        $lines[] = '## Stránky';
        $lines[] = '';

        // Sort: homepage first, then by URL path
        usort($pages, static function (array $a, array $b): int {
            $ap = ltrim((string)($a['url_path'] ?? ''), '/');
            $bp = ltrim((string)($b['url_path'] ?? ''), '/');
            if ($ap === '' && $bp !== '') return -1;
            if ($ap !== '' && $bp === '') return 1;
            return strcmp($ap, $bp);
        });

        foreach ($pages as $page) {
            $path  = ltrim((string)($page['url_path'] ?? ''), '/');
            $url   = $path === '' ? $domain . '/' : $domain . '/' . $path . '/';
            $title = (string)($page['title'] ?? $page['url_path'] ?? $url);

            $meta  = (string)($page['meta_description'] ?? $page['description'] ?? '');
            $entry = '- [' . $title . '](' . $url . ')';
            if ($meta !== '') {
                $entry .= ': ' . $meta;
            }
            $lines[] = $entry;
        }

        $lines[] = '';
        $lines[] = '## Volitelné';
        $lines[] = '';
        $lines[] = '- [Sitemap](' . $domain . '/sitemap.xml): XML sitemap — přehled všech URL stránek';

        return implode("\n", $lines) . "\n";
    }
}
