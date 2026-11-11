-- The venue is only the place of the ceremony, not the theme of the story.
-- Move it from the lead sentence to a side remark; later edits stay untouched.
UPDATE portfolio_entries
SET description = REPLACE(
    description,
    'Natálie a David si řekli své ano 3. října 2026 ve Sportovně střeleckém areálu Jívová. Jmenuji se Tomáš Šincl, jsem svatební kameraman z Olomouce a jejich svatbu jsem natáčel.',
    'Natálie a David si řekli své ano 3. října 2026 v Jívové u Olomouce. Jmenuji se Tomáš Šincl, jsem svatební kameraman z Olomouce a jejich svatbu jsem natáčel. Obřad se konal ve Sportovně střeleckém areálu Jívová.'
)
WHERE slug = 'svatba-jivova-natalie-david'
  AND description LIKE '%si řekli své ano 3. října 2026 ve Sportovně střeleckém areálu Jívová. Jmenuji%';
