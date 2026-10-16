-- Restore Czech diacritics on the terms-of-use page for existing installations.
-- The 2026-09 crawler audit scored /podminky-uziti 35/100 on grammar because the seeded
-- copy was written without diacritics, which also hurts how search and AI crawlers read it.
-- Wording, block keys and sort order stay identical; only accents are restored.
-- Each statement matches the exact legacy content, so any copy already edited in the admin
-- is left untouched.

UPDATE content_blocks b JOIN pages p ON p.id = b.page_id
SET b.content = 'Pravidla provozu webu, používání obsahu a informace o cookies.'
WHERE p.url_path IN ('podminky-uziti', '/podminky-uziti') AND b.block_key = 'hero_sub'
  AND b.content = 'Pravidla provozu webu, pouzivani obsahu a informace o cookies.';

UPDATE content_blocks b JOIN pages p ON p.id = b.page_id
SET b.content = 'Co platí při používání tohoto webu'
WHERE p.url_path IN ('podminky-uziti', '/podminky-uziti') AND b.block_key = 'rules_title'
  AND b.content = 'Co plati pri pouzivani tohoto webu';

UPDATE content_blocks b JOIN pages p ON p.id = b.page_id
SET b.content = 'Používáním tohoto webu potvrzujete, že jste si přečetli tato pravidla. Pokud s nimi nesouhlasíte, web nepoužívejte.'
WHERE p.url_path IN ('podminky-uziti', '/podminky-uziti') AND b.block_key = 'rules_intro'
  AND b.content = 'Pouzivanim tohoto webu potvrzujete, ze jste si precetli tato pravidla. Pokud s nimi nesouhlasite, web nepouzivejte.';

UPDATE content_blocks b JOIN pages p ON p.id = b.page_id
SET b.content = '<h3>Používání webu</h3><p>Web používejte pouze zákonným a bezpečným způsobem. Nesmíte se pokoušet o narušení provozu, zneužití formulářů ani o zásahy do zabezpečení.</p>'
WHERE p.url_path IN ('podminky-uziti', '/podminky-uziti') AND b.block_key = 'rule_1'
  AND b.content = '<h3>Pouzivani webu</h3><p>Web pouzivejte pouze zakonnym a bezpecnym zpusobem. Nesmite se pokouset o naruseni provozu, zneuziti formularu ani o zasahy do zabezpeceni.</p>';

UPDATE content_blocks b JOIN pages p ON p.id = b.page_id
SET b.content = '<h3>Cookies a souhlasy</h3><p>Nezbytné cookies používáme pro fungování webu. Analytické a marketingové cookies se aktivují až po vašem jasném souhlasu v cookie liště. Své volby můžete kdykoli změnit přes nastavení cookies.</p>'
WHERE p.url_path IN ('podminky-uziti', '/podminky-uziti') AND b.block_key = 'rule_2'
  AND b.content = '<h3>Cookies a souhlasy</h3><p>Nezbytne cookies pouzivame pro fungovani webu. Analyticke a marketingove cookies se aktivuji az po vasem jasnem souhlasu v cookie liste. Sve volby muzete kdykoli zmenit pres nastaveni cookies.</p>';

UPDATE content_blocks b JOIN pages p ON p.id = b.page_id
SET b.content = '<h3>Obsah a autorská práva</h3><p>Fotografie, videa, texty a značka webu zůstávají chráněným obsahem provozovatele nebo jeho klientů. Bez souhlasu je nepoužívejte dále, nekopírujte a nevydávejte za vlastní.</p>'
WHERE p.url_path IN ('podminky-uziti', '/podminky-uziti') AND b.block_key = 'rule_3'
  AND b.content = '<h3>Obsah a autorska prava</h3><p>Fotografie, videa, texty a znacka webu zustavaji chranenym obsahem provozovatele nebo jeho klientu. Bez souhlasu je nepouzivejte dale, nekopirujte a nevydavejte za vlastni.</p>';

UPDATE content_blocks b JOIN pages p ON p.id = b.page_id
SET b.content = '<h3>Změny a kontakt</h3><p>Obsah webu i tato pravidla se mohou průběžně měnit. Pokud máte dotaz k provozu webu, soukromí nebo souhlasu s cookies, napište nám přes kontaktní stránku.</p>'
WHERE p.url_path IN ('podminky-uziti', '/podminky-uziti') AND b.block_key = 'rule_4'
  AND b.content = '<h3>Zmeny a kontakt</h3><p>Obsah webu i tato pravidla se mohou prubezne menit. Pokud mate dotaz k provozu webu, soukromi nebo souhlasu s cookies, napiste nam pres kontaktni stranku.</p>';

UPDATE content_blocks b JOIN pages p ON p.id = b.page_id
SET b.content = '<a href="/kontakt" class="btn-secondary">Kontaktovat nás</a>'
WHERE p.url_path IN ('podminky-uziti', '/podminky-uziti') AND b.block_key = 'cta_wrap'
  AND b.content = '<a href="/kontakt" class="btn-secondary">Kontaktovat nas</a>';
