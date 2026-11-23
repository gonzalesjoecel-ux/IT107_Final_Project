-- Run this ONCE in phpMyAdmin (after items_setup.sql and items_upload_update.sql):
-- select your database -> SQL tab -> paste -> Go

-- 1) New columns for users: role, active/disabled, last login
ALTER TABLE users
  ADD COLUMN role       VARCHAR(10) NOT NULL DEFAULT 'user',   -- 'user' or 'admin'
  ADD COLUMN is_active  TINYINT(1)  NOT NULL DEFAULT 1,        -- 0 = disabled by admin
  ADD COLUMN last_login DATETIME    NULL;

-- 2) Activity log (what admins monitor)
CREATE TABLE IF NOT EXISTS activity_log (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NULL,                -- the account the event is about
  actor_id   INT NULL,                -- who did it (only filled for admin actions)
  event      VARCHAR(40)  NOT NULL,   -- login_ok, login_fail, account_locked, logout, item_add, ...
  detail     VARCHAR(255) NULL,
  ip         VARCHAR(45)  NULL,
  created_at DATETIME     NOT NULL,
  INDEX idx_log_user  (user_id),
  INDEX idx_log_event (event),
  INDEX idx_log_time  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Make YOUR account an admin.

UPDATE users SET role = 'admin' WHERE username = 'admon123';


-- =====================================================================
-- STEP 2 (new): "Forgot password" requests
-- Already ran everything above? Then run ONLY this part.
-- (XAMPP uses MariaDB, which understands IF NOT EXISTS. On plain MySQL,
--  delete the words "IF NOT EXISTS".)
-- =====================================================================
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_requested_at DATETIME NULL;