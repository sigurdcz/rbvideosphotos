-- The venue is an area built for weddings and similar events; the shooting range
-- is only a side part of it. Say so instead of leaving the name unexplained.
UPDATE portfolio_entries
SET description = REPLACE(
    description,
    'Obřad se konal ve Sportovně střeleckém areálu Jívová.',
    'Obřad se konal ve Sportovně střeleckém areálu Jívová, který slouží přímo svatbám a dalším společenským akcím.'
)
WHERE slug = 'svatba-jivova-natalie-david'
  AND description LIKE '%Obřad se konal ve Sportovně střeleckém areálu Jívová.%';
