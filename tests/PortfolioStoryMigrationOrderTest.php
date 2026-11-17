<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\ApplicationCompatibleMigrationService;

function register_portfolio_story_migration_order_tests(callable $test, callable $assert): void
{
    foreach ([122, 123, 130] as $appliedThrough) {
        $test('portfolio story upgrade from migration ' . $appliedThrough . ' preserves editorial order', function () use ($assert, $appliedThrough): void {
            $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $db->exec('CREATE TABLE sites (id INTEGER PRIMARY KEY, name TEXT, domain TEXT);
                CREATE TABLE portfolio_entries (id INTEGER PRIMARY KEY, site_id INTEGER, title TEXT, slug TEXT, description TEXT, video_url TEXT, content_type TEXT, status TEXT, published_at TEXT, sort_order INTEGER, meta_title TEXT, meta_description TEXT);
                CREATE TABLE portfolio_videos (id INTEGER PRIMARY KEY, portfolio_id INTEGER, video_url TEXT, sort_order INTEGER);
                INSERT INTO sites VALUES (1, "Test", "https://example.test");
                INSERT INTO portfolio_entries (id, site_id, slug, description) VALUES (1, 1, "existing-story", "Preserve existing content");');
            $instance = new ReflectionProperty(Database::class, 'instance');
            $previous = $instance->getValue();
            $instance->setValue(null, $db);
            try {
                $service = new ApplicationCompatibleMigrationService();
                $files = glob(__DIR__ . '/../database/migrations/1[23][0-9]_*.sql');
                $files = array_values(array_filter($files, static fn(string $file): bool => (int)basename($file) >= 123 && (int)basename($file) <= 131));
                sort($files, SORT_NATURAL);
                $assert(count($files) === 9, 'The complete historical story chain must be present.');

                // Fixtures represent already-applied migrations. The upgrade uses
                // the maintenance runner's SQL pass followed by deferred content.
                $upgrade = static function (array $pending) use ($db, $service): void {
                    $deferred = [];
                    foreach ($pending as $file) {
                        if ($service->supports(basename($file))) {
                            $deferred[] = basename($file);
                        } else {
                            $db->exec((string)file_get_contents($file));
                        }
                    }
                    foreach ($deferred as $name) {
                        $service->apply($name);
                    }
                };
                $pending = [];
                foreach ($files as $file) {
                    if ((int)basename($file) <= $appliedThrough) {
                        $db->exec((string)file_get_contents($file));
                    } else {
                        $pending[] = $file;
                    }
                }
                $upgrade($pending);

                $entry = $db->query("SELECT * FROM portfolio_entries WHERE slug='svatba-jivova-natalie-david'")->fetch();
                $assert($entry !== false && str_contains($entry['description'], 'kam se rád vracím'), 'A batched upgrade must finish with the cameraman text from migration 131.');
                $assert(!str_contains($entry['description'], 'Střelecký areál nepatří mezi místa'), 'The original superseded venue claim must not be published.');
                $assert($entry['video_url'] === 'https://www.youtube.com/watch?v=E29Cz-B2UdA');
                $assert((int)$db->query('SELECT COUNT(*) FROM portfolio_videos')->fetchColumn() === 1);
                $assert($db->query('SELECT description FROM portfolio_entries WHERE id=1')->fetchColumn() === 'Preserve existing content');

                $upgrade($files);
                $assert($db->query("SELECT * FROM portfolio_entries WHERE slug='svatba-jivova-natalie-david'")->fetch() === $entry, 'Replay must preserve the final approved story.');
                $assert((int)$db->query('SELECT COUNT(*) FROM portfolio_videos')->fetchColumn() === 1, 'Replay must not duplicate the video.');
            } finally {
                $instance->setValue(null, $previous);
            }
        });
    }
}
