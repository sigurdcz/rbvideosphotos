-- Add the verified Greek casino advertising signature to saved profiles.
-- NULL/empty profiles continue to use application defaults.
UPDATE sites
SET contact_spam_config_json = JSON_SET(contact_spam_config_json, '$.campaign_rules', JSON_ARRAY())
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_CONTAINS_PATH(contact_spam_config_json, 'one', '$.campaign_rules') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(
    contact_spam_config_json,
    '$.campaign_rules',
    'καζίνο && μπόνους && δωρεάν περιστροφές && κατάθεση'
)
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_TYPE(JSON_EXTRACT(contact_spam_config_json, '$.campaign_rules')) = 'ARRAY'
  AND JSON_CONTAINS(
      contact_spam_config_json,
      JSON_QUOTE('καζίνο && μπόνους && δωρεάν περιστροφές && κατάθεση'),
      '$.campaign_rules'
  ) = 0;
