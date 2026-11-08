# SQL_EXPERIMENT_MAPPING.md — SplitWise+

Status: **Implemented and executed (Phase 2)** on MySQL 8.0.46 — every script runs without error (`tests/database/run.sh`). Every example uses real SplitWise+ tables. No demo-only tables.

## Implemented query numbers (Q1–Q50)

| Exp | Query numbers / objects | File |
|---|---|---|
| 1 SELECT | Q1–Q6 | `queries/01_basic_select.sql` |
| 2 ORDER BY | Q7–Q10 | `queries/02_filtering_and_sorting.sql` |
| 3 WHERE | Q11–Q14 (comparison, BETWEEN, LIKE `%`/`_`, IN, AND/OR/NOT, IS [NOT] NULL) | same |
| 4 DISTINCT / LIMIT / aliases | Q15–Q16 (LIMIT OFFSET and LIMIT offset,count) | same |
| 5 Joins | Q17 INNER/ON · Q18 3-way · Q19 USING · Q20 double alias · Q21 LEFT · Q22 RIGHT · Q23–Q24 SELF · Q25 CROSS · Q26 FULL OUTER emulation | `queries/03_joins.sql` |
| 6 Aggregates | Q27–Q34 (SUM, COUNT, AVG, MIN, MAX, GROUP BY expression / multi-column, HAVING) | `queries/04_aggregates.sql` |
| 7 Subqueries | Q35 scalar · Q36 correlated NOT EXISTS · Q37 nested + derived table · Q38 IN multi-row · Q39 correlated · Q40 NOT EXISTS · Q41 correlated MAX · Q42 ALL/ANY · Q43 NOT IN NULL pitfall | `queries/05_subqueries.sql` |
| 8 Triggers | 11 triggers + `triggers/demo_triggers.sql` (SHOW TRIGGERS, SHOW CREATE TRIGGER, each timing/event firing with OLD/NEW, CREATE → DROP TRIGGER); tests T1–T17 | `triggers/` |
| 9 Views | 11 views + `views/demo_views.sql` (SHOW [FULL] TABLES, CREATE / CREATE OR REPLACE / DROP VIEW, updatable vs not, CHECK OPTION) | `views/` |
| Reports | Q44–Q50 (procedures, view-on-view, pairwise debts, budgets) | `queries/06_business_reports.sql` |
| Indexing | EXPLAIN A–E | `queries/07_explain.sql` |
| ACID | atomicity, savepoint, consistency, isolation, durability | `transactions/demo_acid.sql` |

The tables below explain the concept behind each query (planning numbering).

| Exp | Topic | Main tables | Script |
|---|---|---|---|
| 1 | SELECT … FROM | users, expenses, expense_groups, settlements | `database/queries/01_basic_select.sql` |
| 2 | ORDER BY | expenses, users, settlements | `database/queries/02_filtering_and_sorting.sql` |
| 3 | WHERE | expenses, users, categories | `database/queries/02_filtering_and_sorting.sql` |
| 4 | DISTINCT, LIMIT, OFFSET, aliases | expenses, categories, group_members | `database/queries/02_filtering_and_sorting.sql` |
| 5 | Joins | all core tables | `database/queries/03_joins.sql` |
| 6 | GROUP BY, aggregates | expenses, expense_splits, settlements | `database/queries/04_aggregates.sql` |
| 7 | Subqueries | expenses, users, expense_groups, settlements | `database/queries/05_subqueries.sql` |
| 8 | Triggers | expenses, expense_splits, settlements, audit_logs, notifications, users (demo) | `database/triggers/triggers.sql`, `database/triggers/demo_triggers.sql` |
| 9 | Views | see view list | `database/views/*.sql` |

---

## Experiment 1 — SELECT FROM
Concepts: single column, multiple columns, `*`, SELECT without FROM, expressions.

| # | Query | Concept |
|---|---|---|
| 1.1 | `SELECT full_name FROM users;` — member directory | single column |
| 1.2 | `SELECT description, total_amount, expense_date FROM expenses;` | multiple columns |
| 1.3 | `SELECT * FROM expense_groups;` | all columns |
| 1.4 | `SELECT payer_id, payee_id, amount, status FROM settlements;` | selected settlement columns |
| 1.5 | `SELECT 1500 / 3 AS equal_share, ROUND(1000/3, 2) AS rounded_share, NOW() AS server_time;` — shows why the rounding rule exists | SELECT without FROM, expressions |
| 1.6 | `SELECT description, total_amount, total_amount * 0.18 AS gst_component FROM expenses;` | computed column |

