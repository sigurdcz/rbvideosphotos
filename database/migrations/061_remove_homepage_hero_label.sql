-- Remove the redundant homepage hero label from existing templates.
-- The content block may remain stored for backward compatibility, but it is no longer rendered.

UPDATE templates t
JOIN pages p ON p.template_id = t.id
SET t.html_source = REPLACE(
    REPLACE(
        t.html_source,
        '<p data-block="hero_label" class="section-label">Fotograf a kameraman - Olomouc, Praha</p>',
        ''
    ),
    '<p class="section-label" data-block="hero_label">Fotograf a kameraman - Olomouc, Praha</p>',
    ''
)
WHERE p.url_path IN ('', '/')
  AND t.html_source LIKE '%id="uvod"%';
