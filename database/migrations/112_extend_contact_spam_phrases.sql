-- Extend saved profiles without replacing administrator settings. NULL profiles
-- use ContactSpamConfig::defaults(). Malformed JSON fails visibly in JSON functions.
UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(
    contact_spam_config_json, '$.known_phrases', 'pxqrrbvideosphotos.czpxqr'
)
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('pxqrrbvideosphotos.czpxqr'), '$.known_phrases') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(
    contact_spam_config_json, '$.known_phrases', 'предлагаем кассовые чеки'
)
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('предлагаем кассовые чеки'), '$.known_phrases') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(
    contact_spam_config_json, '$.known_phrases', 'отчетные закрывающие документы'
)
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('отчетные закрывающие документы'), '$.known_phrases') = 0;
