-- O nas: karta fotografa Zdenka - novy popis (doplnena veta o roce 2015)
-- a prvni odrazka.
--
-- Jmeno ("Zdeněk Černohlávek"), role ("Fotograf"), druha a treti odrazka uz
-- maji pozadovane znění, proto je migrace nemeni. Citat z migrace 090 se
-- prepise jen kdyz je stale roven puvodni seed hodnote, a odrazka 1 jen kdyz
-- je rovna puvodni seed hodnote z migrace 074 - admin editace zustane
-- zachovana a opakovane spusteni je idempotentni.
UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = 'Zachycuji emoce a přirozenost tak, abyste se k nim mohli vracet i po letech a znovu je prožívat. Věnuji se focení od roku 2015.',
    cb.updated_at = CURRENT_TIMESTAMP
WHERE p.url_path = 'o-nas'
  AND cb.block_key = 'about_photographer_quote'
  AND cb.content = 'Zachycuji emoce a přirozenost tak, abyste se k nim mohli vracet i po letech a znovu je prožívat.';

UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = 'Stovky spokojených klientů',
    cb.updated_at = CURRENT_TIMESTAMP
WHERE p.url_path = 'o-nas'
  AND cb.block_key = 'about_photographer_point_1'
  AND cb.content = 'Umělecké a přirozené fotografie';
