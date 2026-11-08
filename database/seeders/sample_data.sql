-- =====================================================================
-- SplitWise+ | sample_data.sql — realistic INR test data
-- Load AFTER schema, functions, views, triggers, procedures.
-- Equal splits and settlements go through the stored procedures, so the
-- seed itself exercises the validation logic and triggers.
--
-- Test accounts (all passwords: Password@123 — sample data only):
--   admin, aarav, priya, rohan, sneha, kabir, ananya, vikram, meera
--
-- Expected balances after loading (verified in tests/database/test_balances.sql):
--   Goa Trip 2026 : Aarav +9991.67  Priya +3429.16  Rohan -6287.50  Sneha -7133.33
--   Flat 302      : Priya +26396.50 Kabir -10133.51 Ananya -16262.99
--   College Fr.   : Rohan +450  Aarav +100  Kabir +600  Vikram -1150
--   Office Team   : no expenses (all 0)     Weekend Trek : debt cycle, all 0
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;
SET @hash = '$2y$10$NQyfyEC17L2kCh9IiWdyeeb/l/i.RFt71dXMSSZFT9SZsQa5GKjde';

-- Users -----------------------------------------------------------------
INSERT INTO users (user_id, username, email, password_hash, full_name, phone, role, created_at) VALUES
 (1, 'admin',  'admin@splitwiseplus.test',  @hash, 'System Administrator', NULL,         'admin', '2026-08-01 10:00:00'),
 (2, 'aarav',  'aarav@splitwiseplus.test',  @hash, 'Aarav Sharma',  '9820011111', 'user', '2026-08-20 09:15:00'),
 (3, 'priya',  'priya@splitwiseplus.test',  @hash, 'Priya Nair',    '9820022222', 'user', '2026-08-20 11:40:00'),
 (4, 'rohan',  'rohan@splitwiseplus.test',  @hash, 'Rohan Mehta',   '9820033333', 'user', '2026-08-21 18:05:00'),
 (5, 'sneha',  'sneha@splitwiseplus.test',  @hash, 'Sneha Iyer',    '9820044444', 'user', '2026-08-22 08:30:00'),
 (6, 'kabir',  'kabir@splitwiseplus.test',  @hash, 'Kabir Singh',   '9820055555', 'user', '2026-08-23 20:10:00'),
 (7, 'ananya', 'ananya@splitwiseplus.test', @hash, 'Ananya Desai',  NULL,         'user', '2026-08-24 13:00:00'),
 (8, 'vikram', 'vikram@splitwiseplus.test', @hash, 'Vikram Rao',    '9820077777', 'user', '2026-08-25 16:45:00'),
 (9, 'meera',  'meera@splitwiseplus.test',  @hash, 'Meera Joshi',   NULL,         'user', '2026-08-26 10:20:00');

-- Categories (hierarchy: parent rows first) ------------------------------
INSERT INTO categories (category_id, name, parent_category_id, icon, color) VALUES
 (1,  'Food & Dining', NULL, 'bi-cup-hot',        '#E4572E'),
 (2,  'Restaurants',   1,    'bi-shop',           '#F17C67'),
 (3,  'Groceries',     1,    'bi-basket',         '#F3A712'),
 (4,  'Travel',        NULL, 'bi-airplane',       '#29335C'),
 (5,  'Flights',       4,    'bi-airplane-engines','#3D4C8A'),
 (6,  'Hotels',        4,    'bi-building',       '#5867A8'),
 (7,  'Transport',     NULL, 'bi-car-front',      '#669BBC'),
 (8,  'Rent',          NULL, 'bi-house-door',     '#2A9D8F'),
 (9,  'Utilities',     NULL, 'bi-lightning',      '#8AB17D'),
 (10, 'Entertainment', NULL, 'bi-film',           '#9B5DE5'),
 (11, 'Shopping',      NULL, 'bi-bag',            '#F15BB5'),
 (12, 'Health',        NULL, 'bi-heart-pulse',    '#D62828'),
 (13, 'Education',     NULL, 'bi-book',           '#003049'),
 (14, 'Other',         NULL, 'bi-three-dots',     '#6C757D');

-- Groups ----------------------------------------------------------------
INSERT INTO expense_groups (group_id, name, description, group_type, created_by, created_at) VALUES
 (1, 'Goa Trip 2026',      'Four friends, 4 days in North Goa',   'trip',    2, '2026-09-15 21:00:00'),
 (2, 'Flat 302 Roommates', 'Rent and bills for Andheri flat',     'home',    3, '2026-08-25 10:00:00'),
 (3, 'College Friends',    'Weekend outings',                     'friends', 4, '2026-09-01 19:30:00'),
 (4, 'Office Team',        'Team lunches (nothing recorded yet)', 'work',    5, '2026-10-20 12:00:00'),
 (5, 'Weekend Trek',       'Rajmachi trek',                       'trip',    6, '2026-10-28 08:00:00');

