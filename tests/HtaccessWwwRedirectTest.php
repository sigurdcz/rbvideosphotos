<?php

declare(strict_types=1);

function register_htaccess_www_redirect_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('Apache configs canonicalize HTTP and www to HTTPS non-www', function () use ($assert, $root): void {
        $requiredCondition = 'RewriteCond %{HTTP_HOST} ^www\\.([a-z0-9.-]+)(:[0-9]+)?$ [NC]';
        $requiredRule = 'RewriteRule ^ https://%1%2%{REQUEST_URI} [R=301,L,NE]';
        $httpsRule = 'RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L,NE]';

        foreach (['/.htaccess', '/public/.htaccess'] as $path) {
            $content = (string)file_get_contents($root . $path);
            $assert(substr_count($content, 'RewriteEngine On') === 1);
            $assert(str_contains($content, $requiredCondition));
            $assert(str_contains($content, $requiredRule));
            $assert(str_contains($content, 'RewriteCond %{HTTP:X-Forwarded-Proto} !https [NC]'));
            $assert(str_contains($content, $httpsRule));
            $assert(strpos($content, $requiredCondition) < strpos($content, 'RewriteRule ^admin'));
        }

        $publish = (string)file_get_contents($root . '/app/Services/PublishService.php');
        $assert(str_contains($publish, '"RewriteCond %{HTTP_HOST} ^www\\\\.([a-z0-9.-]+)(:[0-9]+)?$ [NC]"'));
        $assert(str_contains($publish, $requiredRule));
        $assert(str_contains($publish, $httpsRule));

        $backup = (string)file_get_contents($root . '/.htaccess.backup');
        $assert(!str_contains($backup, 'SEO REDIRECT: WWW'));
        $assert(str_contains($backup, 'RewriteRule ^(.*)$ public/index.php [L,QSA]'));
        $assert(substr_count($backup, 'RewriteEngine On') === 1);

        $redirectTarget = static function (string $scheme, string $host, string $uri, string $forwardedProto = ''): ?string {
            if (preg_match('/^www\\.([a-z0-9.-]+)(:[0-9]+)?$/i', $host, $matches)) {
                return 'https://' . $matches[1] . ($matches[2] ?? '') . $uri;
            }
            if ($scheme === 'http' && strtolower($forwardedProto) !== 'https' && !preg_match('/^(?:localhost|127\\.0\\.0\\.1)(?::[0-9]+)?$/i', $host)) {
                return 'https://' . $host . $uri;
            }
            return null;
        };

        $assert($redirectTarget('http', 'www.example.cz', '/kontakt') === 'https://example.cz/kontakt');
        $assert($redirectTarget('https', 'www.jinyprojekt.com', '/sluzby?id=10') === 'https://jinyprojekt.com/sluzby?id=10');
        $assert($redirectTarget('http', 'www.example.cz:8080', '/x?a=1') === 'https://example.cz:8080/x?a=1');
        $assert($redirectTarget('https', 'example.cz', '/kontakt') === null);
        $assert($redirectTarget('http', 'example.cz', '/kontakt?a=1') === 'https://example.cz/kontakt?a=1');
        $assert($redirectTarget('http', 'example.cz', '/kontakt', 'https') === null);
        $assert($redirectTarget('http', 'localhost:8000', '/kontakt') === null);
        $assert($redirectTarget('http', '127.0.0.1:8000', '/kontakt') === null);
        $assert($redirectTarget('https', 'admin.example.cz', '/kontakt') === null);
        $assert($redirectTarget('https', 'api.example.cz', '/kontakt') === null);
    });
}
