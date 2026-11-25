# DBMS_EXPERIMENT_MAPPING.md — SplitWise+ (Experiments 1–12)

Renamed from `SQL_EXPERIMENT_MAPPING.md` (its Experiments 1–9 content is kept below) and extended with Experiments 10–12.

**Status legend:** **Implemented** = files exist · **Executed locally** = run on MySQL 8.0.46 / PHP 8.3 in the build environment with the result checked by a test · **Executed on cloud** = run on a hosted service · **Manually verified** = a person checked it on their own machine · **Not tested** · **Blocked** = needs something not available here (account, credentials).

## All experiments at a glance
| Exp | Title | Implementation | Test procedure | Expected outcome | Actual status |
|---|---|---|---|---|---|
| 1 | SELECT … FROM | `database/queries/01_basic_select.sql` (Q1–Q6) | `tests/database/run.sh` / *SQL scripts* suite of `php tests/run_all.php` | every query runs, returns rows | Implemented · Executed locally |
| 2 | ORDER BY | `queries/02_filtering_and_sorting.sql` (Q7–Q10) | same | sorted results | Implemented · Executed locally |
| 3 | WHERE | `queries/02_…` (Q11–Q14) | same | filtered results | Implemented · Executed locally |
| 4 | DISTINCT, LIMIT, aliases | `queries/02_…` (Q15–Q16) | same | distinct / paged results | Implemented · Executed locally |
| 5 | Joins | `queries/03_joins.sql` (Q17–Q26) | same | joined results | Implemented · Executed locally |
| 6 | Aggregates, GROUP BY, HAVING | `queries/04_aggregates.sql` (Q27–Q34) | same | grouped totals | Implemented · Executed locally |
| 7 | Subqueries | `queries/05_subqueries.sql` (Q35–Q43) | same | correct sub-results | Implemented · Executed locally |
| 8 | Triggers | `database/triggers/` | DB tests T1–T17 | each trigger fires / rejects | Implemented · Executed locally |
| 9 | Views | `database/views/` | DB tests + `demo_views.sql` | views return data; updatability as documented | Implemented · Executed locally |
| 10 | MySQL DDL + foreign keys | `experiments/10_mysql_ddl_fk/` (sandboxes `splitwise_lab_exp10`, `splitwise_lab_exp10_fkdemo`) | `php tests/experiments/test_exp10.php` (18 checks) | 13 expected errors in order; RESTRICT/CASCADE/SET NULL end states; manual's examples + PROGRAM as printed; `splitwise_plus` unchanged | Implemented · **Executed locally** (18/18) · Run by the team in Workbench on `classicmodels` (lab record 19-11-2026; see notes) |
| 11 | MongoDB Atlas + Compass | `experiments/11_mongodb_atlas_compass/` | `node tests/experiments/exp11_dry_run.js` (11 checks, in-memory stand-in); cloud steps in `ATLAS_SETUP.md` | 3 docs with `_id`; `$gt` → 2; Rahul cgpa 9; Amit deleted, 2 left | Implemented · Executed locally (dry run) · **Executed on Atlas + Compass by the team (lab record dated 20-11-2026, screenshots)** |
| 12 | Candidate Management System | `experiments/12_candidate_management/` (DB `splitwise_candidate_lab`) | `bash experiments/12_candidate_management/tests/run.sh` (31 checks, throw-away test DB) | full CRUD through the web UI; constraints enforced | Implemented · **Executed locally** (31/31) |

Experiments 1–9 use only real SplitWise+ tables (no demo-only tables). Experiments 10 and 12 run in their **own** databases and Experiment 11 on MongoDB, so nothing in them changes the production schema or `database/splitwise_plus.sql`.

### Evidence checklist, Experiments 1–9 (screenshots from your own machine)
- [ ] each query file run in MySQL Workbench / CLI with its result grid (Exp 1–7: `queries/01`–`05`)
- [ ] `SHOW TRIGGERS LIKE 'settlements'` + one rejected and one firing trigger (Exp 8)
- [ ] `SHOW FULL TABLES WHERE Table_type='VIEW'` + one view result + the 1288 error on an aggregate view (Exp 9)

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


