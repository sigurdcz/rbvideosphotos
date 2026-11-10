<?php
declare(strict_types=1);

use App\Support\VisibleCopyNormalizer;

function register_visible_copy_normalizer_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('plain visible copy removes AI typography and leaked Markdown markers', function () use ($assert): void {
        $input = 'Nadpis — detail – **tučně** __podtrženo__ ~~škrtnuto~~ ```kód```';
        $assert(
            VisibleCopyNormalizer::plain($input) === 'Nadpis - detail - tučně podtrženo škrtnuto kód',
            'Plain-copy normalization did not remove every supported marker.'
        );
    });

    $test('wedding Markdown keeps formatting syntax but removes long dashes before rendering', function () use ($assert, $root): void {
        $source = (string)file_get_contents($root . '/app/Services/WeddingBlogDemoContentImporter.php');
        $assert(str_contains($source, 'VisibleCopyNormalizer::markdown('));
        $assert(str_contains($source, 'VisibleCopyNormalizer::plain($this->yamlScalar'));
        $assert(VisibleCopyNormalizer::markdown('**Film:** Helena – Tomáš — svatba') === '**Film:** Helena - Tomáš - svatba');
    });

    $test('migration 120 only normalizes audited wedding blog records', function () use ($assert, $root): void {
        $migration = (string)file_get_contents($root . '/database/migrations/120_normalize_wedding_article_typography.sql');
        $assert(str_contains($migration, 'UPDATE blog_posts'));
        $assert(str_contains($migration, "WHERE slug IN ("));
        $assert(substr_count($migration, "'svatba-") >= 10, 'Audited wedding slugs are missing.');
        foreach (['UPDATE templates', 'UPDATE content_blocks', 'UPDATE shared_sections', "REPLACE(content, '__'"] as $unsafe) {
            $assert(!str_contains($migration, $unsafe), 'Migration 120 contains broad unsafe rewrite: ' . $unsafe);
        }

        $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('CREATE TABLE blog_posts (slug TEXT PRIMARY KEY, title TEXT, excerpt TEXT, content TEXT, meta_title TEXT, meta_description TEXT)');
        $insert = $db->prepare('INSERT INTO blog_posts VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute(['svatba-arboretum-bila-lhota-helena-tomas', 'Helena – Tomáš', 'Text — detail', '<p>Den — večer</p>', 'SEO – title', 'SEO — popis']);
        $insert->execute(['unrelated-page', 'Cizí – titul', 'Beze změny', '<p>Cizí — obsah</p>', 'Cizí – SEO', 'Cizí — popis']);
        $db->exec($migration);
        $target = $db->query("SELECT * FROM blog_posts WHERE slug = 'svatba-arboretum-bila-lhota-helena-tomas'")->fetch(PDO::FETCH_ASSOC);
        $unrelated = $db->query("SELECT * FROM blog_posts WHERE slug = 'unrelated-page'")->fetch(PDO::FETCH_ASSOC);
        $assert($target['title'] === 'Helena - Tomáš' && $target['content'] === '<p>Den - večer</p>', 'Audited article was not normalized.');
        $assert($unrelated['title'] === 'Cizí – titul' && $unrelated['content'] === '<p>Cizí — obsah</p>', 'Unrelated article was rewritten.');
    });
}
