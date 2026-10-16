-- Application-compatible replacement of the black About cameraman video fallback.
-- migrate-util.php ingests database/seeds/demo_assets/fallback tomáš.png through
-- MediaIngestionService and assigns its Media Library URL to about_cameraman_image.
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
