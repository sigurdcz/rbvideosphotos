<?php

declare(strict_types=1);

function register_firmy_address_contract_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('canonical seeds and corrective migration use the verified Firmy.cz address', function () use ($assert, $root): void {
        $baseSeed = (string)file_get_contents($root . '/database/seeds/seed.php');
        $demoSeeder = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $migration = (string)file_get_contents($root . '/database/migrations/036_sync_firmy_address_and_about_hero.sql');

        foreach ([$baseSeed, $demoSeeder, $migration] as $source) {
            $assert(str_contains($source, 'Mozartova 1113/65'));
            $assert(str_contains($source, '779 00 Olomouc, Nová Ulice'));
        }
        $assert(str_contains($migration, "m.original_filename = 'weare2.png'"));
        $assert(!str_contains($baseSeed, "'Horní náměstí 583/1'"));
        $assert(!str_contains($demoSeeder, "'Horní náměstí 583/1'"));
    });
}
