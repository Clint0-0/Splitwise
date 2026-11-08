-- =====================================================================
-- SplitWise+ | demo_acid.sql — transactions and ACID on real operations
-- Safe to run on the sample data: every demo ends in ROLLBACK or undoes itself.
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;
SET @app_user_id = 2;

-- ---------------------------------------------------------------------
-- 1. ATOMICITY — expense header + splits succeed or fail together
-- ---------------------------------------------------------------------
START TRANSACTION;
  INSERT INTO expenses (group_id, paid_by, created_by, category_id, description,
                        total_amount, split_method, expense_date)
  VALUES (1, 2, 2, 2, 'Lunch at Thalassa', 2000.00, 'exact', '2026-10-05');
  SET @x = LAST_INSERT_ID();
  INSERT INTO expense_splits (expense_id, user_id, share_amount) VALUES
    (@x, 2, 500.00), (@x, 3, 500.00), (@x, 4, 500.00);   -- only 1500 of 2000!
  -- the application checks the gap before COMMIT:
  SELECT fn_expense_split_gap(@x) AS gap_must_be_zero;   -- 500.00 -> invalid
ROLLBACK;                                                 -- nothing is kept
SELECT COUNT(*) AS lunch_rows_after_rollback FROM expenses WHERE description = 'Lunch at Thalassa';

-- ---------------------------------------------------------------------
-- 2. SAVEPOINT — keep the expense, redo only the splits
-- ---------------------------------------------------------------------
START TRANSACTION;
  INSERT INTO expenses (group_id, paid_by, created_by, category_id, description,
                        total_amount, split_method, expense_date)
  VALUES (1, 2, 2, 2, 'Lunch at Thalassa', 2000.00, 'exact', '2026-10-05');
  SET @x = LAST_INSERT_ID();
  SAVEPOINT before_splits;
  INSERT INTO expense_splits (expense_id, user_id, share_amount) VALUES (@x, 2, 1500.00);
  ROLLBACK TO SAVEPOINT before_splits;                    -- undo the wrong split only
  INSERT INTO expense_splits (expense_id, user_id, share_amount) VALUES
    (@x, 2, 500.00), (@x, 3, 500.00), (@x, 4, 500.00), (@x, 5, 500.00);
  SELECT fn_expense_split_gap(@x) AS gap_now;             -- 0.00
ROLLBACK;                                                 -- demo only: discard

-- ---------------------------------------------------------------------
-- 3. CONSISTENCY — constraints and triggers reject invalid states
-- ---------------------------------------------------------------------
-- (each statement below fails; run one at a time to see the error)
-- INSERT INTO settlements (group_id,payer_id,payee_id,amount,settlement_date,created_by,request_token)
--   VALUES (1, 2, 2, 100, '2026-10-10', 2, 'demo-self');           -- chk_settlements_parties
-- INSERT INTO expenses (group_id,paid_by,created_by,category_id,description,total_amount,expense_date)
--   VALUES (1, 6, 6, 2, 'Not a member', 100, '2026-10-10');         -- trigger: payer not member
-- CALL sp_record_settlement(1, 4, 2, 99999, '2026-10-10', 'cash', NULL, 2, 'demo-over', @s);  -- over-settle

-- Group balance invariant: always 0 per group
SELECT group_id, SUM(net_balance) AS must_be_zero FROM v_group_member_balances GROUP BY group_id;

-- ---------------------------------------------------------------------
-- 4. ISOLATION — concurrent settlements (run in TWO mysql sessions)
-- ---------------------------------------------------------------------
-- Session A                                   Session B
-- START TRANSACTION;
-- SELECT * FROM group_members
--  WHERE group_id = 1 FOR UPDATE;              CALL sp_record_settlement(1,4,2,100,
--                                                 '2026-11-08','cash',NULL,2,'demo-b',@s);
--                                              -- B WAITS (blocked on the row locks)
-- COMMIT;                                      -- B continues and re-reads fresh balances
-- sp_record_settlement locks the group's member rows first, so two settlements for
-- the same group are validated one after another and cannot both overspend.
SELECT @@transaction_isolation AS isolation_level;      -- REPEATABLE-READ (InnoDB default)

-- ---------------------------------------------------------------------
-- 5. DURABILITY — after COMMIT the row survives a server restart
--    (InnoDB redo log; innodb_flush_log_at_trx_commit = 1)
-- ---------------------------------------------------------------------
SELECT @@innodb_flush_log_at_trx_commit AS flush_on_commit;

SET @app_user_id = NULL;
