-- Reorder the /cenik pricing cards and move the `Nejžádanější` badge.
--
-- Display order is the block-key slot (`cenik_card_<n>_*`, CenikPageData loops
-- 1..8) and every slot is bound to a package id in PricingPackageCatalog.
-- New order (new slot <- old slot):
--   1 <- 4 Celodenní svatební focení
--   2 <- 5 Kompletní balíček foto + video (now carries `Nejžádanější`)
--   3 <- 8 Jen natáčení celodenní svatby
--   4 <- 1 Dva kameramani
--   5 <- 2 Focení obřadu a portrétů
--   6 <- 3 Svatební focení do 8 hodin
--   7 <- 6 Portrétní a rodinné focení
--   8 <- 7 Fotografování v ateliéru
--
-- The rows themselves are renamed (block_key, label, sort_order), so every
-- card keeps its own - possibly admin-edited - content. Every UPDATE is a
-- single-table statement; the guards are read into user variables first, so
-- no statement reads content_blocks while writing it and no temporary table
-- is needed (production has no TEMPORARY TABLE privilege).
--
-- Every phase has its own guard, so a re-run is a no-op and a run that
-- stopped after any phase finishes the remaining phases when repeated.
-- Historical pricing migrations remain immutable.

-- Phase 1 guard: cenik pages whose cards 1, 4, 5 and 8 are still in the old
-- order. Each card is recognised by its title OR its CTA link, so an admin
-- edit of one of the two does not hide the page.
SET @m094_old_order_pages = (
  SELECT GROUP_CONCAT(old_order.page_id)
  FROM (
    SELECT cb.page_id
    FROM `content_blocks` cb
    WHERE cb.page_id IN (SELECT p.id FROM `pages` p WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik')
      AND (
        (cb.block_key = 'cenik_card_1_title' AND cb.content = 'Dva kameramani')
        OR (cb.block_key = 'cenik_card_1_cta_url' AND cb.content = '/kontakt?sluzba=svatebni-video')
        OR (cb.block_key = 'cenik_card_4_title' AND cb.content = 'Celodenní svatební focení')
        OR (cb.block_key = 'cenik_card_4_cta_url' AND cb.content = '/kontakt?sluzba=foto-cely-den')
        OR (cb.block_key = 'cenik_card_5_title' AND cb.content = 'Kompletní balíček foto + video')
        OR (cb.block_key = 'cenik_card_5_cta_url' AND cb.content = '/kontakt?sluzba=foto-video-komplet')
        OR (cb.block_key = 'cenik_card_8_title' AND cb.content = 'Jen natáčení celodenní svatby')
        OR (cb.block_key = 'cenik_card_8_cta_url' AND cb.content = '/kontakt?sluzba=jen-natoceni-svatby')
      )
    GROUP BY cb.page_id
    HAVING COUNT(DISTINCT SUBSTRING(cb.block_key, 12, 1)) = 4
  ) old_order
);

-- Phase 1: move every card row to its new slot under a temporary prefix, so
-- the (page_id, block_key) unique key never sees two rows with the same key.
-- label and sort_order are assigned before block_key, while it still holds
-- the old slot. A single UPDATE is atomic, so it never leaves a page half moved.
UPDATE `content_blocks`
SET label = CONCAT(
        'Karta ',
        ELT(CAST(SUBSTRING(block_key, 12, 1) AS UNSIGNED), '4', '5', '6', '1', '2', '7', '8', '3'),
        ' - ',
        SUBSTRING(block_key, 14)
    ),
    sort_order = 10
        + 8 * (CAST(ELT(CAST(SUBSTRING(block_key, 12, 1) AS UNSIGNED), '4', '5', '6', '1', '2', '7', '8', '3') AS UNSIGNED) - 1)
        + FIELD(SUBSTRING(block_key, 14), 'title', 'desc', 'price', 'unit', 'category', 'badge', 'cta_label', 'cta_url') - 1,
    block_key = CONCAT(
        'm094_cenik_card_',
        ELT(CAST(SUBSTRING(block_key, 12, 1) AS UNSIGNED), '4', '5', '6', '1', '2', '7', '8', '3'),
        SUBSTRING(block_key, 13)
    ),
    updated_at = CURRENT_TIMESTAMP
WHERE FIND_IN_SET(page_id, @m094_old_order_pages) > 0
  AND block_key REGEXP '^cenik_card_[1-8]_(title|desc|price|unit|category|badge|cta_label|cta_url)$';

-- Phase 2: drop the temporary prefix. Unguarded on purpose: the prefix only
-- exists after phase 1, and phase 1 moved every card key of the page, so the
-- target keys are free. This also finishes a run that stopped after phase 1.
UPDATE `content_blocks`
SET block_key = SUBSTRING(block_key, 6)
WHERE page_id IN (SELECT p.id FROM `pages` p WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik')
  AND block_key REGEXP '^m094_cenik_card_[1-8]_';

-- Phase 3 guard: pages where the badge is still on Celodenní svatební focení,
-- which after the move sits in slot 1 next to Kompletní balíček in slot 2.
SET @m094_badge_pages = (
  SELECT GROUP_CONCAT(badge_move.page_id)
  FROM (
    SELECT cb.page_id
    FROM `content_blocks` cb
    WHERE cb.page_id IN (SELECT p.id FROM `pages` p WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik')
      AND (
        (cb.block_key = 'cenik_card_1_badge' AND cb.content = 'Nejžádanější')
        OR (cb.block_key = 'cenik_card_1_cta_url' AND cb.content = '/kontakt?sluzba=foto-cely-den')
        OR (cb.block_key = 'cenik_card_2_cta_url' AND cb.content = '/kontakt?sluzba=foto-video-komplet')
      )
    GROUP BY cb.page_id
    HAVING COUNT(DISTINCT cb.block_key) = 3
  ) badge_move
);

-- Phase 3: the badge (and with it .ckp-card--featured and the autoscroll
-- target) moves from Celodenní svatební focení to Kompletní balíček.
UPDATE `content_blocks`
SET content = CASE block_key
        WHEN 'cenik_card_1_badge' THEN ''
        WHEN 'cenik_card_2_badge' THEN 'Nejžádanější'
        ELSE content
    END,
    updated_at = CURRENT_TIMESTAMP
WHERE FIND_IN_SET(page_id, @m094_badge_pages) > 0
  AND block_key IN ('cenik_card_1_badge', 'cenik_card_2_badge');

-- Phase 4, legacy static fallback blocks: same order and badge move, applied
-- only to the exact eight-card grid in the old order (its own guard).
-- Article n (1-based) without its closing tag is
--   SUBSTRING_INDEX(SUBSTRING_INDEX(content, '</article>', n), '</article>', -1),
-- the first one additionally carries the grid's opening tag as a prefix.
UPDATE `content_blocks`
SET content = CONCAT(
        SUBSTRING_INDEX(content, '<article', 1),
        REPLACE(
            SUBSTRING_INDEX(SUBSTRING_INDEX(content, '</article>', 4), '</article>', -1),
            '<article class="ckp-card ckp-card--featured"><span class="ckp-badge">Nejžádanější</span>',
            '<article class="ckp-card">'
        ), '</article>',
        REPLACE(
            SUBSTRING_INDEX(SUBSTRING_INDEX(content, '</article>', 5), '</article>', -1),
            '<article class="ckp-card">',
            '<article class="ckp-card ckp-card--featured"><span class="ckp-badge">Nejžádanější</span>'
        ), '</article>',
        SUBSTRING_INDEX(SUBSTRING_INDEX(content, '</article>', 8), '</article>', -1), '</article>',
        SUBSTRING(
            SUBSTRING_INDEX(content, '</article>', 1),
            CHAR_LENGTH(SUBSTRING_INDEX(content, '<article', 1)) + 1
        ), '</article>',
        SUBSTRING_INDEX(SUBSTRING_INDEX(content, '</article>', 2), '</article>', -1), '</article>',
        SUBSTRING_INDEX(SUBSTRING_INDEX(content, '</article>', 3), '</article>', -1), '</article>',
        SUBSTRING_INDEX(SUBSTRING_INDEX(content, '</article>', 6), '</article>', -1), '</article>',
        SUBSTRING_INDEX(SUBSTRING_INDEX(content, '</article>', 7), '</article>', -1), '</article>',
        SUBSTRING_INDEX(content, '</article>', -1)
    ),
    updated_at = CURRENT_TIMESTAMP
WHERE page_id IN (SELECT p.id FROM `pages` p WHERE TRIM(BOTH '/' FROM p.url_path) = 'cenik')
  AND block_key IN ('pricing_packages', 'pricing_body')
  AND (CHAR_LENGTH(content) - CHAR_LENGTH(REPLACE(content, '</article>', ''))) = 8 * CHAR_LENGTH('</article>')
  AND content LIKE '%>Dva kameramani</h3>%>Focení obřadu a portrétů</h3>%>Svatební focení do 8 hodin</h3>%>Celodenní svatební focení</h3>%>Kompletní balíček foto + video</h3>%>Portrétní a rodinné focení</h3>%>Fotografování v ateliéru</h3>%>Jen natáčení celodenní svatby</h3>%';
