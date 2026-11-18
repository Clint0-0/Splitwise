-- =====================================================================
-- Experiment 10 · 06  ALTER TABLE (ADD / MODIFY / RENAME / DROP COLUMN,
--                    ADD CONSTRAINT, ADD INDEX) and RENAME TABLE
-- =====================================================================
USE splitwise_lab_exp10;

-- [1] ADD COLUMN ... AFTER: optional notes on an expense, placed after description.
-- Why: new requirement without recreating the table or losing rows.
ALTER TABLE expenses ADD COLUMN notes VARCHAR(255) NULL AFTER description;
-- Verification: column order shows notes right after description.
SELECT COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' ORDER BY ORDINAL_POSITION;

-- [2] ADD COLUMN with a DEFAULT: existing rows get the default immediately.
ALTER TABLE expenses ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'INR';
SELECT expense_id, currency FROM expenses;             -- Expected: every row 'INR'

-- [3] MODIFY COLUMN: widen description from 150 to 200 characters (data kept).
ALTER TABLE expenses MODIFY COLUMN description VARCHAR(200) NOT NULL;

-- [4] RENAME COLUMN (MySQL 8.0): users.full_name -> display_name, then back.
ALTER TABLE users RENAME COLUMN full_name TO display_name;
SELECT user_id, display_name FROM users ORDER BY user_id;
ALTER TABLE users RENAME COLUMN display_name TO full_name;

-- [5] ADD CONSTRAINT CHECK and ADD INDEX on an existing table.
ALTER TABLE expenses ADD CONSTRAINT chk_expenses_currency CHECK (currency IN ('INR', 'USD', 'EUR'));
ALTER TABLE expenses ADD INDEX idx_expenses_group_paid (group_id, paid_by);
-- EXPECT ERROR 3819 (Check constraint 'chk_expenses_currency' is violated)
UPDATE expenses SET currency = 'XYZ' WHERE expense_id = 1;

-- [6] DROP COLUMN: currency was a trial; remove it (its CHECK must go first).
ALTER TABLE expenses DROP CHECK chk_expenses_currency;
ALTER TABLE expenses DROP COLUMN currency;
SELECT COUNT(*) AS currency_columns FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'currency';  -- Expected 0

-- [7] RENAME TABLE: categories -> expense_categories. Foreign keys that point at it
-- are updated automatically by InnoDB.
RENAME TABLE categories TO expense_categories;
SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_expenses_category';  -- Expected expense_categories
-- ALTER TABLE ... RENAME TO is the equivalent form; rename back:
ALTER TABLE expense_categories RENAME TO categories;
SHOW TABLES;                                           -- Expected: categories, expense_groups, expenses, group_members, users

-- [8] SHOW CREATE TABLE: the full definition after all changes.
SHOW CREATE TABLE expenses\G