-- Memberships -------------------------------------------------------------
INSERT INTO group_members (group_id, user_id, role, status, joined_at, left_at, added_by) VALUES
 (1, 2, 'admin',  'active', '2026-09-15 21:00:00', NULL, NULL),
 (1, 3, 'member', 'active', '2026-09-15 21:30:00', NULL, 2),
 (1, 4, 'member', 'active', '2026-09-15 22:10:00', NULL, 2),
 (1, 5, 'member', 'active', '2026-09-16 08:00:00', NULL, 2),
 (2, 3, 'admin',  'active', '2026-08-25 10:00:00', NULL, NULL),
 (2, 6, 'member', 'active', '2026-08-25 11:00:00', NULL, 3),
 (2, 7, 'admin',  'active', '2026-08-25 12:00:00', NULL, 3),
 (2, 9, 'member', 'left',   '2026-08-25 12:30:00', '2026-08-31 18:00:00', 3),
 (3, 4, 'admin',  'active', '2026-09-01 19:30:00', NULL, NULL),
 (3, 2, 'member', 'active', '2026-09-01 20:00:00', NULL, 4),
 (3, 6, 'member', 'active', '2026-09-02 09:00:00', NULL, 4),
 (3, 8, 'member', 'active', '2026-09-02 10:00:00', NULL, 4),
 (4, 5, 'admin',  'active', '2026-10-20 12:00:00', NULL, NULL),
 (4, 7, 'member', 'active', '2026-10-20 12:30:00', NULL, 5),
 (5, 6, 'admin',  'active', '2026-10-28 08:00:00', NULL, NULL),
 (5, 5, 'member', 'active', '2026-10-28 08:10:00', NULL, 6),
 (5, 4, 'member', 'active', '2026-10-28 08:20:00', NULL, 6);

-- Invitations (history + one pending, one declined) -------------------------
INSERT INTO group_invitations (group_id, invited_user_id, invited_by, status, created_at, responded_at) VALUES
 (1, 3, 2, 'accepted', '2026-09-15 21:05:00', '2026-09-15 21:30:00'),
 (1, 4, 2, 'accepted', '2026-09-15 21:05:00', '2026-09-15 22:10:00'),
 (1, 5, 2, 'accepted', '2026-09-15 21:05:00', '2026-09-16 08:00:00'),
 (2, 6, 3, 'accepted', '2026-08-25 10:05:00', '2026-08-25 11:00:00'),
 (2, 7, 3, 'accepted', '2026-08-25 10:05:00', '2026-08-25 12:00:00'),
 (2, 9, 3, 'accepted', '2026-08-25 10:05:00', '2026-08-25 12:30:00'),
 (3, 2, 4, 'accepted', '2026-09-01 19:35:00', '2026-09-01 20:00:00'),
 (3, 6, 4, 'accepted', '2026-09-01 19:35:00', '2026-09-02 09:00:00'),
 (3, 8, 4, 'accepted', '2026-09-01 19:35:00', '2026-09-02 10:00:00'),
 (3, 9, 4, 'pending',  '2026-11-05 17:00:00', NULL),
 (4, 7, 5, 'accepted', '2026-10-20 12:05:00', '2026-10-20 12:30:00'),
 (4, 8, 5, 'declined', '2026-10-20 12:05:00', '2026-10-21 09:00:00'),
 (5, 5, 6, 'accepted', '2026-10-28 08:01:00', '2026-10-28 08:10:00'),
 (5, 4, 6, 'accepted', '2026-10-28 08:01:00', '2026-10-28 08:20:00');

-- =====================================================================
-- Expenses. Comments show each participant's resolved share.
-- =====================================================================

-- ---- Goa Trip 2026 (group 1: Aarav 2, Priya 3, Rohan 4, Sneha 5) ----
SET @app_user_id = 2;
-- E1 equal: 22000 / 4 = 5500 each
CALL sp_add_equal_expense(1, 2, 2, 5, 'Flights Mumbai–Goa (return)', 22000.00, '2026-10-02', '2,3,4,5', @e1);

SET @app_user_id = 3;
-- E2 shares 1:1:2:1 (Rohan shared room with cousin) -> 3600 per unit
INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date, notes)
VALUES (1, 3, 3, 6, 'Beach resort, 3 nights', 18000.00, 'shares', '2026-10-03', 'Rohan counted as 2 units');
SET @e2 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount, share_units) VALUES
 (@e2, 2, 3600.00, 1), (@e2, 3, 3600.00, 1), (@e2, 4, 7200.00, 2), (@e2, 5, 3600.00, 1);

