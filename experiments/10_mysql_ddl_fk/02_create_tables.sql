-- =====================================================================
-- Experiment 10 · 02  CREATE TABLE with PRIMARY KEY, UNIQUE, CHECK and FOREIGN KEY
-- Sandbox copies of real SplitWise+ tables (same names, fewer columns).
-- Referential actions are the SAME ones the production schema uses:
--   expenses.paid_by    -> users            RESTRICT  (money history must never vanish)
--   group_members.group -> expense_groups   CASCADE   (membership rows are pure association rows)
--   expenses.deleted_by -> users            SET NULL  (only "who deleted it" is lost, expense stays)
-- =====================================================================
USE splitwise_lab_exp10;

-- [1] Parent table: users
CREATE TABLE users (
  user_id    INT UNSIGNED AUTO_INCREMENT,
  full_name  VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL,
  CONSTRAINT pk_users PRIMARY KEY (user_id),
  CONSTRAINT uq_users_email UNIQUE (email)
) ENGINE=InnoDB;

-- [2] expense_groups: created_by -> users, ON DELETE RESTRICT ON UPDATE CASCADE
--     (production fk_expense_groups_created_by uses exactly this pair)
CREATE TABLE expense_groups (
  group_id    INT UNSIGNED AUTO_INCREMENT,
  group_name  VARCHAR(100) NOT NULL,
  created_by  INT UNSIGNED NOT NULL,
  CONSTRAINT pk_expense_groups PRIMARY KEY (group_id),
  CONSTRAINT fk_expense_groups_created_by FOREIGN KEY (created_by)
    REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- [3] group_members: association table (M:N users <-> groups), composite primary key
CREATE TABLE group_members (
  group_id  INT UNSIGNED NOT NULL,
  user_id   INT UNSIGNED NOT NULL,
  role      VARCHAR(10)  NOT NULL DEFAULT 'member',
  CONSTRAINT pk_group_members PRIMARY KEY (group_id, user_id),
  CONSTRAINT chk_group_members_role CHECK (role IN ('admin', 'member')),
  CONSTRAINT fk_group_members_group FOREIGN KEY (group_id)
    REFERENCES expense_groups (group_id) ON DELETE CASCADE,
  CONSTRAINT fk_group_members_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- [4] categories (lookup)
CREATE TABLE categories (
  category_id    SMALLINT UNSIGNED AUTO_INCREMENT,
  category_name  VARCHAR(50) NOT NULL,
  CONSTRAINT pk_categories PRIMARY KEY (category_id),
  CONSTRAINT uq_categories_name UNIQUE (category_name)
) ENGINE=InnoDB;

-- [5] expenses: three foreign keys with three different behaviours
--     group_id has NO action clause on purpose -> default (NO ACTION = RESTRICT in InnoDB)
CREATE TABLE expenses (
  expense_id    INT UNSIGNED AUTO_INCREMENT,
  group_id      INT UNSIGNED NOT NULL,
  paid_by       INT UNSIGNED NOT NULL,
  category_id   SMALLINT UNSIGNED NOT NULL,
  description   VARCHAR(150) NOT NULL,
  total_amount  DECIMAL(12,2) NOT NULL,
  deleted_by    INT UNSIGNED NULL,          -- must be NULLable for ON DELETE SET NULL
  CONSTRAINT pk_expenses PRIMARY KEY (expense_id),
  CONSTRAINT chk_expenses_amount CHECK (total_amount > 0),
  CONSTRAINT fk_expenses_group     FOREIGN KEY (group_id)    REFERENCES expense_groups (group_id),
  CONSTRAINT fk_expenses_paid_by   FOREIGN KEY (paid_by)     REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_expenses_category  FOREIGN KEY (category_id) REFERENCES categories (category_id) ON DELETE RESTRICT,
  CONSTRAINT fk_expenses_deleted_by FOREIGN KEY (deleted_by) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Sample data (fictional)
INSERT INTO users (user_id, full_name, email) VALUES
  (1, 'Priya Nair',   'priya@example.test'),
  (2, 'Aarav Sharma', 'aarav@example.test'),
  (3, 'Kabir Singh',  'kabir@example.test'),
  (4, 'Meera Iyer',   'meera@example.test'),   -- only created a group (used in the CASCADE update demo)
  (5, 'Temp Admin',   'temp@example.test');    -- only soft-deleted an expense (SET NULL demo)
INSERT INTO expense_groups (group_id, group_name, created_by) VALUES
  (1, 'Flat 302 Roommates', 1),
  (2, 'Weekend Trek',       4);                -- members but no expenses (CASCADE delete demo)
INSERT INTO group_members (group_id, user_id, role) VALUES
  (1, 1, 'admin'), (1, 2, 'member'), (1, 3, 'member'),
  (2, 4, 'admin'), (2, 1, 'member'), (2, 2, 'member');
INSERT INTO categories (category_id, category_name) VALUES (1, 'Rent'), (2, 'Groceries'), (3, 'Utilities');
INSERT INTO expenses (expense_id, group_id, paid_by, category_id, description, total_amount, deleted_by) VALUES
  (1, 1, 1, 1, 'Rent - November',  36000.00, NULL),
  (2, 1, 2, 2, 'Weekly groceries',  1850.75, NULL),
  (3, 1, 3, 3, 'Electricity bill',  3250.00, 5);   -- soft-deleted by user 5

-- Verification: tables and their foreign keys with ON DELETE / ON UPDATE rules.
SHOW TABLES;
SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, DELETE_RULE, UPDATE_RULE
  FROM information_schema.REFERENTIAL_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA = DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME;
-- Expected: fk_expenses_group shows NO ACTION / NO ACTION (no clause written = default).
