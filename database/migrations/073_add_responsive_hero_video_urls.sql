-- Hero video podle sirky okna: tablet a mobil jsou volitelne, prazdne = desktopove video.
-- Zamerne bez backfillu - dokud nikdo pole nevyplni, je vystup HTML bajt za bajt stejny
-- jako dnes, takze goldeny nepotrebuji regeneraci.

ALTER TABLE `sites`
  ADD COLUMN `default_hero_video_url_tablet` VARCHAR(1000) DEFAULT NULL AFTER `default_hero_video_url`,
  ADD COLUMN `default_hero_video_url_mobile` VARCHAR(1000) DEFAULT NULL AFTER `default_hero_video_url_tablet`;
