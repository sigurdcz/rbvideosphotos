-- Application-compatible homepage About image replacement.
-- migrate-util.php ingests weare5.jpg through MediaIngestionService and updates
-- only jb-about-visual__media; older homepage media remain in Media Library.
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
