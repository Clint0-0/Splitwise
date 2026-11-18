-- =====================================================================
-- Experiment 10 · 10  OPTIONAL CLEANUP - DESTRUCTIVE
-- Drops the whole sandbox database. Only run when you are done.
-- Inspect first:  SHOW DATABASES LIKE 'splitwise_lab_exp10';
-- =====================================================================
DROP DATABASE IF EXISTS splitwise_lab_exp10;
SHOW DATABASES LIKE 'splitwise_lab_exp10';             -- Expected: empty set
