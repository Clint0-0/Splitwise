-- =====================================================================
-- SplitWise+ | 02_filtering_and_sorting.sql
-- Experiment 2 (ORDER BY), Experiment 3 (WHERE), Experiment 4 (DISTINCT,
-- LIMIT, OFFSET, aliases)
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

-- Q7  Latest expenses first
-- Explanation : newest date first; ties broken by creation time.
-- Purpose     : default order of the expense history page.
-- Concept     : ORDER BY DESC, multiple columns
SELECT expense_id, description, expense_date, total_amount
  FROM expenses
 WHERE is_deleted = 0
 ORDER BY expense_date DESC, created_at DESC;

-- Q8  Users alphabetically
-- Purpose     : member picker.  Concept : ORDER BY ASC
SELECT user_id, full_name FROM users ORDER BY full_name ASC;

-- Q9  Highest expenses first, then oldest date
-- Explanation : amount descending; equal amounts ordered by date ascending.
-- Purpose     : "biggest bills" report.
-- Concept     : ORDER BY mixed directions
SELECT description, total_amount, expense_date
  FROM expenses
 WHERE is_deleted = 0
 ORDER BY total_amount DESC, expense_date ASC;

-- Q10 Settlements by date
-- Purpose     : chronological settlement ledger.  Concept : ORDER BY date ASC
SELECT settlement_id, settlement_date, amount, status
  FROM settlements
 ORDER BY settlement_date, settlement_id;

-- Q11 Big expenses in a date range
-- Explanation : amount comparison AND date BETWEEN (inclusive).
-- Purpose     : "large spends in October".
-- Concept     : WHERE, comparison, BETWEEN, AND
SELECT description, total_amount, expense_date
  FROM expenses
 WHERE total_amount > 2000
   AND expense_date BETWEEN '2026-10-01' AND '2026-10-31'
   AND is_deleted = 0;

-- Q12 Search users by name / username pattern
-- Explanation : % = any characters, _ = exactly one character.
-- Purpose     : invite search box.
-- Concept     : LIKE with % and _ , OR
SELECT user_id, username, full_name
  FROM users
 WHERE full_name LIKE 'Pri%'
    OR username LIKE 'ka_ir';

-- Q13 Travel-related expenses that are not deleted
-- Explanation : IN list + NOT.
-- Purpose     : category filter on history page (Travel, Flights, Hotels, Transport).
-- Concept     : IN, NOT, AND
SELECT description, category_id, total_amount
  FROM expenses
 WHERE category_id IN (4, 5, 6, 7)
   AND NOT is_deleted = 1;

-- Q14 Incomplete profiles and annotated expenses
-- Explanation : optional columns tested with IS NULL / IS NOT NULL (never = NULL).
-- Purpose     : prompt users to add a phone number; show expenses with notes.
-- Concept     : IS NULL, IS NOT NULL
SELECT username, full_name FROM users WHERE phone IS NULL;
SELECT description, notes FROM expenses WHERE notes IS NOT NULL;

-- Q15 Categories actually used
-- Explanation : removes duplicate category ids.
-- Purpose     : build the category filter only from categories in use.
-- Concept     : DISTINCT
SELECT DISTINCT category_id FROM expenses WHERE is_deleted = 0 ORDER BY category_id;

-- Q16 Paginated expense history
-- Explanation : page 2 with 5 rows per page = skip 5, take 5. Both syntaxes are equivalent.
-- Purpose     : pagination on the history page (offset = (page-1) * size).
-- Concept     : LIMIT … OFFSET …, LIMIT offset, count, column/table aliases
SELECT e.expense_id   AS "ID",
       e.description  AS "Expense",
       e.total_amount AS "Amount (₹)",
       e.expense_date AS "Date"
  FROM expenses AS e
 WHERE e.is_deleted = 0
 ORDER BY e.expense_date DESC, e.expense_id DESC
 LIMIT 5 OFFSET 5;

SELECT e.expense_id, e.description
  FROM expenses e
 WHERE e.is_deleted = 0
 ORDER BY e.expense_date DESC, e.expense_id DESC
 LIMIT 5, 5;
