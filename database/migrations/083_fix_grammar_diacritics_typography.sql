-- Content-quality audit (2026-09-23) flagged a few mechanical grammar/typography
-- errors in admin-edited, live-only content (homepage service cards + the
-- reviews section label). Uses REPLACE() on the exact flagged substring rather
-- than an exact full-block match or a content-match guard, since these blocks
-- are edited through the admin panel and the surrounding copy is not tracked
-- here - REPLACE() only touches the specific substring and is a no-op (safe to
-- re-run) once applied. Deliberately does NOT touch review/testimonial text
-- from this same audit run (a missing comma inside a customer quote) - reviews
-- must stay verbatim/authentic, per explicit instruction.
UPDATE `content_blocks`
SET `content` = REPLACE(`content`, 'Tří až pětiminutový', 'Tří- až pětiminutový')
WHERE `content` LIKE '%Tří až pětiminutový%';

UPDATE `content_blocks`
SET `content` = REPLACE(`content`, 'od příprav po večerní zábavu', 'od příprav až po večerní zábavu')
WHERE `content` LIKE '%od příprav po večerní zábavu%';

UPDATE `content_blocks`
SET `content` = REPLACE(`content`, 'Co o nas rikaji', 'Co o nás říkají')
WHERE `content` LIKE '%Co o nas rikaji%';
