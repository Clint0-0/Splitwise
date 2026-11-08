-- =====================================================================
-- SplitWise+ | procedures.sql — stored procedures
-- Multi-step writes run inside a transaction with an EXIT handler that
-- rolls back and re-raises the error (atomicity).
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

DROP PROCEDURE IF EXISTS sp_record_settlement;
DROP PROCEDURE IF EXISTS sp_respond_settlement;
DROP PROCEDURE IF EXISTS sp_add_equal_expense;
DROP PROCEDURE IF EXISTS sp_get_group_balances;
DROP PROCEDURE IF EXISTS sp_spending_summary;

DELIMITER $$

-- ---------------------------------------------------------------------
-- Record a settlement.
--  * Only payer or payee may record it.
--  * Recorded by payee -> confirmed; recorded by payer -> pending.
--  * Amount <= payer's outstanding payable and <= payee's outstanding
--    receivable, both reduced by pending settlements (stops duplicates).
--  * Locks the group's membership rows FOR UPDATE, so two concurrent
--    settlements in one group are checked one after the other.
--  * Same request_token twice -> returns the existing id (idempotent).
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_record_settlement(
  IN  p_group_id    INT UNSIGNED,
  IN  p_payer_id    INT UNSIGNED,
  IN  p_payee_id    INT UNSIGNED,
  IN  p_amount      DECIMAL(12,2),
  IN  p_date        DATE,
  IN  p_method      VARCHAR(20),
  IN  p_note        VARCHAR(255),
  IN  p_created_by  INT UNSIGNED,
  IN  p_token       CHAR(36),
  OUT p_settlement_id INT UNSIGNED)
