-- =====================================================================
-- SplitWise+ | 01_basic_select.sql — Experiment 1: SELECT … FROM
-- Format per query: number, title, explanation, business purpose, concept.
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

-- Q1  Member directory
-- Explanation : one column from users.
-- Purpose     : list names for the "invite a friend" picker.
-- Concept     : SELECT single column
SELECT full_name FROM users;

-- Q2  Expense register
-- Explanation : three chosen columns from expenses.
-- Purpose     : compact expense list.
-- Concept     : SELECT multiple columns
SELECT description, total_amount, expense_date FROM expenses;

-- Q3  All groups
-- Explanation : every column of every group.
-- Purpose     : admin overview of groups.
-- Concept     : SELECT *
SELECT * FROM expense_groups;

-- Q4  Settlement ledger (selected columns)
-- Explanation : who paid whom, how much, and the state.
-- Purpose     : settlement history table.
-- Concept     : SELECT multiple columns
SELECT payer_id, payee_id, amount, status FROM settlements;

-- Q5  Why rounding rules are needed
-- Explanation : SELECT with no table. 1000/3 is not representable in paise.
-- Purpose     : justify the largest-remainder split rule (333.34 + 333.33 + 333.33).
-- Concept     : SELECT without FROM, expressions, functions
SELECT 1500 / 3                       AS equal_share_1500,
       1000 / 3                       AS raw_share_1000,
       ROUND(1000 / 3, 2)             AS rounded_share,
       ROUND(1000 / 3, 2) * 3         AS rounded_total_loses_a_paisa,
       NOW()                          AS server_time;

-- Q6  Per-person cost if split among 4
-- Explanation : computed column from each row.
-- Purpose     : quick "what if we split equally" preview.
-- Concept     : arithmetic expression in SELECT list
SELECT description, total_amount, ROUND(total_amount / 4, 2) AS per_person_if_4
  FROM expenses;
