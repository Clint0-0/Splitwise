-- =====================================================================
-- Experiment 10 · 09  Verification (read-only)
-- =====================================================================
USE splitwise_lab_exp10;

-- All tables in the sandbox
SELECT TABLE_NAME, ENGINE, TABLE_ROWS FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME;

-- Every foreign key with its columns and actions
SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME,
       k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE
  FROM information_schema.KEY_COLUMN_USAGE k
  JOIN information_schema.REFERENTIAL_CONSTRAINTS r
    ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
 WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL
 ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME;

-- CHECK constraints still defined
SELECT tc.TABLE_NAME, cc.CONSTRAINT_NAME, cc.CHECK_CLAUSE
  FROM information_schema.CHECK_CONSTRAINTS cc
  JOIN information_schema.TABLE_CONSTRAINTS tc
    ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
 WHERE cc.CONSTRAINT_SCHEMA = DATABASE() ORDER BY tc.TABLE_NAME;

-- Final state of the demo data
SELECT 'users' t, COUNT(*) n FROM users UNION ALL
SELECT 'expense_groups', COUNT(*) FROM expense_groups UNION ALL
SELECT 'group_members', COUNT(*) FROM group_members UNION ALL
SELECT 'expenses', COUNT(*) FROM expenses UNION ALL
SELECT 'warehouse', COUNT(*) FROM warehouse UNION ALL
SELECT 'inventoryDetails', COUNT(*) FROM inventoryDetails UNION ALL
SELECT 'employeeWarehouse', COUNT(*) FROM employeeWarehouse;

-- Production database untouched: this sandbox shares no objects with splitwise_plus.
SELECT COUNT(*) AS splitwise_plus_tables FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = 'splitwise_plus' AND TABLE_TYPE = 'BASE TABLE';
