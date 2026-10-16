UPDATE `contact_info`
SET `reviews_url` = 'https://maps.app.goo.gl/DN9Y1ohrSgAePg3n9'
WHERE `reviews_url` IS NULL
   OR TRIM(`reviews_url`) = ''
   OR `reviews_url` = 'https://maps.app.goo.gl/mi3f1qMJENJY2bVN7';
