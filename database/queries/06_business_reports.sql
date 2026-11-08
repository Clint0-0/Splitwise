-- =====================================================================
-- SplitWise+ | 06_business_reports.sql — reports used by the application
-- (views, procedures, functions)
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

-- Q44 Group balances (who gets back / who owes)
-- Purpose : group "Balances" tab.  Concept : stored procedure over a view
CALL sp_get_group_balances(1);

-- Q45 Pairwise debts with original / settled / remaining
-- Explanation : outstanding > 0 → user_a owes user_b; < 0 → user_b owes user_a.
-- Purpose : "You owe Aarav ₹x" lines.  Concept : aggregate view with UNION ALL
SELECT g.name AS group_name,
       IF(d.outstanding > 0, ua.full_name, ub.full_name) AS debtor,
       IF(d.outstanding > 0, ub.full_name, ua.full_name) AS creditor,
       ABS(d.outstanding)                                 AS remaining
  FROM v_pairwise_debts d
  JOIN users ua ON ua.user_id = d.user_a
  JOIN users ub ON ub.user_id = d.user_b
  JOIN expense_groups g ON g.group_id = d.group_id
 WHERE d.outstanding <> 0
 ORDER BY g.name, remaining DESC;

-- Q46 Overall balance per user across all groups
-- Purpose : dashboard header ("You are owed ₹x").  Concept : view on a view
SELECT full_name, receivable, payable, net_balance
  FROM v_user_overall_balance
 ORDER BY net_balance DESC;

-- Q47 Budget utilisation
-- Purpose : budgets page progress bars.  Concept : subquery-based view
SELECT name, limit_amount, spent, remaining, utilization_pct, budget_status
  FROM v_budget_utilization
 ORDER BY utilization_pct DESC;

-- Q48 Priya's October spending by category
-- Purpose : analytics doughnut chart.  Concept : stored procedure with parameters
CALL sp_spending_summary(3, '2026-10-01', '2026-10-31');

-- Q49 Top 3 personal spenders this month (by share)
-- Purpose : admin statistics.  Concept : view + ORDER BY + LIMIT
SELECT full_name, my_share
  FROM v_monthly_user_spending
 WHERE spend_month = '2026-11'
 ORDER BY my_share DESC
 LIMIT 3;

-- Q50 Pending settlements waiting for me (payee = Aarav)
-- Purpose : notification centre / settlements inbox.  Concept : join view + WHERE
SELECT settlement_id, payer_name, amount, settlement_date
  FROM v_settlement_details
 WHERE payee_id = 2 AND status = 'pending';
