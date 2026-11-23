-- =====================================================================
-- Encryptica — items table (title + encrypted content)
-- Run after admin_Setup.sql.  Then run items_upload_update.sql.
-- =====================================================================

USE `encryptica`;

CREATE TABLE IF NOT EXISTS `items` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)      NOT NULL,
  `title`       varchar(120) NOT NULL,
  `content_enc` text         NOT NULL,
  `created_at`  timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_items_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
