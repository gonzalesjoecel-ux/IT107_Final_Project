-- =====================================================================
-- IT 107 Final Project — registration / authentication update
-- Run ONCE in phpMyAdmin (XAMPP / MariaDB) on the `encryptica` database.
-- Every statement is safe to re-run (IF NOT EXISTS).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) users: registration fields required by the handout (section 4)
-- ---------------------------------------------------------------------
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS id_number          VARCHAR(9)  NULL UNIQUE,
  ADD COLUMN IF NOT EXISTS first_name         VARCHAR(25) NULL,
  ADD COLUMN IF NOT EXISTS middle_name        VARCHAR(25) NULL,
  ADD COLUMN IF NOT EXISTS family_name        VARCHAR(25) NULL,
  ADD COLUMN IF NOT EXISTS name_extension     VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS birthdate          DATE        NULL,
  ADD COLUMN IF NOT EXISTS house_street       VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS barangay           VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS municipality_city  VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS province           VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS country            VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS postal_code        VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS privacy_consent    TINYINT(1)  NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS consent_timestamp  DATETIME    NULL,
  ADD COLUMN IF NOT EXISTS lockout_level      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS last_failed_at     DATETIME    NULL,
  ADD COLUMN IF NOT EXISTS is_verified        TINYINT(1)  NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS recovery_mobile    VARCHAR(11) NULL,
  ADD COLUMN IF NOT EXISTS account_type       VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS vault_display_name VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS organization_name  VARCHAR(50) NULL;

-- `is_verified` defaults to 1 for existing accounts. Switch it to 0 when
-- the registration OTP step (handout section 6) is implemented.

-- ---------------------------------------------------------------------
-- 2) Email verification / login / reset OTPs (section 6)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS otp_codes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NULL,
  identifier  VARCHAR(150) NOT NULL,
  purpose     ENUM('registration','login','forgot_password') NOT NULL,
  otp_hash    VARCHAR(255) NOT NULL,
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  ip_address  VARCHAR(45) NULL,
  INDEX idx_otp_identifier (identifier),
  INDEX idx_otp_purpose (purpose),
  INDEX idx_otp_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3) Password history — last three hashes, reuse refused (section 12.7)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_history (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_password_history_user (user_id, created_at),
  CONSTRAINT fk_password_history_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4) Security answers, stored hashed (section 5)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_security_answers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  question_code VARCHAR(10) NOT NULL,
  answer_hash   VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_question (user_id, question_code),
  CONSTRAINT fk_security_answers_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5) Security flags written by the monitoring script (section 14)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS security_flags (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  pattern_type VARCHAR(100) NOT NULL,
  target_type  VARCHAR(50)  NOT NULL,
  target_value VARCHAR(150) NOT NULL,
  details      TEXT NULL,
  detected_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status       VARCHAR(20) NOT NULL DEFAULT 'OPEN',
  INDEX idx_flag_pattern (pattern_type),
  INDEX idx_flag_target (target_type, target_value),
  INDEX idx_flag_detected (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6) IP blocks — failures across any account (section 12.5)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ip_blocks (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  ip_address      VARCHAR(45) NOT NULL,
  reason          VARCHAR(255) NULL,
  failed_attempts INT NOT NULL DEFAULT 0,
  blocked_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  blocked_until   DATETIME NOT NULL,
  INDEX idx_ip_address (ip_address),
  INDEX idx_ip_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7) Quick verification
-- ---------------------------------------------------------------------
SELECT id_number, first_name, family_name, country, is_verified,
       recovery_mobile, account_type, vault_display_name
FROM users LIMIT 1;
