-- Application-compatible marker for the 2026-09-07 model-count copy fix.
-- The migration runner invokes PortfolioDemoContentImporter::refreshMixManifestModelCopy()
-- after a site exists. It corrects title, description, meta title/description and
-- photo alt/title text for the five "modelka-*" mixed-batch galleries, which
-- actually each show two to four different models under one theme-based slug.
-- Slugs, photos, sort order and unrelated galleries stay untouched.
SET @mix_manifest_model_copy_marker = 1;
