<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

require BASE_PATH . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Logger;

$token = 'rbpv-migrate-2026-05-02-t7K9mQ4sX2';
$providedToken = (string)($_GET['token'] ?? '');
$timezone = Config::get('app', 'timezone', 'Europe/Prague');
date_default_timezone_set($timezone);

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
    renderPage('Pøístup zamítnut', '403', '<h1>Pøístup zamítnut.</h1><p>Token v URL je neplatný nebo chybí.</p>', 403);
}

if (file_exists($lockFile)) {
    renderPage('Migrace už probìhla', 'Locked', '<h1>Migrace už byla spuštìna.</h1><p>Lock soubor existuje, takže utilita už znovu nic nespustí. Pokud ji opravdu potøebuješ znovu, smaž <code>storage/.migration-lock</code>.</p>');
}

try {
    $cfg = Config::get('database');
    $driver = $cfg['driver'] ?? 'sqlite';

    if ($driver === 'sqlite') {
        $dbPath = $cfg['path'] ?? (BASE_PATH . '/storage/database.sqlite');
        if (!is_dir(dirname($dbPath))) {
            mkdir(dirname($dbPath), 0775, true);
        }

        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
        $schema = file_get_contents(BASE_PATH . '/database/schema.sqlite.sql');
        if ($schema === false) {
            throw new RuntimeException('Nepodaøilo se naèíst database/schema.sqlite.sql');
        }
        $pdo->exec($schema);

        $hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO users (email, password_hash, role) VALUES (?, ?, ?)');
        $stmt->execute(['admin@example.com', $hash, 'admin']);

        $stmt = $pdo->prepare('INSERT OR IGNORE INTO sites (id, name, domain, default_language) VALUES (1, ?, ?, ?)');
        $stmt->execute(['Mùj web', Config::get('app', 'url', 'http://localhost:8000'), 'cs']);

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Test stránka</title>
</head>
<body>
  <header>
    <h1 data-block="hero_title">Svatební fotograf Olomouc</h1>
    <p data-block="hero_subtitle">Zachytím váš svatební den pøirozenì a krásnì.</p>
  </header>
  <main>
    <section>
      <h2 data-block="about_title">O mnì</h2>
      <p data-block="about_text">Krátký text o fotografovi.</p>
    </section>
    <section>
      <h2 data-block="services_title">Služby</h2>
      <p data-block="services_text">Popis služeb.</p>
    </section>
    <section>
      <h2 data-block="contact_title">Kontakt</h2>
      <p data-block="contact_text">Kontaktujte mì.</p>
    </section>
  </main>
</body>
</html>
HTML;
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO templates (id, site_id, name, html_source) VALUES (1, 1, ?, ?)');
        $stmt->execute(['Testovací šablona', $html]);
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['dbname'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        $pdo = new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        $seed = file_get_contents(BASE_PATH . '/database/seed.sql');
        if ($schema === false || $seed === false) {
            throw new RuntimeException('Nepodaøilo se naèíst SQL soubory pro MySQL migraci');
        }

        $pdo->exec($schema);
        $pdo->exec($seed);
    }

    file_put_contents($lockFile, 'Applied at ' . date('Y-m-d H:i:s') . PHP_EOL);
    Logger::info('migration', 'Migration utility completed', [
        'driver' => $driver,
        'lock_file' => $lockFile,
    ]);

    renderPage(
        'Migrace dokonèena',
        'Success',
        '<h1>Migrace probìhla úspìšnì.</h1><p>Databázové schema i seed data byly vytvoøeny. Login je teï pøipravený.</p><div class="panel"><pre><span class="ok">Admin e-mail:</span> admin@example.com
<span class="ok">Admin heslo:</span> admin123

Doporuèení:
1. Pøihlas se do adminu.
2. Zmìò heslo.
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
        '<h1>Migrace selhala.</h1><p>Zkus zkontrolovat konfiguraci databáze nebo oprávnìní zápisu. Detail chyby je zalogovaný do exception logu.</p><div class="panel"><pre><span class="warn">'
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . '</span></pre></div>',
        500
    );
}

