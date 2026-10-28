-- Add this campaign phrase once, preserving all administrator settings.
-- NULL/empty profiles use the updated application defaults.
UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(
    contact_spam_config_json, '$.known_phrases', 'расширенная информация в отношении физ лиц'
)
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('расширенная информация в отношении физ лиц'), '$.known_phrases') = 0;
