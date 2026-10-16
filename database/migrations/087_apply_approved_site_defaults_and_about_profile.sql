-- Application-compatible approved site defaults and About profile media update.
-- migrate-util.php ingests aboutuszdenek.jpg through MediaIngestionService,
-- updates the photographer media block, Tomas's Vimeo block and site defaults.
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
