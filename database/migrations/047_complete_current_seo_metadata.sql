-- Complete current page metadata and shorten portfolio titles in existing databases.

UPDATE seo_settings s
JOIN pages p ON p.id = s.page_id
SET s.meta_description = CASE p.url_path
        WHEN 'sluzby' THEN 'Svatební, rodinné, portrétní i reklamní focení a video v Olomouci a po celé ČR. Vyberte si službu a domluvte si nezávaznou konzultaci.'
        WHEN 'cenik' THEN 'Přehledný ceník svatebního focení, celodenního videa, portrétního, rodinného a ateliérového focení. Vyberte si foto, video nebo společný balíček.'
        WHEN 'nase-prace' THEN 'Prohlédněte si skutečné svatební, párové a portrétní galerie z Olomouce i celé ČR. Fotografie a videa ukazují náš přirozený styl práce.'
        WHEN 'blog' THEN 'Praktické rady k přípravě na svatební, párové i rodinné focení, výběru místa a videu. Inspirace od fotografa a kameramana z Olomouce.'
        WHEN 'hodnoceni' THEN 'Přečtěte si zkušenosti párů, rodin a firem s focením a videem od RB Videos & Photos. Skutečné reference klientů z Olomouce i celé ČR.'
        WHEN 'kontakt' THEN 'Rezervujte si svatební focení nebo video v Olomouci a po celé ČR. Zavolejte, napište e-mail nebo pošlete nezávaznou poptávku přes formulář.'
        ELSE s.meta_description
    END,
    s.og_description = CASE p.url_path
        WHEN 'sluzby' THEN 'Svatební, rodinné, portrétní i reklamní focení a video v Olomouci a po celé ČR. Vyberte si službu a domluvte si nezávaznou konzultaci.'
        WHEN 'cenik' THEN 'Přehledný ceník svatebního focení, celodenního videa, portrétního, rodinného a ateliérového focení. Vyberte si foto, video nebo společný balíček.'
        WHEN 'nase-prace' THEN 'Prohlédněte si skutečné svatební, párové a portrétní galerie z Olomouce i celé ČR. Fotografie a videa ukazují náš přirozený styl práce.'
        WHEN 'blog' THEN 'Praktické rady k přípravě na svatební, párové i rodinné focení, výběru místa a videu. Inspirace od fotografa a kameramana z Olomouce.'
        WHEN 'hodnoceni' THEN 'Přečtěte si zkušenosti párů, rodin a firem s focením a videem od RB Videos & Photos. Skutečné reference klientů z Olomouce i celé ČR.'
        WHEN 'kontakt' THEN 'Rezervujte si svatební focení nebo video v Olomouci a po celé ČR. Zavolejte, napište e-mail nebo pošlete nezávaznou poptávku přes formulář.'
        ELSE s.og_description
    END,
    s.twitter_description = CASE p.url_path
        WHEN 'sluzby' THEN 'Svatební, rodinné, portrétní i reklamní focení a video v Olomouci a po celé ČR. Vyberte si službu a domluvte si nezávaznou konzultaci.'
        WHEN 'cenik' THEN 'Přehledný ceník svatebního focení, celodenního videa, portrétního, rodinného a ateliérového focení. Vyberte si foto, video nebo společný balíček.'
        WHEN 'nase-prace' THEN 'Prohlédněte si skutečné svatební, párové a portrétní galerie z Olomouce i celé ČR. Fotografie a videa ukazují náš přirozený styl práce.'
        WHEN 'blog' THEN 'Praktické rady k přípravě na svatební, párové i rodinné focení, výběru místa a videu. Inspirace od fotografa a kameramana z Olomouce.'
        WHEN 'hodnoceni' THEN 'Přečtěte si zkušenosti párů, rodin a firem s focením a videem od RB Videos & Photos. Skutečné reference klientů z Olomouce i celé ČR.'
        WHEN 'kontakt' THEN 'Rezervujte si svatební focení nebo video v Olomouci a po celé ČR. Zavolejte, napište e-mail nebo pošlete nezávaznou poptávku přes formulář.'
        ELSE s.twitter_description
    END
WHERE p.url_path IN ('sluzby', 'cenik', 'nase-prace', 'blog', 'hodnoceni', 'kontakt');

