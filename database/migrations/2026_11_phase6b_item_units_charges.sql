SET NAMES utf8mb4;
-- =====================================================================
-- SplitWise+ | Phase 6 migration, part 2 (run AFTER 2026_11_phase6_itemized_upi.sql)
-- Adds: expense_item_shares.share_units (uneven / quantity-based item
-- shares) and expense_charges (tax, service charge, discount lines).
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

SELECT 'Phase 6 part 2 applied. Now re-run database/security/app_user.sql.' AS next_step;
