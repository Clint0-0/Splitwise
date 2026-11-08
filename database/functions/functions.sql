-- =====================================================================
-- SplitWise+ | functions.sql — stored functions
-- READS SQL DATA is required when binary logging is on (MySQL 8 default).
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

DROP FUNCTION IF EXISTS fn_member_net_balance;
DROP FUNCTION IF EXISTS fn_expense_split_gap;
DROP FUNCTION IF EXISTS fn_budget_spent;

DELIMITER $$

-- net = paid - owed + sent - received  (positive = gets money back)
CREATE FUNCTION fn_member_net_balance(p_group_id INT UNSIGNED, p_user_id INT UNSIGNED)
RETURNS DECIMAL(12,2)
READS SQL DATA
BEGIN
  DECLARE v_paid, v_owed, v_sent, v_received DECIMAL(12,2) DEFAULT 0;

  SELECT COALESCE(SUM(total_amount), 0) INTO v_paid
    FROM expenses
   WHERE group_id = p_group_id AND paid_by = p_user_id AND is_deleted = 0;

  SELECT COALESCE(SUM(s.share_amount), 0) INTO v_owed
    FROM expense_splits s
    JOIN expenses e ON e.expense_id = s.expense_id
   WHERE e.group_id = p_group_id AND s.user_id = p_user_id AND e.is_deleted = 0;

  SELECT COALESCE(SUM(amount), 0) INTO v_sent
    FROM settlements
   WHERE group_id = p_group_id AND payer_id = p_user_id AND status = 'confirmed';

  SELECT COALESCE(SUM(amount), 0) INTO v_received
    FROM settlements
   WHERE group_id = p_group_id AND payee_id = p_user_id AND status = 'confirmed';

  RETURN v_paid - v_owed + v_sent - v_received;
END$$

-- total - sum(shares). Must be 0.00 for every valid expense. NULL if expense missing.
CREATE FUNCTION fn_expense_split_gap(p_expense_id INT UNSIGNED)
RETURNS DECIMAL(12,2)
READS SQL DATA
BEGIN
  DECLARE v_gap DECIMAL(12,2);
  SELECT e.total_amount - COALESCE((SELECT SUM(s.share_amount)
                                      FROM expense_splits s
                                     WHERE s.expense_id = e.expense_id), 0)
    INTO v_gap
    FROM expenses e
   WHERE e.expense_id = p_expense_id;
  RETURN v_gap;
END$$

-- Personal budget: the owner's SHARE of expenses. Group budget: group expense totals.
-- A category budget also counts its sub-categories (e.g. Food & Dining -> Groceries).
CREATE FUNCTION fn_budget_spent(p_budget_id INT UNSIGNED)
RETURNS DECIMAL(12,2)
READS SQL DATA
BEGIN
  DECLARE v_user, v_group INT UNSIGNED;
  DECLARE v_cat SMALLINT UNSIGNED;
  DECLARE v_start, v_end DATE;
  DECLARE v_spent DECIMAL(12,2) DEFAULT 0;

  SELECT user_id, group_id, category_id, start_date, end_date
    INTO v_user, v_group, v_cat, v_start, v_end
    FROM budgets WHERE budget_id = p_budget_id;

  IF v_user IS NOT NULL THEN
    SELECT COALESCE(SUM(s.share_amount), 0) INTO v_spent
      FROM expense_splits s
      JOIN expenses   e ON e.expense_id = s.expense_id
      JOIN categories c ON c.category_id = e.category_id
     WHERE s.user_id = v_user
       AND e.is_deleted = 0
       AND e.expense_date BETWEEN v_start AND v_end
       AND (v_cat IS NULL OR v_cat IN (c.category_id, c.parent_category_id));
  ELSEIF v_group IS NOT NULL THEN
    SELECT COALESCE(SUM(e.total_amount), 0) INTO v_spent
      FROM expenses e
      JOIN categories c ON c.category_id = e.category_id
     WHERE e.group_id = v_group
       AND e.is_deleted = 0
       AND e.expense_date BETWEEN v_start AND v_end
       AND (v_cat IS NULL OR v_cat IN (c.category_id, c.parent_category_id));
  END IF;

  RETURN v_spent;
END$$

DELIMITER ;
