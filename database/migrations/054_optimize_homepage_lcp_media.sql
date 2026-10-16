-- Application-compatible marker for restricted production hosting.
-- migrate-util.php handles this migration by regenerating only the existing
-- Media Library item referenced by homepage block jb-about-visual__media.
-- The SQL runner keeps a harmless no-op so fresh/local databases preserve the
-- same append-only migration sequence without filesystem side effects.
SELECT 1;
