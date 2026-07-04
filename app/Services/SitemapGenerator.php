<?php

namespace App\Services;

class SitemapGenerator
{
    public function generate(array $pages, string $domain, string $changefreq = 'monthly', string $priority = '0.8'): string
    {
        $domain = rtrim($domain, '/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($pages as $page) {
            $path    = trim((string)($page['url_path'] ?? ''), '/');
            $loc     = $this->buildUrl($domain, $path);
            $lastmod = date('Y-m-d', strtotime($page['published_at'] ?? $page['updated_at'] ?? 'now'));
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function buildUrl(string $domain, string $path): string
    {
        if ($path === '') {
            return $domain . '/';
        }

        return $domain . '/' . ltrim($path, '/');
    }
}