## Experiment 10 — MySQL DDL commands and foreign key constraints
**Aim:** study and implement DDL (`CREATE/DROP DATABASE`, `CREATE/ALTER/RENAME/DROP TABLE`, `ADD/DROP COLUMN`, `DROP FOREIGN KEY`, `SHOW CREATE TABLE`) and referential actions RESTRICT, CASCADE, SET NULL.
**Files:** `experiments/10_mysql_ddl_fk/` — `01`…`10` + optional `11_cleanup_drop_database.sql`, `run.sh`, `README.md`, `POST_LAB_QUESTIONS.md`. **Sandboxes:** `splitwise_lab_exp10`, `splitwise_lab_exp10_fkdemo` (outside `database/build.sh`). Checked against the lab manual `Experiment_No_10_NEP.docx`.

| Concept | Script | SplitWise+ link |
|---|---|---|
| CREATE DATABASE (charset/collation), DROP DATABASE (scratch DB), USE | 01 | same utf8mb4 setup as `splitwise_plus` |
| CREATE TABLE, PK, composite PK, UNIQUE, CHECK, named FKs | 02 | cut-down `users`, `expense_groups`, `group_members`, `categories`, `expenses` |
| RESTRICT / NO ACTION / default → errors 1451, 1452 | 03 | `expenses.paid_by → users` (production: RESTRICT) |
| ON DELETE CASCADE, ON UPDATE CASCADE | 04 | `group_members.group_id` (production: CASCADE); `expense_groups.created_by` ON UPDATE CASCADE (production) |
| ON DELETE SET NULL; NOT NULL + SET NULL → 1830; SET DEFAULT accepted but not performed → 1451 | 05 | `expenses.deleted_by` (production: SET NULL) |
| ADD COLUMN … AFTER / DEFAULT, MODIFY, RENAME COLUMN, ADD CONSTRAINT CHECK (→ 3819), ADD INDEX, DROP CHECK, DROP COLUMN, RENAME TABLE, ALTER … RENAME TO, SHOW CREATE TABLE | 06 | |
| DROP FOREIGN KEY, orphan accepted, re-add fails (1452) until cleaned, DROP TABLE order (3730) | 07 | |
| Manual PROGRAM, statements as printed (warehouse, products.warehouseID, warehouseProducts → inventoryDetails, drop capacity, employeeWarehouse SET NULL, delete warehouse 1) | 08 | independent `products` table created in the sandbox; the manual's 4-value insert after `DROP COLUMN capacity` shown as error 1136, then corrected |
| Manual theory examples as printed (tasks/checklists; fkdemo categories/products: RESTRICT 1452/1451, ON UPDATE/DELETE CASCADE, ON UPDATE/DELETE SET NULL, DROP FOREIGN KEY fk_category, ADD COLUMN FIRST/AFTER, multi DROP COLUMN) | 09 | manual's drop order categories-before-products shown as error 3730, then corrected |
| information_schema verification | 10 | read-only count proving `splitwise_plus` untouched |
| Post-lab questions (5) | `POST_LAB_QUESTIONS.md` | |

**Test procedure:** `php tests/experiments/test_exp10.php` (runs `run.sh --yes`, then 18 checks). **Expected:** errors exactly `1451 1451 1451 1452 1830 1451 3819 1452 3730 1136 1452 1451 3730`; group 2 members 3 → 0; created_by 4 → 40; deleted_by 5 → NULL; warehouse 1 deleted → allocation 101 NULL, inventoryDetails 4 → 2, 2 products keep a dangling warehouseID (no FK, as in the manual); fkdemo: CASCADE 1 → 100 and watches deleted, SET NULL leaves all 4 products NULL; `splitwise_plus` fingerprint identical. **Actual:** 18/18 passed (2026-11-18). **Evidence:** checklist in the experiment README.

