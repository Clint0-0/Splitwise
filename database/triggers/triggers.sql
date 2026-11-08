-- =====================================================================
-- SplitWise+ | triggers.sql — Experiment 8
-- All six timing/event combinations, each with a business purpose.
-- MySQL triggers are row-level only (FOR EACH ROW); no COMMIT/ROLLBACK
-- inside; a trigger never modifies the table that fired it.
-- @app_user_id is set by the PHP app on each connection (who acted).
-- Caveat: FK cascades (e.g. ON DELETE CASCADE) do NOT fire triggers.
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

DROP TRIGGER IF EXISTS trg_expenses_before_insert;
DROP TRIGGER IF EXISTS trg_expenses_after_insert;
DROP TRIGGER IF EXISTS trg_expenses_after_update;
DROP TRIGGER IF EXISTS trg_expenses_after_delete;
DROP TRIGGER IF EXISTS trg_expense_splits_before_insert;
DROP TRIGGER IF EXISTS trg_expense_splits_after_insert;
DROP TRIGGER IF EXISTS trg_expense_splits_after_delete;
DROP TRIGGER IF EXISTS trg_settlements_after_insert;
DROP TRIGGER IF EXISTS trg_settlements_before_update;
DROP TRIGGER IF EXISTS trg_settlements_after_update;
DROP TRIGGER IF EXISTS trg_settlements_before_delete;

DELIMITER $$

-- BEFORE INSERT: validate a new expense ---------------------------------
CREATE TRIGGER trg_expenses_before_insert
BEFORE INSERT ON expenses
FOR EACH ROW
BEGIN
  SET NEW.description = TRIM(NEW.description);

  IF NEW.expense_date > CURDATE() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Expense date cannot be in the future';
  END IF;

  IF NEW.group_id IS NOT NULL THEN
    IF (SELECT is_archived FROM expense_groups WHERE group_id = NEW.group_id) = 1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot add expenses to an archived group';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM group_members
                    WHERE group_id = NEW.group_id AND user_id = NEW.paid_by
                      AND status = 'active') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payer must be an active member of the group';
    END IF;
  END IF;
END$$

-- AFTER INSERT: audit new expense ---------------------------------------
CREATE TRIGGER trg_expenses_after_insert
AFTER INSERT ON expenses
FOR EACH ROW
BEGIN
  INSERT INTO audit_logs (table_name, record_id, action, new_data, changed_by)
  VALUES ('expenses', NEW.expense_id, 'INSERT',
          JSON_OBJECT('group_id', NEW.group_id, 'paid_by', NEW.paid_by,
                      'description', NEW.description, 'total_amount', NEW.total_amount,
                      'split_method', NEW.split_method, 'expense_date', NEW.expense_date),
          @app_user_id);
END$$

-- AFTER UPDATE: audit OLD -> NEW, classify soft delete / restore, notify -
CREATE TRIGGER trg_expenses_after_update
AFTER UPDATE ON expenses
FOR EACH ROW
BEGIN
  DECLARE v_action VARCHAR(12);
  SET v_action = CASE
                   WHEN OLD.is_deleted = 0 AND NEW.is_deleted = 1 THEN 'SOFT_DELETE'
                   WHEN OLD.is_deleted = 1 AND NEW.is_deleted = 0 THEN 'RESTORE'
                   ELSE 'UPDATE' END;

  INSERT INTO audit_logs (table_name, record_id, action, old_data, new_data, changed_by)
  VALUES ('expenses', NEW.expense_id, v_action,
          JSON_OBJECT('description', OLD.description, 'total_amount', OLD.total_amount,
                      'paid_by', OLD.paid_by, 'category_id', OLD.category_id,
                      'split_method', OLD.split_method, 'expense_date', OLD.expense_date,
                      'is_deleted', OLD.is_deleted, 'version', OLD.version),
          JSON_OBJECT('description', NEW.description, 'total_amount', NEW.total_amount,
                      'paid_by', NEW.paid_by, 'category_id', NEW.category_id,
                      'split_method', NEW.split_method, 'expense_date', NEW.expense_date,
                      'is_deleted', NEW.is_deleted, 'version', NEW.version),
          @app_user_id);

  -- tell the other participants (not the person who made the change)
  IF NEW.group_id IS NOT NULL THEN
    INSERT INTO notifications (user_id, type, title, message, entity_type, entity_id)
    SELECT s.user_id,
           IF(v_action = 'SOFT_DELETE', 'expense_deleted', 'expense_updated'),
           IF(v_action = 'SOFT_DELETE', 'Expense deleted', 'Expense updated'),
           CONCAT('"', NEW.description, '" (₹', NEW.total_amount, ') was ',
                  LOWER(REPLACE(v_action, '_', ' ')), 'd'),
           'expense', NEW.expense_id
      FROM expense_splits s
     WHERE s.expense_id = NEW.expense_id
       AND s.user_id <> COALESCE(@app_user_id, 0);
  END IF;
