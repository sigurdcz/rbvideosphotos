<?php

declare(strict_types=1);

use App\Services\PhpRuntimeInfoHttpResponder;

function register_php_runtime_info_tests(callable $test, callable $assert): void
{
    $test('runtime info endpoint requires MIGRATE_TOKEN and returns a bounded safe payload', function () use ($assert): void {
        $previous = $_SERVER;
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            unset($_SERVER['HTTP_X_MIGRATE_TOKEN'], $_SERVER['HTTP_AUTHORIZATION']);
            $responder = new PhpRuntimeInfoHttpResponder('secret-token', '8.5.11', 'fpm-fcgi', static fn(string $name): bool => $name !== 'gd');

            ob_start();
            $handled = $responder->serve(PhpRuntimeInfoHttpResponder::PATH);
            $missing = json_decode((string) ob_get_clean(), true);
            $assert($handled && http_response_code() === 404 && $missing === ['status' => 'error', 'error' => 'not_found']);

            $_SERVER['HTTP_X_MIGRATE_TOKEN'] = 'wrong-token';
            ob_start();
            $responder->serve(PhpRuntimeInfoHttpResponder::PATH);
            $wrong = json_decode((string) ob_get_clean(), true);
            $assert(http_response_code() === 404 && $wrong === ['status' => 'error', 'error' => 'not_found']);

            $_SERVER['HTTP_X_MIGRATE_TOKEN'] = 'secret-token';
            ob_start();
            $responder->serve(PhpRuntimeInfoHttpResponder::PATH);
            $payload = json_decode((string) ob_get_clean(), true);
            $assert(http_response_code() === 200 && $payload['status'] === 'ok');
            $assert($payload['php']['version'] === '8.5.11' && $payload['php']['version_id'] === 80511);
            $assert($payload['php']['security_minimum'] === '8.5.12');
            $assert($payload['php']['meets_security_minimum'] === false && $payload['php']['security_status'] === 'outdated');
            $assert($payload['required_extensions']['gd'] === false && $payload['required_extensions']['pdo_mysql'] === true);
            $encoded = json_encode($payload);
            foreach (['password', 'document_root', 'php_ini', 'environment', 'database'] as $forbidden) {
                $assert(!str_contains(strtolower((string) $encoded), $forbidden), 'Payload leaked forbidden runtime detail: ' . $forbidden);
            }

            $_SERVER['REQUEST_METHOD'] = 'HEAD';
            ob_start();
            $responder->serve(PhpRuntimeInfoHttpResponder::PATH);
            $assert(ob_get_clean() === '' && http_response_code() === 200, 'HEAD must return no body.');

            $assert($responder->serve('/other') === false);
        } finally {
            $_SERVER = $previous;
            http_response_code(200);
        }
    });

    $test('runtime info endpoint supports bearer token and does not guess unknown branch safety', function () use ($assert): void {
        $previous = $_SERVER;
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            unset($_SERVER['HTTP_X_MIGRATE_TOKEN']);
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret-token';
            ob_start();
            (new PhpRuntimeInfoHttpResponder('secret-token', '8.6.0', 'cli', static fn(): bool => true))
                ->serve(PhpRuntimeInfoHttpResponder::PATH);
            $payload = json_decode((string) ob_get_clean(), true);
            $assert($payload['php']['security_minimum'] === null);
            $assert($payload['php']['meets_security_minimum'] === null);
            $assert($payload['php']['security_status'] === 'unknown_branch');
        } finally {
            $_SERVER = $previous;
            http_response_code(200);
        }
    });

    $test('runtime info wiring stays before sessions and outside discovery files', function () use ($assert): void {
        $root = dirname(__DIR__);
        $index = (string) file_get_contents($root . '/public/index.php');
        $route = strpos($index, 'PhpRuntimeInfoHttpResponder())->serve');
        $auth = strpos($index, "Auth::start();\n");
        $assert($route !== false && $auth !== false && $route < $auth, 'Runtime info endpoint must run before Auth::start().');
        $assert(!str_contains((string) file_get_contents($root . '/app/Services/SitemapGenerator.php'), PhpRuntimeInfoHttpResponder::PATH));
        $assert(!str_contains((string) file_get_contents($root . '/app/Services/RobotsGenerator.php'), PhpRuntimeInfoHttpResponder::PATH));
    });
}