## Experiment 2 — ORDER BY
| # | Query | Concept |
|---|---|---|
| 2.1 | Latest expenses first (`ORDER BY expense_date DESC, created_at DESC`) | DESC, multi-column |
| 2.2 | Highest expenses first | DESC |
| 2.3 | Users alphabetically (`ORDER BY full_name ASC`) | ASC |
| 2.4 | Settlements by date, oldest first | ASC on date |
| 2.5 | Expenses by amount DESC then date ASC | multi-column, mixed direction |

## Experiment 3 — WHERE
| # | Query | Concept |
|---|---|---|
| 3.1 | Expenses `> 2000` | comparison |
| 3.2 | Expenses `BETWEEN '2026-10-01' AND '2026-10-31'` | BETWEEN |
| 3.3 | Users `WHERE full_name LIKE 'Ra%'` | LIKE, `%` |
| 3.4 | Usernames `LIKE 'a_i%'` | `_` wildcard |
| 3.5 | Expenses in category Food AND group Goa Trip AND NOT deleted | AND, NOT |
| 3.6 | Expenses in Travel OR Transport | OR |
| 3.7 | Users `WHERE phone IS NULL` (incomplete profiles) | IS NULL |
| 3.8 | Expenses `WHERE notes IS NOT NULL` | IS NOT NULL |
| 3.9 | Settlements `status <> 'confirmed' AND amount >= 500` | combined |

## Experiment 4 — DISTINCT, LIMIT, OFFSET, aliases
| # | Query | Concept |
|---|---|---|
| 4.1 | `SELECT DISTINCT category_id FROM expenses` — categories actually used | DISTINCT |
| 4.2 | `SELECT DISTINCT group_id FROM group_members WHERE user_id = ?` | DISTINCT |
| 4.3 | Expense history page 3, 10 per page: `LIMIT 10 OFFSET 20` | LIMIT OFFSET (app pagination) |
| 4.4 | Same with `LIMIT 20, 10` | LIMIT offset, count |
| 4.5 | 5 most recent transactions | LIMIT |
| 4.6 | `SELECT e.description AS "Expense", e.total_amount AS "Amount (₹)" FROM expenses AS e` | column + table aliases |

## Experiment 5 — Joins
| # | Query | Join | Business meaning |
|---|---|---|---|
| 5.1 | expenses ⋈ users ON paid_by | INNER, ON | expense with payer name |
| 5.2 | group_members ⋈ users ⋈ expense_groups | INNER (3-way) | members of each group |
| 5.3 | expense_splits JOIN expenses **USING (expense_id)** | INNER, USING | participants of each expense |
| 5.4 | settlements ⋈ users p ⋈ users r | INNER with two aliases | "Rahul paid Priya ₹300" |
| 5.5 | users LEFT JOIN expenses ON paid_by | LEFT | users incl. those who never paid |
| 5.6 | expenses RIGHT JOIN categories | RIGHT | every category incl. unused ones |
| 5.7 | group_members gm1 JOIN group_members gm2 ON same group, gm1.user_id < gm2.user_id | **SELF** | pairs of users who share a group (who can owe whom) |
| 5.8 | categories c JOIN categories p ON c.parent_category_id = p.category_id | **SELF** | sub-category with parent (Groceries → Food & Dining) |
| 5.9 | active members of a group **CROSS JOIN** active categories, then LEFT JOIN spending | **CROSS** | member × category spending matrix showing ₹0 cells. Cartesian product size = members × categories is shown and explained |
| 5.10 | LEFT JOIN … UNION … RIGHT JOIN | FULL OUTER emulation | users who paid vs users who owe, incl. either side missing. MySQL has no `FULL OUTER JOIN` |

## Experiment 6 — GROUP BY and aggregates
| # | Query | Concept |
|---|---|---|
| 6.1 | Total spending per group | SUM, GROUP BY |
| 6.2 | Total paid per user | SUM |
| 6.3 | Total spending per category | SUM + JOIN |
| 6.4 | Average expense amount | AVG |
| 6.5 | Highest and lowest expense per group | MAX, MIN |
| 6.6 | Expenses per month: `GROUP BY DATE_FORMAT(expense_date,'%Y-%m')` | COUNT, grouping by expression |
| 6.7 | Monthly share per user: `GROUP BY user_id, month` | multi-column grouping |
| 6.8 | Confirmed settlement total per group | SUM + WHERE |
| 6.9 | Groups with spending `HAVING SUM(total_amount) > 10000` | HAVING |
| 6.10 | Categories used `HAVING COUNT(*) >= 3` | HAVING with COUNT |

All queries comply with `ONLY_FULL_GROUP_BY` (enabled by default in MySQL 8).

