-- Replace the sixth service copy without rewriting historical migration 052.
UPDATE `service_items`
SET `title` = 'Letecké záběry dronem',
    `description` = 'Letecké záběry místa, příjezdu novomanželů i okolní krajiny dodají svatebnímu filmu působivou perspektivu a atmosféru.'
WHERE `sort_order` = 6
  AND (`title` = 'Dron & same-day edit' OR `title` = 'Letecké záběry dronem');
