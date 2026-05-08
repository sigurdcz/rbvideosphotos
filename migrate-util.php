<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

require BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Logger;

Env::load(BASE_PATH . '/.env');

$token = (string) getenv('MIGRATE_TOKEN');
if ($token === '') {
    renderPage('Chyba konfigurace', '500', '<h1>MIGRATE_TOKEN není nastaven.</h1><p>Přidej <code>MIGRATE_TOKEN=...</code> do <code>.env</code>.</p>', 500);
}
$providedToken = (string) ($_GET['token'] ?? '');
date_default_timezone_set(getenv('APP_TZ') ?: 'Europe/Prague');

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$lockFile = BASE_PATH . '/storage/.migration-lock';
$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

function renderPage(string $title, string $status, string $body, int $code = 200): void
{
    http_response_code($code);
    echo '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow,noarchive"><title>'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</title><style>'
        . ':root{--bg:#0f1115;--panel:rgba(20,24,31,.92);--text:#f5f7fb;--muted:rgba(245,247,251,.72);--accent:#c4963c;--accent-dark:#a67c2a;--border:rgba(196,150,60,.18);--ok:#2ea36b;--warn:#d98b3a;--shadow:0 30px 60px rgba(0,0,0,.34)}'
        . '*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;font-family:"Segoe UI",system-ui,sans-serif;color:var(--text);background:radial-gradient(circle at top, rgba(196,150,60,.18), transparent 34%),linear-gradient(160deg,#090b0f 0%,#131722 100%)}'
        . '.shell{width:min(100%,780px);background:var(--panel);border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow);overflow:hidden;backdrop-filter:blur(14px)}'
        . '.head{padding:18px 22px;border-bottom:1px solid var(--border);color:var(--accent);font-size:12px;font-weight:700;letter-spacing:.18em;text-transform:uppercase}.body{padding:36px 32px}.pill{display:inline-flex;align-items:center;margin-bottom:18px;padding:8px 14px;border-radius:999px;background:rgba(196,150,60,.12);color:var(--accent);font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}h1{margin:0 0 14px;font-size:clamp(1.8rem,4.4vw,3rem);line-height:1.08}p{margin:0;color:var(--muted);line-height:1.75}.panel{margin-top:22px;padding:18px 18px 16px;border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(255,255,255,.03)}pre{margin:0;white-space:pre-wrap;word-break:break-word;color:#fff;font:13px/1.65 Consolas,monospace}.ok{color:var(--ok)}.warn{color:var(--warn)}a{color:var(--accent)}</style></head><body><div class="shell"><div class="head">Migration Utility</div><div class="body"><div class="pill">'
        . htmlspecialchars($status, ENT_QUOTES, 'UTF-8')
        . '</div>' . $body . '</div></div></body></html>';
    exit;
}

if (!hash_equals($token, $providedToken)) {
    Logger::warningTo('exceptions', 'migration_auth', 'Invalid migration token', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
    ]);
    renderPage('Přístup zamítnut', '403', '<h1>Přístup zamítnut.</h1><p>Token v URL je neplatný nebo chybí.</p>', 403);
}

if (file_exists($lockFile)) {
    renderPage('Migrace již proběhla', 'Locked', '<h1>Migrace již byla spuštěna.</h1><p>Lock soubor existuje, takže utilita již znovu nic nespustí. Pokud ji opravdu potřebuješ znovu, smaž <code>storage/.migration-lock</code>.</p>');
}

try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $db   = getenv('DB_NAME') ?: 'seo_editor_cms';
    $user = getenv('DB_USER') ?: 'seo_editor_app';
    $pass = getenv('DB_PASS') ?: '';

    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (id INT UNSIGNED NOT NULL AUTO_INCREMENT, migration VARCHAR(255) NOT NULL UNIQUE, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
    sort($files, SORT_NATURAL);

    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $applied, true)) {
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Nepodařilo se načíst migraci: ' . $name);
        }

        // MySQL DDL (CREATE TABLE etc.) causes an implicit commit,
        // so wrapping schema migrations in a transaction doesn't work —
        // beginTransaction/commit/rollBack would all throw "no active transaction".
        // We run the SQL directly and record it in schema_migrations separately.
        $pdo->exec($sql);
        $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')
            ->execute([$name]);
    }

    file_put_contents($lockFile, 'Applied at ' . date('Y-m-d H:i:s') . PHP_EOL);
    Logger::info('migration', 'Migration utility completed', [
        'driver' => 'mysql',
        'database' => $db,
        'lock_file' => $lockFile,
    ]);

    renderPage(
        'Migrace dokončena',
        'Success',
        '<h1>Migrace proběhla úspěšně.</h1><p>Databázové schema bylo vytvořeno v MariaDB/MySQL databázi.</p><div class="panel"><pre><span class="ok">Databáze:</span> ' . htmlspecialchars($db, ENT_QUOTES, 'UTF-8') . '
<span class="ok">Admin e-mail:</span> admin@example.com
<span class="ok">Admin heslo:</span> admin123

Doporučení:
1. Přihlas se do adminu.
2. Změň heslo.
3. Smaž nebo zablokuj tento util soubor.</pre></div>'
    );
} catch (Throwable $e) {
    Logger::criticalTo('exceptions', 'migration_failure', $e->getMessage(), [
        'type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
    ]);

    renderPage(
        'Migrace selhala',
        'Error',
        '<h1>Migrace selhala.</h1><p>Zkontroluj prosím přihlašovací údaje k MariaDB/MySQL v <code>.env</code> a ověř, že databáze i uživatel existují. Detail chyby je zalogovaný do exception logu.</p><div class="panel"><pre><span class="warn">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</span></pre></div>',
        500
    );
}
