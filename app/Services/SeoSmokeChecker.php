<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMXPath;

final class SeoSmokeChecker
{
    /** @var callable(string): array{status:int,body:string,headers:array<string,string>} */
    private $fetch;

    /** @param callable(string): array{status:int,body:string,headers:array<string,string>} $fetch */
    public function __construct(callable $fetch)
    {
        $this->fetch = $fetch;
    }

    /**
     * @param list<string> $requiredPaths
     * @return array{ok:bool,checked:int,errors:list<string>}
     */
    public function check(string $baseUrl, array $requiredPaths): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $errors = [];
        $checked = [];
        $internalLinks = [];

        foreach ($requiredPaths as $path) {
            $url = $this->url($baseUrl, $path);
            $response = $this->fetchOnce($url, $checked);
            $this->checkPage($url, $response, $errors);
            $this->collectInternalLinks($baseUrl, $url, $response, $internalLinks);
        }

        $sitemapUrl = $baseUrl . '/sitemap.xml';
        $sitemap = $this->fetchOnce($sitemapUrl, $checked);
        $sitemapUrls = $this->parseSitemap($sitemapUrl, $sitemap, $errors);
        $sitemapSet = array_fill_keys($sitemapUrls, true);

        foreach ($requiredPaths as $path) {
            $url = $this->url($baseUrl, $path);
            if (!isset($sitemapSet[$url])) {
                $errors[] = $url . ': missing from sitemap.xml';
            }
        }

        foreach ($sitemapUrls as $url) {
            if (!$this->sameOrigin($baseUrl, $url)) {
                $errors[] = $url . ': sitemap URL is outside the canonical origin';
                continue;
            }
            $response = $this->fetchOnce($url, $checked);
            $this->checkPage($url, $response, $errors);
            $this->collectInternalLinks($baseUrl, $url, $response, $internalLinks);
        }

        foreach (array_keys($internalLinks) as $url) {
            $response = $this->fetchOnce($url, $checked);
            if ($response['status'] >= 400 || $response['status'] === 0) {
                $errors[] = $url . ': internal link returned HTTP ' . $response['status'];
            }
        }

