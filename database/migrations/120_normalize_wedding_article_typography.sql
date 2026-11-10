-- Normalize only the wedding articles where the rendered staging audit found
-- em/en dashes. Do not rewrite unrelated HTML, templates or structured JSON.
UPDATE blog_posts
SET title = REPLACE(REPLACE(title, '—', '-'), '–', '-'),
    excerpt = REPLACE(REPLACE(excerpt, '—', '-'), '–', '-'),
    content = REPLACE(REPLACE(content, '—', '-'), '–', '-'),
    meta_title = REPLACE(REPLACE(meta_title, '—', '-'), '–', '-'),
    meta_description = REPLACE(REPLACE(meta_description, '—', '-'), '–', '-')
WHERE slug IN (
    'podzimni-svatba-u-robinsona-ester-tomas',
    'svatba-arboretum-bila-lhota-helena-tomas',
    'svatba-hospudka-ve-stodole-best',
    'svatba-hrad-sternberk-marketa-petr',
    'svatba-jivova-lucie-miroslav',
    'svatba-la-futerra-zlamanka-iveta-rene',
    'svatba-penzion-fojtstvi-nikola-jirka',
    'svatba-ruzove-udoli-ivanka-lubomir',
    'svatba-rybnik-sobacov-michaela-jan',
    'svatba-troubelice-marie-miroslav',
    'svatba-u-robinsona-martina-jaroslav',
    'zarijova-svatba-jivova-simona-michal'
);
