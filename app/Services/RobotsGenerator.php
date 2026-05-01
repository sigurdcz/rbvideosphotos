<?php

namespace App\Services;

class RobotsGenerator
{
    public function generate(string $domain): string
    {
        $domain = rtrim($domain, '/');
        return "User-agent: *\nAllow: /\n\nSitemap: {$domain}/sitemap.xml\n";
    }
}
