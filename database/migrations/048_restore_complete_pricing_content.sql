-- Restore the complete client-approved pricing content in existing databases.
-- Homepage intentionally reads only titles and non-price feature text from the
-- first three packages; prices remain exclusive to /cenik.

SET @cenik_page_id := (
    SELECT id FROM pages WHERE TRIM(BOTH '/' FROM url_path) = 'cenik' ORDER BY id LIMIT 1
);

CREATE TEMPORARY TABLE tmp_pricing_blocks (
    block_key VARCHAR(100) NOT NULL,
    block_type VARCHAR(20) NOT NULL,
    label VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    sort_order INT NOT NULL,
    autofill_disabled TINYINT(1) NOT NULL
);

INSERT INTO tmp_pricing_blocks
    (block_key, block_type, label, content, sort_order, autofill_disabled)
VALUES
('hero_heading', 'text', 'Hero - nadpis', 'Ceník focení a svatebního videa', 1, 1),
('hero_intro', 'textarea', 'Hero - intro', 'Přehledné balíčky pro svatby, portréty, rodiny i ateliérové focení. Vyberte si rozsah podle toho, zda potřebujete zachytit pouze obřad, celý svatební den, video nebo kompletní foto + video.', 2, 1),
('pricing_weddings_heading', 'text', 'Ceník - nadpis balíčků', 'Vyberte si balíček', 3, 1),
('pricing_weddings_sub', 'textarea', 'Ceník - úvod balíčků', 'Každý balíček má jasně danou cenu a stručný popis, aby bylo hned zřejmé, pro jaký typ focení nebo natáčení se hodí. Konkrétní rozsah vždy doladíme podle místa, délky akce a vašich představ.', 4, 1),

('cenik_card_1_category', 'text', 'Karta 1 - category', 'Svatební video', 10, 1),
('cenik_card_1_title', 'text', 'Karta 1 - title', 'Celodenní svatební video', 11, 1),
('cenik_card_1_price', 'text', 'Karta 1 - price', '17 000 Kč', 12, 1),
('cenik_card_1_unit', 'text', 'Karta 1 - unit', 'celý den', 13, 1),
('cenik_card_1_desc', 'textarea', 'Karta 1 - desc', 'Celodenní svatební video zachytí atmosféru celého dne od důležitých okamžiků až po přirozené emoce hostů. Vhodné pro páry, které chtějí mít ze svatby živou vzpomínku v pohybu.\n\nObsahuje:\n• natáčení celého svatebního dne\n• zachycení obřadu, hostů a atmosféry\n• reportážní styl videa\n• doprava za 7 Kč / km', 14, 1),
('cenik_card_1_badge', 'text', 'Karta 1 - badge', '', 15, 1),
('cenik_card_1_cta_label', 'text', 'Karta 1 - cta_label', 'Poptat svatební video', 16, 1),
('cenik_card_1_cta_url', 'text', 'Karta 1 - cta_url', '/kontakt?sluzba=svatebni-video', 17, 1),

('cenik_card_2_category', 'text', 'Karta 2 - category', 'Svatební focení', 18, 1),
('cenik_card_2_title', 'text', 'Karta 2 - title', 'Focení obřadu a portrétů', 19, 1),
('cenik_card_2_price', 'text', 'Karta 2 - price', '7 000 Kč', 20, 1),
('cenik_card_2_unit', 'text', 'Karta 2 - unit', 'balíček', 21, 1),
('cenik_card_2_desc', 'textarea', 'Karta 2 - desc', 'Kratší svatební focení pro páry, které chtějí zachytit hlavně samotný obřad, společné fotografie a portréty novomanželů.\n\nObsahuje:\n• focení svatebního obřadu\n• portréty novomanželů\n• základní skupinové fotografie\n• vhodné pro menší svatby', 22, 1),
('cenik_card_2_badge', 'text', 'Karta 2 - badge', '', 23, 1),
('cenik_card_2_cta_label', 'text', 'Karta 2 - cta_label', 'Poptat focení obřadu', 24, 1),
('cenik_card_2_cta_url', 'text', 'Karta 2 - cta_url', '/kontakt?sluzba=obrad-portrety', 25, 1),

