-- Compatibility marker for restricted shared hosting.
-- Migration 048 is immutable, but temporary-table DDL is denied by the
-- production DB account. migrate-util.php applies the same approved pricing
-- rows through DemoContentSeeder::restoreRealPricingContent(), records 048 as
-- application-compatible, and then records this marker normally.
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
