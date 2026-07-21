-- Vyrovna bocni cenikove karty: Premium shrne sdilene polozky Standardniho
-- a ponecha pouze sve odlisujici benefity. Historicka migrace 014 zustava beze zmeny.

UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = 'Kompletní zachycení celého svatebního dne bez spěchu a kompromisů. Přibližně 10–12 hodin focení, prioritní zpracování a svatební video / reels v ceně. Delší archivace výstupů.

Obsahuje:
• vše ze Standardního balíčku
• focení cca 10–12 hodin
• větší počet upravených fotografií
• detailní zachycení atmosféry, hostů a momentů
• svatební video + krátký sestřih / reels v ceně
• prioritní zpracování
• archivace hotových výstupů na 5 let'
WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik'
  AND cb.block_key = 'cenik_card_3_desc';
