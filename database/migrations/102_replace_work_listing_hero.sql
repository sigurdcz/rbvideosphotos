-- Application-compatible replacement of the /nase-prace hero photograph.
-- migrate-util.php ingests database/seeds/demo_assets/Snímek obrazovky 2026-10-06 132148.png
-- through MediaIngestionService and assigns its Media Library URL to hero_bg.
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
