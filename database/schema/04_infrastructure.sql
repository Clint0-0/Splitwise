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
  CONSTRAINT pk_app_sessions PRIMARY KEY (session_id),
  INDEX idx_app_sessions_activity (last_activity)
) ENGINE=InnoDB;

-- Small expiring counters/values: login-throttle counters, the mock-mail cursor.
CREATE TABLE app_kv (
  kv_key     VARCHAR(100) NOT NULL,
  kv_value   VARCHAR(255) NOT NULL,
  expires_at INT UNSIGNED NULL,          -- NULL = never expires
  CONSTRAINT pk_app_kv PRIMARY KEY (kv_key),
  INDEX idx_app_kv_expires (expires_at)
) ENGINE=InnoDB;
