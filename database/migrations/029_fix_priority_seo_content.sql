UPDATE `content_blocks` b
JOIN `pages` p ON p.id = b.page_id
SET b.content = CASE p.url_path
  WHEN '/svatebni-foto-video' THEN 'Svatební foto a video v Olomouci'
  WHEN '/rande-a-zasnuby' THEN 'Párové focení a zásnuby v Olomouci'
  WHEN '/reklamni-foto-video' THEN 'Reklamní foto a video v Olomouci'
  WHEN '/rodinne-a-portretni-foceni' THEN 'Rodinné a portrétní focení v Olomouci'
  WHEN 'hodnoceni' THEN 'Recenze na svatební focení a video'
  WHEN 'kontakt' THEN 'Kontakt na fotografa a kameramana v Olomouci'
  WHEN 'nase-prace' THEN 'Portfolio fotografa a kameramana'
  WHEN 'cenik' THEN 'Ceník svatebního focení a videa'
  ELSE b.content
END
WHERE b.block_key IN ('hero_heading', 'hero_h1')
  AND p.url_path IN (
    '/svatebni-foto-video',
    '/rande-a-zasnuby',
    '/reklamni-foto-video',
    '/rodinne-a-portretni-foceni',
    'hodnoceni',
    'kontakt',
    'nase-prace',
    'cenik'
  );

UPDATE `content_blocks` b
JOIN `pages` p ON p.id = b.page_id
SET b.content = '<h2>Párové focení, které je opravdu o vás</h2><p>Párové focení je vhodné pro dvojice, které chtějí společné fotografie bez strojených póz. Může být vzpomínkou na běžné období, dárkem k výročí, součástí oznámení nebo přípravou na žádost o ruku. Před focením si krátce řekneme, co máte rádi, jaké prostředí vám sedí a zda chcete spíš klidnou procházku, městský příběh nebo fotografie v přírodě. Nemusíte mít zkušenosti před fotoaparátem. Během focení vás jednoduše vedeme a současně necháváme prostor pro přirozenou blízkost, pohyb a humor.</p><h2>Zásnuby a žádost o ruku</h2><p>Pokud plánujete překvapení, pomůžeme vybrat místo, čas i nenápadný scénář. Domluvíme si přesný bod setkání a způsob, jak poznáme správný okamžik. Fotograf zůstane před žádostí o ruku v pozadí a po prvních emocích může následovat krátké párové focení. Nejlépe funguje podvečerní světlo, ale termín přizpůsobíme vašemu plánu a počasí. V Olomouci fotíme v historickém centru, parcích i na místech, ke kterým máte vlastní vztah; po dohodě přijedeme také do okolí, po Moravě nebo kamkoli v České republice.</p><h2>Jak probíhá rezervace a focení</h2><p>Po odeslání poptávky ověříme termín a upřesníme účel fotografií, přibližnou délku a lokalitu. Doporučíme vhodnou denní dobu a poradíme s oblečením tak, aby barvy spolupracovaly, ale abyste se stále cítili jako vy. Na místě začínáme jednoduše, krátkou chůzí a rozhovorem. Průběžně hlídáme světlo, pozadí i drobné detaily. Výsledkem nejsou jen portréty do rámečku, ale souvislá série, která připomene atmosféru společného dne.</p><h2>Co dostanete</h2><ul><li>předchozí konzultaci k místu, času a průběhu,</li><li>citlivé vedení bez nuceného pózování,</li><li>pečlivě vybrané a upravené fotografie,</li><li>online galerii vhodnou ke stažení a sdílení,</li><li>jasně domluvený termín předání.</li></ul><p>Ukázky skutečných párů najdete v našem <a href="/nase-prace">portfoliu fotografa a kameramana</a>. Orientační varianty služeb jsou uvedené v <a href="/cenik">ceníku focení</a> a zkušenosti klientů na stránce <a href="/hodnoceni">Reference</a>.</p><h2>Časté otázky</h2><h3>Co když neumíme pózovat?</h3><p>To je běžné. Místo složitých póz používáme jednoduché pokyny, pohyb a situace, při kterých se můžete soustředit jeden na druhého.</p><h3>Co když bude špatné počasí?</h3><p>Předpověď sledujeme a při dešti se domluvíme na náhradním čase nebo vhodné kryté lokalitě. Změnu neschováváme do podmínek, řešíme ji společně předem.</p><p>Chcete naplánovat párové nebo zásnubní focení? <a href="/kontakt">Napište nám termín a svou představu</a>; ozveme se s konkrétním dalším krokem.</p>'
WHERE p.url_path = '/rande-a-zasnuby' AND b.block_key = 'body';

