-- Application-compatible repair marker for the missing homepage About image.
-- migrate-util.php ingests the exact weare2.png source through MediaIngestionService
-- and upserts only jb-about-visual__media; unrelated homepage data is untouched.
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