('cenik_card_3_category', 'text', 'Karta 3 - category', 'Svatební focení', 26, 1),
('cenik_card_3_title', 'text', 'Karta 3 - title', 'Svatební focení do 8 hodin', 27, 1),
('cenik_card_3_price', 'text', 'Karta 3 - price', '10 000 Kč', 28, 1),
('cenik_card_3_unit', 'text', 'Karta 3 - unit', 'do 8 hodin', 29, 1),
('cenik_card_3_desc', 'textarea', 'Karta 3 - desc', 'Praktický balíček pro svatby, kde chcete zachytit obřad, portréty, rodinu, hosty i část hostiny. Dobrá volba pro menší a středně velké svatby.\n\nObsahuje:\n• focení do 8 hodin\n• obřad, portréty a reportáž\n• fotografie rodiny a hostů\n• zachycení přirozené atmosféry', 30, 1),
('cenik_card_3_badge', 'text', 'Karta 3 - badge', '', 31, 1),
('cenik_card_3_cta_label', 'text', 'Karta 3 - cta_label', 'Ověřit termín', 32, 1),
('cenik_card_3_cta_url', 'text', 'Karta 3 - cta_url', '/kontakt?sluzba=foto-do-8-hodin', 33, 1),

('cenik_card_4_category', 'text', 'Karta 4 - category', 'Svatební focení', 34, 1),
('cenik_card_4_title', 'text', 'Karta 4 - title', 'Celodenní svatební focení', 35, 1),
('cenik_card_4_price', 'text', 'Karta 4 - price', '15 000 Kč', 36, 1),
('cenik_card_4_unit', 'text', 'Karta 4 - unit', 'nad 8 hodin / celý den', 37, 1),
('cenik_card_4_desc', 'textarea', 'Karta 4 - desc', 'Celodenní focení pro páry, které chtějí mít zdokumentovaný celý svatební den od příprav přes obřad až po večerní zábavu.\n\nObsahuje:\n• focení nad 8 hodin\n• přípravy, obřad, portréty i večer\n• reportážní fotografie celého dne\n• detaily, hosté, emoce a atmosféra', 38, 1),
('cenik_card_4_badge', 'text', 'Karta 4 - badge', '', 39, 1),
('cenik_card_4_cta_label', 'text', 'Karta 4 - cta_label', 'Poptat celodenní focení', 40, 1),
('cenik_card_4_cta_url', 'text', 'Karta 4 - cta_url', '/kontakt?sluzba=foto-cely-den', 41, 1),

('cenik_card_5_category', 'text', 'Karta 5 - category', 'Kompletní balíček', 42, 1),
('cenik_card_5_title', 'text', 'Karta 5 - title', 'Kompletní balíček foto + video', 43, 1),
('cenik_card_5_price', 'text', 'Karta 5 - price', '31 000 Kč', 44, 1),
('cenik_card_5_unit', 'text', 'Karta 5 - unit', 'celý den', 45, 1),
('cenik_card_5_desc', 'textarea', 'Karta 5 - desc', 'Kompletní celodenní balíček pro páry, které chtějí mít svatební den zachycený fotograficky i ve videu. Ideální řešení, pokud chcete sjednotit styl, komunikaci i výsledek.\n\nObsahuje:\n• celodenní svatební focení\n• celodenní svatební video\n• obřad, portréty, hostina i atmosféra dne\n• doprava do 80 km zdarma', 46, 1),
('cenik_card_5_badge', 'text', 'Karta 5 - badge', 'Nejžádanější', 47, 1),
('cenik_card_5_cta_label', 'text', 'Karta 5 - cta_label', 'Nezávazně poptat termín', 48, 1),
('cenik_card_5_cta_url', 'text', 'Karta 5 - cta_url', '/kontakt?sluzba=foto-video-komplet', 49, 1),

('cenik_card_6_category', 'text', 'Karta 6 - category', 'Portrétní a rodinné focení', 50, 1),
('cenik_card_6_title', 'text', 'Karta 6 - title', 'Portrétní a rodinné focení', 51, 1),
('cenik_card_6_price', 'text', 'Karta 6 - price', '1 800 Kč', 52, 1),
('cenik_card_6_unit', 'text', 'Karta 6 - unit', 'focení', 53, 1),
('cenik_card_6_desc', 'textarea', 'Karta 6 - desc', 'Přirozené portrétní, párové nebo rodinné focení vhodné pro rodiny, děti, páry, těhotenské fotografie i osobní prezentaci.\n\nObsahuje:\n• portrétní nebo rodinné focení\n• přirozený styl fotografií\n• vhodné pro rodiny, páry i jednotlivce\n• focení podle domluvy', 54, 1),
('cenik_card_6_badge', 'text', 'Karta 6 - badge', '', 55, 1),
('cenik_card_6_cta_label', 'text', 'Karta 6 - cta_label', 'Domluvit focení', 56, 1),
('cenik_card_6_cta_url', 'text', 'Karta 6 - cta_url', '/kontakt?sluzba=portretni-rodinne-foceni', 57, 1),

