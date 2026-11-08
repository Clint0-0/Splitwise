-- =====================================================================
-- SplitWise+ | 03_joins.sql — Experiment 5: joins
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

-- Q17 Expenses with payer name
-- Explanation : each expense matched to the user in paid_by.
-- Purpose     : history page shows "Paid by Aarav".
-- Concept     : INNER JOIN … ON, table aliases
SELECT e.expense_id, e.description, e.total_amount, u.full_name AS paid_by
  FROM expenses e
 INNER JOIN users u ON u.user_id = e.paid_by
 WHERE e.is_deleted = 0;

-- Q18 Members of every group
-- Explanation : associative table joined to both sides of the M:N.
-- Purpose     : group members page.
-- Concept     : 3-table INNER JOIN
SELECT g.name AS group_name, u.full_name, gm.role, gm.status
  FROM group_members gm
  JOIN users u          ON u.user_id = gm.user_id
  JOIN expense_groups g ON g.group_id = gm.group_id
 ORDER BY g.name, gm.role, u.full_name;

-- Q19 Participants and shares of each expense
-- Explanation : USING works because the column is named expense_id in both tables.
-- Purpose     : expense details page.
-- Concept     : JOIN … USING
SELECT expense_id, e.description, s.user_id, s.share_amount
  FROM expense_splits s
  JOIN expenses e USING (expense_id)
 ORDER BY expense_id, s.user_id;

-- Q20 Settlements with payer and payee names
-- Explanation : users joined twice under two aliases (role names).
-- Purpose     : "Rohan paid Aarav ₹5,000".
-- Concept     : same table joined twice
SELECT s.settlement_id, p.full_name AS payer, r.full_name AS payee, s.amount, s.status
  FROM settlements s
  JOIN users p ON p.user_id = s.payer_id
  JOIN users r ON r.user_id = s.payee_id;

-- Q21 Every user and the expenses they paid (including none)
-- Explanation : users with no matching expense keep a row with NULLs.
-- Purpose     : find members who never pay.
-- Concept     : LEFT JOIN
SELECT u.full_name, e.description, e.total_amount
  FROM users u
  LEFT JOIN expenses e ON e.paid_by = u.user_id AND e.is_deleted = 0
 ORDER BY u.full_name;

-- Q22 Every category and its expenses (including unused categories)
-- Explanation : keep all rows of the right-hand table (categories).
-- Purpose     : show Health and Education with no spending.
-- Concept     : RIGHT JOIN
SELECT c.name AS category, e.description, e.total_amount
  FROM expenses e
 RIGHT JOIN categories c ON c.category_id = e.category_id AND e.is_deleted = 0
 ORDER BY c.name;

-- Q23 Pairs of users who share a group
-- Explanation : group_members joined to itself; a.user_id < b.user_id avoids
--               self-pairs and duplicate (A,B)/(B,A) rows.
-- Purpose     : the pairs who can owe each other money (pairwise debt screen).
-- Concept     : SELF JOIN
SELECT g.name AS group_name, ua.full_name AS member_1, ub.full_name AS member_2
  FROM group_members a
  JOIN group_members b  ON b.group_id = a.group_id AND a.user_id < b.user_id
  JOIN users ua         ON ua.user_id = a.user_id
  JOIN users ub         ON ub.user_id = b.user_id
  JOIN expense_groups g ON g.group_id = a.group_id
 WHERE a.status = 'active' AND b.status = 'active'
 ORDER BY g.name, member_1, member_2;

-- Q24 Sub-categories with their parent
-- Explanation : categories joined to itself through parent_category_id.
-- Purpose     : roll Groceries and Restaurants up into Food & Dining.
-- Concept     : SELF JOIN (hierarchy)
SELECT child.name AS category, parent.name AS parent_category
  FROM categories child
  JOIN categories parent ON parent.category_id = child.parent_category_id;

-- Q25 Member × category spending matrix for Goa Trip, including ₹0 cells
-- Explanation : CROSS JOIN builds every (member, category) combination
--               (4 members × 14 categories = 56 rows); LEFT JOIN then adds the
--               actual shares. Without the CROSS JOIN, zero cells would be missing.
-- Purpose     : heat-map chart on the group analytics page.
-- Concept     : CROSS JOIN (Cartesian product) + LEFT JOIN
SELECT u.full_name, c.name AS category, COALESCE(SUM(s.share_amount), 0) AS share
  FROM group_members gm
  JOIN users u ON u.user_id = gm.user_id
 CROSS JOIN categories c
  LEFT JOIN expenses e
         ON e.group_id = gm.group_id AND e.category_id = c.category_id AND e.is_deleted = 0
  LEFT JOIN expense_splits s
         ON s.expense_id = e.expense_id AND s.user_id = gm.user_id
 WHERE gm.group_id = 1
 GROUP BY u.full_name, c.name
 ORDER BY u.full_name, c.name;

-- Q26 FULL OUTER JOIN emulation: who paid vs who owes, per user
-- Explanation : MySQL has no FULL OUTER JOIN. LEFT JOIN ∪ RIGHT JOIN gives rows
--               present on either side; UNION removes the duplicated matches.
-- Purpose     : users who only pay, only owe, or both.
-- Concept     : LEFT JOIN UNION RIGHT JOIN
SELECT p.user_id AS payer_side, o.user_id AS owing_side, p.paid, o.owed
  FROM (SELECT paid_by AS user_id, SUM(total_amount) AS paid
          FROM expenses WHERE is_deleted = 0 GROUP BY paid_by) p
  LEFT JOIN (SELECT s.user_id, SUM(s.share_amount) AS owed
               FROM expense_splits s JOIN expenses e ON e.expense_id = s.expense_id
              WHERE e.is_deleted = 0 GROUP BY s.user_id) o ON o.user_id = p.user_id
UNION
SELECT p.user_id, o.user_id, p.paid, o.owed
  FROM (SELECT paid_by AS user_id, SUM(total_amount) AS paid
          FROM expenses WHERE is_deleted = 0 GROUP BY paid_by) p
 RIGHT JOIN (SELECT s.user_id, SUM(s.share_amount) AS owed
               FROM expense_splits s JOIN expenses e ON e.expense_id = s.expense_id
              WHERE e.is_deleted = 0 GROUP BY s.user_id) o ON o.user_id = p.user_id;
