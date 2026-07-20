-- Opravuje spatne prirazenou fotku u service polozky "Svatebni den — foto + video"
-- (sort_order 1): pouzivala fotku fotografu (media original_filename weare2.png,
-- urcenou pro sekci O nas / jb-about-visual__media), misto spravne fotky
-- nevesty a zenicha (svatba-nevesta-zenich-polibek-kamenna-zed.webp), ktera
-- je jiz spravne pouzita napr. u polozky "Svatebni klipy" a jako hero pozadi.

UPDATE service_items
SET image_url = '/storage/media/2026/07/img_6a5476313bfdf9.73040329.webp'
WHERE title = 'Svatební den — foto + video'
  AND sort_order = 1
  AND image_url = '/storage/media/2026/07/img_6a5476313a0dc3.03536396.png';
