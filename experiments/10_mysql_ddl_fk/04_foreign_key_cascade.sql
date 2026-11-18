-- =====================================================================
-- Experiment 10 · 04  CASCADE (ON DELETE CASCADE, ON UPDATE CASCADE)
-- CASCADE is used only on association rows (group_members) and on a key update.
-- It is NOT used for money tables: deleting a user or group must never silently
-- delete expenses or settlements (that is why 03 used RESTRICT).
-- =====================================================================
USE splitwise_lab_exp10;

-- Before: group 2 'Weekend Trek' has 3 member rows.
SELECT group_id, COUNT(*) AS members FROM group_members GROUP BY group_id;   -- Expected: 1->3, 2->3

-- [1] ON DELETE CASCADE: delete the group; its membership rows go with it.
-- Why: a membership row has no meaning without its group.
-- Expected: Query OK, 1 row affected (the 3 child rows are removed by InnoDB, not counted).
DELETE FROM expense_groups WHERE group_id = 2;

-- Verification
SELECT COUNT(*) AS group2_member_rows FROM group_members WHERE group_id = 2;  -- Expected 0
SELECT COUNT(*) AS group1_member_rows FROM group_members WHERE group_id = 1;  -- Expected 3 (untouched)

-- [2] ON UPDATE CASCADE: fk_expense_groups_created_by. Change user 1's id;
-- but user 1 is also referenced by RESTRICT/NO ACTION keys (expenses, group_members),
-- so first show the cascade on a user referenced ONLY through the cascading key.
INSERT INTO expense_groups (group_id, group_name, created_by) VALUES (3, 'Office Lunch', 4);
UPDATE users SET user_id = 40 WHERE user_id = 4;       -- Expected: Query OK, 1 row affected

-- Verification: the child value followed the parent key.
SELECT group_id, group_name, created_by FROM expense_groups WHERE group_id = 3;  -- Expected created_by = 40
