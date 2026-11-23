-- =====================================================================
-- Encryptica — adds the picture/document columns to items
-- Run after items.sql.  Safe to run more than once (MariaDB 10.4+).
-- =====================================================================

USE `encryptica`;

ALTER TABLE `items`
  ADD COLUMN IF NOT EXISTS `file_name`   varchar(255)     DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `file_stored` varchar(80)      DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `file_mime`   varchar(100)     DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `file_size`   int(10) unsigned DEFAULT NULL;
