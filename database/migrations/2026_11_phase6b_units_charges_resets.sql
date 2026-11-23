SET NAMES utf8mb4;
-- =====================================================================
-- SplitWise+ | Phase 6 migration, part 2 (run AFTER 2026_11_phase6_itemized_upi.sql)
-- Adds: expense_item_shares.share_units (uneven / quantity-based item
-- shares), expense_charges (tax, service charge, discount lines),
-- password_resets and app_sessions.user_id (log out everywhere).
-- Non-destructive. Back up first. Run ONCE as root, then re-run
-- database/security/app_user.sql (with YOUR app password).
-- =====================================================================
USE splitwise_plus;

ALTER TABLE expense_item_shares
  ADD COLUMN share_units DECIMAL(6,2) NOT NULL DEFAULT 1.00 AFTER user_id,
  ADD CONSTRAINT chk_item_shares_units CHECK (share_units > 0);

CREATE TABLE expense_charges (
  charge_id   INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  expense_id  INT UNSIGNED     NOT NULL,
  line_no     TINYINT UNSIGNED NOT NULL,
  kind        ENUM('tax','service','discount','other') NOT NULL,
  label       VARCHAR(60)      NOT NULL,
  amount      DECIMAL(12,2)    NOT NULL,
  CONSTRAINT pk_expense_charges PRIMARY KEY (charge_id),
  CONSTRAINT uq_expense_charges_line UNIQUE (expense_id, line_no),
  CONSTRAINT fk_expense_charges_expense FOREIGN KEY (expense_id)
    REFERENCES expenses (expense_id) ON DELETE CASCADE,
  CONSTRAINT chk_expense_charges_line CHECK (line_no BETWEEN 1 AND 10),
  CONSTRAINT chk_expense_charges_label CHECK (CHAR_LENGTH(TRIM(label)) > 0),
  CONSTRAINT chk_expense_charges_sign CHECK (
    (kind = 'discount' AND amount < 0) OR (kind IN ('tax','service') AND amount > 0)
    OR (kind = 'other' AND amount <> 0))
) ENGINE=InnoDB;

ALTER TABLE app_sessions
  ADD COLUMN user_id INT UNSIGNED NULL AFTER last_activity,
  ADD INDEX idx_app_sessions_user (user_id);

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

SELECT 'Phase 6 part 2 applied. Now re-run database/security/app_user.sql.' AS next_step;
