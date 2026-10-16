-- Cenik: karta "Jen natáčení celodenní svatby" se prejmenuje na
-- "Celodenní svatební video". Meni se JEN nazev (title); kategorie, cena,
-- jednotka, popis, odrazky, CTA, stitek, slug/id balicku
-- (svatebni-video-jen-natoceni) i odkaz tlacitka zustavaji.
--
-- Karta je po migraci 094 slot 3 (pred ni slot 8), proto se slot nepredpoklada:
-- zasahne se kazdy `cenik_card_<n>_title`, ktery se presne rovna puvodnimu
-- nazvu, takze admin editace nazvu zustane zachovana. Opakovane spusteni je
-- no-op (po prejmenovani uz nic neodpovida puvodni hodnote).
UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = 'Celodenní svatební video',
    cb.updated_at = CURRENT_TIMESTAMP
WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik'
  AND cb.block_key LIKE 'cenik\_card\_%\_title'
  AND cb.content = 'Jen natáčení celodenní svatby';

-- Legacy staticke HTML bloky: nahrazuje se jen nadpis karty
-- (<h3 class="ckp-title">...</h3>); FAQ, popisky tlacitek ani jine texty
-- s timto retezcem se nedotknou.
UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = REPLACE(
        cb.content,
        '<h3 class="ckp-title">Jen natáčení celodenní svatby</h3>',
        '<h3 class="ckp-title">Celodenní svatební video</h3>'
    ),
    cb.updated_at = CURRENT_TIMESTAMP
WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik'
  AND cb.block_key IN ('pricing_packages', 'pricing_body')
  AND cb.content LIKE '%<h3 class="ckp-title">Jen natáčení celodenní svatby</h3>%';
