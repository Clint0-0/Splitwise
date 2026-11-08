-- =====================================================================
-- SplitWise+ | 03_indexes.sql — secondary indexes (reasons in DATABASE_SCHEMA.md §8)
-- UNIQUE indexes are declared with the tables. InnoDB drops its own
-- implicit FK index once one of these can serve the FK instead.
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

-- "my groups" page
CREATE INDEX idx_group_members_user_status ON group_members (user_id, status);
-- group history sorted by date, balance sums over active expenses
CREATE INDEX idx_expenses_group_deleted_date ON expenses (group_id, is_deleted, expense_date);
-- paid() aggregation per user
CREATE INDEX idx_expenses_paid_by ON expenses (paid_by);
-- category reports and budget windows
CREATE INDEX idx_expenses_category_date ON expenses (category_id, expense_date);
-- owed() aggregation, "my expenses"
CREATE INDEX idx_expense_splits_user ON expense_splits (user_id);
-- balance sums and settlement history per group
CREATE INDEX idx_settlements_group_status ON settlements (group_id, status);
CREATE INDEX idx_settlements_payer ON settlements (payer_id);
CREATE INDEX idx_settlements_payee ON settlements (payee_id);
-- unread badge, newest first
CREATE INDEX idx_notifications_user_read_date ON notifications (user_id, is_read, created_at);
-- record history in admin activity view
CREATE INDEX idx_audit_logs_record ON audit_logs (table_name, record_id);
CREATE INDEX idx_audit_logs_changed_at ON audit_logs (changed_at);
-- M:N reverse lookup: expenses by tag
CREATE INDEX idx_expense_tags_tag ON expense_tags (tag_id);
-- budget lists
CREATE INDEX idx_budgets_user ON budgets (user_id);
CREATE INDEX idx_budgets_group ON budgets (group_id);
