# Experiment 10 — MySQL DDL Commands and Foreign Key Constraints

**Aim:** study and implement MySQL DDL — `CREATE/DROP DATABASE`, `CREATE/ALTER/RENAME/DROP TABLE`, `ADD/DROP/MODIFY/RENAME COLUMN`, foreign keys, `DROP FOREIGN KEY`, `SHOW CREATE TABLE` — and the referential actions **RESTRICT, CASCADE, SET NULL** (plus what NO ACTION and SET DEFAULT really do in InnoDB).

Follows the lab manual (`Experiment_No_10_NEP.docx`): its theory examples (tasks/checklists, fkdemo categories/products) and its PROGRAM (warehouse/inventory) are run **as printed**, plus the same concepts on SplitWise+ tables.

Everything runs in **separate sandbox databases `splitwise_lab_exp10` and `splitwise_lab_exp10_fkdemo`** (the manual's `fkdemo`, renamed with the sandbox prefix). Nothing here is part of `database/build.sh` or `database/splitwise_plus.sql`, and no script names `splitwise_plus` except one read-only count in `10`.

## How the examples relate to SplitWise+
The sandbox holds cut-down copies of real SplitWise+ tables (`users`, `expense_groups`, `group_members`, `categories`, `expenses`) and uses **the same referential actions as the production schema** (`docs/DATABASE_SCHEMA.md`):

| Foreign key | Action | Why (same reason as production) |
|---|---|---|
| `expenses.paid_by → users` | ON DELETE RESTRICT | money history must never disappear or point to nobody |
| `expenses.group_id → expense_groups` | *(no clause)* = NO ACTION ≡ RESTRICT | default behaviour demo |
| `group_members.group_id → expense_groups` | ON DELETE CASCADE | membership rows mean nothing without the group |
| `expense_groups.created_by → users` | ON DELETE RESTRICT ON UPDATE CASCADE | key change follows; deletion blocked |
| `expenses.deleted_by → users` | ON DELETE SET NULL | expense stays, "deleted by" becomes unknown |

CASCADE is deliberately **not** used on financial rows (expenses, settlements).

## Files and order
| # | File | Shows |
|---|---|---|
| 01 | `01_create_database.sql` | `SHOW DATABASES`, `DROP DATABASE IF EXISTS`, `CREATE DATABASE … CHARACTER SET`, safe `DROP DATABASE` on a scratch DB, `USE` |
| 02 | `02_create_tables.sql` | `CREATE TABLE` with PK, composite PK, UNIQUE, CHECK, 5 named FKs; sample rows; FK rules from `information_schema` |
| 03 | `03_foreign_key_restrict.sql` | RESTRICT / NO ACTION / default: parent delete & key update rejected (1451), orphan insert rejected (1452) |
| 04 | `04_foreign_key_cascade.sql` | ON DELETE CASCADE (group → members), ON UPDATE CASCADE (user id → group creator) |
| 05 | `05_foreign_key_set_null.sql` | ON DELETE SET NULL; SET NULL on NOT NULL column refused (1830); SET DEFAULT accepted but not performed (1451) |
| 06 | `06_alter_and_rename.sql` | `ADD COLUMN … AFTER`, `ADD COLUMN … DEFAULT`, `MODIFY`, `RENAME COLUMN`, `ADD CONSTRAINT CHECK`, `ADD INDEX`, `DROP CHECK`, `DROP COLUMN`, `RENAME TABLE`, `ALTER TABLE … RENAME TO`, `SHOW CREATE TABLE` |
| 07 | `07_drop_foreign_key.sql` | `DROP FOREIGN KEY` (index stays), orphan accepted without FK, re-adding FK fails until cleaned, `DROP TABLE` order (3730) |
| 08 | `08_warehouse_inventory_program.sql` | the manual's PROGRAM, statements as printed (below) |
| 09 | `09_manual_fkdemo_examples.sql` | the manual's theory examples as printed: `CREATE DATABASE IF NOT EXISTS`, tasks/checklists, categories/products for RESTRICT (1452, 1451), ON UPDATE/DELETE CASCADE, ON UPDATE/DELETE SET NULL, `SHOW CREATE TABLE` + `DROP FOREIGN KEY fk_category`, RENAME TABLE, ADD COLUMN FIRST/AFTER, multi-column DROP COLUMN |
| 10 | `10_verification_queries.sql` | read-only: tables, every FK with its rules, CHECKs, row counts |
| 11 | `11_cleanup_drop_database.sql` | **optional, destructive**: drops both sandboxes |
| — | `POST_LAB_QUESTIONS.md` | answers to the manual's 5 post-lab questions |

Every numbered step in the SQL files carries: the statement, what it does, why, the expected result and a verification query. Statements that are **meant to fail** are marked `-- EXPECT ERROR <code>`.

## Running
Safety first:
1. *(Habit for any schema work — not needed for the sandbox)* back up the real DB: `mysqldump -u root -p --routines --triggers splitwise_plus > ~/splitwise_plus_backup.sql`
2. Inspect: open the scripts; check `SHOW DATABASES LIKE 'splitwise_lab_exp10%';`.
3. Run:
```bash
cd experiments/10_mysql_ddl_fk
MYSQL_ARGS="-uroot -p" ./run.sh          # asks YES, writes output/run_<time>.txt
```
With `-p`, mysql asks for the password once per script (10 times). To type it once, store it with `mysql_config_editor set --login-path=lab --user=root --password` and use `MYSQL_ARGS="--login-path=lab"`.
**MySQL Workbench 8.0 CE (the manual's tool):** *File → Open SQL Script* → `01_create_database.sql` → in the *Query* menu tick **Continue SQL Script Execution on Errors** (or the ⏯ toolbar toggle) → *Execute* (⚡). Repeat for `02` … `10` in order. The expected errors appear red in the *Output* panel — that is the point of those steps. Workbench GUI steps from the manual (*Create a new schema* → Apply, *Set as Default Schema*, *Drop Schema… → Review SQL → Execute*) can be shown on a throw-away schema such as `testdb2`.

Windows command prompt (no bash): run each file in order with `--force` so the expected errors don't stop the script:
```bat
for %f in (0*.sql 10_*.sql) do C:\xampp\mysql\bin\mysql -u root -p --force -v -v -v --table < %f
```
Do **not** run `11_cleanup_drop_database.sql` unless you want the sandboxes gone.

## Practical program (warehouse / inventory) — `08`
The manual's `products` table (`productCode`, `productLine`) is from the classicmodels sample DB and does not exist in SplitWise+, so `08` first creates an **independent** `products` table in the sandbox. Then the manual's statements, unchanged:
1. `warehouse(warehouseID INT PRIMARY KEY, warehouseName VARCHAR(100) NOT NULL, city VARCHAR(50), capacity INT)`
2. `ALTER TABLE products ADD COLUMN warehouseID INT AFTER productLine`
3. `warehouseProducts(warehouseID, productCode, quantity)`, `PRIMARY KEY (warehouseID, productCode)`, FKs to `warehouse` and `products`, both `ON DELETE CASCADE`
4. `RENAME TABLE warehouseProducts TO inventoryDetails`; `ALTER TABLE warehouse DROP COLUMN capacity`
5. `employeeWarehouse(allocationID INT PRIMARY KEY, employeeNumber INT, warehouseID INT)` with FK `ON DELETE SET NULL`
6. the manual's inserts: warehouses (1 Central/Margao, 2 North/Mapusa), allocations (101, 1002, 1), (102, 1056, 2)
7. `DELETE FROM warehouse WHERE warehouseID = 1` →

| Table | Before | After | Because |
|---|---|---|---|
| `employeeWarehouse` | 101 → 1, 102 → 2 | 101 → **NULL**, 102 → 2 | ON DELETE SET NULL |
| `inventoryDetails` *(extra rows added to show it)* | 4 rows (2 for warehouse 1) | 2 rows | ON DELETE CASCADE |
| `products.warehouseID` | 2 products → 1 | still 1 (dangling) | step 2 adds the column **without** an FK — shows why constraints matter |

### Two problems in the manual's text (handled in the scripts)
| Where | Problem | What the script does |
|---|---|---|
| PROGRAM, insert into `warehouse` | the INSERT gives 4 values (`…, 500`) but runs **after** `DROP COLUMN capacity`, so the table has 3 columns → **ERROR 1136** | runs it as printed (marked EXPECT ERROR), then the corrected 3-value insert |
| SET NULL example, step 1 | `DROP TABLE IF EXISTS categories;` before `products` — `products` still references `categories` → **ERROR 3730**, and the later `CREATE TABLE categories` would then fail too | runs it as printed (EXPECT ERROR), drops `products` first, then `categories` |

Also verified: the manual says SET DEFAULT is "rejected by InnoDB". On MySQL 8.0.46 the definition is *accepted*, but the action is never performed — the parent delete fails with 1451 (script 05). The manual says the default action is RESTRICT; `information_schema` reports it as **NO ACTION**, which behaves identically in InnoDB (scripts 02/09).

## Measured results (MySQL 8.0.46)
`./run.sh --yes` → 13 expected errors, in order: 1451, 1451, 1451, 1452 (03) · 1830, 1451 (05) · 3819 (06) · 1452, 3730 (07) · 1136 (08) · 1452, 1451, 3730 (09). All verification queries return the expected values. Automated check: `php tests/experiments/test_exp10.php`. Output of a real run: `output/` (git-ignored; regenerate with `./run.sh`).

Measured detail worth knowing: on 8.0.46 `ON DELETE SET DEFAULT` is **accepted** in `CREATE TABLE` and shown by `SHOW CREATE TABLE`, but InnoDB never performs it — deleting the parent fails with 1451, like RESTRICT.

## Evidence checklist (screenshots of your own run)
- [ ] 01 database creation + scratch DROP DATABASE
- [ ] 02 `SHOW TABLES` + FK rule table
- [ ] 03 RESTRICT errors 1451/1452
- [ ] 04 CASCADE (group 2 members 3 → 0; created_by 4 → 40)
- [ ] 05 SET NULL (deleted_by 5 → NULL) and SET DEFAULT result
- [ ] 06 ALTER TABLE column list, RENAME TABLE, SHOW CREATE TABLE
- [ ] 07 DROP FOREIGN KEY + orphan + re-add
- [ ] 08 warehouse program before/after delete (allocation 101 → NULL)
- [ ] 09 manual examples: iPad 1452, update 1451, CASCADE 1 → 100 and watches deleted, SET NULL results, SHOW CREATE TABLE before/after DROP FOREIGN KEY
- [ ] 10 verification queries
- [ ] Workbench: create schema `testdb2` via GUI, Set as Default Schema, Drop Schema → Review SQL
