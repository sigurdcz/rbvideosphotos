-- Application-compatible repair marker for the historical sixth service.
-- migrate-util.php restores Dron & same-day edit and regenerates only the
-- homepage and /sluzby service blocks without resetting unrelated content.
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
