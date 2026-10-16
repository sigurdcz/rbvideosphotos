UPDATE `sites`
SET `layout_nav_home` = REPLACE(`layout_nav_home`, 'href="#faq"', 'href="/faq"')
WHERE `layout_nav_home` LIKE '%href="#faq"%';
