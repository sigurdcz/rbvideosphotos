-- Application-compatible homepage About image replacement.
-- migrate-util.php ingests weare4.jpg through MediaIngestionService and updates
-- only jb-about-visual__media; the existing weare2.png media remains available
-- for its separate SEO and service-card uses.
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
