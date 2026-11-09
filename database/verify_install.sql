SET NAMES utf8mb4;
-- =====================================================================
-- SplitWise+ | verify_install.sql — 1-minute install check for each laptop
-- Run after importing splitwise_plus.sql (mysql CLI or phpMyAdmin SQL tab).
-- Read-only. Every row must say PASS.
-- =====================================================================
USE splitwise_plus;

SELECT check_name, IF(ok, 'PASS', 'FAIL') AS result, detail FROM (
  SELECT 1 AS n, 'Server is MySQL 8.0 (not MariaDB)' AS check_name,
         VERSION() LIKE '8.0.%' AND VERSION() NOT LIKE '%MariaDB%' AS ok, VERSION() AS detail
  UNION ALL
  SELECT 2, '16 tables (14 business + 2 runtime)',
         (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='splitwise_plus' AND TABLE_TYPE='BASE TABLE') = 16,
         (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='splitwise_plus' AND TABLE_TYPE='BASE TABLE')
  UNION ALL
  SELECT 3, '11 views',
         (SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA='splitwise_plus') = 11,
         (SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA='splitwise_plus')
  UNION ALL
  SELECT 4, '11 triggers',
         (SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='splitwise_plus') = 11,
         (SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='splitwise_plus')
  UNION ALL
  SELECT 5, '5 procedures + 3 functions',
         (SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='splitwise_plus') = 8,
         (SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='splitwise_plus')
  UNION ALL
  SELECT 6, 'Sample data loaded (23 expenses, 10 settlements)',
         (SELECT COUNT(*) FROM expenses) = 23 AND (SELECT COUNT(*) FROM settlements) = 10,
         CONCAT((SELECT COUNT(*) FROM expenses), ' / ', (SELECT COUNT(*) FROM settlements))
  UNION ALL
  SELECT 7, 'Every group balance sums to 0',
         NOT EXISTS (SELECT 1 FROM v_group_member_balances GROUP BY group_id HAVING SUM(net_balance) <> 0), ''
  UNION ALL
  SELECT 8, 'Goa: Aarav +9991.67, Sneha -7133.33',
         fn_member_net_balance(1,2) = 9991.67 AND fn_member_net_balance(1,5) = -7133.33,
         CONCAT(fn_member_net_balance(1,2), ' / ', fn_member_net_balance(1,5))
  UNION ALL
  SELECT 9, 'Text stored as real UTF-8 (₹ and é)',
         (SELECT COUNT(*) FROM expenses WHERE description = 'Breakfast at café') = 1
         AND HEX('₹') = 'E282B9', (SELECT HEX(description) FROM expenses WHERE description LIKE 'Breakfast%')
  UNION ALL
  SELECT 10, 'Strict SQL mode + ONLY_FULL_GROUP_BY on',
         @@sql_mode LIKE '%STRICT_TRANS_TABLES%' AND @@sql_mode LIKE '%ONLY_FULL_GROUP_BY%', @@sql_mode
) checks ORDER BY n;
