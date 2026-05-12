ALTER TABLE content_blocks
  ADD COLUMN autofill_disabled TINYINT(1) NOT NULL DEFAULT 0 AFTER content;