END$$

-- AFTER DELETE: hard delete (admin cleanup only) is still audited -------
CREATE TRIGGER trg_expenses_after_delete
AFTER DELETE ON expenses
FOR EACH ROW
BEGIN
  INSERT INTO audit_logs (table_name, record_id, action, old_data, changed_by)
  VALUES ('expenses', OLD.expense_id, 'DELETE',
          JSON_OBJECT('group_id', OLD.group_id, 'paid_by', OLD.paid_by,
                      'description', OLD.description, 'total_amount', OLD.total_amount,
                      'expense_date', OLD.expense_date, 'is_deleted', OLD.is_deleted),
          @app_user_id);
END$$

-- BEFORE INSERT: participant must be allowed in this expense ------------
CREATE TRIGGER trg_expense_splits_before_insert
BEFORE INSERT ON expense_splits
FOR EACH ROW
BEGIN
  DECLARE v_group INT UNSIGNED;
  DECLARE v_payer INT UNSIGNED;
  DECLARE v_total DECIMAL(12,2);

  SELECT group_id, paid_by, total_amount INTO v_group, v_payer, v_total
    FROM expenses WHERE expense_id = NEW.expense_id;

  IF v_group IS NULL AND NEW.user_id <> v_payer THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'A personal expense can only be split to its payer';
  END IF;

  IF v_group IS NOT NULL AND NOT EXISTS (
       SELECT 1 FROM group_members
        WHERE group_id = v_group AND user_id = NEW.user_id AND status = 'active') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Participant must be an active member of the group';
  END IF;

  IF NEW.share_amount > v_total THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Share cannot exceed the expense total';
  END IF;
END$$

-- AFTER INSERT: tell a participant they owe money (first version only) --
CREATE TRIGGER trg_expense_splits_after_insert
AFTER INSERT ON expense_splits
FOR EACH ROW
BEGIN
  DECLARE v_payer INT UNSIGNED;
  DECLARE v_version INT UNSIGNED;
  DECLARE v_desc VARCHAR(120);
  DECLARE v_payer_name VARCHAR(100);

  SELECT e.paid_by, e.version, e.description, u.full_name
    INTO v_payer, v_version, v_desc, v_payer_name
    FROM expenses e JOIN users u ON u.user_id = e.paid_by
   WHERE e.expense_id = NEW.expense_id;

  IF v_version = 1 AND NEW.user_id <> v_payer AND NEW.share_amount > 0
     AND NEW.user_id <> COALESCE(@app_user_id, 0) THEN
    INSERT INTO notifications (user_id, type, title, message, entity_type, entity_id)
    VALUES (NEW.user_id, 'expense_added', 'New expense',
            CONCAT(v_payer_name, ' added "', v_desc, '". Your share: ₹', NEW.share_amount),
            'expense', NEW.expense_id);
  END IF;
END$$

-- AFTER DELETE: archive a removed split (happens when an expense is re-split)
CREATE TRIGGER trg_expense_splits_after_delete
AFTER DELETE ON expense_splits
FOR EACH ROW
BEGIN
  INSERT INTO audit_logs (table_name, record_id, action, old_data, changed_by)
  VALUES ('expense_splits', OLD.split_id, 'DELETE',
          JSON_OBJECT('expense_id', OLD.expense_id, 'user_id', OLD.user_id,
                      'share_amount', OLD.share_amount,
                      'share_percentage', OLD.share_percentage,
                      'share_units', OLD.share_units,
                      'share_adjustment', OLD.share_adjustment),
          @app_user_id);
