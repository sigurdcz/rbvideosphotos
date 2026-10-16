-- Application-compatible portfolio cover change.
-- migrate-util.php dispatches to PortfolioDemoContentImporter::applyManifestCovers():
-- the entry with slug kamila-a-matej (first card on /nase-prace) gets the existing
-- Media Library record weare5.jpg as its first gallery media, i.e. the listing
-- cover and the detail hero/OG image. The original photos stay in the gallery in
-- their order. Idempotent: no change when weare5.jpg is already first.
-- Clean Armagedon only records this marker; the Demo seed owns the same state.
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