## Experiment 7 — Subqueries
| # | Query | Kind |
|---|---|---|
| 7.1 | Expenses above overall average: `WHERE total_amount > (SELECT AVG(...))` | scalar, non-correlated |
| 7.2 | Users who never paid an expense: `NOT EXISTS (SELECT 1 FROM expenses e WHERE e.paid_by = u.user_id)` | correlated, NOT EXISTS |
| 7.3 | Groups whose spending exceeds the average group spending | nested (subquery inside subquery) |
| 7.4 | Users in the "Goa Trip" group: `user_id IN (SELECT user_id FROM group_members WHERE group_id = (SELECT …))` | multi-row IN + nested scalar |
| 7.5 | Expenses greater than *their own group's* average | correlated |
| 7.6 | Groups with no settlements | NOT EXISTS |
| 7.7 | Highest expense per group | correlated `= (SELECT MAX…)` |
| 7.8 | Per-group totals from a derived table, then filtered | derived table (`FROM (SELECT …) AS t`) |
| 7.9 | Expenses larger than ALL expenses of a given group / ANY | multi-row comparison (`> ALL`, `> ANY`) |
| 7.10 | NOT IN vs NOT EXISTS when subquery returns NULL — demonstration of why NOT IN returns no rows if a NULL appears (`expenses.group_id` is nullable) | NULL pitfall |

## Experiment 8 — Triggers
| Timing/event | Trigger | Business purpose | Demo |
|---|---|---|---|
| BEFORE INSERT | `trg_expenses_before_insert`, `trg_expense_splits_before_insert` | reject future dates / non-member payer / non-member participant (`SIGNAL SQLSTATE '45000'`) | insert invalid row → error |
| AFTER INSERT | `trg_settlements_after_insert` | create notification for the other party + audit row | insert settlement → row in `notifications` |
| BEFORE UPDATE | `trg_settlements_before_update` | state machine (pending → confirmed/rejected/cancelled; finals immutable), sets `responded_at` using `NEW` | update confirmed → error |
| AFTER UPDATE | `trg_expenses_after_update` | audit `OLD`→`NEW` JSON; soft delete logged as `SOFT_DELETE` | edit expense → audit row |
| BEFORE DELETE | `trg_settlements_before_delete` | confirmed settlements can't be deleted | delete → error |
| AFTER DELETE | `trg_expense_splits_after_delete`, `trg_expenses_after_delete` | archive deleted rows (`OLD`) | re-split expense → old splits archived |

Also demonstrated: `FOR EACH ROW` (MySQL has row-level triggers only), `SHOW TRIGGERS LIKE 'settlements';`, `DROP TRIGGER IF EXISTS …;`, and the caveat that FK cascades do not fire triggers.

## Experiment 9 — Views
| View | Type demonstrated |
|---|---|
| `v_active_expenses` | simple single-table, **updatable**, `WITH CHECK OPTION` (an UPDATE setting `is_deleted=1` through it is rejected) |
| `v_active_group_members` | simple, updatable |
| `v_expense_details` | join view |
| `v_settlement_details` | join view (self-aliased users) |
| `v_group_expense_summary`, `v_monthly_user_spending` | aggregate views (non-updatable) |
| `v_budget_utilization`, `v_expenses_above_group_avg` | subquery-based views |
| `v_group_member_balances` | derived tables + aggregates |
| `v_user_overall_balance` | **view based on another view** |

Plus: `CREATE VIEW`, `CREATE OR REPLACE VIEW`, `SHOW TABLES`, `SHOW FULL TABLES WHERE Table_type = 'VIEW'`, `DROP VIEW IF EXISTS`, and an UPDATE attempt on an aggregate view → error 1288 ("target table is not updatable") with explanation (views with GROUP BY, DISTINCT, aggregates, UNION, or non-MERGE algorithm are not updatable).

**Measured updatability** (`information_schema.VIEWS.IS_UPDATABLE`): YES for `v_active_expenses`, `v_active_group_members`, `v_settlement_details` (inner join — an UPDATE may touch one base table only) and `v_budget_utilization` (derived table merged — only pass-through columns; computed columns raise "not updatable"). All others NO.

---

## Proposed query collection (≈46 queries)

Each query in the files carries: number, title, SQL, explanation, business purpose, DBMS concept.

| File | Count | Contents |
|---|---|---|
| `01_basic_select.sql` | 6 | Exp 1 |
| `02_filtering_and_sorting.sql` | 10 | best of Exp 2, 3, 4 (not every row above duplicated) |
| `03_joins.sql` | 10 | Exp 5 |
| `04_aggregates.sql` | 8 | Exp 6 |
| `05_subqueries.sql` | 8 | Exp 7 |
| `06_business_reports.sql` | 6 | group balances, pairwise debts, settlement summary, budget utilization, top spenders this month, category trend (uses views + functions) |

Additional scripts: `database/transactions/demo_acid.sql` (COMMIT, ROLLBACK, SAVEPOINT on expense + splits; concurrent settlement with `FOR UPDATE` in two sessions), `database/queries/07_explain.sql` (EXPLAIN before/after indexes).
