-- =====================================================================
-- Experiment 10 · 03  RESTRICT (and NO ACTION, the default)
-- Contains statements that are EXPECTED to fail. Run with:  mysql --force
-- =====================================================================
USE splitwise_lab_exp10;

-- [1] Delete user 1. She paid expense 1 (fk_expenses_paid_by, RESTRICT), created group 1
-- (fk_expense_groups_created_by, RESTRICT) and is a member (fk_group_members_user, RESTRICT).
-- MySQL reports the first constraint it checks (here the group's created_by); any one blocks it.
-- Why it must fail: expense 1 would point to a payer who no longer exists -> balances break.
-- EXPECT ERROR 1451 (Cannot delete or update a parent row)
DELETE FROM users WHERE user_id = 1;

-- [2] Change the primary key of a referenced user. ON UPDATE has no clause -> NO ACTION.
-- In InnoDB NO ACTION is checked immediately, exactly like RESTRICT.
-- EXPECT ERROR 1451
UPDATE users SET user_id = 100 WHERE user_id = 2;

-- [3] Delete a group that still has expenses. fk_expenses_group has NO clause at all
-- -> default action = NO ACTION -> behaves as RESTRICT.
-- EXPECT ERROR 1451
DELETE FROM expense_groups WHERE group_id = 1;

-- [4] The child side is protected too: an expense paid by a non-existent user.
-- EXPECT ERROR 1452 (Cannot add or update a child row)
INSERT INTO expenses (group_id, paid_by, category_id, description, total_amount)
VALUES (1, 999, 1, 'Ghost payer', 10.00);

-- Verification: nothing changed.
SELECT (SELECT COUNT(*) FROM users) AS users_rows,                -- Expected 5
       (SELECT COUNT(*) FROM expenses) AS expense_rows,           -- Expected 3
       (SELECT COUNT(*) FROM expense_groups) AS group_rows,       -- Expected 2
       (SELECT COUNT(*) FROM users WHERE user_id = 100) AS id100; -- Expected 0