UPDATE `seo_settings` s
JOIN `pages` p ON p.id = s.page_id
SET
  s.meta_title = CASE p.url_path
    WHEN '/svatebni-foto-video' THEN 'Svatební foto a video Olomouc | RB Videos & Photos'
    WHEN '/rande-a-zasnuby' THEN 'Párové focení a zásnuby Olomouc | RB Videos & Photos'
    WHEN '/reklamni-foto-video' THEN 'Reklamní foto a video Olomouc | RB Videos & Photos'
    WHEN '/rodinne-a-portretni-foceni' THEN 'Rodinné a portrétní focení Olomouc | RB Videos & Photos'
    WHEN 'cenik' THEN 'Ceník svatebního focení a videa | RB Videos & Photos'
    WHEN 'kontakt' THEN 'Fotograf a kameraman Olomouc — kontakt | RB Videos & Photos'
    WHEN 'nase-prace' THEN 'Portfolio fotografa a kameramana | RB Videos & Photos'
    ELSE s.meta_title
  END,
  s.meta_description = CASE p.url_path
    WHEN '/svatebni-foto-video' THEN 'Přirozené svatební fotografie a film od sehraného týmu. Svatební fotograf a kameraman v Olomouci, na Moravě i po celé ČR.'
    WHEN '/rande-a-zasnuby' THEN 'Přirozené párové a zásnubní focení v Olomouci a okolí. Pomůžeme s místem, světlem i nenápadným plánem žádosti o ruku.'
    WHEN '/reklamni-foto-video' THEN 'Profesionální reklamní fotografie a video pro firmy, produkty a značky v Olomouci. Obsah pro web, sociální sítě i kampaně.'
    WHEN '/rodinne-a-portretni-foceni' THEN 'Rodinné, těhotenské a portrétní focení v Olomouci bez nuceného pózování. Přirozené fotografie a citlivé vedení.'
    WHEN 'cenik' THEN 'Ceník svatebního focení, videa a dalších fotografických služeb. Vyberte rozsah a pošlete nezávaznou poptávku na míru.'
    WHEN 'kontakt' THEN 'Kontaktujte fotografa a kameramana v Olomouci. Telefon, e-mail, adresa a nezávazný formulář pro svatby, páry, rodiny i firmy.'
    WHEN 'nase-prace' THEN 'Portfolio svatebních, párových, rodinných a reklamních fotografií a videí. Prohlédněte si skutečné realizace RB Videos & Photos.'
    ELSE s.meta_description
  END,
  s.og_title = s.meta_title,
  s.og_description = s.meta_description,
  s.twitter_title = s.meta_title,
  s.twitter_description = s.meta_description
WHERE p.url_path IN (
  '/svatebni-foto-video',
  '/rande-a-zasnuby',
  '/reklamni-foto-video',
  '/rodinne-a-portretni-foceni',
  'cenik',
  'kontakt',
  'nase-prace'
);

INSERT INTO `seo_settings`
  (`page_id`,`meta_title`,`meta_description`,`canonical_url`,`robots_index`,`robots_follow`,`og_title`,`og_description`,`twitter_title`,`twitter_description`,`schema_type`,`schema_json`)
SELECT
  p.id,
  'Recenze na svatební focení a video | RB Videos & Photos',
  'Autentické zkušenosti klientů se svatebním focením, videem, párovými i rodinnými fotografiemi. Prohlédněte si reference a Google profil.',
  '/hodnoceni',
  1,
  1,
  'Recenze na svatební focení a video | RB Videos & Photos',
  'Autentické zkušenosti klientů se svatebním focením, videem, párovými i rodinnými fotografiemi.',
  'Recenze na svatební focení a video | RB Videos & Photos',
  'Autentické zkušenosti klientů se svatebním focením, videem, párovými i rodinnými fotografiemi.',
  'WebPage',
  JSON_OBJECT('@context','https://schema.org','@type','WebPage','name','Recenze na svatební focení a video','url','/hodnoceni')
FROM `pages` p
WHERE p.url_path = 'hodnoceni'
ON DUPLICATE KEY UPDATE
  meta_title=VALUES(meta_title),
  meta_description=VALUES(meta_description),
  canonical_url=VALUES(canonical_url),
  robots_index=1,
  robots_follow=1,
  og_title=VALUES(og_title),
  og_description=VALUES(og_description),
  twitter_title=VALUES(twitter_title),
  twitter_description=VALUES(twitter_description),
  schema_type=VALUES(schema_type),
  schema_json=VALUES(schema_json);

UPDATE `seo_settings` s
JOIN `pages` p ON p.id = s.page_id
SET
  s.meta_title = 'Párové focení a zásnuby Olomouc | RB Videos & Photos',
  s.meta_description = 'Přirozené párové a zásnubní focení v Olomouci a okolí. Pomůžeme s místem, světlem i nenápadným plánem žádosti o ruku.',
  s.og_title = 'Párové focení a zásnuby Olomouc | RB Videos & Photos',
  s.og_description = 'Přirozené párové a zásnubní focení v Olomouci a okolí.',
  s.twitter_title = 'Párové focení a zásnuby Olomouc | RB Videos & Photos',
  s.twitter_description = 'Přirozené párové a zásnubní focení v Olomouci a okolí.',
  s.og_image_id = COALESCE((SELECT m.id FROM media m WHERE m.site_id=p.site_id AND m.original_filename='par-v-prirode-romanticky-portret-zapad-slunce.webp' LIMIT 1), s.og_image_id),
  s.twitter_image_id = COALESCE((SELECT m.id FROM media m WHERE m.site_id=p.site_id AND m.original_filename='par-v-prirode-romanticky-portret-zapad-slunce.webp' LIMIT 1), s.twitter_image_id),
  s.schema_type = 'Service',
  s.schema_json = JSON_OBJECT(
    '@context','https://schema.org',
    '@type','Service',
    'name','Párové focení a zásnuby v Olomouci',
    'description','Přirozené párové a zásnubní focení v Olomouci a okolí.',
    'url','/rande-a-zasnuby',
    'areaServed',JSON_ARRAY('Olomouc','Olomoucký kraj','Morava','Česká republika')
  )
WHERE p.url_path = '/rande-a-zasnuby';

UPDATE `seo_settings` s
JOIN `pages` p ON p.id=s.page_id
SET s.schema_type='ContactPage',
    s.schema_json=JSON_OBJECT('@context','https://schema.org','@type','ContactPage','name','Kontakt na fotografa a kameramana v Olomouci','url','/kontakt')
WHERE p.url_path='kontakt';
