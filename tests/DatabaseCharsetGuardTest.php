<?php

declare(strict_types=1);

function register_database_charset_guard_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('reviews table charset is repaired by a corrective migration', function () use ($assert, $root): void {
        $migration = (string) file_get_contents($root . '/database/migrations/041_fix_reviews_table_charset.sql');
        $assert(
            str_contains($migration, 'ALTER TABLE reviews CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'),
            'Migration 041 must convert reviews to utf8mb4_unicode_ci.'
        );

        $schema = (string) file_get_contents($root . '/database/schema.sql');
        $assert(
            str_contains($schema, 'CREATE TABLE IF NOT EXISTS `reviews`'),
            'schema.sql must document the reviews table.'
        );
    });

    $test('every CREATE TABLE migration declares an explicit utf8mb4 charset', function () use ($assert, $root): void {
        // A CREATE TABLE without DEFAULT CHARSET inherits the database default,
        // which is utf8mb3 on some shared hosts and breaks every utf8mb4 bound
        // parameter with collation error 3988. Migration 012 predates this rule
        // and is compensated by migration 041; nothing else may repeat it.
        $legacyExceptions = ['012_create_reviews_table.sql'];

        foreach (glob($root . '/database/migrations/*.sql') ?: [] as $file) {
            $name = basename($file);
            if (in_array($name, $legacyExceptions, true)) {
                continue;
            }
            $sql = (string) file_get_contents($file);
            $creates = preg_match_all('/CREATE TABLE/i', $sql);
            $charsets = preg_match_all('/CHARSET=utf8mb4/i', $sql);
            $assert(
                $charsets >= $creates,
                "{$name}: each CREATE TABLE must declare DEFAULT CHARSET=utf8mb4 ({$creates} tables, {$charsets} charset declarations)."
            );
        }
    });
}
