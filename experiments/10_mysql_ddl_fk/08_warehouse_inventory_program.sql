-- =====================================================================
-- Experiment 10 · 08  Practical program: warehouse / inventory
-- Runs inside the sandbox. The manual's products table (productCode, productLine)
-- comes from the classicmodels sample database and does NOT exist in SplitWise+,
-- so a small independent `products` table is created here first.
-- =====================================================================
USE splitwise_lab_exp10;

DROP TABLE IF EXISTS employeeWarehouse, inventoryDetails, warehouseProducts, products, warehouse;

-- [0] Prerequisite products table (classicmodels-style columns, fictional rows)
CREATE TABLE products (
  productCode      VARCHAR(15)  NOT NULL,
  productName      VARCHAR(70)  NOT NULL,
  productLine      VARCHAR(50)  NOT NULL,
  productVendor    VARCHAR(50)  NOT NULL,
  quantityInStock  SMALLINT     NOT NULL DEFAULT 0,
  buyPrice         DECIMAL(10,2) NOT NULL,
  CONSTRAINT pk_products PRIMARY KEY (productCode)
) ENGINE=InnoDB;
INSERT INTO products VALUES
  ('S10_1678', '1969 Harley Davidson Ultimate Chopper', 'Motorcycles',  'Min Lin Diecast',       7933, 48.81),
  ('S10_1949', '1952 Alpine Renault 1300',              'Classic Cars', 'Classic Metal Creations', 7305, 98.58),
  ('S12_1666', '1958 Setra Bus',                        'Trucks and Buses', 'Welly Diecast',     1579, 77.90),
  ('S18_1097', '1940 Ford Pickup Truck',                'Trucks and Buses', 'Studio M Art Models', 2613, 58.33);

-- [1] Create warehouse (warehouseID, warehouseName, city, capacity)
CREATE TABLE warehouse (
  warehouseID    INT          NOT NULL AUTO_INCREMENT,
  warehouseName  VARCHAR(50)  NOT NULL,
  city           VARCHAR(50)  NOT NULL,
  capacity       INT          NOT NULL,
  CONSTRAINT pk_warehouse PRIMARY KEY (warehouseID),
  CONSTRAINT chk_warehouse_capacity CHECK (capacity > 0)
) ENGINE=InnoDB;
DESCRIBE warehouse;

-- [2] Add warehouseID to products, positioned AFTER productLine (as the manual asks;
-- the manual adds only the column, no constraint - see step [12] for the consequence)
ALTER TABLE products ADD COLUMN warehouseID INT NULL AFTER productLine;
DESCRIBE products;

-- [3]-[6] warehouseProducts with composite primary key (warehouseID, productCode)
-- and foreign keys to warehouse and products, both ON DELETE CASCADE.
CREATE TABLE warehouseProducts (
  warehouseID  INT          NOT NULL,
  productCode  VARCHAR(15)  NOT NULL,
  quantity     INT          NOT NULL DEFAULT 0,
  CONSTRAINT pk_warehouseProducts PRIMARY KEY (warehouseID, productCode),
  CONSTRAINT chk_warehouseProducts_qty CHECK (quantity >= 0),
  CONSTRAINT fk_wp_warehouse FOREIGN KEY (warehouseID) REFERENCES warehouse (warehouseID) ON DELETE CASCADE,
  CONSTRAINT fk_wp_product   FOREIGN KEY (productCode) REFERENCES products (productCode)  ON DELETE CASCADE
) ENGINE=InnoDB;
SHOW CREATE TABLE warehouseProducts\G

-- [7] Rename warehouseProducts -> inventoryDetails (constraints move with the table)
RENAME TABLE warehouseProducts TO inventoryDetails;
SHOW TABLES LIKE 'inventoryDetails';

-- [8] Drop capacity from warehouse (its CHECK constraint is removed first)
ALTER TABLE warehouse DROP CHECK chk_warehouse_capacity;
ALTER TABLE warehouse DROP COLUMN capacity;
DESCRIBE warehouse;                                    -- Expected: warehouseID, warehouseName, city

-- [9]-[10] employeeWarehouse with FK to warehouse ON DELETE SET NULL (column NULLable)
CREATE TABLE employeeWarehouse (
  employeeID    INT          NOT NULL AUTO_INCREMENT,
  employeeName  VARCHAR(60)  NOT NULL,
  role          VARCHAR(30)  NOT NULL,
  warehouseID   INT          NULL,
  CONSTRAINT pk_employeeWarehouse PRIMARY KEY (employeeID),
  CONSTRAINT fk_ew_warehouse FOREIGN KEY (warehouseID) REFERENCES warehouse (warehouseID) ON DELETE SET NULL
) ENGINE=InnoDB;
SHOW CREATE TABLE employeeWarehouse\G

-- [11] Sample records
INSERT INTO warehouse (warehouseID, warehouseName, city) VALUES
  (1, 'North Hub', 'Delhi'), (2, 'West Hub', 'Mumbai'), (3, 'South Hub', 'Chennai');
UPDATE products SET warehouseID = 1 WHERE productCode IN ('S10_1678', 'S10_1949');
UPDATE products SET warehouseID = 2 WHERE productCode IN ('S12_1666', 'S18_1097');
INSERT INTO inventoryDetails (warehouseID, productCode, quantity) VALUES
  (1, 'S10_1678', 120), (1, 'S10_1949', 60), (2, 'S12_1666', 45), (2, 'S18_1097', 80), (3, 'S10_1678', 30);
INSERT INTO employeeWarehouse (employeeName, role, warehouseID) VALUES
  ('Ravi Kumar', 'Manager', 1), ('Anita Desai', 'Picker', 1),
  ('Suresh Patil', 'Manager', 2), ('Lakshmi Rao', 'Picker', 3);

-- Before the delete
SELECT * FROM inventoryDetails ORDER BY warehouseID, productCode;   -- 5 rows, 2 for warehouse 1
SELECT * FROM employeeWarehouse ORDER BY employeeID;                -- Ravi, Anita -> warehouse 1

-- [12] Delete warehouse 1 and verify the referential actions
DELETE FROM warehouse WHERE warehouseID = 1;
-- ON DELETE CASCADE: warehouse 1's inventory rows are gone
SELECT * FROM inventoryDetails ORDER BY warehouseID, productCode;   -- Expected 3 rows, none for warehouse 1
-- ON DELETE SET NULL: Ravi and Anita now have warehouseID = NULL
SELECT employeeID, employeeName, warehouseID FROM employeeWarehouse ORDER BY employeeID;
SELECT COUNT(*) AS employees_without_warehouse FROM employeeWarehouse WHERE warehouseID IS NULL;  -- Expected 2
-- products.warehouseID has NO foreign key (manual step 2), so it still says 1:
-- a dangling reference. This is exactly what a foreign key would have prevented.
SELECT productCode, warehouseID FROM products WHERE warehouseID = 1;  -- Expected 2 dangling rows
