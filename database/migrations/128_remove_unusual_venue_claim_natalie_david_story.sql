-- The claim that a shooting range is an unusual wedding venue was unfounded.
-- Replace just that opening wording; later editorial edits stay untouched.
UPDATE portfolio_entries
SET description = REPLACE(
    description,
    'Střelecký areál není místo, kde by většina snoubenců čekala svůj obřad. Natálie a David si tu 3. října 2026 přesto řekli své ano a právě to podzimnímu dni dodalo osobitý ráz.',
    'Natálie a David si řekli své ano 3. října 2026 ve Sportovně střeleckém areálu Jívová.'
)
WHERE slug = 'svatba-jivova-natalie-david'
  AND description LIKE '%Střelecký areál není místo%';
