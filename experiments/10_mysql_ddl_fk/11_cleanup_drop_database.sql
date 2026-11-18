-- =====================================================================
-- Experiment 10 · 11  OPTIONAL CLEANUP - DESTRUCTIVE
-- Drops both sandbox databases. Only run when you are done.
-- Inspect first:  SHOW DATABASES LIKE 'splitwise_lab_exp10';
-- =====================================================================
DROP DATABASE IF EXISTS splitwise_lab_exp10;
DROP DATABASE IF EXISTS splitwise_lab_exp10_fkdemo;
SHOW DATABASES LIKE 'splitwise_lab_exp10%';            -- Expected: empty set
