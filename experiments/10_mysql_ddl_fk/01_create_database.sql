-- =====================================================================
-- Experiment 10 · 01  CREATE DATABASE / DROP DATABASE
-- !! DESTRUCTIVE FOR THE SANDBOX ONLY: drops splitwise_lab_exp10 and splitwise_lab_exp10_fkdemo,
--    recreates splitwise_lab_exp10 (script 09 recreates the fkdemo one).
-- Never touches splitwise_plus. Inspect before running:
--     SHOW DATABASES LIKE 'splitwise_lab_exp10%';
-- =====================================================================

-- [1] Look before changing anything (manual inspection step).
-- Expected: 0 rows on the first run, 2 (splitwise_lab_exp10, splitwise_lab_exp10_fkdemo) after an earlier run.
-- splitwise_plus is not touched.
SHOW DATABASES LIKE 'splitwise_lab_exp10%';

-- [2] DROP DATABASE IF EXISTS — removes both sandboxes from an earlier run.
-- Why: makes the experiment repeatable. IF EXISTS avoids error 1008 on the first run.
-- Expected: "Query OK" (or a warning 1008 note on the first run).
DROP DATABASE IF EXISTS splitwise_lab_exp10;
DROP DATABASE IF EXISTS splitwise_lab_exp10_fkdemo;

-- [3] CREATE DATABASE with an explicit character set and collation.
-- Why: utf8mb4 stores ₹ and every Indian script, same as the real SplitWise+ database.
-- Expected: Query OK, 1 row affected.
CREATE DATABASE splitwise_lab_exp10
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

-- Verification: database exists with the chosen charset/collation.
SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
  FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'splitwise_lab_exp10';

-- [4] DROP DATABASE demonstrated safely on a throw-away scratch database.
-- Why: shows DROP DATABASE without deleting anything that matters.
CREATE DATABASE splitwise_lab_exp10_scratch;
SHOW DATABASES LIKE 'splitwise_lab_exp10%';          -- Expected: 2 rows
DROP DATABASE splitwise_lab_exp10_scratch;            -- Expected: Query OK, 0 rows affected
SHOW DATABASES LIKE 'splitwise_lab_exp10%';          -- Expected: 1 row (scratch is gone)

-- [5] Select the sandbox. Every later script starts with this USE, so they can
-- only ever change objects inside splitwise_lab_exp10.
USE splitwise_lab_exp10;
SELECT DATABASE() AS current_database;                -- Expected: splitwise_lab_exp10
