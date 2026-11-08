SET NAMES utf8mb4;
-- =====================================================================
-- SplitWise+ | demo_triggers.sql — Experiment 8 trigger operations
-- Run after the full import. Leaves the database unchanged:
-- every data change is rolled back; the demo trigger is dropped at the end.
-- =====================================================================
USE splitwise_plus;
SET @app_user_id = 2;

-- ---------------------------------------------------------------------
-- 1. SHOW TRIGGERS — list all triggers, then only those on one table
-- ---------------------------------------------------------------------
SHOW TRIGGERS;
SHOW TRIGGERS LIKE 'settlements';
SHOW TRIGGERS WHERE `Timing` = 'BEFORE';

-- Same information from the data dictionary: all 6 timing/event pairs are covered
SELECT ACTION_TIMING, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, TRIGGER_NAME, ACTION_ORIENTATION
  FROM information_schema.TRIGGERS
 WHERE TRIGGER_SCHEMA = 'splitwise_plus'
 ORDER BY FIELD(ACTION_TIMING, 'BEFORE', 'AFTER'),
          FIELD(EVENT_MANIPULATION, 'INSERT', 'UPDATE', 'DELETE'), EVENT_OBJECT_TABLE;
-- ACTION_ORIENTATION is always ROW: MySQL has no statement-level triggers.

SHOW CREATE TRIGGER trg_settlements_before_update;

-- ---------------------------------------------------------------------
-- 2. Each timing/event pair firing on real data (rolled back)
-- ---------------------------------------------------------------------
START TRANSACTION;

-- BEFORE INSERT (NEW is changed before it is stored): description is trimmed
-- (plain INSERTs, not sp_add_equal_expense: a procedure that COMMITs would end this demo transaction)
INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date)
VALUES (1, 2, 2, 2, '   Late-night chai   ', 120.00, 'equal', '2026-10-05');
SET @demo = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount) VALUES (@demo, 2, 60.00), (@demo, 3, 60.00);
SELECT description AS trimmed_by_before_insert FROM expenses WHERE expense_id = @demo;

-- AFTER INSERT: audit row and a notification for Priya were written by triggers
SELECT action, new_data FROM audit_logs WHERE table_name = 'expenses' AND record_id = @demo;
SELECT user_id, type, message FROM notifications WHERE entity_type = 'expense' AND entity_id = @demo;

-- AFTER UPDATE: OLD and NEW values both captured; soft delete classified
UPDATE expenses SET is_deleted = 1, deleted_at = NOW(), deleted_by = 2 WHERE expense_id = @demo;
SELECT action, JSON_EXTRACT(old_data, '$.is_deleted') AS old_flag,
               JSON_EXTRACT(new_data, '$.is_deleted') AS new_flag
  FROM audit_logs WHERE table_name = 'expenses' AND record_id = @demo ORDER BY log_id;

-- AFTER DELETE: removed split rows are archived using OLD
DELETE FROM expense_splits WHERE expense_id = @demo;
SELECT action, old_data FROM audit_logs WHERE table_name = 'expense_splits'
 ORDER BY log_id DESC LIMIT 2;

-- BEFORE UPDATE: pending -> rejected is allowed and responded_at is set via NEW
UPDATE settlements SET status = 'rejected' WHERE settlement_id = 3;
SELECT settlement_id, status, responded_at FROM settlements WHERE settlement_id = 3;

ROLLBACK;

-- BEFORE UPDATE / BEFORE DELETE rejecting invalid changes (each raises SQLSTATE 45000):
-- UPDATE settlements SET status = 'cancelled' WHERE settlement_id = 1;
--   -> Settlement is final (confirmed/rejected/cancelled) and cannot be changed
-- DELETE FROM settlements WHERE settlement_id = 1;
--   -> Confirmed settlements cannot be deleted; record a reverse settlement instead
-- INSERT INTO expenses (group_id,paid_by,created_by,category_id,description,total_amount,expense_date)
--   VALUES (1, 6, 6, 2, 'Kabir is not in Goa group', 100, '2026-10-05');
--   -> Payer must be an active member of the group

-- ---------------------------------------------------------------------
-- 3. Trigger lifecycle: CREATE -> SHOW -> fire -> DROP
--    Demo trigger: store usernames in lower case (BEFORE INSERT ON users).
-- ---------------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_demo_users_lowercase_username;

DELIMITER $$
CREATE TRIGGER trg_demo_users_lowercase_username
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
  SET NEW.username = LOWER(NEW.username);
  SET NEW.email    = LOWER(NEW.email);
END$$
DELIMITER ;

SHOW TRIGGERS LIKE 'users';

START TRANSACTION;
  INSERT INTO users (username, email, password_hash, full_name)
  VALUES ('Demo_User', 'Demo.User@SplitwisePlus.TEST', 'x', 'Demo User');
  SELECT username, email FROM users WHERE full_name = 'Demo User';   -- demo_user, demo.user@splitwiseplus.test
ROLLBACK;

DROP TRIGGER trg_demo_users_lowercase_username;
SHOW TRIGGERS LIKE 'users';                                           -- empty again

SET @app_user_id = NULL;
