-- Nový blogový článek "Jak probíhá svatební focení v Brně" (slug
-- svatebni-fotograf-brno, dostupný na /blog/svatebni-fotograf-brno).
-- Stejná struktura a tón jako existující článek svatebni-fotograf-olomouc
-- (proces, ne konkrétní smyšlené zakázky) — tým sídlí v Olomouci a do Brna
-- dojíždí, článek to neskrývá ani nepředstírá brněnskou pobočku.
INSERT INTO `blog_posts`
  (`site_id`, `title`, `slug`, `excerpt`, `content`, `featured_image_id`,
   `status`, `published_at`, `sort_order`, `meta_title`, `meta_description`)
SELECT
  s.id,
  'Jak probíhá svatební focení v Brně',
  'svatebni-fotograf-brno',
  'Svatební fotograf a kameraman z Olomouce, který jezdí fotit a natáčet i do Brna a na jižní Moravu. Jak probíhá domluva, harmonogram a focení svatebního dne.',
  '<p>Sídlíme v Olomouci, ale na svatby jezdíme po celé Moravě — pravidelně i do Brna a okolí. Vzdálenost neřešíme, jen si ji promítneme do harmonogramu dne, aby na focení a natáčení zbyl klidný čas.</p><h2>Před svatbou projdeme harmonogram</h2><p>Potřebujeme znát místo příprav, obřadu a hostiny, přibližné časy přesunů a seznam důležitých skupinových fotografií. U brněnských svateb navíc počítáme s cestou předem, ať dorazíme s rezervou a nic nenaháníme. Společně vybereme vhodný okamžik pro portréty, takže v den svatby nemusíte nic znovu vysvětlovat.</p><h2>Reportáž během dne</h2><p>Přípravy, obřad, gratulace, hosté, výzdoba a drobné reakce fotografujeme převážně bez zásahu. Sledujeme světlo a dění kolem, ale neděláme umělé znovu-nastavené scény jen kvůli fotce. Pokud je potřeba něco organizačně upřesnit, domlouváme se stručně se svědky nebo koordinátorem svatby.</p><h2>Portréty bez složitého pózování</h2><p>Při společném focení vás jednoduše navedeme, kam se postavit nebo kudy se projít. Není potřeba vědět, co dělat s rukama ani trénovat pózy. Portrétní část plánujeme tak, aby zapadla do programu a neodvedla vás na dlouhou dobu od hostů.</p><h2>Focení v Brně a okolí</h2><p>Konkrétní lokalitu vybíráme podle místa svatby, světla, počasí a času, který na přesun máte k dispozici. Brno i jeho okolí nabízí spoustu možností od parků po přírodu za městem — probereme to spolu podle toho, kde budete mít obřad a hostinu, ať focení nezabere zbytečný čas navíc.</p><h2>Výběr, úpravy a předání</h2><p>Po svatbě fotografie vytřídíme, barevně upravíme a připravíme do online galerie. Přesný rozsah, termín předání a dobu archivace potvrzujeme podle vybrané varianty. Neupravené záběry nejsou běžnou součástí výsledného předání.</p><p>Pokud plánujete svatbu v Brně nebo na jižní Moravě a chcete přirozené focení bez strojeného pózování, ozvěte se — rádi probereme termín a domluvíme, jak to nejlépe zvládnout i s cestou.</p><p class="content-related-links">Související informace: <a href="/svatebni-foto-video">svatební foto a video služba</a>, <a href="/cenik">balíčky a ceník svatebního focení</a>, <a href="/kontakt">nezávazná poptávka termínu</a>.</p>',
  (SELECT m.id FROM media m WHERE m.site_id = s.id AND m.original_filename = 'svatba-nevesta-kytice-zapad-slunce-u-vody.webp' ORDER BY m.id DESC LIMIT 1),
  'published',
  NOW(),
  0,
  'Jak probíhá svatební focení v Brně | RB Videos & Photos',
  'Svatební fotograf a kameraman z Olomouce, který jezdí fotit a natáčet i do Brna a na jižní Moravu. Jak probíhá domluva, harmonogram a focení svatebního dne.'
FROM `sites` s
WHERE NOT EXISTS (
  SELECT 1 FROM `blog_posts` b WHERE b.site_id = s.id AND b.slug = 'svatebni-fotograf-brno'
);
