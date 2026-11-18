-- =====================================================================
-- Experiment 10 · 09  The lab manual's own theory examples, run as printed
-- (tasks/checklists, fkdemo categories/products for RESTRICT, CASCADE, SET NULL,
--  DROP FOREIGN KEY, ADD/DROP COLUMN syntax).
-- The manual's database `fkdemo` is created here as `splitwise_lab_exp10_fkdemo`
-- (sandbox prefix, so no lab script can ever hit another database).
-- !! DESTRUCTIVE FOR THIS SANDBOX ONLY. Contains statements EXPECTED to fail: mysql --force
-- =====================================================================
DROP DATABASE IF EXISTS splitwise_lab_exp10_fkdemo;
CREATE DATABASE IF NOT EXISTS splitwise_lab_exp10_fkdemo;   -- manual: CREATE DATABASE [IF NOT EXISTS] …
USE splitwise_lab_exp10_fkdemo;

-- ---------------------------------------------------------------- CREATE TABLE examples
-- 1) Basic CREATE TABLE
CREATE TABLE tasks (
  id INT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  start_date DATE,
  due_date DATE
);
-- 2) Table with a composite PK and a foreign key (ON UPDATE RESTRICT, ON DELETE CASCADE)
CREATE TABLE checklists(
  id INT,
  task_id INT,
  title VARCHAR(255) NOT NULL,
  is_completed BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (id, task_id),
  FOREIGN KEY (task_id)
    REFERENCES tasks (id)
    ON UPDATE RESTRICT
    ON DELETE CASCADE
);
INSERT INTO tasks VALUES (1, 'Prepare lab record', '2026-11-01', '2026-11-20');
INSERT INTO checklists (id, task_id, title) VALUES (1, 1, 'Write aim'), (2, 1, 'Paste outputs');
DELETE FROM tasks WHERE id = 1;                                   -- ON DELETE CASCADE
SELECT COUNT(*) AS checklist_rows_left FROM checklists;           -- Expected 0

-- ---------------------------------------------------------------- 1) RESTRICT & NO ACTION
CREATE TABLE categories(
  categoryId INT AUTO_INCREMENT PRIMARY KEY,
  categoryName VARCHAR(100) NOT NULL
) ENGINE = INNODB;
CREATE TABLE products(
  productId INT AUTO_INCREMENT PRIMARY KEY,
  productName VARCHAR(100) NOT NULL,
  categoryId INT,
  CONSTRAINT fk_category FOREIGN KEY (categoryId)
    REFERENCES categories(categoryId)
) ENGINE = INNODB;
INSERT INTO categories(categoryName)
VALUES
('Smartphone'),
('Smartwatch');
SELECT * FROM categories;
INSERT INTO products(productName, categoryId)
VALUES('iPhone',1);                                               -- works: category 1 exists
-- EXPECT ERROR 1452 (category 3 does not exist)
INSERT INTO products(productName, categoryId)
VALUES('iPad',3);
-- EXPECT ERROR 1451 (category 1 is referenced by iPhone)
UPDATE categories
SET categoryId = 100
WHERE categoryId = 1;
-- No clause was written, so MySQL 8.0 reports NO ACTION (behaves as RESTRICT):
SELECT DELETE_RULE, UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_category';

-- ---------------------------------------------------------------- 2) CASCADE
DROP TABLE products;
CREATE TABLE products(
  productId INT AUTO_INCREMENT PRIMARY KEY,
  productName varchar(100) not null,
  categoryId INT NOT NULL,
  CONSTRAINT fk_category
    FOREIGN KEY (categoryId)
    REFERENCES categories(categoryId)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=INNODB;
INSERT INTO products(productName, categoryId)
VALUES
('iPhone', 1),
('Galaxy Note',1),
('Apple Watch',2),
('Samsung Galary Watch',2);
SELECT * FROM products;
UPDATE categories
SET categoryId = 100
WHERE categoryId = 1;                                             -- ON UPDATE CASCADE
SELECT * FROM categories;
SELECT * FROM products;                                           -- Expected: iPhone, Galaxy Note -> 100
DELETE FROM categories
WHERE categoryId = 2;                                             -- ON DELETE CASCADE
SELECT * FROM categories;
SELECT * FROM products;                                           -- Expected: the two watches are gone

-- ---------------------------------------------------------------- 3) SET NULL
-- The manual drops categories FIRST, but products still references it, so:
-- EXPECT ERROR 3730 (Cannot drop table 'categories' referenced by a foreign key constraint)
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;                                  -- correct order: child first, then parent
CREATE TABLE categories(
  categoryId INT AUTO_INCREMENT PRIMARY KEY,
  categoryName VARCHAR(100) NOT NULL
)ENGINE=INNODB;
CREATE TABLE products(
  productId INT AUTO_INCREMENT PRIMARY KEY,
  productName varchar(100) not null,
  categoryId INT,
  CONSTRAINT fk_category
    FOREIGN KEY (categoryId)
    REFERENCES categories(categoryId)
    ON UPDATE SET NULL
    ON DELETE SET NULL
)ENGINE=INNODB;
INSERT INTO categories(categoryName)
VALUES
('Smartphone'),
('Smartwatch');
INSERT INTO products(productName, categoryId)
VALUES
('iPhone', 1),
('Galaxy Note',1),
('Apple Watch',2),
('Samsung Galary Watch',2);
UPDATE categories
SET categoryId = 100
WHERE categoryId = 1;                                             -- ON UPDATE SET NULL
SELECT * FROM categories;
SELECT * FROM products;                                           -- Expected: iPhone, Galaxy Note -> NULL
DELETE FROM categories
WHERE categoryId = 2;                                             -- ON DELETE SET NULL
SELECT * FROM products;                                           -- Expected: all four categoryId NULL

-- ---------------------------------------------------------------- Drop a foreign key
SHOW CREATE TABLE products\G                                      -- shows CONSTRAINT `fk_category`
ALTER TABLE products
DROP FOREIGN KEY fk_category;
SHOW CREATE TABLE products\G                                      -- constraint gone (its index KEY `fk_category` stays)

-- ---------------------------------------------------------------- RENAME / ADD / DROP COLUMN syntax
RENAME TABLE tasks
TO todo_tasks;
ALTER TABLE todo_tasks
ADD COLUMN priority TINYINT FIRST;                               -- FIRST | AFTER existing_column
ALTER TABLE todo_tasks
ADD COLUMN owner VARCHAR(50) AFTER title;
-- drop several columns in one statement
ALTER TABLE todo_tasks
DROP COLUMN priority,
DROP COLUMN owner;
DESCRIBE todo_tasks;                                              -- Expected: id, title, start_date, due_date
