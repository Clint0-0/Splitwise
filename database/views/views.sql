-- =====================================================================
-- SplitWise+ | views.sql — 11 views (Experiment 9)
-- Updatable: v_active_expenses, v_active_group_members (single table, no
-- aggregates/DISTINCT/GROUP BY/UNION/subquery in select list).
-- All others are read-only by MySQL's view rules.
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

-- 1. Simple single-table, UPDATABLE, WITH CHECK OPTION -----------------
--    An UPDATE through this view that sets is_deleted = 1 is rejected
--    because the row would leave the view.
CREATE OR REPLACE VIEW v_active_expenses AS
SELECT expense_id, group_id, paid_by, created_by, category_id, description,
       total_amount, split_method, expense_date, notes, is_deleted, version,
       created_at, updated_at
  FROM expenses
 WHERE is_deleted = 0
WITH CASCADED CHECK OPTION;

-- 2. Simple single-table, UPDATABLE -------------------------------------
CREATE OR REPLACE VIEW v_active_group_members AS
SELECT membership_id, group_id, user_id, role, status, joined_at
  FROM group_members
 WHERE status = 'active';

-- 3. Join view: expense with payer, category (+parent), group ----------
CREATE OR REPLACE VIEW v_expense_details AS
SELECT e.expense_id,
       e.expense_date,
       e.description,
       e.total_amount,
       e.split_method,
       e.group_id,
       COALESCE(g.name, 'Personal') AS group_name,
       e.paid_by,
       payer.full_name              AS payer_name,
       c.name                       AS category_name,
       COALESCE(pc.name, c.name)    AS parent_category_name,
       e.notes,
       e.is_deleted
  FROM expenses e
  JOIN users payer          ON payer.user_id = e.paid_by
  JOIN categories c         ON c.category_id = e.category_id
  LEFT JOIN categories pc   ON pc.category_id = c.parent_category_id
  LEFT JOIN expense_groups g ON g.group_id = e.group_id;

-- 4. Join view: users joined twice (payer / payee aliases) --------------
CREATE OR REPLACE VIEW v_settlement_details AS
SELECT s.settlement_id,
       s.group_id,
       g.name        AS group_name,
       s.payer_id,
       p.full_name   AS payer_name,
       s.payee_id,
       r.full_name   AS payee_name,
       s.amount,
       s.settlement_date,
       s.method,
       s.status,
       s.note
  FROM settlements s
  JOIN users p           ON p.user_id = s.payer_id
  JOIN users r           ON r.user_id = s.payee_id
  JOIN expense_groups g  ON g.group_id = s.group_id;

-- 5. Balance per member per group (derived tables + aggregates) ---------
--    net = paid - owed + sent - received
CREATE OR REPLACE VIEW v_group_member_balances AS
SELECT gm.group_id,
       g.name                       AS group_name,
       gm.user_id,
       u.full_name,
       gm.status                    AS member_status,
       COALESCE(pd.paid, 0)         AS total_paid,
       COALESCE(ow.owed, 0)         AS total_owed,
       COALESCE(st.sent, 0)         AS settled_paid,
       COALESCE(rc.received, 0)     AS settled_received,
       COALESCE(pd.paid, 0) - COALESCE(ow.owed, 0)
         + COALESCE(st.sent, 0) - COALESCE(rc.received, 0) AS net_balance
  FROM group_members gm
  JOIN users u           ON u.user_id = gm.user_id
  JOIN expense_groups g  ON g.group_id = gm.group_id
  LEFT JOIN (SELECT group_id, paid_by AS user_id, SUM(total_amount) AS paid
               FROM expenses
              WHERE is_deleted = 0 AND group_id IS NOT NULL
              GROUP BY group_id, paid_by) pd
         ON pd.group_id = gm.group_id AND pd.user_id = gm.user_id
  LEFT JOIN (SELECT e.group_id, s.user_id, SUM(s.share_amount) AS owed
               FROM expense_splits s
               JOIN expenses e ON e.expense_id = s.expense_id
              WHERE e.is_deleted = 0 AND e.group_id IS NOT NULL
              GROUP BY e.group_id, s.user_id) ow
         ON ow.group_id = gm.group_id AND ow.user_id = gm.user_id
  LEFT JOIN (SELECT group_id, payer_id AS user_id, SUM(amount) AS sent
               FROM settlements WHERE status = 'confirmed'
              GROUP BY group_id, payer_id) st
         ON st.group_id = gm.group_id AND st.user_id = gm.user_id
  LEFT JOIN (SELECT group_id, payee_id AS user_id, SUM(amount) AS received
               FROM settlements WHERE status = 'confirmed'
              GROUP BY group_id, payee_id) rc
         ON rc.group_id = gm.group_id AND rc.user_id = gm.user_id;

-- 6. View based on another view: overall balance per user --------------
CREATE OR REPLACE VIEW v_user_overall_balance AS
SELECT user_id,
       full_name,
       SUM(total_paid)                                        AS total_paid,
       SUM(total_owed)                                        AS total_owed,
       SUM(net_balance)                                       AS net_balance,
       SUM(CASE WHEN net_balance > 0 THEN net_balance ELSE 0 END)  AS receivable,
       SUM(CASE WHEN net_balance < 0 THEN -net_balance ELSE 0 END) AS payable
  FROM v_group_member_balances
 GROUP BY user_id, full_name;

