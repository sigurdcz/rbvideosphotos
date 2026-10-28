-- Append this campaign signature without replacing administrator rules/settings.
-- NULL/empty profiles use the updated application defaults.
UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(
    contact_spam_config_json, '$.known_phrases', 'voici un article tres interessant a lire pendant son temps libre'
)
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('voici un article tres interessant a lire pendant son temps libre'), '$.known_phrases') = 0;
