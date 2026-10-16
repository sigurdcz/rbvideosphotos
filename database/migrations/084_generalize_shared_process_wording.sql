-- Content-quality audit (2026-09-23, consistency): the "Jak probíhá spolupráce"
-- section is a single shared_sections row injected onto EVERY service/portfolio
-- page regardless of occasion (SharedProcessRenderer::inject) - including
-- /reklamni-foto-video (corporate) and /rodinne-a-portretni-foceni (family),
-- where it wrongly claims "V den svatby ..." ("On your wedding day ...").
--
-- Of 8 text fields in the section only these 2 mention "svatba" specifically;
-- everything else is already occasion-neutral. Rewording just these two keeps
-- the section correct on every page type without building separate per-service
-- variants, which would be a much larger change for the same practical result.
UPDATE `shared_sections`
SET `content_json` = JSON_SET(
  `content_json`,
  '$.steps[2].text', 'V den focení nebo natáčení pracujeme nenápadně a zachytíme emoce, atmosféru i klíčové okamžiky.',
  '$.benefits[3].text', 'Každou zakázku bereme jako jedinečný příběh.'
)
WHERE `section_key` = 'process_cooperation'
  AND JSON_UNQUOTE(JSON_EXTRACT(`content_json`, '$.steps[2].text')) = 'V den svatby pracujeme nenápadně a zachytíme emoce, atmosféru i klíčové okamžiky.'
  AND JSON_UNQUOTE(JSON_EXTRACT(`content_json`, '$.benefits[3].text')) = 'Každou svatbu bereme jako jedinečný příběh.';
