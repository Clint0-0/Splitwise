-- =====================================================================
-- Experiment 10 · 07  DROP FOREIGN KEY, SHOW CREATE TABLE, DROP TABLE
-- Contains statements that are EXPECTED to fail. Run with:  mysql --force
-- =====================================================================
USE splitwise_lab_exp10;

-- [1] SHOW CREATE TABLE before: constraint fk_expenses_paid_by is present.
SHOW CREATE TABLE expenses;

-- [2] DROP FOREIGN KEY by its constraint name. The supporting index stays.
ALTER TABLE expenses DROP FOREIGN KEY fk_expenses_paid_by;
SELECT COUNT(*) AS fk_left FROM information_schema.REFERENTIAL_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_expenses_paid_by';      -- Expected 0
SHOW INDEX FROM expenses WHERE Key_name = 'fk_expenses_paid_by';                        -- Expected: index still there

-- [3] Why constraints matter: without the FK an orphan row is accepted.
INSERT INTO expenses (group_id, paid_by, category_id, description, total_amount)
VALUES (1, 999, 1, 'Orphan - payer 999 does not exist', 10.00);   -- Expected: Query OK (no FK to stop it)
SELECT e.expense_id, e.paid_by, u.user_id AS matching_user
  FROM expenses e LEFT JOIN users u ON u.user_id = e.paid_by
 WHERE u.user_id IS NULL;                                           -- Expected: 1 orphan row

-- [4] Re-adding the FK fails while the orphan exists.
-- EXPECT ERROR 1452
ALTER TABLE expenses ADD CONSTRAINT fk_expenses_paid_by
  FOREIGN KEY (paid_by) REFERENCES users (user_id) ON DELETE RESTRICT;
-- Clean the orphan, then re-add: succeeds.
DELETE FROM expenses WHERE paid_by = 999;
ALTER TABLE expenses ADD CONSTRAINT fk_expenses_paid_by
  FOREIGN KEY (paid_by) REFERENCES users (user_id) ON DELETE RESTRICT;
SHOW CREATE TABLE expenses;                                        -- Expected: fk_expenses_paid_by back

-- [5] DROP TABLE order: a parent referenced by a child cannot be dropped first.
CREATE TABLE tags (tag_id INT UNSIGNED PRIMARY KEY, tag_name VARCHAR(30) NOT NULL);
CREATE TABLE expense_tags (
  expense_id INT UNSIGNED NOT NULL, tag_id INT UNSIGNED NOT NULL,
  CONSTRAINT pk_expense_tags PRIMARY KEY (expense_id, tag_id),
  CONSTRAINT fk_expense_tags_expense FOREIGN KEY (expense_id) REFERENCES expenses (expense_id) ON DELETE CASCADE,
  CONSTRAINT fk_expense_tags_tag     FOREIGN KEY (tag_id)     REFERENCES tags (tag_id) ON DELETE CASCADE
);
-- EXPECT ERROR 3730 (Cannot drop table 'tags' referenced by a foreign key constraint)
DROP TABLE tags;
DROP TABLE expense_tags;                               -- child first
DROP TABLE tags;                                       -- then parent: OK
SHOW TABLES;                                           -- Expected: tags / expense_tags gone
