-- Activate the requested GTM container only for the RB Videos & Photos site.
-- Both the production and staging hostnames are included; the site-name match
-- covers environments whose domain still points at the production canonical URL.
UPDATE sites
SET google_tag_manager_id = 'GTM-P7Z5TP6G'
WHERE LOWER(TRIM(name)) = 'rb videos & photos'
   OR LOWER(TRIM(TRAILING '/' FROM domain)) IN (
       'https://rbvideosphotos.cz',
       'http://rbvideosphotos.cz',
       'https://staging.rbvideosphotos.cz',
       'http://staging.rbvideosphotos.cz'
   );