INSERT INTO seo_settings
    (page_id, meta_title, meta_description, meta_keywords, canonical_url, robots_index, robots_follow,
     og_title, og_description, og_image_id, twitter_title, twitter_description, twitter_image_id, schema_type, schema_json)
SELECT p.id,
       'Podmínky užití a ochrana obsahu | RB Videos & Photos',
       'Pravidla používání webu RB Videos & Photos, informace o cookies, autorských právech, ochraně fotografií a možnostech kontaktu.',
       'podmínky užití, cookies, autorská práva, ochrana fotografií',
       '/podminky-uziti', 1, 1,
       'Podmínky užití a ochrana obsahu | RB Videos & Photos',
       'Pravidla používání webu RB Videos & Photos, informace o cookies, autorských právech, ochraně fotografií a možnostech kontaktu.',
       (SELECT m.id FROM media m WHERE m.site_id = p.site_id AND m.original_filename = 'rodinne-foceni-rodina-dite-louka.webp' ORDER BY m.id DESC LIMIT 1),
       'Podmínky užití a ochrana obsahu | RB Videos & Photos',
       'Pravidla používání webu RB Videos & Photos, informace o cookies, autorských právech, ochraně fotografií a možnostech kontaktu.',
       (SELECT m.id FROM media m WHERE m.site_id = p.site_id AND m.original_filename = 'rodinne-foceni-rodina-dite-louka.webp' ORDER BY m.id DESC LIMIT 1),
       'WebPage',
       JSON_OBJECT('@context', 'https://schema.org', '@type', 'WebPage', 'name', 'Podmínky užití a ochrana obsahu | RB Videos & Photos', 'description', 'Pravidla používání webu RB Videos & Photos, informace o cookies, autorských právech, ochraně fotografií a možnostech kontaktu.', 'url', '/podminky-uziti')
FROM pages p
WHERE p.url_path = 'podminky-uziti'
ON DUPLICATE KEY UPDATE
    meta_title = VALUES(meta_title), meta_description = VALUES(meta_description),
    meta_keywords = VALUES(meta_keywords), canonical_url = VALUES(canonical_url),
    robots_index = 1, robots_follow = 1,
    og_title = VALUES(og_title), og_description = VALUES(og_description), og_image_id = VALUES(og_image_id),
    twitter_title = VALUES(twitter_title), twitter_description = VALUES(twitter_description), twitter_image_id = VALUES(twitter_image_id),
    schema_type = VALUES(schema_type), schema_json = VALUES(schema_json);

UPDATE portfolio_entries
SET meta_title = CASE slug
    WHEN 'kamila-a-matej-duriskovi' THEN 'Kamila a Matěj - svatební focení v krajině'
    WHEN 'aneta-a-martin-hrazdilovi' THEN 'Aneta a Martin - svatební portréty v parku'
    WHEN 'lenka-a-lukas-tuikovi' THEN 'Lenka a Lukáš - svatební reportáž'
    WHEN 'marie-a-miroslav-cervenkovi' THEN 'Marie a Miroslav - svatební portréty v zeleni'
    WHEN 'martina-a-jaroslav-zackovi' THEN 'Martina a Jaroslav - svatební focení v parku'
    WHEN 'simona-a-michal-novakovi' THEN 'Simona a Michal - svatební portrét v krajině'
    WHEN 'lubos-a-iva-neverilovi' THEN 'Luboš a Iva - svatební focení v louce'
    WHEN 'lucie-a-jarek-stipcakovi' THEN 'Lucie a Jarek - svatební focení v krajině'
    WHEN 'lea-foceni-pro-ritualni-saty' THEN 'Lea - portrétní focení pro Rituální šaty'
    ELSE meta_title
END
WHERE slug IN (
    'kamila-a-matej-duriskovi', 'aneta-a-martin-hrazdilovi', 'lenka-a-lukas-tuikovi',
    'marie-a-miroslav-cervenkovi', 'martina-a-jaroslav-zackovi', 'simona-a-michal-novakovi',
    'lubos-a-iva-neverilovi', 'lucie-a-jarek-stipcakovi', 'lea-foceni-pro-ritualni-saty'
);
