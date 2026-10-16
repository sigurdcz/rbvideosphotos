-- Application-compatible production SEO repair marker.
-- migrate-util.php creates/updates only the required service pages and their
-- page-level SEO settings through DemoContentSeeder::restoreProductionSeoContent().
-- This avoids a destructive full Demo reset on an existing production site.
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
