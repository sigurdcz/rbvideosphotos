-- Hero video: tablet (701-1050 px) ma prehravat stejne video jako desktop.
-- Tabletova varianta se proto vycisti; prazdna varianta se do HTML nezapise
-- (HeroMediaRenderer) a pickEmbedUrl() v frontend-runtime.js pak na tabletu
-- vrati desktopovou URL. Desktopove (default_hero_video_url) ani mobilni
-- (default_hero_video_url_mobile) video se nemeni.
--
-- Idempotentni: opakovane spusteni uz nic nenajde. Na cistem resetu (pred
-- seedem) jsou tabulky prazdne a migrace je no-op; stejny stav tam nastavuji
-- seed.php a DemoContentSeeder.
UPDATE sites
SET default_hero_video_url_tablet = NULL
WHERE default_hero_video_url_tablet IS NOT NULL;

-- Homepage muze mit vlastni strankovy blok hero_video_tablet; vycisti se jen
-- neprazdny, ostatni stranky a bloky zustavaji.
UPDATE content_blocks cb
JOIN pages p ON p.id = cb.page_id
SET cb.content = '',
    cb.updated_at = CURRENT_TIMESTAMP
WHERE TRIM(BOTH '/' FROM p.url_path) = ''
  AND cb.block_key = 'hero_video_tablet'
  AND cb.content IS NOT NULL
  AND cb.content <> '';
