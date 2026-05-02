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
        $debug = Config::get('app', 'debug');

        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);

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
        Logger::criticalTo('exceptions', 'exception', $exception->getMessage(), [
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
            header('Content-Type: text/html; charset=UTF-8');
        }

        if (Config::get('app', 'debug')) {
            echo '<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }

        echo self::renderErrorPage();
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && self::isFatal($error['type'])) {
            Logger::criticalTo('exceptions', 'fatal_error', $error['message'], [
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
                header('Content-Type: text/html; charset=UTF-8');
            }

            self::$requestFinished = true;

            if (!Config::get('app', 'debug')) {
                echo self::renderErrorPage();
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

    private static function renderErrorPage(): string
    {
        $homeUrl = Config::get('app', 'url', '/');
        $siteName = Config::get('app', 'name', 'SEO Mini CMS');

        return <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dočasná chyba | {$siteName}</title>
  <style>
    :root {
      --bg: #0f1115;
      --panel: rgba(20, 24, 31, 0.88);
      --text: #f5f7fb;
      --muted: rgba(245, 247, 251, 0.72);
      --accent: #c4963c;
      --accent-dark: #a67c2a;
      --border: rgba(196, 150, 60, 0.18);
      --shadow: 0 30px 60px rgba(0, 0, 0, 0.34);
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      font-family: "Segoe UI", system-ui, sans-serif;
      color: var(--text);
      background:
        radial-gradient(circle at top, rgba(196, 150, 60, 0.18), transparent 34%),
        linear-gradient(160deg, #090b0f 0%, #131722 100%);
    }

    .error-shell {
      width: min(100%, 760px);
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 24px;
      box-shadow: var(--shadow);
      overflow: hidden;
      backdrop-filter: blur(14px);
    }

    .error-head {
      padding: 18px 22px;
      border-bottom: 1px solid var(--border);
      color: var(--accent);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.18em;
      text-transform: uppercase;
    }

    .error-body {
      padding: 42px 32px 34px;
    }

    .error-code {
      display: inline-flex;
      align-items: center;
      margin-bottom: 18px;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(196, 150, 60, 0.12);
      color: var(--accent);
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    h1 {
      margin: 0 0 14px;
      font-size: clamp(2rem, 5vw, 3.4rem);
      line-height: 1.05;
    }

    p {
      margin: 0;
      max-width: 56ch;
      color: var(--muted);
      font-size: 1rem;
      line-height: 1.75;
    }

    .actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 28px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 46px;
      padding: 0 18px;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 700;
      letter-spacing: 0.03em;
      transition: transform .18s ease, background .18s ease, border-color .18s ease;
    }

    .btn:hover {
      transform: translateY(-1px);
    }

    .btn-primary {
      background: var(--accent);
      color: #fff;
    }

    .btn-primary:hover {
      background: var(--accent-dark);
    }

    .btn-secondary {
      border: 1px solid rgba(255,255,255,.12);
      color: var(--text);
      background: rgba(255,255,255,.03);
    }

    .hint {
      margin-top: 28px;
      padding-top: 20px;
      border-top: 1px solid rgba(255,255,255,.08);
      font-size: .93rem;
      color: rgba(245, 247, 251, 0.54);
    }
  </style>
</head>
<body>
  <div class="error-shell">
    <div class="error-head">Server Error</div>
    <div class="error-body">
      <div class="error-code">Chyba 500</div>
      <h1>Na straně serveru se něco pokazilo.</h1>
      <p>Požadavek se nepodařilo dokončit. Zkus to prosím znovu za chvíli. Pokud problém přetrvá, chyba je už zalogovaná a může se dál dohledat v serverových logách.</p>
      <div class="actions">
        <a class="btn btn-primary" href="{$homeUrl}">Zpět na web</a>
        <a class="btn btn-secondary" href="javascript:location.reload();">Obnovit stránku</a>
      </div>
      <div class="hint">{$siteName}</div>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
