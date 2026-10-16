-- O nas: make the hero and team copy editable.
--
-- Until now the hero subtitle, both CTA buttons, the team heading and every
-- word of the two person cards (name, role, quote, bullet points, buttons and
-- the media alt/aria texts) were hardcoded in the theme template, so neither
-- the admin "Bloky obsahu" tab nor the inline editor could reach them. The
-- template now reads each of these keys with the former literal as its
-- fallback, so this migration only has to create the rows.
--
-- ON DUPLICATE KEY UPDATE deliberately refreshes block_type/label/sort_order
-- but never `content`: re-running the migration must not undo an admin edit.
INSERT INTO `content_blocks`
  (`page_id`, `block_key`, `block_type`, `label`, `content`, `autofill_disabled`, `sort_order`)
SELECT
  p.id,
  src.block_key,
  src.block_type,
  src.label,
  src.content,
  0,
  src.sort_order
FROM `pages` p
JOIN (
  SELECT 'about_hero_cta_primary' AS block_key, 'text' AS block_type, 'Hero - hlavní tlačítko' AS label, 'Získat nabídku' AS content, 10 AS sort_order
  UNION ALL SELECT 'about_hero_cta_secondary', 'text', 'Hero - druhé tlačítko', 'Podívat se na ukázky', 11
  UNION ALL SELECT 'about_team_heading', 'text', 'Tým - nadpis sekce', 'Kdo stojí za RB Videos & Photos', 12
  UNION ALL SELECT 'about_photographer_name', 'text', 'Fotograf - jméno', 'Zdeněk Černohlávek', 13
  UNION ALL SELECT 'about_photographer_role', 'text', 'Fotograf - role', 'Fotograf', 14
  UNION ALL SELECT 'about_photographer_quote', 'textarea', 'Fotograf - citát', 'Zachycuji emoce a přirozenost tak, aby se k nim vracely i za roky a znovu je prožívaly.', 15
  UNION ALL SELECT 'about_photographer_point_1', 'text', 'Fotograf - odrážka 1', 'Umělecké a přirozené fotografie', 16
  UNION ALL SELECT 'about_photographer_point_2', 'text', 'Fotograf - odrážka 2', 'Cit pro detail a světlo', 17
  UNION ALL SELECT 'about_photographer_point_3', 'text', 'Fotograf - odrážka 3', 'Individuální přístup ke každému páru', 18
  UNION ALL SELECT 'about_photographer_cta_primary', 'text', 'Fotograf - tlačítko 1', 'Ukázky práce', 19
  UNION ALL SELECT 'about_photographer_cta_secondary', 'text', 'Fotograf - tlačítko 2', 'Spojit se', 20
  UNION ALL SELECT 'about_photographer_photo_alt', 'text', 'Fotograf - popis fotky (alt)', 'Zdeněk Černohlávek, fotograf', 21
  UNION ALL SELECT 'about_photographer_video_label', 'text', 'Fotograf - popis tlačítka přehrát video', 'Přehrát video se Zdeňkem Černohlávkem', 22
  UNION ALL SELECT 'about_cameraman_name', 'text', 'Kameraman - jméno', 'Tomáš Šincl', 23
  UNION ALL SELECT 'about_cameraman_role', 'text', 'Kameraman - role', 'Kameraman a editor', 24
  UNION ALL SELECT 'about_cameraman_quote', 'textarea', 'Kameraman - citát', 'Vyprávím vaše příběhy pohyblivým obrazem, který dojme a zůstane navždy.', 25
  UNION ALL SELECT 'about_cameraman_point_1', 'text', 'Kameraman - odrážka 1', 'Filmové svatební video', 26
  UNION ALL SELECT 'about_cameraman_point_2', 'text', 'Kameraman - odrážka 2', 'Dynamické střihy a emoce', 27
  UNION ALL SELECT 'about_cameraman_point_3', 'text', 'Kameraman - odrážka 3', 'Příběh, který má začátek i konec', 28
  UNION ALL SELECT 'about_cameraman_cta_primary', 'text', 'Kameraman - tlačítko 1', 'Ukázky práce', 29
  UNION ALL SELECT 'about_cameraman_cta_secondary', 'text', 'Kameraman - tlačítko 2', 'Spojit se', 30
  UNION ALL SELECT 'about_cameraman_photo_alt', 'text', 'Kameraman - popis fotky (alt)', 'Tomáš Šincl, kameraman', 31
  UNION ALL SELECT 'about_cameraman_video_label', 'text', 'Kameraman - popis tlačítka přehrát video', 'Přehrát video s Tomášem Šinclem', 32
) src
WHERE p.url_path = 'o-nas'
ON DUPLICATE KEY UPDATE
  `block_type` = VALUES(`block_type`),
  `label` = VALUES(`label`),
  `sort_order` = VALUES(`sort_order`);

-- The o-nas hero subtitle diverged: the template printed one sentence while the
-- database held another, and only the template's text was ever visible. Now
-- that the paragraph reads from `hero_sub`, the stored value would suddenly
-- appear on the page, so it is realigned with what visitors already see. The
-- WHERE clause pins the update to the exact stale seed text, so a site whose
-- admin already rewrote this paragraph keeps their wording.
UPDATE `content_blocks` cb
JOIN `pages` p ON p.id = cb.page_id
SET cb.`content` = 'Jsme Zdeněk a Tomáš - fotograf a kameraman. Zachycujeme pravé emoce, krásu okamžiku a příběhy, které vydrží navždy.'
WHERE p.url_path = 'o-nas'
  AND cb.`block_key` = 'hero_sub'
  AND cb.`content` = 'Jsme sehraný fotografický a filmařský tým z Olomouce. Zachycujeme přirozené emoce, skutečné okamžiky a příběhy, ke kterým se budete rádi vracet.';
