-- =====================================================================
-- SplitWise+ | 05_subqueries.sql — Experiment 7: subqueries
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

-- Q35 Expenses above the overall average
-- Explanation : inner query runs once and returns one value.
-- Purpose : flag unusually large bills.  Concept : scalar, non-correlated subquery
SELECT description, total_amount
  FROM expenses
 WHERE is_deleted = 0
   AND total_amount > (SELECT AVG(total_amount) FROM expenses WHERE is_deleted = 0);

-- Q36 Users who have never paid for an expense
-- Explanation : inner query re-evaluated per user (references u.user_id).
-- Purpose : nudge members who never pay.  Concept : correlated subquery, NOT EXISTS
SELECT u.full_name
  FROM users u
 WHERE u.role = 'user'
   AND NOT EXISTS (SELECT 1 FROM expenses e WHERE e.paid_by = u.user_id AND e.is_deleted = 0);

-- Q37 Groups that spent more than the average group
-- Explanation : subquery inside a subquery inside HAVING; the innermost is a derived table.
-- Purpose : group comparison report.  Concept : nested subquery, derived table
SELECT group_id, SUM(total_amount) AS total
  FROM expenses
 WHERE is_deleted = 0 AND group_id IS NOT NULL
 GROUP BY group_id
HAVING SUM(total_amount) > (SELECT AVG(t.group_total)
                              FROM (SELECT SUM(total_amount) AS group_total
                                      FROM expenses
                                     WHERE is_deleted = 0 AND group_id IS NOT NULL
                                     GROUP BY group_id) AS t);

-- Q38 Members of "Goa Trip 2026"
-- Explanation : multi-row subquery feeding IN; it contains a scalar subquery.
-- Purpose : membership list by group name.  Concept : IN with multi-row subquery
SELECT full_name
  FROM users
 WHERE user_id IN (SELECT user_id FROM group_members
                    WHERE status = 'active'
                      AND group_id = (SELECT group_id FROM expense_groups WHERE name = 'Goa Trip 2026'));

-- Q39 Expenses above their own group's average
-- Explanation : the average is recomputed for each outer row's group.
-- Purpose : outliers inside each group.  Concept : correlated subquery
SELECT e.group_id, e.description, e.total_amount
  FROM expenses e
 WHERE e.group_id IS NOT NULL AND e.is_deleted = 0
   AND e.total_amount > (SELECT AVG(x.total_amount) FROM expenses x
                          WHERE x.group_id = e.group_id AND x.is_deleted = 0);

-- Q40 Groups with no settlements yet
-- Purpose : remind groups to settle up.  Concept : NOT EXISTS
SELECT g.name
  FROM expense_groups g
 WHERE NOT EXISTS (SELECT 1 FROM settlements s WHERE s.group_id = g.group_id);

-- Q41 Highest expense in each group
-- Purpose : "biggest bill" per group.  Concept : correlated subquery with = (SELECT MAX…)
SELECT e.group_id, e.description, e.total_amount
  FROM expenses e
 WHERE e.group_id IS NOT NULL AND e.is_deleted = 0
   AND e.total_amount = (SELECT MAX(x.total_amount) FROM expenses x
                          WHERE x.group_id = e.group_id AND x.is_deleted = 0);

-- Q42 Expenses bigger than every College Friends expense (ALL) / than any (ANY)
-- Purpose : comparisons across groups.  Concept : multi-row comparison > ALL, > ANY
SELECT description, total_amount FROM expenses
 WHERE is_deleted = 0
   AND total_amount > ALL (SELECT total_amount FROM expenses WHERE group_id = 3 AND is_deleted = 0);

SELECT description, total_amount FROM expenses
 WHERE is_deleted = 0 AND group_id <> 3
   AND total_amount > ANY (SELECT total_amount FROM expenses WHERE group_id = 3 AND is_deleted = 0);

-- Q43 The NOT IN + NULL pitfall
-- Explanation : expenses.group_id is NULL for personal expenses. "x NOT IN (…, NULL)"
--               is never TRUE, so the first query returns NO rows. NOT EXISTS is safe.
-- Purpose : find groups that have no expenses (answer: Office Team).
-- Concept : NOT IN vs NOT EXISTS with NULLs
SELECT name FROM expense_groups
 WHERE group_id NOT IN (SELECT group_id FROM expenses);              -- returns nothing

SELECT g.name FROM expense_groups g
 WHERE NOT EXISTS (SELECT 1 FROM expenses e WHERE e.group_id = g.group_id);  -- correct
