ALTER TABLE `content_blocks`
  MODIFY `block_type` ENUM('text','textarea','richtext','image','link','html','video')
  NOT NULL DEFAULT 'text';