('cenik_card_7_category', 'text', 'Karta 7 - category', 'Ateliérové focení', 58, 1),
('cenik_card_7_title', 'text', 'Karta 7 - title', 'Fotografování v ateliéru', 59, 1),
('cenik_card_7_price', 'text', 'Karta 7 - price', '2 600 Kč', 60, 1),
('cenik_card_7_unit', 'text', 'Karta 7 - unit', 'focení', 61, 1),
('cenik_card_7_desc', 'textarea', 'Karta 7 - desc', 'Ateliérové focení pro portréty, rodiny, děti, páry nebo osobní fotografie. Vhodné pro čistý vizuální styl, kontrolované světlo a elegantní výsledek.\n\nObsahuje:\n• focení v ateliéru\n• portréty, rodinné i osobní fotografie\n• kontrolované světlo\n• profesionální prostředí', 62, 1),
('cenik_card_7_badge', 'text', 'Karta 7 - badge', '', 63, 1),
('cenik_card_7_cta_label', 'text', 'Karta 7 - cta_label', 'Poptat ateliérové focení', 64, 1),
('cenik_card_7_cta_url', 'text', 'Karta 7 - cta_url', '/kontakt?sluzba=atelierove-foceni', 65, 1),

('pricing_transport_heading', 'text', 'Ceník - doprava nadpis', 'Doprava a individuální domluva', 70, 1),
('pricing_transport_text', 'textarea', 'Ceník - doprava text', 'U kompletního svatebního balíčku foto + video je doprava do 80 km zdarma. U samostatného celodenního videa počítáme dopravu 7 Kč / km. Finální rozsah služeb vždy ladíme podle místa, délky akce a konkrétní představy klienta.', 71, 1),
('cenik_faq_1_question', 'text', 'Ceník FAQ 1 - question', 'Kolik stojí svatební focení?', 80, 1),
('cenik_faq_1_answer', 'textarea', 'Ceník FAQ 1 - answer', 'Svatební focení začíná od 7 000 Kč za obřad a portréty. Focení do 8 hodin stojí 10 000 Kč a celodenní svatební focení nad 8 hodin stojí 15 000 Kč.', 81, 1),
('cenik_faq_2_question', 'text', 'Ceník FAQ 2 - question', 'Kolik stojí svatební video?', 82, 1),
('cenik_faq_2_answer', 'textarea', 'Ceník FAQ 2 - answer', 'Celodenní svatební video stojí 17 000 Kč. K ceně se připočítává doprava 7 Kč / km.', 83, 1),
('cenik_faq_3_question', 'text', 'Ceník FAQ 3 - question', 'Nabízíte foto a video dohromady?', 84, 1),
('cenik_faq_3_answer', 'textarea', 'Ceník FAQ 3 - answer', 'Ano. Kompletní balíček foto + video na celý den stojí 31 000 Kč a doprava do 80 km je zdarma.', 85, 1),
('cenik_faq_4_question', 'text', 'Ceník FAQ 4 - question', 'Fotíte i portrétní a rodinné fotografie?', 86, 1),
('cenik_faq_4_answer', 'textarea', 'Ceník FAQ 4 - answer', 'Ano. Portrétní a rodinné focení stojí 1 800 Kč. Fotografování v ateliéru stojí 2 600 Kč.', 87, 1),
('cenik_faq_5_question', 'text', 'Ceník FAQ 5 - question', 'Je doprava v ceně?', 88, 1),
('cenik_faq_5_answer', 'textarea', 'Ceník FAQ 5 - answer', 'U kompletního balíčku foto + video je doprava do 80 km zdarma. U samostatného celodenního videa je doprava 7 Kč / km.', 89, 1),
('cta_heading', 'text', 'CTA - nadpis', 'Chcete ověřit volný termín?', 90, 1),
('cta_sub', 'textarea', 'CTA - text', 'Napište nám datum, místo konání a službu, o kterou máte zájem. Ozveme se vám s možnostmi a doporučením vhodného balíčku.', 91, 1),
('cta_wrap', 'html', 'CTA - tlačítko', '<a href="/kontakt" class="btn-primary">Nezávazně poptat termín</a>', 92, 1);

INSERT INTO content_blocks
    (page_id, block_key, block_type, label, content, sort_order, autofill_disabled)
SELECT @cenik_page_id, block_key, block_type, label, content, sort_order, autofill_disabled
FROM tmp_pricing_blocks
WHERE @cenik_page_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    block_type = VALUES(block_type),
    label = VALUES(label),
    content = VALUES(content),
    sort_order = VALUES(sort_order),
    autofill_disabled = VALUES(autofill_disabled);

DROP TEMPORARY TABLE tmp_pricing_blocks;
