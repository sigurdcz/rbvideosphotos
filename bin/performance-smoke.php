<?php

declare(strict_types=1);

$baseUrl = rtrim((string)($argv[1] ?? ''), '/');
if ($baseUrl === '' || !preg_match('~^https?://~i', $baseUrl)) {
    fwrite(STDERR, "Pouziti: php bin/performance-smoke.php https://example.cz\n");
    exit(2);
}
if (!extension_loaded('curl')) {
    fwrite(STDERR, "Performance smoke vyzaduje PHP rozsireni curl.\n");
    exit(2);
}

/** @return array{status:int,headers:array<string,string>,body:string} */
function performanceRequest(string $url, bool $head = false, array $requestHeaders = []): array
{
    $headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_NOBODY => $head,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            $length = strlen($line);
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
            return $length;
        },
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP pozadavek selhal: ' . $error);
    }
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['status' => $status, 'headers' => $headers, 'body' => (string)$body];
}

$errors = [];
$expect = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) $errors[] = $message;
};

try {
    $home = performanceRequest($baseUrl . '/');
    $html = $home['body'];
    $expect($home['status'] === 200, 'Homepage ma HTTP ' . $home['status'] . ', ocekavano 200.');
    $expect(!preg_match('~<iframe[^>]+(?:youtube|vimeo)~i', $html), 'Pocatecni HTML obsahuje externi video iframe.');
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $expect($xpath->query('//img[@fetchpriority="high"]')->length === 1, 'Pocatecni HTML nema prave jeden prioritni hero obrazek.');
    $mobilePreloads = $xpath->query('//link[@data-mobile-lcp-preload]');
    $expect($mobilePreloads->length <= 1, 'Mobilni LCP preload je duplicitni.');
    foreach ($mobilePreloads as $preload) {
        $image = $xpath->query('//img[@data-mobile-lcp]')->item(0);
        $expect($preload->getAttribute('media') === '(max-width: 700px)', 'Mobilni LCP preload neni omezen na mobil.');
        $expect($image instanceof DOMElement, 'Mobilni LCP preload nema odpovidajici obrazek.');
        if ($image instanceof DOMElement) {
            $expect(!$image->hasAttribute('hidden') && $image->getAttribute('loading') === 'eager', 'Mobilni LCP obrazek je skryty nebo lazy.');
            foreach (['href' => 'src', 'imagesrcset' => 'srcset', 'imagesizes' => 'sizes'] as $linkAttr => $imageAttr) {
                $expect($preload->getAttribute($linkAttr) === $image->getAttribute($imageAttr), 'Mobilni preload se lisi od obrazku: ' . $linkAttr);
            }
        }
    }
    foreach ($xpath->query('//img[@loading="eager"]') as $image) {
        $expect($image->getAttribute('fetchpriority') === 'high' || $image->hasAttribute('data-eager-image')
            || str_contains($image->getAttribute('class'), 'site-logo-img'), 'Neoznaceny obrazek ma eager loading.');
    }
    $expect(substr_count($html, ' srcset=') > 0, 'Pocatecni HTML neobsahuje responzivni srcset.');
    $expect(str_contains($html, 'for="hp-contact-shooting-type"'), 'Select shooting_type nema explicitni label.');
    $expect((bool)preg_match('~<select\b(?=[^>]*\bid="hp-contact-shooting-type")(?=[^>]*\baria-label="[^"]+")[^>]*>~i', $html), 'Select shooting_type nema robustni accessible name.');
    $expect(!str_contains($html, 'Material+Symbols'), 'HTML znovu nacita Material Symbols font.');
    $expect(str_contains($html, '/assets/cookie-consent.css" media="print"'), 'Cookie CSS znovu blokuje prvni render.');
    $expect(str_contains($html, '<style data-cms-frontend-runtime-css>'), 'Frontend runtime CSS neni inline v kriticke ceste.');
    $expect(str_contains($html, '<style data-cms-body-vars>'), 'Body CSS promenne nejsou inline.');
    $expect(str_contains($html, '<style data-cms-video-overlay>'), 'Video overlay CSS neni inline.');
    $expect(!str_contains($html, 'href="/assets/frontend-runtime.css"'), 'Frontend runtime CSS znovu vytvari blokujici request.');
    $expect(!preg_match('~href="/assets/generated/(?:body-vars|video-overlay)-~', $html), 'Male kriticke CSS znovu vytvari blokujici generated request.');

    if (preg_match('~<img\b(?=[^>]*fetchpriority="high")(?=[^>]*\bsrc="[^"]+")[^>]*>~i', $html, $priorityMatch)) {
        $priorityTag = $priorityMatch[0];
        preg_match('~\bsrc="([^"]+)"~i', $priorityTag, $srcMatch);
        $priorityPath = (string)($srcMatch[1] ?? '');
        $priorityUrl = str_starts_with($priorityPath, 'http') ? $priorityPath : $baseUrl . $priorityPath;
        $expect(str_contains($priorityTag, 'data-lcp-candidate') || str_contains($priorityTag, 'hero-bg'), 'Vysokou prioritu nema LCP kandidat ani hero poster.');
        $expect(str_contains($priorityTag, 'loading="eager"'), 'LCP kandidat neni eager-load.');
        $priority = performanceRequest($priorityUrl, true);
        $cache = strtolower((string)($priority['headers']['cache-control'] ?? ''));
        $length = (int)($priority['headers']['content-length'] ?? 0);
        $etag = (string)($priority['headers']['etag'] ?? '');
        $expect($priority['status'] === 200, 'LCP kandidat ma HTTP ' . $priority['status'] . '.');
        $expect(str_contains($cache, 'max-age=31536000') && str_contains($cache, 'immutable'), 'LCP kandidat nema rocni immutable cache.');
        $expect(!isset($priority['headers']['set-cookie']), 'LCP kandidat zaklada session cookie.');
        $expect($length > 0 && $length <= 72 * 1024, 'LCP kandidat prekrocil limit 72 KiB: ' . $length . ' B.');
        $expect($etag !== '', 'LCP kandidat nema ETag.');
        if ($etag !== '') {
            $conditional = performanceRequest($priorityUrl, true, ['If-None-Match: ' . $etag]);
            $expect($conditional['status'] === 304, 'Podmineny pozadavek LCP kandidata nevratil 304.');
        }
    } else {
        $errors[] = 'V HTML nebyl nalezen explicitni LCP kandidat.';
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

if ($errors !== []) {
    fwrite(STDERR, "Performance smoke selhal:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "Performance smoke prosel: {$baseUrl}\n";
