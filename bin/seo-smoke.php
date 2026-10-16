<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Services\SeoSmokeChecker;

$baseUrl = rtrim((string) ($argv[1] ?? ''), '/');
if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
    fwrite(STDERR, "Usage: php bin/seo-smoke.php https://canonical.example [ca-bundle.pem]\n");
    exit(2);
}
$caBundle = trim((string) ($argv[2] ?? getenv('SEO_SMOKE_CA_BUNDLE') ?: ''));
if ($caBundle !== '' && !is_readable($caBundle)) {
    fwrite(STDERR, "SEO smoke failed: CA bundle is not readable: {$caBundle}\n");
    exit(2);
}

$requiredPaths = [
    '/', '/sluzby', '/cenik', '/faq', '/kontakt', '/o-nas', '/hodnoceni', '/blog', '/nase-prace',
    '/svatebni-foto-video', '/rodinne-a-portretni-foceni', '/reklamni-foto-video', '/rande-a-zasnuby',
];

$fetch = static function (string $url) use ($caBundle): array {
    $headers = [];
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Cannot initialize curl.');
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'SEO-Editor-Smoke/1.0',
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        },
    ]);
    if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
        curl_setopt($curl, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
    }
    if ($caBundle !== '') {
        curl_setopt($curl, CURLOPT_CAINFO, $caBundle);
    }
    $body = curl_exec($curl);
    if ($body === false) {
        $message = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException($url . ': curl failed: ' . $message);
    }
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return ['status' => $status, 'body' => (string) $body, 'headers' => $headers];
};

try {
    $result = (new SeoSmokeChecker($fetch))->check($baseUrl, $requiredPaths);
} catch (Throwable $error) {
    fwrite(STDERR, "SEO smoke failed: {$error->getMessage()}\n");
    exit(1);
}

if (!$result['ok']) {
    fwrite(STDERR, "SEO smoke failed ({$result['checked']} URL checked):\n");
    foreach ($result['errors'] as $error) {
        fwrite(STDERR, "- {$error}\n");
    }
    exit(1);
}

echo "SEO smoke passed: {$result['checked']} URL checked.\n";