SET @app_user_id = 4;
-- E3 percentage 30/25/25/20 of 4750
INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date)
VALUES (1, 4, 4, 2, 'Seafood dinner at Baga', 4750.00, 'percentage', '2026-10-03');
SET @e3 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount, share_percentage) VALUES
 (@e3, 2, 1425.00, 30.00), (@e3, 3, 1187.50, 25.00), (@e3, 4, 1187.50, 25.00), (@e3, 5, 950.00, 20.00);

SET @app_user_id = 5;
-- E4 exact: Priya did not ride
INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date)
VALUES (1, 5, 5, 7, 'Scooter rentals (3 scooters)', 2400.00, 'exact', '2026-10-04');
SET @e4 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount) VALUES
 (@e4, 2, 800.00), (@e4, 4, 800.00), (@e4, 5, 800.00);

SET @app_user_id = 2;
-- E5 adjustment: Priya +600 for parasailing; base (6000-600)/4 = 1350
INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date, notes)
VALUES (1, 2, 2, 10, 'Water sports package', 6000.00, 'adjustment', '2026-10-04', 'Priya added parasailing (+₹600)');
SET @e5 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount, share_adjustment) VALUES
 (@e5, 2, 1350.00, 0), (@e5, 3, 1950.00, 600.00), (@e5, 4, 1350.00, 0), (@e5, 5, 1350.00, 0);

SET @app_user_id = 3;
-- E6 equal with rounding: 1000/3 -> payer Priya 333.34, others 333.33
CALL sp_add_equal_expense(1, 3, 3, 2, 'Breakfast at café', 1000.00, '2026-10-05', '2,3,5', @e6);

SET @app_user_id = 4;
-- E7 duplicate entry, later soft-deleted (excluded from balances)
CALL sp_add_equal_expense(1, 4, 4, 11, 'Souvenirs (duplicate entry)', 1200.00, '2026-10-05', '2,3,4,5', @e7);
UPDATE expenses SET is_deleted = 1, deleted_at = '2026-10-06 09:00:00', deleted_by = 4 WHERE expense_id = @e7;

-- ---- Flat 302 (group 2: Priya 3, Kabir 6, Ananya 7) ----
SET @app_user_id = 3;
-- E8–E10 rent, percentage 40/30/30 (Priya has the larger room)
INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date)
VALUES (2, 3, 3, 8, 'Rent – September', 36000.00, 'percentage', '2026-09-01');
SET @e8 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount, share_percentage) VALUES
 (@e8, 3, 14400.00, 40.00), (@e8, 6, 10800.00, 30.00), (@e8, 7, 10800.00, 30.00);

INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date)
VALUES (2, 3, 3, 8, 'Rent – October', 36000.00, 'percentage', '2026-10-01');
SET @e9 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount, share_percentage) VALUES
 (@e9, 3, 14400.00, 40.00), (@e9, 6, 10800.00, 30.00), (@e9, 7, 10800.00, 30.00);

INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date)
VALUES (2, 3, 3, 8, 'Rent – November', 36000.00, 'percentage', '2026-11-01');
SET @e10 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount, share_percentage) VALUES
 (@e10, 3, 14400.00, 40.00), (@e10, 6, 10800.00, 30.00), (@e10, 7, 10800.00, 30.00);

SET @app_user_id = 6;
-- E11 equal 3250/3 -> payer Kabir 1083.34, others 1083.33
CALL sp_add_equal_expense(2, 6, 6, 9, 'Electricity bill – October', 3250.00, '2026-10-15', '3,6,7', @e11);

SET @app_user_id = 7;
-- E12 entered as 4000, then corrected to 4120.50 (edit flow: version check,
-- re-split inside one transaction; triggers archive old splits)
CALL sp_add_equal_expense(2, 7, 7, 3, 'BigBasket groceries', 4000.00, '2026-10-20', '3,6,7', @e12);
START TRANSACTION;
  UPDATE expenses SET total_amount = 4120.50, version = version + 1,
         notes = 'Corrected: delivery charge was missing'
   WHERE expense_id = @e12 AND version = 1;
  DELETE FROM expense_splits WHERE expense_id = @e12;
  INSERT INTO expense_splits (expense_id, user_id, share_amount) VALUES
   (@e12, 3, 1373.50), (@e12, 6, 1373.50), (@e12, 7, 1373.50);
