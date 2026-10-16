-- Prepinac automatickeho prehravani videi (hero na homepage, Tomas Sincl na /o-nas).
-- Vychozi 1, aby se zachovalo chovani, ktere na webu uz bezi.
ALTER TABLE `sites`
  ADD COLUMN `video_autoplay_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `default_hero_video_url`;