END$$

-- AFTER INSERT: notify the other party + audit --------------------------
CREATE TRIGGER trg_settlements_after_insert
AFTER INSERT ON settlements
FOR EACH ROW
BEGIN
  DECLARE v_payer_name, v_payee_name VARCHAR(100);
  SELECT full_name INTO v_payer_name FROM users WHERE user_id = NEW.payer_id;
  SELECT full_name INTO v_payee_name FROM users WHERE user_id = NEW.payee_id;

  IF NEW.status = 'pending' THEN
    INSERT INTO notifications (user_id, type, title, message, entity_type, entity_id)
    VALUES (NEW.payee_id, 'settlement_request', 'Please confirm a payment',
            CONCAT(v_payer_name, ' says they paid you ₹', NEW.amount, '. Confirm or reject.'),
            'settlement', NEW.settlement_id);
  ELSEIF NEW.status = 'confirmed' THEN
    INSERT INTO notifications (user_id, type, title, message, entity_type, entity_id)
    VALUES (NEW.payer_id, 'settlement_confirmed', 'Payment recorded',
            CONCAT(v_payee_name, ' recorded receiving ₹', NEW.amount, ' from you.'),
            'settlement', NEW.settlement_id);
  END IF;

  INSERT INTO audit_logs (table_name, record_id, action, new_data, changed_by)
  VALUES ('settlements', NEW.settlement_id, 'INSERT',
          JSON_OBJECT('group_id', NEW.group_id, 'payer_id', NEW.payer_id,
                      'payee_id', NEW.payee_id, 'amount', NEW.amount, 'status', NEW.status),
          @app_user_id);
END$$

-- BEFORE UPDATE: settlement state machine -------------------------------
--   pending -> confirmed | rejected | cancelled ; final states are immutable
CREATE TRIGGER trg_settlements_before_update
BEFORE UPDATE ON settlements
FOR EACH ROW
BEGIN
  IF OLD.status <> 'pending' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Settlement is final (confirmed/rejected/cancelled) and cannot be changed';
  END IF;

  IF NEW.amount <> OLD.amount OR NEW.payer_id <> OLD.payer_id
     OR NEW.payee_id <> OLD.payee_id OR NEW.group_id <> OLD.group_id THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Settlement amount and parties cannot be edited; cancel and re-record';
  END IF;

  IF NEW.status <> OLD.status THEN
    SET NEW.responded_at = NOW();
  END IF;
END$$

-- AFTER UPDATE: tell the payer the outcome + audit ----------------------
CREATE TRIGGER trg_settlements_after_update
AFTER UPDATE ON settlements
FOR EACH ROW
BEGIN
  IF NEW.status IN ('confirmed','rejected') AND OLD.status = 'pending' THEN
    INSERT INTO notifications (user_id, type, title, message, entity_type, entity_id)
    VALUES (NEW.payer_id,
            IF(NEW.status = 'confirmed', 'settlement_confirmed', 'settlement_rejected'),
            IF(NEW.status = 'confirmed', 'Payment confirmed', 'Payment rejected'),
            CONCAT('Your payment of ₹', NEW.amount, ' was ', NEW.status, '.'),
            'settlement', NEW.settlement_id);
  END IF;

  INSERT INTO audit_logs (table_name, record_id, action, old_data, new_data, changed_by)
  VALUES ('settlements', NEW.settlement_id, 'UPDATE',
          JSON_OBJECT('status', OLD.status), JSON_OBJECT('status', NEW.status),
          @app_user_id);
END$$

-- BEFORE DELETE: confirmed money movements can never be deleted ---------
CREATE TRIGGER trg_settlements_before_delete
BEFORE DELETE ON settlements
FOR EACH ROW
BEGIN
  IF OLD.status = 'confirmed' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Confirmed settlements cannot be deleted; record a reverse settlement instead';
  END IF;
END$$

DELIMITER ;