-- 7. Pairwise debts: "original owed / settled / remaining" -------------
--    Signed contributions are normalised to the pair (user_a < user_b).
--    outstanding > 0 means user_a owes user_b; < 0 means user_b owes user_a.
CREATE OR REPLACE VIEW v_pairwise_debts AS
SELECT t.group_id,
       t.user_a,
       t.user_b,
       SUM(t.gross_a_to_b)   AS gross_a_owes_b,
       SUM(t.gross_b_to_a)   AS gross_b_owes_a,
       SUM(t.settled_a_to_b) AS settled_a_paid_b,
       SUM(t.settled_b_to_a) AS settled_b_paid_a,
       SUM(t.gross_a_to_b) - SUM(t.gross_b_to_a)
         - SUM(t.settled_a_to_b) + SUM(t.settled_b_to_a) AS outstanding
  FROM (
        -- debts from expense splits: participant owes payer
        SELECT e.group_id,
               LEAST(s.user_id, e.paid_by)    AS user_a,
               GREATEST(s.user_id, e.paid_by) AS user_b,
               IF(s.user_id < e.paid_by, s.share_amount, 0) AS gross_a_to_b,
               IF(s.user_id > e.paid_by, s.share_amount, 0) AS gross_b_to_a,
               0 AS settled_a_to_b,
               0 AS settled_b_to_a
          FROM expense_splits s
          JOIN expenses e ON e.expense_id = s.expense_id
         WHERE e.is_deleted = 0 AND e.group_id IS NOT NULL AND s.user_id <> e.paid_by
        UNION ALL
        -- confirmed settlements reduce the payer's debt to the payee
        SELECT group_id,
               LEAST(payer_id, payee_id),
               GREATEST(payer_id, payee_id),
               0, 0,
               IF(payer_id < payee_id, amount, 0),
               IF(payer_id > payee_id, amount, 0)
          FROM settlements
         WHERE status = 'confirmed'
       ) t
 GROUP BY t.group_id, t.user_a, t.user_b;

-- 8. Aggregate view: group expense summary -----------------------------
CREATE OR REPLACE VIEW v_group_expense_summary AS
SELECT g.group_id,
       g.name                          AS group_name,
       COUNT(e.expense_id)             AS expense_count,
       COALESCE(SUM(e.total_amount),0) AS total_spent,
       ROUND(AVG(e.total_amount), 2)   AS avg_expense,
       MIN(e.total_amount)             AS min_expense,
       MAX(e.total_amount)             AS max_expense,
       MAX(e.expense_date)             AS last_expense_date
  FROM expense_groups g
  LEFT JOIN expenses e ON e.group_id = g.group_id AND e.is_deleted = 0
 GROUP BY g.group_id, g.name;

-- 9. Aggregate view: each user's share per month (personal + group) ----
CREATE OR REPLACE VIEW v_monthly_user_spending AS
SELECT s.user_id,
       u.full_name,
       DATE_FORMAT(e.expense_date, '%Y-%m') AS spend_month,
       COUNT(*)                             AS expense_count,
       SUM(s.share_amount)                  AS my_share
  FROM expense_splits s
  JOIN expenses e ON e.expense_id = s.expense_id
  JOIN users u    ON u.user_id = s.user_id
 WHERE e.is_deleted = 0
 GROUP BY s.user_id, u.full_name, DATE_FORMAT(e.expense_date, '%Y-%m');

-- 10. Subquery-based view: budget utilisation --------------------------
CREATE OR REPLACE VIEW v_budget_utilization AS
SELECT x.*,
       x.limit_amount - x.spent                    AS remaining,
       ROUND(x.spent * 100 / x.limit_amount, 2)    AS utilization_pct,
       CASE WHEN x.spent > x.limit_amount THEN 'over_budget'
            WHEN x.spent * 100 >= x.limit_amount * x.alert_threshold THEN 'warning'
            ELSE 'ok' END                          AS budget_status
  FROM (
        SELECT b.budget_id, b.name, b.user_id, b.group_id, b.category_id,
               b.limit_amount, b.period_type, b.start_date, b.end_date, b.alert_threshold,
               CASE
                 WHEN b.user_id IS NOT NULL THEN
                   (SELECT COALESCE(SUM(s.share_amount), 0)
                      FROM expense_splits s
                      JOIN expenses e   ON e.expense_id = s.expense_id
                      JOIN categories c ON c.category_id = e.category_id
                     WHERE s.user_id = b.user_id AND e.is_deleted = 0
                       AND e.expense_date BETWEEN b.start_date AND b.end_date
                       AND (b.category_id IS NULL
                            OR b.category_id IN (c.category_id, c.parent_category_id)))
                 ELSE
                   (SELECT COALESCE(SUM(e.total_amount), 0)
                      FROM expenses e
                      JOIN categories c ON c.category_id = e.category_id
                     WHERE e.group_id = b.group_id AND e.is_deleted = 0
                       AND e.expense_date BETWEEN b.start_date AND b.end_date
                       AND (b.category_id IS NULL
                            OR b.category_id IN (c.category_id, c.parent_category_id)))
               END AS spent
          FROM budgets b
         WHERE b.is_active = 1
       ) x;

-- 11. Correlated-subquery view: expenses above their group's average ---
CREATE OR REPLACE VIEW v_expenses_above_group_avg AS
SELECT e.expense_id, e.group_id, e.description, e.total_amount,
       (SELECT ROUND(AVG(e2.total_amount), 2)
          FROM expenses e2
         WHERE e2.group_id = e.group_id AND e2.is_deleted = 0) AS group_avg
  FROM expenses e
 WHERE e.group_id IS NOT NULL
   AND e.is_deleted = 0
   AND e.total_amount > (SELECT AVG(e3.total_amount)
                           FROM expenses e3
                          WHERE e3.group_id = e.group_id AND e3.is_deleted = 0);
