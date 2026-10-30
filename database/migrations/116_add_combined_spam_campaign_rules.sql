-- Add explicit AND-fragment campaign rules; preserve administrator settings.
-- NULL/empty profiles continue to use application defaults.
UPDATE sites
SET contact_spam_config_json = JSON_SET(contact_spam_config_json, '$.campaign_rules', JSON_ARRAY())
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_CONTAINS_PATH(contact_spam_config_json, 'one', '$.campaign_rules') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(contact_spam_config_json, '$.campaign_rules', 'qualifies them and books the appointment && blue phone icon && 14 days')
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_TYPE(JSON_EXTRACT(contact_spam_config_json, '$.campaign_rules')) = 'ARRAY'
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('qualifies them and books the appointment && blue phone icon && 14 days'), '$.campaign_rules') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(contact_spam_config_json, '$.campaign_rules', 'read the news && per month && 50,000,000')
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_TYPE(JSON_EXTRACT(contact_spam_config_json, '$.campaign_rules')) = 'ARRAY'
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('read the news && per month && 50,000,000'), '$.campaign_rules') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(contact_spam_config_json, '$.campaign_rules', 'loveshop && shop1-biz && nft')
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_TYPE(JSON_EXTRACT(contact_spam_config_json, '$.campaign_rules')) = 'ARRAY'
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('loveshop && shop1-biz && nft'), '$.campaign_rules') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(contact_spam_config_json, '$.campaign_rules', 'aiflipdomains && lifetime access && one-time')
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_TYPE(JSON_EXTRACT(contact_spam_config_json, '$.campaign_rules')) = 'ARRAY'
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('aiflipdomains && lifetime access && one-time'), '$.campaign_rules') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(contact_spam_config_json, '$.campaign_rules', 'verstorbenen mandanten && 50-50 && millionen us-dollar')
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_TYPE(JSON_EXTRACT(contact_spam_config_json, '$.campaign_rules')) = 'ARRAY'
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('verstorbenen mandanten && 50-50 && millionen us-dollar'), '$.campaign_rules') = 0;
