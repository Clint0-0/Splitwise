-- =====================================================================
-- SplitWise+ | reference_data.sql — data every installation needs (also the
-- hosted production one built with --no-sample): the category hierarchy.
-- =====================================================================
SET NAMES utf8mb4;

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
