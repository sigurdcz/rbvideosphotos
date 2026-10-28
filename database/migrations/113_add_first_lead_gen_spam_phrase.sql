-- Preserve all administrator settings and add this campaign signature once.
-- NULL/empty profiles use the updated application defaults.
UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(
    contact_spam_config_json, '$.known_phrases', 'you''re reading this because i just used your contact form'
)
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('you''re reading this because i just used your contact form'), '$.known_phrases') = 0;
