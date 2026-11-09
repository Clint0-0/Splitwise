SET NAMES utf8mb4;
-- =====================================================================
-- SplitWise+ | app_user.sql — least-privilege account for the PHP app
-- Run ONCE as root AFTER importing splitwise_plus.sql:
--   mysql -u root -p < database/security/app_user.sql
-- Then put the same password in .env (DB_USER=splitwise_app).
-- NOT part of splitwise_plus.sql, so no password ends up in the import file.
--
-- Principle of least privilege:
--   * no DDL (CREATE/ALTER/DROP), no GRANT, no access to other databases
--   * settlements: read-only for the app; every write must go through
--     sp_record_settlement / sp_respond_settlement (validation + locking)
--   * audit_logs: read-only; rows are written only by triggers
--   * categories: read-only (managed by the import / DBA)
-- Triggers and procedures run with their DEFINER's rights (SQL SECURITY
-- DEFINER, the MySQL default), so they can still write audit_logs,
-- notifications and settlements on the app's behalf.
-- =====================================================================

-- CHANGE THIS PASSWORD before running (and never commit the real one).
CREATE USER IF NOT EXISTS 'splitwise_app'@'localhost' IDENTIFIED BY 'Change_Me_123!';
CREATE USER IF NOT EXISTS 'splitwise_app'@'127.0.0.1' IDENTIFIED BY 'Change_Me_123!';

-- Start clean if the script is re-run
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'splitwise_app'@'localhost';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'splitwise_app'@'127.0.0.1';

-- Read everything (tables + views) and run procedures/functions
GRANT SELECT, EXECUTE ON splitwise_plus.* TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';

-- Write only the tables the application owns
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.users             TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.expense_groups    TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.group_members     TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.group_invitations TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.expenses          TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.expense_splits    TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.budgets           TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.notifications     TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.receipts          TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.tags              TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.expense_tags      TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
-- shared runtime state (sessions, throttle counters, mail cursor) — Phase 5.5
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.app_sessions      TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
GRANT INSERT, UPDATE, DELETE ON splitwise_plus.app_kv            TO 'splitwise_app'@'localhost', 'splitwise_app'@'127.0.0.1';
-- settlements, audit_logs, categories: SELECT only (granted above)

SHOW GRANTS FOR 'splitwise_app'@'localhost';
