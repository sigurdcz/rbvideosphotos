-- Remove client surnames from current blog and portfolio content for GDPR privacy.
-- Historical migrations remain immutable; old portfolio URLs keep working through 301 redirects.

UPDATE `portfolio_entries`
SET `title` = CASE `slug`
        WHEN 'kamila-a-matej-duriskovi' THEN 'Kamila a Matěj - svatební focení v krajině'
        WHEN 'aneta-a-martin-hrazdilovi' THEN 'Aneta a Martin - park, blízké portréty a architektura'
        WHEN 'lenka-a-lukas-tuikovi' THEN 'Lenka a Lukáš - svatební reportáž v několika prostředích'
        WHEN 'marie-a-miroslav-cervenkovi' THEN 'Marie a Miroslav - svatební portréty v zeleni'
        WHEN 'martina-a-jaroslav-zackovi' THEN 'Martina a Jaroslav - prsteny, tenisky, most a portréty'
        WHEN 'simona-a-michal-novakovi' THEN 'Simona a Michal - jednoduchý svatební portrét v krajině'
        WHEN 'lubos-a-iva-neverilovi' THEN 'Luboš a Iva - louka, závoj a portrétní focení'
        WHEN 'lucie-a-jarek-stipcakovi' THEN 'Lucie a Jarek - svatební focení v otevřené krajině'
        ELSE `title`
    END,
    `slug` = CASE `slug`
        WHEN 'kamila-a-matej-duriskovi' THEN 'kamila-a-matej'
        WHEN 'aneta-a-martin-hrazdilovi' THEN 'aneta-a-martin'
        WHEN 'lenka-a-lukas-tuikovi' THEN 'lenka-a-lukas'
        WHEN 'marie-a-miroslav-cervenkovi' THEN 'marie-a-miroslav'
        WHEN 'martina-a-jaroslav-zackovi' THEN 'martina-a-jaroslav'
        WHEN 'simona-a-michal-novakovi' THEN 'simona-a-michal'
        WHEN 'lubos-a-iva-neverilovi' THEN 'lubos-a-iva'
        WHEN 'lucie-a-jarek-stipcakovi' THEN 'lucie-a-jarek'
        ELSE `slug`
    END
WHERE `slug` IN (
    'kamila-a-matej-duriskovi', 'aneta-a-martin-hrazdilovi',
    'lenka-a-lukas-tuikovi', 'marie-a-miroslav-cervenkovi',
    'martina-a-jaroslav-zackovi', 'simona-a-michal-novakovi',
    'lubos-a-iva-neverilovi', 'lucie-a-jarek-stipcakovi'
);

INSERT INTO `redirects` (`site_id`, `from_path`, `to_path`, `status_code`)
SELECT mapping.site_id, mapping.from_path, mapping.to_path, 301
FROM (
    SELECT s.id AS site_id, '/kamila-a-matej-duriskovi' AS from_path, '/kamila-a-matej' AS to_path FROM sites s
    UNION ALL SELECT s.id, '/aneta-a-martin-hrazdilovi', '/aneta-a-martin' FROM sites s
    UNION ALL SELECT s.id, '/lenka-a-lukas-tuikovi', '/lenka-a-lukas' FROM sites s
    UNION ALL SELECT s.id, '/marie-a-miroslav-cervenkovi', '/marie-a-miroslav' FROM sites s
    UNION ALL SELECT s.id, '/martina-a-jaroslav-zackovi', '/martina-a-jaroslav' FROM sites s
    UNION ALL SELECT s.id, '/simona-a-michal-novakovi', '/simona-a-michal' FROM sites s
    UNION ALL SELECT s.id, '/lubos-a-iva-neverilovi', '/lubos-a-iva' FROM sites s
    UNION ALL SELECT s.id, '/lucie-a-jarek-stipcakovi', '/lucie-a-jarek' FROM sites s
) mapping
WHERE NOT EXISTS (
    SELECT 1 FROM redirects r
    WHERE r.site_id = mapping.site_id AND r.from_path = mapping.from_path
);

UPDATE `redirects`
SET `to_path` = CASE `from_path`
        WHEN '/kamila-a-matej-duriskovi' THEN '/kamila-a-matej'
        WHEN '/aneta-a-martin-hrazdilovi' THEN '/aneta-a-martin'
        WHEN '/lenka-a-lukas-tuikovi' THEN '/lenka-a-lukas'
        WHEN '/marie-a-miroslav-cervenkovi' THEN '/marie-a-miroslav'
        WHEN '/martina-a-jaroslav-zackovi' THEN '/martina-a-jaroslav'
        WHEN '/simona-a-michal-novakovi' THEN '/simona-a-michal'
        WHEN '/lubos-a-iva-neverilovi' THEN '/lubos-a-iva'
        WHEN '/lucie-a-jarek-stipcakovi' THEN '/lucie-a-jarek'
        ELSE `to_path`
    END,
    `status_code` = 301
WHERE `from_path` IN (
    '/kamila-a-matej-duriskovi', '/aneta-a-martin-hrazdilovi',
    '/lenka-a-lukas-tuikovi', '/marie-a-miroslav-cervenkovi',
    '/martina-a-jaroslav-zackovi', '/simona-a-michal-novakovi',
    '/lubos-a-iva-neverilovi', '/lucie-a-jarek-stipcakovi'
);

