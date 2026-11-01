-- Append narrow campaign combinations; retain every administrator setting.
-- NULL/empty profiles use defaults. Legacy profiles may lack campaign_rules.
UPDATE sites
SET contact_spam_config_json = JSON_SET(contact_spam_config_json, '$.campaign_rules', JSON_ARRAY())
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_CONTAINS_PATH(contact_spam_config_json, 'one', '$.campaign_rules') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(contact_spam_config_json, '$.campaign_rules', 'драгон мани && пополнение и вывод && подробно разобрано здесь')
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_TYPE(JSON_EXTRACT(contact_spam_config_json, '$.campaign_rules')) = 'ARRAY'
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('драгон мани && пополнение и вывод && подробно разобрано здесь'), '$.campaign_rules') = 0;

UPDATE sites
SET contact_spam_config_json = JSON_ARRAY_APPEND(contact_spam_config_json, '$.campaign_rules', 'roberthip && kam dashur të di çmimin tuaj')
WHERE contact_spam_config_json IS NOT NULL
  AND TRIM(contact_spam_config_json) <> ''
  AND JSON_TYPE(JSON_EXTRACT(contact_spam_config_json, '$.campaign_rules')) = 'ARRAY'
  AND JSON_CONTAINS(contact_spam_config_json, JSON_QUOTE('roberthip && kam dashur të di çmimin tuaj'), '$.campaign_rules') = 0;