COMMIT;

SET @app_user_id = 6;
-- E13 exact
INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date)
VALUES (2, 6, 6, 9, 'Wi-Fi – November', 1199.00, 'exact', '2026-11-05');
SET @e13 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount) VALUES
 (@e13, 3, 400.00), (@e13, 6, 399.00), (@e13, 7, 400.00);

SET @app_user_id = 3;
-- E14 equal 2780/3: 2 leftover paise -> Priya (payer) and Kabir (lowest id)
CALL sp_add_equal_expense(2, 3, 3, 3, 'DMart groceries', 2780.00, '2026-11-03', '3,6,7', @e14);

-- ---- College Friends (group 3: Rohan 4, Aarav 2, Kabir 6, Vikram 8) — no settlements ----
SET @app_user_id = 4;
CALL sp_add_equal_expense(3, 4, 4, 10, 'Movie night at PVR', 1600.00, '2026-09-20', '2,4,6,8', @e15);

SET @app_user_id = 2;
-- E16 shares: Kabir 2 units (two pizzas)
INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date)
VALUES (3, 2, 2, 2, 'Pizza after the movie', 1250.00, 'shares', '2026-09-20');
SET @e16 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount, share_units) VALUES
 (@e16, 2, 250.00, 1), (@e16, 4, 250.00, 1), (@e16, 6, 500.00, 2), (@e16, 8, 250.00, 1);

SET @app_user_id = 6;
CALL sp_add_equal_expense(3, 6, 6, 10, 'Bowling at Smaaash', 2000.00, '2026-10-25', '2,4,6,8', @e17);

-- ---- Weekend Trek (group 5: Kabir 6, Sneha 5, Rohan 4) — debt cycle, payer not a participant ----
SET @app_user_id = 6;
INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date)
VALUES (5, 6, 6, 4, 'Trek permit for Sneha', 500.00, 'exact', '2026-11-01');
SET @e18 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount) VALUES (@e18, 5, 500.00);

SET @app_user_id = 5;
INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date)
VALUES (5, 5, 5, 1, 'Snacks for Rohan', 500.00, 'exact', '2026-11-01');
SET @e19 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount) VALUES (@e19, 4, 500.00);

SET @app_user_id = 4;
INSERT INTO expenses (group_id, paid_by, created_by, category_id, description, total_amount, split_method, expense_date)
VALUES (5, 4, 4, 7, 'Taxi for Kabir', 500.00, 'exact', '2026-11-01');
SET @e20 = LAST_INSERT_ID();
INSERT INTO expense_splits (expense_id, user_id, share_amount) VALUES (@e20, 6, 500.00);

-- ---- Personal expenses (no group) ----
SET @app_user_id = 2;
CALL sp_add_equal_expense(NULL, 2, 2, 11, 'Running shoes', 3499.00, '2026-10-12', '2', @e21);
SET @app_user_id = 3;
CALL sp_add_equal_expense(NULL, 3, 3, 3, 'Weekly groceries', 1850.75, '2026-11-02', '3', @e22);
CALL sp_add_equal_expense(NULL, 3, 3, 10, 'Streaming subscription', 649.00, '2026-11-04', '3', @e23);
UPDATE expenses SET notes = 'Monthly plan' WHERE expense_id = @e23;

-- Tags --------------------------------------------------------------------
INSERT INTO tags (tag_id, name) VALUES (1, 'goa-2026'), (2, 'rent'), (3, 'monthly-bill'), (4, 'weekend');
INSERT INTO expense_tags (expense_id, tag_id) VALUES
 (@e1, 1), (@e2, 1), (@e3, 1), (@e4, 1), (@e5, 1), (@e6, 1),
 (@e8, 2), (@e9, 2), (@e10, 2), (@e8, 3), (@e9, 3), (@e10, 3), (@e11, 3), (@e13, 3),
 (@e15, 4), (@e16, 4), (@e17, 4), (@e18, 4), (@e19, 4), (@e20, 4);

