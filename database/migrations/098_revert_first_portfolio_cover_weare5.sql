-- Application-compatible revert of migration 095.
-- 095 put weare5.jpg (photo of the photographers) first in the gallery of the
-- portfolio entry kamila-a-matej by mistake. migrate-util.php dispatches to
-- PortfolioDemoContentImporter::revertMistakenWeare5Cover(): it removes only the
-- weare5.jpg media (by original_filename) from that entry's gallery, keeps the
-- remaining photos in their order (0..n), and restores the weare5.jpg alt/title
-- from 086 only while they still hold the values written by 095.
-- Idempotent; a clean Armagedon only records this marker (Demo seed owns the state).
UPDATE schema_migrations SET migration = migration WHERE 1 = 0;
