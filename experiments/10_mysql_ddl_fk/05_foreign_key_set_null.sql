-- =====================================================================
-- Experiment 10 · 05  SET NULL (+ what NO ACTION and SET DEFAULT really do)
-- Contains statements that are EXPECTED to fail. Run with:  mysql --force
-- =====================================================================
USE splitwise_lab_exp10;

-- Before: expense 3 was soft-deleted by user 5.
SELECT expense_id, description, deleted_by FROM expenses WHERE expense_id = 3;  -- Expected deleted_by = 5

-- [1] ON DELETE SET NULL: remove user 5. The expense stays; only "deleted by" becomes unknown.
-- Why: the expense is money history and must remain; who-deleted-it is optional information.
DELETE FROM users WHERE user_id = 5;                   -- Expected: Query OK, 1 row affected

-- Verification
SELECT expense_id, description, deleted_by FROM expenses WHERE expense_id = 3;  -- Expected deleted_by = NULL
SELECT COUNT(*) AS expenses_still_there FROM expenses;                          -- Expected 3

-- [2] SET NULL needs a NULLable column. MySQL refuses the definition otherwise.
-- EXPECT ERROR 1830 (Column cannot be NOT NULL: needed in a foreign key constraint SET NULL)
CREATE TABLE bad_set_null (
  id      INT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  CONSTRAINT fk_bad_set_null FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL
);

-- [3] SET DEFAULT. Measured on MySQL 8.0.46: the definition is ACCEPTED and stored,
-- but InnoDB does not perform it - deleting the parent is rejected like RESTRICT.
-- So SET DEFAULT is not a working referential action in InnoDB.
CREATE TABLE set_default_demo (
  id          INT PRIMARY KEY,
  category_id SMALLINT UNSIGNED DEFAULT 1,
  CONSTRAINT fk_set_default_demo FOREIGN KEY (category_id) REFERENCES categories (category_id) ON DELETE SET DEFAULT
);
INSERT INTO categories (category_id, category_name) VALUES (9, 'Temporary');
INSERT INTO set_default_demo VALUES (1, 9);
-- EXPECT ERROR 1451 (the row is NOT switched to the default 1)
DELETE FROM categories WHERE category_id = 9;
SELECT id, category_id FROM set_default_demo;          -- Expected: 1, 9 (unchanged)
DROP TABLE set_default_demo;
DELETE FROM categories WHERE category_id = 9;          -- now succeeds (no child left)

-- Summary of MySQL 8.0 InnoDB referential actions:
--   RESTRICT    reject the parent change at once.
--   NO ACTION   standard SQL "check at end of statement"; InnoDB checks at once -> same as RESTRICT.
--   (no clause) = NO ACTION -> behaves as RESTRICT  (see fk_expenses_group in 02/03).
--   CASCADE     delete/update the child rows too.
--   SET NULL    child foreign key column becomes NULL (column must allow NULL).
--   SET DEFAULT parsed and stored, not performed by InnoDB (parent change rejected).
