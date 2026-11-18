-- =====================================================================
-- Experiment 10 · 08  PROGRAM (from the lab manual): warehouse / inventory
-- Statements follow the manual exactly. The manual's `products` table
-- (productCode, productLine) comes from the classicmodels sample database and
-- does NOT exist in SplitWise+, so step [0] creates an independent one here.
-- Contains statements that are EXPECTED to fail. Run with:  mysql --force
-- =====================================================================
USE splitwise_lab_exp10;

DROP TABLE IF EXISTS employeeWarehouse, inventoryDetails, warehouseProducts, products, warehouse;

-- [0] Prerequisite: classicmodels-style products table (fictional rows)
CREATE TABLE products (
  productCode      VARCHAR(15)   NOT NULL,
  productName      VARCHAR(70)   NOT NULL,
  productLine      VARCHAR(50)   NOT NULL,
  productVendor    VARCHAR(50)   NOT NULL,
  quantityInStock  SMALLINT      NOT NULL DEFAULT 0,
  buyPrice         DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (productCode)
) ENGINE=InnoDB;
INSERT INTO products VALUES
  ('S10_1678', '1969 Harley Davidson Ultimate Chopper', 'Motorcycles',      'Min Lin Diecast',         7933, 48.81),
  ('S10_1949', '1952 Alpine Renault 1300',              'Classic Cars',     'Classic Metal Creations', 7305, 98.58),
  ('S12_1666', '1958 Setra Bus',                        'Trucks and Buses', 'Welly Diecast',           1579, 77.90),
  ('S18_1097', '1940 Ford Pickup Truck',                'Trucks and Buses', 'Studio M Art Models',     2613, 58.33);

-- [1] Manual: "Create a table named warehouse ... warehouse ID is the primary key and the
--     warehouse name cannot be NULL."
CREATE TABLE warehouse (
  warehouseID INT PRIMARY KEY,
  warehouseName VARCHAR(100) NOT NULL,
  city VARCHAR(50),
  capacity INT
);
DESCRIBE warehouse;

-- [2] Manual: "Add a column named warehouseID of type INT to the existing products table
--     after the productLine column."  (column only, no constraint - see [7])
ALTER TABLE products
ADD COLUMN warehouseID INT AFTER productLine;
DESCRIBE products;

-- [3] Manual: warehouseProducts, composite PK, both FKs ON DELETE CASCADE
CREATE TABLE warehouseProducts (
  warehouseID INT,
  productCode VARCHAR(15),
  quantity INT,
  PRIMARY KEY (warehouseID, productCode),
  FOREIGN KEY (warehouseID)
    REFERENCES warehouse(warehouseID)
    ON DELETE CASCADE,
  FOREIGN KEY (productCode)
    REFERENCES products(productCode)
    ON DELETE CASCADE
) ENGINE = InnoDB;
SHOW CREATE TABLE warehouseProducts;      -- constraint names generated: warehouseproducts_ibfk_1 / _2

-- [4] Manual: rename warehouseProducts -> inventoryDetails; drop capacity
RENAME TABLE warehouseProducts
TO inventoryDetails;
ALTER TABLE warehouse
DROP COLUMN capacity;
DESCRIBE warehouse;                        -- Expected: warehouseID, warehouseName, city

-- [5] Manual: employeeWarehouse with FK to warehouse ON DELETE SET NULL
CREATE TABLE employeeWarehouse (
  allocationID INT PRIMARY KEY,
  employeeNumber INT,
  warehouseID INT,
  FOREIGN KEY (warehouseID)
    REFERENCES warehouse(warehouseID)
    ON DELETE SET NULL
) ENGINE = InnoDB;
SHOW CREATE TABLE employeeWarehouse;

-- [6] Manual's INSERT, as printed. It still lists 4 values (… 500), but capacity was
--     dropped in step [4], so the table now has 3 columns.
-- EXPECT ERROR 1136 (Column count doesn't match value count at row 1)
INSERT INTO warehouse
VALUES
(1, 'Central Warehouse', 'Margao', 500),
(2, 'North Warehouse', 'Mapusa', 300);
-- Corrected for the current structure (capacity no longer exists):
INSERT INTO warehouse
VALUES
(1, 'Central Warehouse', 'Margao'),
(2, 'North Warehouse', 'Mapusa');

INSERT INTO employeeWarehouse
VALUES
(101, 1002, 1),
(102, 1056, 2);

-- Extra rows (not in the manual) so the CASCADE on inventoryDetails is visible too
UPDATE products SET warehouseID = 1 WHERE productCode IN ('S10_1678', 'S10_1949');
UPDATE products SET warehouseID = 2 WHERE productCode IN ('S12_1666', 'S18_1097');
INSERT INTO inventoryDetails (warehouseID, productCode, quantity) VALUES
  (1, 'S10_1678', 120), (1, 'S10_1949', 60), (2, 'S12_1666', 45), (2, 'S18_1097', 80);

-- Before the delete
SELECT * FROM employeeWarehouse ORDER BY allocationID;           -- 101 -> 1, 102 -> 2
SELECT * FROM inventoryDetails ORDER BY warehouseID, productCode; -- 4 rows, 2 for warehouse 1

-- [7] Manual: delete warehouse 1 and verify
DELETE FROM warehouse
WHERE warehouseID = 1;

-- ON DELETE SET NULL: allocation 101 now has warehouseID = NULL; 102 unchanged
SELECT * FROM employeeWarehouse ORDER BY allocationID;
SELECT COUNT(*) AS allocations_without_warehouse FROM employeeWarehouse WHERE warehouseID IS NULL;  -- Expected 1
-- ON DELETE CASCADE: warehouse 1's inventory rows are gone
SELECT * FROM inventoryDetails ORDER BY warehouseID, productCode;  -- Expected 2 rows, both warehouse 2
-- products.warehouseID has NO foreign key (manual step [2]), so it still says 1:
-- a dangling reference - exactly what a foreign key would have prevented.
SELECT productCode, warehouseID FROM products WHERE warehouseID = 1;  -- Expected 2 rows
