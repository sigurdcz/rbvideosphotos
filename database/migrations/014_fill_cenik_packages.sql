-- Naplní ceníkové balíčky reálnými daty pro fotografa a videografa.
-- Trh ČR 2026: Olomouc / Praha.
-- Základní = individuální focení, Standardní = celá svatba foto+video,
-- Prémiový = celá svatba + zásnuby + album + delší video.

DELETE cb FROM content_blocks cb
JOIN pages p ON p.id = cb.page_id
WHERE TRIM(p.url_path) = 'cenik'
  AND cb.block_key IN (
    'cenik_card_1_title', 'cenik_card_1_desc', 'cenik_card_1_price',
    'cenik_card_2_title', 'cenik_card_2_desc', 'cenik_card_2_price',
    'cenik_card_3_title', 'cenik_card_3_desc', 'cenik_card_3_price'
  );

INSERT INTO content_blocks (page_id, block_key, block_type, label, content, sort_order, autofill_disabled)
SELECT p.id, k.block_key, k.block_type, k.label, k.content, k.sort_order, 1
FROM pages p
CROSS JOIN (
  SELECT 'cenik_card_1_title' AS block_key,
         'text'               AS block_type,
         'Karta 1 — nadpis'  AS label,
         'Základní'           AS content,
         3                   AS sort_order
  UNION ALL
  SELECT 'cenik_card_1_desc', 'textarea', 'Karta 1 — popis',
  'Průkazové, portrétní nebo firemní focení.

• Průkazové a osobní fotografie
• Portrétní session (do 2 hodin, jedno místo)
• Produktové foto (do 20 produktů)
• Reportáž z akce (do 2 hodin)
• 30–60 upravených fotografií
• Soukromá online galerie na 60 dní
• Předání do 10 pracovních dní',
  4
  UNION ALL
  SELECT 'cenik_card_1_price', 'text', 'Karta 1 — cena', 'od 3 500 Kč', 5
  UNION ALL
  SELECT 'cenik_card_2_title', 'text', 'Karta 2 — nadpis', 'Standardní', 6
  UNION ALL
  SELECT 'cenik_card_2_desc', 'textarea', 'Karta 2 — popis',
  'Fotograf a kameraman na celý svatební den.

• Přípravy, obřad, hostina, první tanec
• Focení 8–10 hodin
• 400–500 upravených fotografií
• Highlight video 8–12 minut
• Soukromá online galerie na 1 rok
• Předání do 6 týdnů',
  7
  UNION ALL
  SELECT 'cenik_card_2_price', 'text', 'Karta 2 — cena', 'od 26 900 Kč', 8
  UNION ALL
  SELECT 'cenik_card_3_title', 'text', 'Karta 3 — nadpis', 'Prémiový', 9
  UNION ALL
  SELECT 'cenik_card_3_desc', 'textarea', 'Karta 3 — popis',
  'Vše ze Standardního + výjimečné doplňky.

• Focení celého dne až do večera (12 hod)
• 600+ upravených fotografií
• Příběhové video 20–25 min + krátký highlight
• Zásnubní session v hodnotě 6 000 Kč
• Luxusní fotoalbum 30×30 cm (40 stran)
• Soukromá online galerie na 1 rok
• Předání do 4 týdnů',
  10
  UNION ALL
  SELECT 'cenik_card_3_price', 'text', 'Karta 3 — cena', 'od 39 900 Kč', 11
) k
WHERE TRIM(p.url_path) = 'cenik';
