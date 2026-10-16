-- Application-compatible service image change.
-- migrate-util.php dispatches to DemoContentSeeder::replaceFirstServiceVisualWithWeare5():
-- the service "Svatební den - foto + video" (first card on /sluzby and in the
-- homepage services grid; found by title or /svatebni-foto-video, not by id)
-- gets weare5.jpg. The photo is reused from Media Library by original_filename
-- or ingested from database/seeds/demo_assets through MediaIngestionService;
-- the shared media alt/title are not changed. Stored sluzby_items/sluzby_grid/
-- service_1 HTML with autofill enabled is cleared so it is rebuilt on render.
-- Idempotent. Clean Armagedon only records this marker; the Demo seed owns the same state.
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
