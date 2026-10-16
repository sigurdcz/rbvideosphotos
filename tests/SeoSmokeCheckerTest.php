<?php

declare(strict_types=1);

use App\Services\SeoSmokeChecker;

function register_seo_smoke_checker_tests(callable $test, callable $assert): void
{
    $page = static fn(string $url, string $robots = 'index,follow'): string => '<!doctype html><html><head>'
        . '<title>Unique title</title><meta name="description" content="Useful description">'
        . '<meta name="robots" content="' . $robots . '"><link rel="canonical" href="' . $url . '">'
        . '<meta property="og:title" content="OG title"><meta property="og:description" content="OG description"><meta property="og:url" content="' . $url . '">'
        . '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="Twitter title">'
        . '<script id="cms-tracking-config" type="application/json">{}</script>'
        . '<script type="application/ld+json">{"@context":"https://schema.org","@type":"WebPage"}</script>'
        . '</head><body><h1>Page heading</h1></body></html>';

    $test('SEO smoke accepts complete indexable pages and sitemap', function () use ($assert, $page): void {
        $responses = [
            'https://example.test/' => ['status' => 200, 'body' => $page('https://example.test/'), 'headers' => []],
            'https://example.test/service' => ['status' => 200, 'body' => $page('https://example.test/service'), 'headers' => []],
            'https://example.test/sitemap.xml' => ['status' => 200, 'body' => '<?xml version="1.0"?><urlset><url><loc>https://example.test/</loc></url><url><loc>https://example.test/service</loc></url></urlset>', 'headers' => []],
        ];
        $result = (new SeoSmokeChecker(static fn(string $url): array => $responses[$url]))->check('https://example.test', ['/', '/service']);
        $assert($result['ok'] === true, implode("\n", $result['errors']));
        $assert($result['checked'] === 3);
    });

    $test('SEO smoke rejects 404, noindex, missing sitemap URL and invalid JSON-LD', function () use ($assert, $page): void {
        $broken = str_replace('{"@context":"https://schema.org","@type":"WebPage"}', '{broken', $page('https://example.test/', 'noindex,nofollow'));
        $responses = [
            'https://example.test/' => ['status' => 200, 'body' => $broken, 'headers' => []],
            'https://example.test/service' => ['status' => 404, 'body' => '', 'headers' => []],
            'https://example.test/sitemap.xml' => ['status' => 200, 'body' => '<?xml version="1.0"?><urlset><url><loc>https://example.test/</loc></url></urlset>', 'headers' => []],
        ];
        $result = (new SeoSmokeChecker(static fn(string $url): array => $responses[$url]))->check('https://example.test', ['/', '/service']);
        $joined = implode("\n", $result['errors']);
        $assert($result['ok'] === false);
        $assert(str_contains($joined, 'expected HTTP 200, got 404'));
        $assert(str_contains($joined, 'expected explicit index robots directive'));
        $assert(str_contains($joined, 'invalid JSON-LD'));
        $assert(str_contains($joined, 'missing from sitemap.xml'));
    });
}