**Team's lab record (Expt_No.10, 19-11-2026, MySQL Workbench, `classicmodels`):** all six PROGRAM statements shown with result grids. Two gaps to fix before the viva: (1) the last grid still shows a `capacity` column although `DROP COLUMN capacity` ran earlier (the manual's 4-value `INSERT` only works if run before the drop: error 1136 otherwise, as `08_warehouse_inventory_program.sql` shows); (2) the SET NULL result is not shown: add `SELECT * FROM employeeWarehouse;` → allocation 101 has `warehouseID` NULL.

## Experiment 11 — Cloud database with MongoDB Atlas and MongoDB Compass
**Aim:** create a MongoDB database and collection on Atlas, connect with Compass, perform CRUD on student records.
**Files:** `experiments/11_mongodb_atlas_compass/` — `ATLAS_SETUP.md`, `sample_students.json`, `mongodb_crud_commands.js`, `compass_filters.json`, `POST_LAB_QUESTIONS.md`, `screenshots/`.

| Operation | Command / filter | Expected |
|---|---|---|
| Create | `insertOne(Rahul)`, `insertMany([Priya, Amit])` (or Compass *Insert Document* with the JSON array) | 3 documents, generated `_id` |
| Read | `{}` · `{ "name": "Rahul" }` · `{ "cgpa": { "$gt": 8 } }` | 3 · 1 · Rahul + Priya |
| Update | `updateOne({ rollNo: 101 }, { $set: { cgpa: 9.0 } })` | matched 1, modified 1 |
| Delete | `deleteOne({ rollNo: 103 })` | deleted 1; 2 left |

**Test procedure:** local — `node tests/experiments/exp11_dry_run.js` runs the unchanged CRUD script against an in-memory stand-in (sequence and counts, JSON consistency, no credentials in the folder); cloud — follow `ATLAS_SETUP.md`. **Actual:** dry run 11/11 passed. **Executed on the cloud by the team (lab record Experiment_No_11, 20-11-2026):** free `StudentCluster` on Atlas, Compass connected, `CollegeDB.Students` created, Rahul/Priya/Amit inserted with generated ObjectIds, filters `{}` / name / `$gt`, Rahul's cgpa updated to 9, Amit deleted (2 left). Matches the expected results above. No MongoDB code or connection was added to SplitWise+.

## Experiment 12 — Candidate Management System (SQL + front end)
**Aim:** design and develop a Candidate Management System with ER modelling, normalization to 3NF and SQL CRUD through a front end (MySQL, PHP, HTML, CSS).
**Files:** `experiments/12_candidate_management/` — `docs/REQUIREMENTS.md`, `docs/ER_DIAGRAM.md` (+ `er_diagram.png/svg/dot` generated from `information_schema`), `docs/NORMALIZATION.md`, `database/candidate_management.sql`, `sample_data.sql`, `app_user.sql`, `crud_queries.sql`, `public/*.php`, `src/`, `config/database.php`, `tests/`.

| Concept | Where |
|---|---|
| ER model: Candidate 1:N Application N:1 Position (resolves M:N), cardinality, participation | `docs/ER_DIAGRAM.md` |
| UNF → 1NF → 2NF (partial dependencies removed) → 3NF (transitive removed), FDs listed | `docs/NORMALIZATION.md` |
| INSERT (transaction), SELECT with JOIN/subquery/EXISTS/LIKE search, UPDATE, DELETE + CASCADE | `database/crud_queries.sql`, `src/CandidateRepository.php` |
| UNIQUE, CHECK (phone regexp, email shape), ENUM, NOT NULL, FK CASCADE/RESTRICT | `database/candidate_management.sql` |
| PDO prepared statements, server-side validation, CSRF, escaping, confirmation before delete, empty states | `public/`, `src/` |

**Test procedure:** `bash experiments/12_candidate_management/tests/run.sh`. **Actual:** 31/31 passed (2026-11-18), axe 0 violations; results in `tests/CRUD_TESTS.md`; real screenshots of the local run in `docs/screenshots/`.


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
