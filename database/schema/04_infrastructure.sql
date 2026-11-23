SET NAMES utf8mb4;
-- =====================================================================
-- SplitWise+ | 04_infrastructure.sql — shared state for multi-instance hosting
-- (Phase 5.5). On Vercel each request may reach a different container and
-- the container disk is thrown away, so state that used to live in files
-- lives in MySQL instead. Neither table holds business data.
-- =====================================================================
USE splitwise_plus;

-- PHP sessions (App\Core\DbSessionHandler). Unix timestamps avoid time-zone mix-ups.
CREATE TABLE app_sessions (
  session_id    VARCHAR(128)  NOT NULL,
  payload       MEDIUMBLOB    NOT NULL,
  last_activity INT UNSIGNED  NOT NULL,
  user_id       INT UNSIGNED  NULL,       -- who is logged in (Phase 6: "log out everywhere" after a password reset)
  CONSTRAINT pk_app_sessions PRIMARY KEY (session_id),
  INDEX idx_app_sessions_activity (last_activity),
  INDEX idx_app_sessions_user (user_id)
) ENGINE=InnoDB;

-- Small expiring counters/values: login-throttle counters, the mock-mail cursor.
CREATE TABLE app_kv (
  kv_key     VARCHAR(100) NOT NULL,
  kv_value   VARCHAR(255) NOT NULL,
  expires_at INT UNSIGNED NULL,          -- NULL = never expires
  CONSTRAINT pk_app_kv PRIMARY KEY (kv_key),
  INDEX idx_app_kv_expires (expires_at)
) ENGINE=InnoDB;

-- Password-reset tokens (Phase 6). Only the SHA-256 of the token is stored; the
-- token itself exists only in the e-mailed link. Single use, expires after 1 hour.
CREATE TABLE password_resets (
  token_hash  CHAR(64)     NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_password_resets PRIMARY KEY (token_hash),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT chk_password_resets_hash CHECK (REGEXP_LIKE(token_hash, '^[0-9a-f]{64}$', 'c')),
  INDEX idx_password_resets_user (user_id)
) ENGINE=InnoDB;
