-- =====================================================================
-- SplitWise+ | demo_views.sql — Experiment 9 view operations
-- Run after the full import. Leaves the database unchanged.
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

-- SHOW TABLES lists tables AND views; SHOW FULL TABLES shows which is which
SHOW TABLES;
SHOW FULL TABLES WHERE Table_type = 'VIEW';

-- CREATE VIEW (simple, single table)
DROP VIEW IF EXISTS v_demo_upi_settlements;
CREATE VIEW v_demo_upi_settlements AS
SELECT settlement_id, payer_id, payee_id, amount, note
  FROM settlements
 WHERE method = 'upi';
SELECT * FROM v_demo_upi_settlements;

-- CREATE OR REPLACE VIEW: change the definition (add status column)
CREATE OR REPLACE VIEW v_demo_upi_settlements AS
SELECT settlement_id, payer_id, payee_id, amount, status, note
  FROM settlements
 WHERE method = 'upi';
SELECT * FROM v_demo_upi_settlements;

-- DROP VIEW
DROP VIEW v_demo_upi_settlements;

-- Which views are updatable? (information_schema says YES/NO)
SELECT TABLE_NAME, IS_UPDATABLE
  FROM information_schema.VIEWS
 WHERE TABLE_SCHEMA = 'splitwise_plus'
 ORDER BY IS_UPDATABLE DESC, TABLE_NAME;

-- Updating THROUGH an updatable view changes the base table
START TRANSACTION;
  UPDATE v_active_expenses SET notes = 'Edited through a view' WHERE expense_id = 1;
  SELECT expense_id, notes FROM expenses WHERE expense_id = 1;
ROLLBACK;

-- WITH CHECK OPTION: a change that would push the row out of the view is rejected
-- UPDATE v_active_expenses SET is_deleted = 1 WHERE expense_id = 1;
--   -> ERROR 1369 (HY000): CHECK OPTION failed 'splitwise_plus.v_active_expenses'

-- Aggregate views are not updatable
-- UPDATE v_group_expense_summary SET total_spent = 0 WHERE group_id = 1;
--   -> ERROR 1288 (HY000): The target table v_group_expense_summary of the UPDATE is not updatable

-- View built on another view
SHOW CREATE VIEW v_user_overall_balance;
