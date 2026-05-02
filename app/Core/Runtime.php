<?php

namespace App\Core;

use Throwable;

class Runtime
{
    private static float $startedAt = 0.0;
    private static bool $requestStarted = false;
    private static bool $requestFinished = false;
    private static string $channel = 'app';

    public static function boot(string $channel = 'app'): void
    {
        self::$channel = $channel;
        self::$startedAt = microtime(true);

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function startRequest(): void
    {
        self::$requestStarted = true;

        Logger::info('traffic', 'Request started', self::requestContext([
            'channel' => self::$channel,
        ]));
    }

    public static function finishRequest(): void
    {
        if (!self::$requestStarted || self::$requestFinished) {
            return;
        }

        self::$requestFinished = true;

        Logger::info('traffic', 'Request finished', self::requestContext([
            'channel' => self::$channel,
            'status_code' => http_response_code(),
            'duration_ms' => self::durationMs(),
        ]));
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        Logger::error('php_error', $message, [
            'channel' => self::$channel,
            'severity' => $severity,
            'file' => $file,
            'line' => $line,
            'request' => self::requestContext(),
        ]);

        return false;
    }

    public static function handleException(Throwable $exception): void
    {
        Logger::critical('exception', $exception->getMessage(), [
            'channel' => self::$channel,
            'type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'request' => self::requestContext([
                'duration_ms' => self::durationMs(),
            ]),
        ]);

        self::$requestFinished = true;

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (Config::get('app', 'debug')) {
            echo '<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }

        echo 'Internal Server Error';
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && self::isFatal($error['type'])) {
            Logger::critical('fatal_error', $error['message'], [
                'channel' => self::$channel,
                'type' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line'],
                'request' => self::requestContext([
                    'duration_ms' => self::durationMs(),
                ]),
            ]);

            if (!headers_sent()) {
                http_response_code(500);
            }

            self::$requestFinished = true;

            if (!Config::get('app', 'debug')) {
                echo 'Internal Server Error';
            }

            return;
        }

        self::finishRequest();
    }

    private static function requestContext(array $extra = []): array
    {
        $context = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        ];

        if (!empty($_SESSION['user_email'])) {
            $context['user_email'] = (string) $_SESSION['user_email'];
        }

        return array_merge($context, $extra);
    }

    private static function durationMs(): int
    {
        if (self::$startedAt <= 0) {
            return 0;
        }

        return (int) round((microtime(true) - self::$startedAt) * 1000);
    }

    private static function isFatal(int $type): bool
    {
        return in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
    }
}
