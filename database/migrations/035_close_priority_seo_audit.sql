INSERT INTO `templates` (`site_id`, `name`, `html_source`, `css_source`, `js_source`, `created_at`, `updated_at`)
SELECT
  s.id,
  'O nás',
  '<section class="page-hero"><div class="container"><p class="section-label">Poznejte nás</p><h1 data-block="hero_h1">O nás</h1><p data-block="hero_sub" class="hero-sub"></p></div></section><section class="section"><div class="container svc-detail" data-block="body"></div></section>',
  '.svc-detail{max-width:900px}.svc-detail h2{margin:2rem 0 .75rem}.svc-detail p{line-height:1.75}.svc-detail ul{padding-left:1.25rem;line-height:1.8}.svc-detail .about-roles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin:2rem 0}.svc-detail .about-role{border:1px solid rgba(184,122,32,.3);padding:1.5rem;background:#fff}.svc-detail .about-links{display:flex;flex-wrap:wrap;gap:1rem;margin-top:2rem}@media(max-width:700px){.svc-detail .about-roles{grid-template-columns:1fr}}',
  '',
  NOW(),
  NOW()
FROM `sites` s
WHERE NOT EXISTS (SELECT 1 FROM `templates` t WHERE t.site_id=s.id AND t.name = 'O nás');

INSERT INTO `pages` (`site_id`,`template_id`,`title`,`slug`,`url_path`,`language`,`status`,`published_at`)
SELECT s.id, t.id, 'O nás', 'o-nas', 'o-nas', 'cs', 'published', NOW()
FROM `sites` s
JOIN `templates` t ON t.site_id=s.id AND t.name = 'O nás'
WHERE NOT EXISTS (SELECT 1 FROM `pages` p WHERE p.site_id=s.id AND p.slug='o-nas');

INSERT INTO `content_blocks` (`page_id`,`block_key`,`block_type`,`label`,`content`,`sort_order`)
SELECT p.id, v.block_key, v.block_type, v.label, v.content, v.sort_order
FROM `pages` p
JOIN (
  SELECT 'hero_h1' block_key, 'text' block_type, 'Hlavní nadpis' label, 'O nás' content, 1 sort_order
  UNION ALL SELECT 'hero_sub','textarea','Úvod','Jsme sehraný fotografický a filmařský tým z Olomouce. Zachycujeme přirozené emoce, skutečné okamžiky a příběhy, ke kterým se budete rádi vracet.',2
  UNION ALL SELECT 'body','html','Obsah','<h2>Dva pohledy, jeden příběh</h2><p>RB Videos &amp; Photos tvoří fotograf Banny a kameraman Tomáš. Při společných zakázkách pracujeme jako jeden tým: předem si rozdělíme role, respektujeme harmonogram a během dne se pohybujeme tak, abychom zachytili důležité okamžiky bez zbytečného narušování.</p><div class="about-roles"><article class="about-role"><h3>Banny — fotografie</h3><p>Vede fotografickou část, pomáhá s přirozeným postojem, světlem a kompozicí. Cílem nejsou nucené pózy, ale fotografie, na kterých se poznáte.</p></article><article class="about-role"><h3>Tomáš — video</h3><p>Zachycuje pohyb, zvuk a atmosféru dne. Při natáčení spolupracuje s fotografem, aby oba výstupy vznikaly plynule a bez opakování důležitých momentů.</p></article></div><h2>Jak pracujeme</h2><ul><li>před zakázkou si ujasníme termín, místo, rozsah a očekávané výstupy,</li><li>během focení a natáčení citlivě vedeme, ale necháváme prostor přirozeným situacím,</li><li>hotové materiály pečlivě vybíráme, upravujeme a předáváme v domluveném termínu,</li><li>působíme v Olomouci, na Moravě i po celé České republice.</li></ul><p>Konkrétní ukázky najdete v <a href="/nase-prace">portfoliu</a>, zkušenosti zákazníků na stránce <a href="/hodnoceni">Reference</a> a rozsah služeb v <a href="/sluzby">přehledu služeb</a>.</p><div class="about-links"><a class="btn btn-primary" href="/kontakt">Nezávazná konzultace</a><a class="btn btn-secondary" href="/nase-prace">Prohlédnout práci</a></div>',3
) v
WHERE p.slug='o-nas'
ON DUPLICATE KEY UPDATE content=VALUES(content), label=VALUES(label), block_type=VALUES(block_type), sort_order=VALUES(sort_order);

