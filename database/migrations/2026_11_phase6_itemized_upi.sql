SET NAMES utf8mb4;
-- =====================================================================
-- SplitWise+ | Phase 6 migration for an EXISTING splitwise_plus database
-- Adds: itemized bills (expense_items, expense_item_shares, 'itemized'
-- split method, v_expense_item_allocation) and users.upi_id.
--
-- Non-destructive: no table is dropped, no row is changed. Back up first:
--   mysqldump -u root -p --routines --triggers splitwise_plus > backup_before_phase6.sql
-- Run as root:
--   mysql -u root -p < database/migrations/2026_11_phase6_itemized_upi.sql
-- Then re-run database/security/app_user.sql (with YOUR app password) so the
-- app account may write the two new tables.
-- A fresh install from splitwise_plus.sql already contains all of this.
-- Run it ONCE. A second run stops at the first ALTER (error 3822 "Duplicate check
-- constraint name") without changing anything (each ALTER is atomic).
-- =====================================================================
USE splitwise_plus;

ALTER TABLE users
  ADD COLUMN upi_id VARCHAR(100) NULL AFTER notify_email,
  ADD CONSTRAINT chk_users_upi_id CHECK (upi_id IS NULL
    OR REGEXP_LIKE(upi_id, '^[A-Za-z0-9._-]{2,64}@[A-Za-z][A-Za-z0-9]{1,63}$', 'c'));

ALTER TABLE expenses
  MODIFY split_method ENUM('equal','percentage','exact','shares','adjustment','itemized') NOT NULL DEFAULT 'equal';

CREATE TABLE expense_items (
  item_id     INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  expense_id  INT UNSIGNED     NOT NULL,
  line_no     TINYINT UNSIGNED NOT NULL,
  name        VARCHAR(80)      NOT NULL,
  quantity    DECIMAL(7,2)     NOT NULL DEFAULT 1.00,
  amount      DECIMAL(12,2)    NOT NULL,
  CONSTRAINT pk_expense_items PRIMARY KEY (item_id),
  CONSTRAINT uq_expense_items_line UNIQUE (expense_id, line_no),
  CONSTRAINT uq_expense_items_item_expense UNIQUE (item_id, expense_id),
  CONSTRAINT fk_expense_items_expense FOREIGN KEY (expense_id)
    REFERENCES expenses (expense_id) ON DELETE CASCADE,
  CONSTRAINT chk_expense_items_line CHECK (line_no BETWEEN 1 AND 50),
  CONSTRAINT chk_expense_items_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
  CONSTRAINT chk_expense_items_qty CHECK (quantity > 0),
  CONSTRAINT chk_expense_items_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE expense_item_shares (
  item_id       INT UNSIGNED  NOT NULL,
  expense_id    INT UNSIGNED  NOT NULL,
  user_id       INT UNSIGNED  NOT NULL,
  share_amount  DECIMAL(12,2) NOT NULL,
  CONSTRAINT pk_expense_item_shares PRIMARY KEY (item_id, user_id),
  CONSTRAINT fk_item_shares_item FOREIGN KEY (item_id, expense_id)
    REFERENCES expense_items (item_id, expense_id) ON DELETE CASCADE,
  CONSTRAINT fk_item_shares_split FOREIGN KEY (expense_id, user_id)
    REFERENCES expense_splits (expense_id, user_id) ON DELETE CASCADE,
  CONSTRAINT chk_item_shares_amount CHECK (share_amount >= 0)
) ENGINE=InnoDB;

CREATE OR REPLACE VIEW v_expense_item_allocation AS
SELECT s.expense_id, s.user_id, u.full_name,
       COALESCE(SUM(sh.share_amount), 0.00)                  AS items_subtotal,
       s.share_amount - COALESCE(SUM(sh.share_amount), 0.00) AS extras_share,
       s.share_amount,
       COUNT(sh.item_id)                                     AS item_count
  FROM expense_splits s
  JOIN expenses e ON e.expense_id = s.expense_id AND e.split_method = 'itemized'
  JOIN users u    ON u.user_id = s.user_id
  LEFT JOIN expense_item_shares sh
         ON sh.expense_id = s.expense_id AND sh.user_id = s.user_id
 GROUP BY s.expense_id, s.user_id, u.full_name, s.share_amount;

SELECT 'Phase 6 migration applied. Now re-run database/security/app_user.sql.' AS next_step;
