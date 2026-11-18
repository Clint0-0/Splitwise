# Experiment 10 — Post-lab questions

**1. What is the difference between the CREATE DATABASE and CREATE TABLE commands in MySQL?**
`CREATE DATABASE` creates a new, empty database (schema) — a named container with its own default character set and collation, e.g. `CREATE DATABASE splitwise_lab_exp10 CHARACTER SET utf8mb4;`. `CREATE TABLE` creates one table *inside* a database: its columns, data types, keys and constraints, e.g. `CREATE TABLE users (user_id INT PRIMARY KEY, …)`. A database holds many tables; a table cannot exist without a database (select it with `USE` or prefix it, `db.table`).

**2. What is a foreign key constraint? Explain how it helps maintain referential integrity in a relational database.**
A foreign key is a column (or set of columns) in a child table whose values must match the primary/unique key of a row in a parent table, e.g. `expenses.paid_by REFERENCES users(user_id)`. MySQL then enforces referential integrity automatically:
- a child row cannot point to a parent that does not exist (insert/update rejected, error 1452);
- a parent row that is still referenced cannot be deleted or have its key changed — or the change is propagated — according to the referential action (RESTRICT/NO ACTION → error 1451, CASCADE, SET NULL).
So there are never "orphan" rows such as an expense paid by a deleted user (script 07 shows the orphan that appears once the FK is dropped).

**3. Differentiate between ON DELETE CASCADE, ON DELETE SET NULL and ON DELETE RESTRICT with suitable examples.**
| Action | When the parent row is deleted… | Example from this experiment |
|---|---|---|
| `ON DELETE CASCADE` | the matching child rows are deleted too | deleting warehouse 1 deletes its rows in `inventoryDetails`; deleting group 2 deletes its `group_members` rows |
| `ON DELETE SET NULL` | the child's foreign-key column becomes NULL (column must allow NULL); child rows stay | deleting warehouse 1 sets `employeeWarehouse.warehouseID` of allocation 101 to NULL; deleting user 5 sets `expenses.deleted_by` to NULL |
| `ON DELETE RESTRICT` (= NO ACTION = default) | the delete is rejected while child rows exist (error 1451) | `DELETE FROM users WHERE user_id = 1` fails because user 1 paid an expense |
Use CASCADE for rows that mean nothing without the parent (association rows), SET NULL for optional links, RESTRICT for history that must never disappear (SplitWise+ money records).

**4. What is the purpose of the ALTER TABLE command? Explain how it can be used to add and drop columns from an existing table.**
`ALTER TABLE` changes the structure of an existing table without losing its data: add/drop/modify/rename columns, add/drop constraints and indexes, rename the table.
- Add: `ALTER TABLE products ADD COLUMN warehouseID INT AFTER productLine;` (`FIRST` or `AFTER col` sets the position; existing rows get NULL or the column's DEFAULT).
- Drop: `ALTER TABLE warehouse DROP COLUMN capacity;` — the column and its data are removed; several at once: `ALTER TABLE t DROP COLUMN a, DROP COLUMN b;`. A column used by a CHECK or a foreign key must have that constraint removed first.

**5. What is the difference between DROP DATABASE, DROP TABLE and RENAME TABLE commands? Explain their effects on database objects.**
| Command | Effect |
|---|---|
| `DROP DATABASE db` | permanently deletes the database and **every** object in it (tables, views, triggers, routines) and all data; `IF EXISTS` avoids an error if missing |
| `DROP TABLE t` | permanently deletes one table: its rows, indexes and triggers; refused (error 3730) while another table's foreign key references it — drop the child first |
| `RENAME TABLE a TO b` | only changes the name; data, indexes and constraints are kept, and foreign keys pointing to it follow automatically (`warehouseProducts` → `inventoryDetails`) |
DROP commands cannot be rolled back (DDL causes an implicit commit) — hence the backups and the separate sandbox in this experiment.