UPDATE `portfolio_entries`
SET `description` = REPLACE(`description`, 'Zdeněk Černohlávek', 'Zdeněk'),
    `meta_title` = REPLACE(`meta_title`, 'Zdeněk Černohlávek', 'Zdeněk'),
    `meta_description` = REPLACE(`meta_description`, 'Zdeněk Černohlávek', 'Zdeněk');

UPDATE `blog_posts`
SET `slug` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`slug`, '-hufovi', ''), '-kankovsti', ''), '-plaskovi', ''), '-krupovi', ''), '-jungligovi', ''), '-gruntovi', ''), '-provazovi', ''), '-krylovi', ''), '-novakovi', ''), '-zackovi', ''), '-neverilovi', ''),
    `title` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`title`, 'Hufovi', ''), 'Kaňkovští', ''), 'Pláškovi', ''), 'Krůpovi', ''), 'Jungligovi', ''), 'Gruntovi', ''), 'Provazovi', ''), 'Krylovi', ''), 'Novákovi', ''), 'Žáčkovi', ''), 'Nevěřilovi', ''),
    `excerpt` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`excerpt`, 'Hufovi', ''), 'Kaňkovští', ''), 'Pláškovi', ''), 'Krůpovi', ''), 'Jungligovi', ''), 'Gruntovi', ''), 'Provazovi', ''), 'Krylovi', ''), 'Novákovi', ''), 'Žáčkovi', ''), 'Nevěřilovi', ''),
    `content` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`content`, 'Hufovi', ''), 'Kaňkovští', ''), 'Pláškovi', ''), 'Krůpovi', ''), 'Jungligovi', ''), 'Gruntovi', ''), 'Provazovi', ''), 'Krylovi', ''), 'Novákovi', ''), 'Žáčkovi', ''), 'Nevěřilovi', ''),
    `meta_title` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`meta_title`, 'Hufovi', ''), 'Kaňkovští', ''), 'Pláškovi', ''), 'Krůpovi', ''), 'Jungligovi', ''), 'Gruntovi', ''), 'Provazovi', ''), 'Krylovi', ''), 'Novákovi', ''), 'Žáčkovi', ''), 'Nevěřilovi', ''),
    `meta_description` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`meta_description`, 'Hufovi', ''), 'Kaňkovští', ''), 'Pláškovi', ''), 'Krůpovi', ''), 'Jungligovi', ''), 'Gruntovi', ''), 'Provazovi', ''), 'Krylovi', ''), 'Novákovi', ''), 'Žáčkovi', ''), 'Nevěřilovi', '');

UPDATE `blog_posts`
SET `title` = REPLACE(`title`, '  ', ' '),
    `excerpt` = REPLACE(`excerpt`, '  ', ' '),
    `content` = REPLACE(`content`, '  ', ' '),
    `meta_title` = REPLACE(`meta_title`, '  ', ' '),
    `meta_description` = REPLACE(`meta_description`, '  ', ' ');

UPDATE `media` m
JOIN (
    SELECT media_id FROM portfolio_media
    UNION
    SELECT media_id FROM blog_media
) article_media ON article_media.media_id = m.id
SET m.original_filename = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(m.original_filename,
        'Ďuriškovi', ''), 'Hrazdilovi', ''), 'Ťuikovi', ''), 'Červenkovi', ''), 'Žáčkovi', ''), 'Novákovi', ''), 'Nevěřilovi', ''), 'Štipčákovi', ''),
        'Hufovi', ''), 'Kaňkovští', ''), 'Pláškovi', ''), 'Krůpovi', ''), 'Jungligovi', ''), 'Gruntovi', ''), 'Provazovi', ''), 'Krylovi', ''), 'Černohlávek', ''), 'Cervenkovi', ''), 'Zackovi', ''),
    m.alt_text = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(m.alt_text,
        'Ďuriškovi', ''), 'Hrazdilovi', ''), 'Ťuikovi', ''), 'Červenkovi', ''), 'Žáčkovi', ''), 'Novákovi', ''), 'Nevěřilovi', ''), 'Štipčákovi', ''),
        'Hufovi', ''), 'Kaňkovští', ''), 'Pláškovi', ''), 'Krůpovi', ''), 'Jungligovi', ''), 'Gruntovi', ''), 'Provazovi', ''), 'Krylovi', ''), 'Černohlávek', ''), 'Cervenkovi', ''), 'Zackovi', ''),
    m.title = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(m.title,
        'Ďuriškovi', ''), 'Hrazdilovi', ''), 'Ťuikovi', ''), 'Červenkovi', ''), 'Žáčkovi', ''), 'Novákovi', ''), 'Nevěřilovi', ''), 'Štipčákovi', ''),
        'Hufovi', ''), 'Kaňkovští', ''), 'Pláškovi', ''), 'Krůpovi', ''), 'Jungligovi', ''), 'Gruntovi', ''), 'Provazovi', ''), 'Krylovi', ''), 'Černohlávek', ''), 'Cervenkovi', ''), 'Zackovi', '');

UPDATE `redirects`
SET `to_path` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`to_path`,
    'kamila-a-matej-duriskovi', 'kamila-a-matej'),
    'aneta-a-martin-hrazdilovi', 'aneta-a-martin'),
    'lenka-a-lukas-tuikovi', 'lenka-a-lukas'),
    'marie-a-miroslav-cervenkovi', 'marie-a-miroslav'),
    'martina-a-jaroslav-zackovi', 'martina-a-jaroslav'),
    'simona-a-michal-novakovi', 'simona-a-michal'),
    'lubos-a-iva-neverilovi', 'lubos-a-iva'),
    'lucie-a-jarek-stipcakovi', 'lucie-a-jarek');