        return ['ok' => $errors === [], 'checked' => count($checked), 'errors' => array_values(array_unique($errors))];
    }

    /** @param array<string,array{status:int,body:string,headers:array<string,string>}> $cache */
    private function fetchOnce(string $url, array &$cache): array
    {
        if (!isset($cache[$url])) {
            $cache[$url] = ($this->fetch)($url);
        }
        return $cache[$url];
    }

    /** @param array{status:int,body:string,headers:array<string,string>} $response @param list<string> $errors */
    private function checkPage(string $url, array $response, array &$errors): void
    {
        if ($response['status'] !== 200) {
            $errors[] = $url . ': expected HTTP 200, got ' . $response['status'];
            return;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $response['body']);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            $errors[] = $url . ': HTML cannot be parsed';
            return;
        }

        $xpath = new DOMXPath($dom);
        $this->requireOneNonEmpty($xpath, '//title', 'title', $url, $errors);
        $this->requireOneNonEmpty($xpath, '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]/@content', 'meta description', $url, $errors);
        $this->requireOneNonEmpty($xpath, '//h1', 'H1', $url, $errors);
        $this->requireOneNonEmpty($xpath, '//meta[@property="og:title"]/@content', 'og:title', $url, $errors);
        $this->requireOneNonEmpty($xpath, '//meta[@property="og:description"]/@content', 'og:description', $url, $errors);
        $this->requireOneNonEmpty($xpath, '//meta[@property="og:url"]/@content', 'og:url', $url, $errors);
        $this->requireOneNonEmpty($xpath, '//meta[@name="twitter:card"]/@content', 'twitter:card', $url, $errors);
        $this->requireOneNonEmpty($xpath, '//meta[@name="twitter:title"]/@content', 'twitter:title', $url, $errors);
        if (!str_contains($response['body'], 'cms-tracking-config')) {
            $errors[] = $url . ': tracking runtime marker is missing';
        }

        $robots = strtolower(trim($this->firstValue($xpath, '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="robots"]/@content')));
        if ($robots === '' || str_contains($robots, 'noindex') || !str_contains($robots, 'index')) {
            $errors[] = $url . ': expected explicit index robots directive, got ' . ($robots === '' ? '(missing)' : $robots);
        }

        $canonical = rtrim($this->firstValue($xpath, '//link[translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="canonical"]/@href'), '/');
        if ($canonical !== rtrim($url, '/')) {
            $errors[] = $url . ': self-canonical mismatch (' . ($canonical === '' ? 'missing' : $canonical) . ')';
        }

        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $node) {
            $json = trim((string) $node->textContent);
            if ($json === '' || json_decode($json, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = $url . ': invalid JSON-LD (' . json_last_error_msg() . ')';
            }
        }
    }

    /** @param list<string> $errors */
    private function requireOneNonEmpty(DOMXPath $xpath, string $query, string $label, string $url, array &$errors): void
    {
        $nodes = $xpath->query($query);
        $count = $nodes === false ? 0 : $nodes->length;
        if ($count !== 1) {
            $errors[] = $url . ': expected exactly one ' . $label . ', found ' . $count;
            return;
        }
        if (trim((string) $nodes->item(0)?->textContent) === '') {
            $errors[] = $url . ': empty ' . $label;
        }
    }

    private function firstValue(DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        return $nodes !== false && $nodes->length > 0 ? trim((string) $nodes->item(0)?->nodeValue) : '';
    }

    /** @param array{status:int,body:string,headers:array<string,string>} $response @param array<string,true> $links */
    private function collectInternalLinks(string $baseUrl, string $pageUrl, array $response, array &$links): void
    {
        if ($response['status'] !== 200 || $response['body'] === '') {
            return;
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $response['body']);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return;
        }
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//a[@href]/@href') ?: [] as $node) {
            $href = trim((string) $node->nodeValue);
            if ($href === '' || str_starts_with($href, '#') || preg_match('~^(mailto|tel|javascript):~i', $href)) {
                continue;
            }
            if (str_starts_with($href, '/')) {
                $url = $this->url($baseUrl, (string) parse_url($href, PHP_URL_PATH));
            } elseif (preg_match('~^https?://~i', $href)) {
                $url = preg_replace('~#.*$~', '', $href) ?? $href;
            } else {
                $directory = rtrim(dirname((string) parse_url($pageUrl, PHP_URL_PATH)), '/\\');
                $url = $this->url($baseUrl, $directory . '/' . (string) parse_url($href, PHP_URL_PATH));
            }
            if ($this->sameOrigin($baseUrl, $url)) {
                $links[$url] = true;
            }
        }
    }

    /** @param array{status:int,body:string,headers:array<string,string>} $response @param list<string> $errors @return list<string> */
    private function parseSitemap(string $url, array $response, array &$errors): array
    {
        if ($response['status'] !== 200) {
            $errors[] = $url . ': expected HTTP 200, got ' . $response['status'];
            return [];
        }
        $xml = simplexml_load_string($response['body']);
        if ($xml === false) {
            $errors[] = $url . ': invalid XML';
            return [];
        }
        $urls = [];
        foreach ($xml->url as $item) {
            $loc = trim((string) $item->loc);
            if ($loc !== '') {
                $urls[] = $loc;
            }
        }
        if ($urls === []) {
            $errors[] = $url . ': sitemap contains no URLs';
        }
        if (count($urls) !== count(array_unique($urls))) {
            $errors[] = $url . ': sitemap contains duplicate URLs';
        }
        return array_values(array_unique($urls));
    }

    private function url(string $baseUrl, string $path): string
    {
        $path = trim($path);
        return $path === '' || $path === '/' ? $baseUrl . '/' : $baseUrl . '/' . ltrim($path, '/');
    }

    private function sameOrigin(string $baseUrl, string $url): bool
    {
        return strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)) === strtolower((string) parse_url($url, PHP_URL_SCHEME))
            && strtolower((string) parse_url($baseUrl, PHP_URL_HOST)) === strtolower((string) parse_url($url, PHP_URL_HOST))
            && (parse_url($baseUrl, PHP_URL_PORT) ?: null) === (parse_url($url, PHP_URL_PORT) ?: null);
    }
}