BEGIN
  DECLARE v_locked INT;
  DECLARE v_payer_net, v_payee_net, v_pending_out, v_pending_in DECIMAL(12,2);
  DECLARE v_status VARCHAR(10);
  DECLARE v_msg VARCHAR(255);

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  IF p_created_by NOT IN (p_payer_id, p_payee_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only the payer or the payee can record a settlement';
  END IF;
  IF p_amount IS NULL OR p_amount <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Settlement amount must be positive';
  END IF;
  IF p_date > CURDATE() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Settlement date cannot be in the future';
  END IF;

  START TRANSACTION;

  -- serialise settlements per group
  SELECT COUNT(*) INTO v_locked
    FROM group_members WHERE group_id = p_group_id FOR UPDATE;

  -- idempotent replay
  SET p_settlement_id = NULL;
  SELECT settlement_id INTO p_settlement_id
    FROM settlements WHERE request_token = p_token;

  IF p_settlement_id IS NULL THEN
    IF (SELECT COUNT(*) FROM group_members
         WHERE group_id = p_group_id AND user_id IN (p_payer_id, p_payee_id)) <> 2 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payer and payee must both be members of the group';
    END IF;

    SET v_payer_net = fn_member_net_balance(p_group_id, p_payer_id);
    SET v_payee_net = fn_member_net_balance(p_group_id, p_payee_id);

    SELECT COALESCE(SUM(amount), 0) INTO v_pending_out
      FROM settlements WHERE group_id = p_group_id AND payer_id = p_payer_id AND status = 'pending';
    SELECT COALESCE(SUM(amount), 0) INTO v_pending_in
      FROM settlements WHERE group_id = p_group_id AND payee_id = p_payee_id AND status = 'pending';

    IF p_amount > (-v_payer_net - v_pending_out) THEN
      SET v_msg = CONCAT('Amount exceeds what the payer owes (available: ₹',
                         GREATEST(-v_payer_net - v_pending_out, 0), ')');
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;
    IF p_amount > (v_payee_net - v_pending_in) THEN
      SET v_msg = CONCAT('Amount exceeds what the payee is owed (available: ₹',
                         GREATEST(v_payee_net - v_pending_in, 0), ')');
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    SET v_status = IF(p_created_by = p_payee_id, 'confirmed', 'pending');

    INSERT INTO settlements (group_id, payer_id, payee_id, amount, settlement_date, method,
                             note, status, created_by, responded_at, request_token)
    VALUES (p_group_id, p_payer_id, p_payee_id, p_amount, p_date, p_method,
            p_note, v_status, p_created_by,
            IF(v_status = 'confirmed', NOW(), NULL), p_token);

    SET p_settlement_id = LAST_INSERT_ID();
  END IF;

  COMMIT;
END$$

-- ---------------------------------------------------------------------
-- Respond to a pending settlement: payee confirms/rejects, creator cancels.
-- The trigger trg_settlements_before_update enforces the state machine.
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_respond_settlement(
  IN p_settlement_id INT UNSIGNED,
  IN p_user_id       INT UNSIGNED,
  IN p_action        VARCHAR(10))      -- 'confirm' | 'reject' | 'cancel'
BEGIN
  DECLARE v_status VARCHAR(10);
  DECLARE v_payee, v_creator INT UNSIGNED;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  SELECT status, payee_id, created_by INTO v_status, v_payee, v_creator
    FROM settlements WHERE settlement_id = p_settlement_id FOR UPDATE;

  IF v_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Settlement not found';
  END IF;
  IF v_status <> 'pending' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only pending settlements can be answered';
  END IF;

  IF p_action IN ('confirm','reject') AND p_user_id <> v_payee THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only the payee can confirm or reject';
  ELSEIF p_action = 'cancel' AND p_user_id <> v_creator THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only the creator can cancel';
  ELSEIF p_action NOT IN ('confirm','reject','cancel') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown action';
  END IF;

  UPDATE settlements
     SET status = CASE p_action WHEN 'confirm' THEN 'confirmed'
                                WHEN 'reject'  THEN 'rejected'
                                ELSE 'cancelled' END
   WHERE settlement_id = p_settlement_id;

  COMMIT;
END$$

-- ---------------------------------------------------------------------
-- Add an equal-split expense entirely in SQL (used by seed data + demos).
-- p_participants: comma-separated user ids, e.g. '2,3,5'.
-- Rounding: every participant gets FLOOR(total/n) paise; the leftover
-- paise (< n) go one each to the payer first, then ascending user_id.
-- The application uses the same rule in PHP (SplitCalculator).
-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_add_equal_expense(
  IN  p_group_id     INT UNSIGNED,
  IN  p_paid_by      INT UNSIGNED,
  IN  p_created_by   INT UNSIGNED,
  IN  p_category_id  SMALLINT UNSIGNED,
  IN  p_description  VARCHAR(120),
  IN  p_total        DECIMAL(12,2),
  IN  p_date         DATE,
  IN  p_participants VARCHAR(500),
  OUT p_expense_id   INT UNSIGNED)
BEGIN
  DECLARE v_n INT;
  DECLARE v_total_paise BIGINT;
  DECLARE v_base BIGINT;
  DECLARE v_left INT;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  IF p_participants IS NULL OR NOT REGEXP_LIKE(p_participants, '^[0-9]+(,[0-9]+)*$') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Participants must be a comma-separated list of user ids';
  END IF;

  START TRANSACTION;

  INSERT INTO expenses (group_id, paid_by, created_by, category_id, description,
                        total_amount, split_method, expense_date)
  VALUES (p_group_id, p_paid_by, p_created_by, p_category_id, p_description,
          p_total, 'equal', p_date);
  SET p_expense_id = LAST_INSERT_ID();

  SELECT COUNT(DISTINCT jt.uid) INTO v_n
    FROM JSON_TABLE(CONCAT('[', p_participants, ']'), '$[*]'
                    COLUMNS (uid INT UNSIGNED PATH '$')) jt;

  SET v_total_paise = ROUND(p_total * 100);
  SET v_base = FLOOR(v_total_paise / v_n);
  SET v_left = v_total_paise - v_base * v_n;

  INSERT INTO expense_splits (expense_id, user_id, share_amount)
  SELECT p_expense_id,
         r.uid,
         (v_base + IF(r.rn <= v_left, 1, 0)) / 100
    FROM (SELECT d.uid,
                 ROW_NUMBER() OVER (ORDER BY (d.uid = p_paid_by) DESC, d.uid) AS rn
            FROM (SELECT DISTINCT jt.uid
                    FROM JSON_TABLE(CONCAT('[', p_participants, ']'), '$[*]'
                                    COLUMNS (uid INT UNSIGNED PATH '$')) jt) d) r;

  IF fn_expense_split_gap(p_expense_id) <> 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Split total does not match expense total';
  END IF;

  COMMIT;
END$$

-- ---------------------------------------------------------------------
CREATE PROCEDURE sp_get_group_balances(IN p_group_id INT UNSIGNED)
READS SQL DATA
BEGIN
  SELECT user_id, full_name, member_status, total_paid, total_owed,
         settled_paid, settled_received, net_balance
    FROM v_group_member_balances
   WHERE group_id = p_group_id
   ORDER BY net_balance DESC, full_name;
END$$

-- Category-wise spending (user's share) between two dates --------------
CREATE PROCEDURE sp_spending_summary(
  IN p_user_id INT UNSIGNED, IN p_from DATE, IN p_to DATE)
READS SQL DATA
BEGIN
  SELECT COALESCE(pc.name, c.name)  AS category_group,
         c.name                     AS category,
         COUNT(*)                   AS expense_count,
         SUM(s.share_amount)        AS my_share
    FROM expense_splits s
    JOIN expenses e         ON e.expense_id = s.expense_id
    JOIN categories c       ON c.category_id = e.category_id
    LEFT JOIN categories pc ON pc.category_id = c.parent_category_id
   WHERE s.user_id = p_user_id
     AND e.is_deleted = 0
     AND e.expense_date BETWEEN p_from AND p_to
   GROUP BY COALESCE(pc.name, c.name), c.name
   ORDER BY my_share DESC;
END$$

DELIMITER ;