-- =====================================================================
-- Settlements (through sp_record_settlement / sp_respond_settlement)
-- =====================================================================
-- Goa: Rohan pays Aarav 5000 (Aarav records -> confirmed)
SET @app_user_id = 2;
CALL sp_record_settlement(1, 4, 2, 5000.00, '2026-10-10', 'upi', 'Goa share part 1', 2, 'seed-settlement-0001', @s1);
-- Sneha pays Priya 3000 (Priya records -> confirmed)
SET @app_user_id = 3;
CALL sp_record_settlement(1, 5, 3, 3000.00, '2026-10-11', 'upi', NULL, 3, 'seed-settlement-0002', @s2);
-- Sneha says she paid Aarav 2000 -> pending (awaiting Aarav)
SET @app_user_id = 5;
CALL sp_record_settlement(1, 5, 2, 2000.00, '2026-11-07', 'upi', 'Sent on GPay', 5, 'seed-settlement-0003', @s3);
-- Rohan claims 1000 to Priya -> Priya rejects (not received)
SET @app_user_id = 4;
CALL sp_record_settlement(1, 4, 3, 1000.00, '2026-10-12', 'cash', NULL, 4, 'seed-settlement-0004', @s4);
SET @app_user_id = 3;
CALL sp_respond_settlement(@s4, 3, 'reject');
-- Rohan records 500 to Aarav by mistake -> cancels it himself
SET @app_user_id = 4;
CALL sp_record_settlement(1, 4, 2, 500.00, '2026-10-12', 'cash', 'wrong amount', 4, 'seed-settlement-0005', @s5);
CALL sp_respond_settlement(@s5, 4, 'cancel');

-- Flat 302: rent repayments (Priya records -> confirmed), one partial, one pending
SET @app_user_id = 3;
CALL sp_record_settlement(2, 6, 3, 10800.00, '2026-09-05', 'bank_transfer', 'Sept rent', 3, 'seed-settlement-0006', @s6);
CALL sp_record_settlement(2, 7, 3, 10800.00, '2026-09-06', 'bank_transfer', 'Sept rent', 3, 'seed-settlement-0007', @s7);
CALL sp_record_settlement(2, 6, 3, 10800.00, '2026-10-05', 'bank_transfer', 'Oct rent',  3, 'seed-settlement-0008', @s8);
CALL sp_record_settlement(2, 7, 3,  5000.00, '2026-10-07', 'cash',          'Oct rent (part)', 3, 'seed-settlement-0009', @s9);
SET @app_user_id = 7;
CALL sp_record_settlement(2, 7, 3,  4000.00, '2026-11-06', 'upi', 'Rest of Oct rent', 7, 'seed-settlement-0010', @s10);

-- =====================================================================
-- Budgets
-- =====================================================================
INSERT INTO budgets (user_id, group_id, category_id, name, limit_amount, period_type, start_date, end_date, alert_threshold, created_by) VALUES
 (3,    NULL, 1,    'Food – November',        10000.00, 'monthly', '2026-11-01', '2026-11-30', 80, 3),
 (2,    NULL, NULL, 'October overall',        15000.00, 'monthly', '2026-10-01', '2026-10-31', 80, 2),
 (NULL, 1,    NULL, 'Goa trip budget',        60000.00, 'custom',  '2026-10-01', '2026-10-10', 80, 2),
 (6,    NULL, 9,    'Utilities – November',    1000.00, 'monthly', '2026-11-01', '2026-11-30', 75, 6),
 (7,    NULL, 3,    'Groceries – October',     3000.00, 'monthly', '2026-10-01', '2026-10-31', 80, 7),
 (5,    NULL, 10,   'Entertainment – September', 2000.00, 'monthly', '2026-09-01', '2026-09-30', 80, 5);

-- App-generated notifications (invitation + budget warnings) ----------------
INSERT INTO notifications (user_id, type, title, message, entity_type, entity_id, created_at) VALUES
 (9, 'group_invite',   'Group invitation', 'Rohan Mehta invited you to "College Friends".', 'invitation', 10, '2026-11-05 17:00:00'),
 (2, 'budget_warning', 'Budget exceeded',  'October overall: ₹17,007.33 of ₹15,000.00 used (113%).', 'budget', 2, '2026-10-25 20:00:00'),
 (2, 'budget_warning', 'Group budget at 90%', 'Goa trip budget: ₹54,150.00 of ₹60,000.00 used.', 'budget', 3, '2026-10-05 22:00:00'),
 (3, 'budget_warning', 'Group budget at 90%', 'Goa trip budget: ₹54,150.00 of ₹60,000.00 used.', 'budget', 3, '2026-10-05 22:00:00'),
 (4, 'budget_warning', 'Group budget at 90%', 'Goa trip budget: ₹54,150.00 of ₹60,000.00 used.', 'budget', 3, '2026-10-05 22:00:00'),
 (5, 'budget_warning', 'Group budget at 90%', 'Goa trip budget: ₹54,150.00 of ₹60,000.00 used.', 'budget', 3, '2026-10-05 22:00:00');

-- Older notifications already read
UPDATE notifications SET is_read = 1, read_at = NOW() WHERE created_at < '2026-10-15';

SET @app_user_id = NULL;
