<?php

/*
 * Initializes the SQLite database with schema and seed data.
 * Run once: php database/init-sqlite.php
 */

define('BASE_PATH', dirname(__DIR__));
$dbPath = BASE_PATH . '/storage/database.sqlite';

echo "Initializing SQLite database at: {$dbPath}\n";

if (!is_dir(BASE_PATH . '/storage')) {
    mkdir(BASE_PATH . '/storage', 0755, true);
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');

// Schema
$schema = file_get_contents(BASE_PATH . '/database/schema.sqlite.sql');
$pdo->exec($schema);
echo "Schema created.\n";

// Seed: admin user (password: admin123)
$hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare('INSERT OR IGNORE INTO users (email, password_hash, role) VALUES (?, ?, ?)');
$stmt->execute(['admin@example.com', $hash, 'admin']);

// Seed: default site
$stmt = $pdo->prepare('INSERT OR IGNORE INTO sites (id, name, domain, default_language) VALUES (1, ?, ?, ?)');
$stmt->execute(['Můj web', 'http://localhost:8000', 'cs']);

// Seed: sample template
$html = <<<HTML
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
    <p data-block="hero_subtitle">Zachytím váš svatební den přirozeně a krásně.</p>
  </header>
  <main>
    <section>
      <h2 data-block="about_title">O mně</h2>
      <p data-block="about_text">Krátký text o fotografovi.</p>
    </section>
    <section>
      <h2 data-block="services_title">Služby</h2>
      <p data-block="services_text">Popis služeb.</p>
    </section>
    <section>
      <h2 data-block="contact_title">Kontakt</h2>
      <p data-block="contact_text">Kontaktujte mě.</p>
    </section>
  </main>
</body>
</html>
HTML;

$stmt = $pdo->prepare('INSERT OR IGNORE INTO templates (id, site_id, name, html_source) VALUES (1, 1, ?, ?)');
$stmt->execute(['Testovací šablona', $html]);

echo "Seed data inserted.\n";
echo "\nDone! You can now log in at http://localhost:8000/admin\n";
echo "Email:    admin@example.com\n";
echo "Password: admin123\n";
