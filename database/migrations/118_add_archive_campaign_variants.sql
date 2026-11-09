-- Append only the missing archive variants. Preserve switches, scores and custom lists.
UPDATE sites
SET contact_spam_config_json = JSON_SET(contact_spam_config_json, '$.campaign_rules', JSON_ARRAY())
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_CONTAINS_PATH(contact_spam_config_json, 'one', '$.campaign_rules') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(contact_spam_config_json, '$.campaign_rules', 'roberthip && achọrọ m ịmara ọnụahịa gị')
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_TYPE(JSON_EXTRACT(contact_spam_config_json, '$.campaign_rules')) = 'ARRAY'
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('roberthip && achọrọ m ịmara ọnụahịa gị'), '$.campaign_rules') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(contact_spam_config_json, '$.campaign_rules', 'roberthip && meg akartam tudni az árát')
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_TYPE(JSON_EXTRACT(contact_spam_config_json, '$.campaign_rules')) = 'ARRAY'
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('roberthip && meg akartam tudni az árát'), '$.campaign_rules') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(contact_spam_config_json, '$.campaign_rules', 'it handles questions && books the appointment && blue phone icon && 14 days')
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_TYPE(JSON_EXTRACT(contact_spam_config_json, '$.campaign_rules')) = 'ARRAY'
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('it handles questions && books the appointment && blue phone icon && 14 days'), '$.campaign_rules') = 0;