INSERT INTO `seo_settings`
  (`page_id`,`meta_title`,`meta_description`,`canonical_url`,`robots_index`,`robots_follow`,`og_title`,`og_description`,`twitter_title`,`twitter_description`,`schema_type`,`schema_json`)
SELECT p.id,
  'O nás — fotograf a kameraman Olomouc | RB Videos & Photos',
  'Poznejte fotografa Bannyho a kameramana Tomáše z RB Videos & Photos. Jeden sehraný tým pro přirozené fotografie a filmové video.',
  '/o-nas',1,1,
  'O nás | RB Videos & Photos',
  'Fotograf Banny a kameraman Tomáš — sehraný tým z Olomouce pro fotografie a video.',
  'O nás | RB Videos & Photos',
  'Fotograf Banny a kameraman Tomáš — sehraný tým z Olomouce pro fotografie a video.',
  'AboutPage',
  JSON_OBJECT('@context','https://schema.org','@type','AboutPage','name','O nás','url','/o-nas','about',JSON_OBJECT('@type','Organization','name','RB Videos & Photos','url','/'))
FROM `pages` p WHERE p.slug='o-nas'
ON DUPLICATE KEY UPDATE
  meta_title=VALUES(meta_title),meta_description=VALUES(meta_description),canonical_url=VALUES(canonical_url),
  robots_index=1,robots_follow=1,og_title=VALUES(og_title),og_description=VALUES(og_description),
  twitter_title=VALUES(twitter_title),twitter_description=VALUES(twitter_description),
  schema_type=VALUES(schema_type),schema_json=VALUES(schema_json);

UPDATE `blog_posts`
SET `meta_title` = TRIM(REGEXP_REPLACE(`meta_title`, '\\\\s*\\\\|\\\\s*RB\\\\s+(Photos\\\\s*&\\\\s*Videos|Videos\\\\s*&\\\\s*Photos)\\\\s*$', ''))
WHERE `meta_title` REGEXP '\\\\|\\\\s*RB\\\\s+(Photos|Videos)';

UPDATE `portfolio_entries`
SET
  `title` = CASE `slug`
    WHEN 'svatebni-foto-video-olomouc' THEN 'Ukázka svatebního foto a videa v Olomouci'
    WHEN 'svatebni-fotograf-brno' THEN 'Ukázka svatebního focení v Brně'
    WHEN 'rodinne-portretni-foceni' THEN 'Ukázka rodinného a portrétního focení'
    WHEN 'reklamni-foto-video-olomouc' THEN 'Ukázka reklamního foto a videa v Olomouci'
    WHEN 'portretni-foceni-olomouc' THEN 'Ukázka portrétního focení v Olomouci'
    ELSE `title`
  END,
  `meta_title` = CASE `slug`
    WHEN 'svatebni-foto-video-olomouc' THEN 'Ukázka svatebního foto a videa v Olomouci'
    WHEN 'svatebni-fotograf-brno' THEN 'Ukázka svatebního focení v Brně'
    WHEN 'rodinne-portretni-foceni' THEN 'Ukázka rodinného a portrétního focení'
    WHEN 'reklamni-foto-video-olomouc' THEN 'Ukázka reklamního foto a videa v Olomouci'
    WHEN 'portretni-foceni-olomouc' THEN 'Ukázka portrétního focení v Olomouci'
    ELSE `meta_title`
  END
WHERE `slug` IN ('svatebni-foto-video-olomouc','svatebni-fotograf-brno','rodinne-portretni-foceni','reklamni-foto-video-olomouc','portretni-foceni-olomouc');
