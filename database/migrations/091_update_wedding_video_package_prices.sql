-- Update the client-approved wedding video package and combined package prices.
-- Historical pricing migrations remain immutable; this applies to existing databases.

UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = CASE cb.block_key
    WHEN 'cenik_card_1_title' THEN 'Dva kameramani'
    WHEN 'cenik_card_1_price' THEN '25 000 Kč'
    WHEN 'cenik_card_1_desc' THEN 'Dva kameramani zachytí svatební den z více úhlů a umožní současně pokrýt hlavní okamžiky i přirozené reakce hostů.\n\nObsahuje:\n• dva kameramani po celý svatební den\n• více úhlů obřadu a důležitých okamžiků\n• zachycení hostů, detailů a atmosféry\n• doprava za 7 Kč / km'
    WHEN 'cenik_card_1_cta_label' THEN 'Poptat dva kameramany'
    WHEN 'cenik_card_5_price' THEN '32 000 Kč'
    WHEN 'cenik_faq_2_question' THEN 'Kolik stojí svatební video se dvěma kameramany?'
    WHEN 'cenik_faq_2_answer' THEN 'Balíček se dvěma kameramany stojí 25 000 Kč. K ceně se připočítává doprava 7 Kč / km.'
    WHEN 'cenik_faq_3_answer' THEN 'Ano. Kompletní balíček foto + video na celý den stojí 32 000 Kč a doprava do 80 km je zdarma.'
    WHEN 'cenik_faq_5_answer' THEN 'U kompletního balíčku foto + video je doprava do 80 km zdarma. U balíčku se dvěma kameramany je doprava 7 Kč / km.'
    WHEN 'pricing_transport_text' THEN 'U kompletního svatebního balíčku foto + video je doprava do 80 km zdarma. U balíčku se dvěma kameramany počítáme dopravu 7 Kč / km. Finální rozsah služeb vždy ladíme podle místa, délky akce a konkrétní představy klienta.'
    ELSE cb.content
END,
cb.updated_at = CURRENT_TIMESTAMP
WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik'
  AND cb.block_key IN (
      'cenik_card_1_title', 'cenik_card_1_price', 'cenik_card_1_desc',
      'cenik_card_1_cta_label', 'cenik_card_5_price',
      'cenik_faq_2_question', 'cenik_faq_2_answer', 'cenik_faq_3_answer',
      'cenik_faq_5_answer', 'pricing_transport_text'
  );

UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = REPLACE(
        REPLACE(
            REPLACE(cb.content, 'Celodenní svatební video', 'Dva kameramani'),
            '17 000 Kč',
            '25 000 Kč'
        ),
        '31 000 Kč',
        '32 000 Kč'
    ),
    cb.updated_at = CURRENT_TIMESTAMP
WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik'
  AND cb.block_key IN ('pricing_packages', 'pricing_body');

UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(cb.content,
                    'Celodenní svatební video zachytí atmosféru celého dne od důležitých okamžiků až po přirozené emoce hostů. Vhodné pro páry, které chtějí mít ze svatby živou vzpomínku v pohybu.',
                    'Dva kameramani zachytí svatební den z více úhlů a umožní současně pokrýt hlavní okamžiky i přirozené reakce hostů.'
                ),
                'natáčení celého svatebního dne',
                'dva kameramani po celý svatební den'
            ),
            'zachycení obřadu, hostů a atmosféry',
            'více úhlů obřadu a důležitých okamžiků'
        ),
        'reportážní styl videa',
        'zachycení hostů, detailů a atmosféry'
    ),
    cb.updated_at = CURRENT_TIMESTAMP
WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik'
  AND cb.block_key IN ('pricing_packages', 'pricing_body');
