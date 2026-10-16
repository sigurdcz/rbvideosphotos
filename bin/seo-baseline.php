<?php

declare(strict_types=1);

$baseUrl = rtrim((string)($argv[1] ?? ''), '/');
if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
    fwrite(STDERR, "Usage: php bin/seo-baseline.php https://canonical.example\n");
    exit(2);
}

/** @return array{status:int,body:string} */
function baselineFetch(string $url): array
{
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Nelze inicializovat cURL.');
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'SEO-Editor-Baseline/1.0',
    ]);
    if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
        curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
    }
    $body = curl_exec($curl);
    if ($body === false) {
        $message = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException($url . ': ' . $message);
    }
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return ['status' => $status, 'body' => (string)$body];
}

/** @return list<string> */
function baselineUrls(string $baseUrl): array
{
    $sitemap = baselineFetch($baseUrl . '/sitemap.xml');
    if ($sitemap['status'] !== 200) {
        throw new RuntimeException('Sitemap nevrací HTTP 200.');
    }
    preg_match_all('~<loc>(.*?)</loc>~is', $sitemap['body'], $matches);
    $urls = array_map(static fn(string $url): string => html_entity_decode(trim($url), ENT_QUOTES | ENT_XML1), $matches[1] ?? []);
    return array_values(array_unique(array_filter($urls)));
}

echo "url\tstatus\ttitle_chars\th1_count\twords\tjsonld_blocks\tjsonld_errors\tcta_links\tmailto_links\n";
foreach (baselineUrls($baseUrl) as $url) {
    $response = baselineFetch($url);
    $html = $response['body'];
    preg_match('~<title[^>]*>(.*?)</title>~is', $html, $titleMatch);
    preg_match_all('~<h1\b[^>]*>.*?</h1>~is', $html, $h1Matches);
    preg_match_all('~<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $html, $jsonMatches);
    preg_match_all('~<a\b[^>]*href=["\'](?:/kontakt|/cenik|tel:|mailto:)[^"\']*["\'][^>]*>~is', $html, $ctaMatches);
    preg_match_all('~<a\b[^>]*href=["\']mailto:~is', $html, $mailtoMatches);

    $jsonErrors = 0;
    foreach ($jsonMatches[1] ?? [] as $json) {
        json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5));
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonErrors++;
        }
    }

    $body = preg_replace('~<(script|style|svg)\b[^>]*>.*?</\1>~is', ' ', $html) ?? $html;
    $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    preg_match_all('~[\p{L}\p{N}]+(?:[-’\'][\p{L}\p{N}]+)*~u', $text, $wordMatches);
    $path = (string)(parse_url($url, PHP_URL_PATH) ?: '/');
    echo implode("\t", [
        $path,
        (string)$response['status'],
        (string)mb_strlen(trim(strip_tags((string)($titleMatch[1] ?? '')))),
        (string)count($h1Matches[0] ?? []),
        (string)count($wordMatches[0] ?? []),
        (string)count($jsonMatches[1] ?? []),
        (string)$jsonErrors,
        (string)count($ctaMatches[0] ?? []),
        (string)count($mailtoMatches[0] ?? []),
    ]), "\n";
}
