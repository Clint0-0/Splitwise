-- =====================================================================
-- SplitWise+ | 04_aggregates.sql — Experiment 6: GROUP BY & aggregates
-- All queries are valid under ONLY_FULL_GROUP_BY (MySQL 8 default).
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

-- Q27 Total spending per group
-- Purpose : group cards on the dashboard.  Concept : SUM, COUNT, GROUP BY
SELECT g.name, COUNT(e.expense_id) AS expenses, SUM(e.total_amount) AS total_spent
  FROM expenses e
  JOIN expense_groups g ON g.group_id = e.group_id
 WHERE e.is_deleted = 0
 GROUP BY g.group_id, g.name;

-- Q28 Total paid per user
-- Purpose : "who fronts the money".  Concept : SUM, GROUP BY
SELECT u.full_name, SUM(e.total_amount) AS total_paid
  FROM expenses e JOIN users u ON u.user_id = e.paid_by
 WHERE e.is_deleted = 0
 GROUP BY u.user_id, u.full_name
 ORDER BY total_paid DESC;

-- Q29 Spending per parent category
-- Explanation : grouping by an expression (parent name, or own name if top-level).
-- Purpose : category pie chart.  Concept : GROUP BY expression, SUM
SELECT COALESCE(p.name, c.name) AS category_group, SUM(e.total_amount) AS total
  FROM expenses e
  JOIN categories c      ON c.category_id = e.category_id
  LEFT JOIN categories p ON p.category_id = c.parent_category_id
 WHERE e.is_deleted = 0
 GROUP BY COALESCE(p.name, c.name)
 ORDER BY total DESC;

-- Q30 Average, smallest and largest expense per group
-- Purpose : group statistics.  Concept : AVG, MIN, MAX
SELECT group_id, ROUND(AVG(total_amount), 2) AS avg_expense,
       MIN(total_amount) AS smallest, MAX(total_amount) AS largest
  FROM expenses
 WHERE is_deleted = 0 AND group_id IS NOT NULL
 GROUP BY group_id;

-- Q31 Number of expenses per month
-- Purpose : monthly activity chart.  Concept : COUNT, GROUP BY expression
SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month, COUNT(*) AS expenses, SUM(total_amount) AS total
  FROM expenses
 WHERE is_deleted = 0
 GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
 ORDER BY month;

-- Q32 Each user's share per month
-- Explanation : spending = the user's share, not what they paid.
-- Purpose : personal monthly trend line.  Concept : GROUP BY multiple columns
SELECT s.user_id, DATE_FORMAT(e.expense_date, '%Y-%m') AS month, SUM(s.share_amount) AS my_share
  FROM expense_splits s JOIN expenses e ON e.expense_id = s.expense_id
 WHERE e.is_deleted = 0
 GROUP BY s.user_id, DATE_FORMAT(e.expense_date, '%Y-%m')
 ORDER BY s.user_id, month;

-- Q33 Confirmed settlement totals per group
-- Purpose : settlement summary card.  Concept : SUM + COUNT with WHERE
SELECT group_id, COUNT(*) AS settlements, SUM(amount) AS total_settled
  FROM settlements
 WHERE status = 'confirmed'
 GROUP BY group_id;

-- Q34 Groups that spent more than ₹10,000, and categories used 3+ times
-- Explanation : HAVING filters groups after aggregation (WHERE cannot use SUM).
-- Purpose : "big spenders" filter.  Concept : HAVING
SELECT group_id, SUM(total_amount) AS total
  FROM expenses
 WHERE is_deleted = 0 AND group_id IS NOT NULL
 GROUP BY group_id
HAVING SUM(total_amount) > 10000;

SELECT category_id, COUNT(*) AS times_used
  FROM expenses
 WHERE is_deleted = 0
 GROUP BY category_id
HAVING COUNT(*) >= 3;
