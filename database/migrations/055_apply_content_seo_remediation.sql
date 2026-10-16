-- Application-compatible marker for the approved 2026-08-11 content SEO repair.
-- The migration runner invokes DemoContentSeeder::restoreContentSeoRemediation()
-- after a site exists. It updates content and metadata, shortens five wedding
-- article slugs with 301 redirects and refreshes supplied portfolio alt text.
-- Existing pricing, reviews, contacts, theme and unrelated media stay intact.
SET @content_seo_remediation_marker = 1;
