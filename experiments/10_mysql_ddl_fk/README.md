# Experiment 10 — MySQL DDL Commands and Foreign Key Constraints

**Aim:** study and implement MySQL DDL — `CREATE/DROP DATABASE`, `CREATE/ALTER/RENAME/DROP TABLE`, `ADD/DROP/MODIFY/RENAME COLUMN`, foreign keys, `DROP FOREIGN KEY`, `SHOW CREATE TABLE` — and the referential actions **RESTRICT, CASCADE, SET NULL** (plus what NO ACTION and SET DEFAULT really do in InnoDB).

Everything runs in a **separate sandbox database `splitwise_lab_exp10`**. Nothing here is part of `database/build.sh` or `database/splitwise_plus.sql`, and no script names `splitwise_plus` except one read-only count in `09`.

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
| 08 | `08_warehouse_inventory_program.sql` | the manual's practical program (below) |
| 09 | `09_verification_queries.sql` | read-only: tables, every FK with its rules, CHECKs, row counts |
| 10 | `10_cleanup_drop_database.sql` | **optional, destructive**: drops the sandbox |

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
Windows / XAMPP (no bash): in a command prompt, run each file in order with `--force` so the expected errors don't stop the script:
```bat
for %f in (0*.sql) do C:\xampp\mysql\bin\mysql -u root -p --force -v -v -v --table < %f
```
(MySQL Workbench works too: open each file, *Continue on SQL error*, execute.) Do **not** run `10_cleanup_drop_database.sql` unless you want the sandbox gone.

## Practical program (warehouse / inventory) — `08`
The manual's `products` table (`productCode`, `productLine`) is from the classicmodels sample DB and does not exist in SplitWise+, so `08` first creates an **independent** `products` table in the sandbox. Then, exactly as the manual asks:
1. `warehouse(warehouseID, warehouseName, city, capacity)`
2. `ALTER TABLE products ADD COLUMN warehouseID INT NULL AFTER productLine`
3–6. `warehouseProducts(warehouseID, productCode, quantity)`, composite PK, FKs to `warehouse` and `products`, both `ON DELETE CASCADE`
7. `RENAME TABLE warehouseProducts TO inventoryDetails`
8. `ALTER TABLE warehouse DROP COLUMN capacity`
9–10. `employeeWarehouse(employeeID, employeeName, role, warehouseID NULL)` with FK `ON DELETE SET NULL`
11. sample rows (3 warehouses, 4 products, 5 inventory rows, 4 employees)
12. `DELETE FROM warehouse WHERE warehouseID = 1` →

| Table | Before | After | Because |
|---|---|---|---|
| `inventoryDetails` | 5 rows (2 for warehouse 1) | 3 rows | ON DELETE CASCADE |
| `employeeWarehouse` | Ravi, Anita → 1 | Ravi, Anita → **NULL** | ON DELETE SET NULL |
| `products.warehouseID` | 2 products → 1 | still 1 (dangling) | manual adds the column **without** an FK — shows why constraints matter |

## Measured results (MySQL 8.0.46)
`./run.sh --yes` → 9 expected errors, in order: 1451, 1451, 1451, 1452 (03) · 1830, 1451 (05) · 3819 (06) · 1452, 3730 (07). All verification queries return the expected values. Automated check: `tests/experiments/run_experiment_tests.php` (section E10). Output of a real run: `output/` (git-ignored; regenerate with `./run.sh`).

Measured detail worth knowing: on 8.0.46 `ON DELETE SET DEFAULT` is **accepted** in `CREATE TABLE` and shown by `SHOW CREATE TABLE`, but InnoDB never performs it — deleting the parent fails with 1451, like RESTRICT.

## Evidence checklist (screenshots of your own run)
- [ ] 01 database creation + scratch DROP DATABASE
- [ ] 02 `SHOW TABLES` + FK rule table
- [ ] 03 RESTRICT errors 1451/1452
- [ ] 04 CASCADE (group 2 members 3 → 0; created_by 4 → 40)
- [ ] 05 SET NULL (deleted_by 5 → NULL) and SET DEFAULT result
- [ ] 06 ALTER TABLE column list, RENAME TABLE, SHOW CREATE TABLE
- [ ] 07 DROP FOREIGN KEY + orphan + re-add
- [ ] 08 warehouse program before/after delete
- [ ] 09 verification queries
