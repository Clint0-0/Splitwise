-- =====================================================================
-- SplitWise+ | 07_explain.sql — indexing evidence with EXPLAIN
-- Run each EXPLAIN; compare the "key" and "type" columns.
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

-- A. Group history: uses idx_expenses_group_deleted_date (type = ref)
EXPLAIN
SELECT expense_id, description, expense_date
  FROM expenses
 WHERE group_id = 1 AND is_deleted = 0
 ORDER BY expense_date DESC;

-- B. Same query forced to ignore the index: type = ALL (full table scan)
EXPLAIN
SELECT expense_id, description, expense_date
  FROM expenses IGNORE INDEX (idx_expenses_group_deleted_date)
 WHERE group_id = 1 AND is_deleted = 0
 ORDER BY expense_date DESC;

-- C. A user's shares: uses idx_expense_splits_user
EXPLAIN
SELECT expense_id, share_amount FROM expense_splits WHERE user_id = 3;

-- D. Unread notifications: uses idx_notifications_user_read_date
EXPLAIN
SELECT notification_id, title FROM notifications
 WHERE user_id = 3 AND is_read = 0
 ORDER BY created_at DESC;

-- E. Membership check done on every group request: uses UNIQUE uq_group_members_group_user (const)
EXPLAIN
SELECT 1 FROM group_members WHERE group_id = 1 AND user_id = 3;

-- List all indexes of a table
SHOW INDEX FROM expenses;
