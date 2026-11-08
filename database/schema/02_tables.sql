-- =====================================================================
-- SplitWise+ | 02_tables.sql — 14 tables, keys and constraints
-- Order respects FK dependencies. See docs/DATABASE_SCHEMA.md.
-- Note: MySQL 8 forbids CASCADE/SET NULL FK actions on columns that are
-- used in CHECK constraints, so those FKs use RESTRICT.
-- =====================================================================
SET NAMES utf8mb4;
USE splitwise_plus;

-- 1. users ------------------------------------------------------------
CREATE TABLE users (
  user_id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  username       VARCHAR(30)   NOT NULL,
  email          VARCHAR(100)  NOT NULL,
  password_hash  VARCHAR(255)  NOT NULL,
  full_name      VARCHAR(100)  NOT NULL,
  phone          VARCHAR(15)   NULL,
  avatar_path    VARCHAR(255)  NULL,
  role           ENUM('user','admin') NOT NULL DEFAULT 'user',
  is_active      TINYINT(1)    NOT NULL DEFAULT 1,
  notify_email   TINYINT(1)    NOT NULL DEFAULT 1,
  last_login_at  DATETIME      NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_users PRIMARY KEY (user_id),
  CONSTRAINT uq_users_username UNIQUE (username),
  CONSTRAINT uq_users_email UNIQUE (email),
  CONSTRAINT chk_users_username_len CHECK (CHAR_LENGTH(username) >= 3),
  CONSTRAINT chk_users_email_format CHECK (email LIKE '%_@_%._%'),
  CONSTRAINT chk_users_is_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB;

-- 2. expense_groups ("groups" is reserved in MySQL 8) -----------------
CREATE TABLE expense_groups (
  group_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)  NOT NULL,
  description  VARCHAR(255) NULL,
  group_type   ENUM('trip','home','family','friends','work','other') NOT NULL DEFAULT 'other',
  created_by   INT UNSIGNED NOT NULL,
  is_archived  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_expense_groups PRIMARY KEY (group_id),
  CONSTRAINT fk_expense_groups_created_by FOREIGN KEY (created_by)
    REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_expense_groups_name CHECK (CHAR_LENGTH(TRIM(name)) > 0)
) ENGINE=InnoDB;

-- 3. group_members (users M:N groups) ---------------------------------
CREATE TABLE group_members (
  membership_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id      INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  role          ENUM('admin','member') NOT NULL DEFAULT 'member',
  status        ENUM('active','left','removed') NOT NULL DEFAULT 'active',
  joined_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  left_at       DATETIME     NULL,
  added_by      INT UNSIGNED NULL,
  CONSTRAINT pk_group_members PRIMARY KEY (membership_id),
  CONSTRAINT uq_group_members_group_user UNIQUE (group_id, user_id),
  CONSTRAINT fk_group_members_group FOREIGN KEY (group_id)
    REFERENCES expense_groups (group_id) ON DELETE CASCADE,
  CONSTRAINT fk_group_members_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_group_members_added_by FOREIGN KEY (added_by)
    REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT chk_group_members_left CHECK (
    (status = 'active' AND left_at IS NULL) OR (status <> 'active' AND left_at IS NOT NULL))
) ENGINE=InnoDB;

-- 4. group_invitations ------------------------------------------------
CREATE TABLE group_invitations (
  invitation_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id        INT UNSIGNED NOT NULL,
  invited_user_id INT UNSIGNED NOT NULL,
  invited_by      INT UNSIGNED NOT NULL,
  status          ENUM('pending','accepted','declined','cancelled') NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at    DATETIME NULL,
  -- 1 while pending, NULL otherwise: lets UNIQUE allow only one pending invite
  pending_flag    TINYINT GENERATED ALWAYS AS (IF(status = 'pending', 1, NULL)) STORED,
  CONSTRAINT pk_group_invitations PRIMARY KEY (invitation_id),
  CONSTRAINT uq_group_invitations_one_pending UNIQUE (group_id, invited_user_id, pending_flag),
  CONSTRAINT fk_group_invitations_group FOREIGN KEY (group_id)
    REFERENCES expense_groups (group_id) ON DELETE CASCADE,
  CONSTRAINT fk_group_invitations_invited FOREIGN KEY (invited_user_id)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_group_invitations_by FOREIGN KEY (invited_by)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT chk_group_invitations_self CHECK (invited_user_id <> invited_by),
  CONSTRAINT chk_group_invitations_resp CHECK (
    (status = 'pending' AND responded_at IS NULL) OR (status <> 'pending' AND responded_at IS NOT NULL))
) ENGINE=InnoDB;

-- 5. categories (self-referencing hierarchy) --------------------------
CREATE TABLE categories (
  category_id        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name               VARCHAR(50) NOT NULL,
  parent_category_id SMALLINT UNSIGNED NULL,
  icon               VARCHAR(50) NOT NULL DEFAULT 'bi-tag',
  color              CHAR(7)     NOT NULL DEFAULT '#6c757d',
  is_active          TINYINT(1)  NOT NULL DEFAULT 1,
  CONSTRAINT pk_categories PRIMARY KEY (category_id),
  CONSTRAINT uq_categories_name UNIQUE (name),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_category_id)
    REFERENCES categories (category_id) ON DELETE RESTRICT,
  CONSTRAINT chk_categories_color CHECK (color REGEXP '^#[0-9A-Fa-f]{6}$')
) ENGINE=InnoDB;

-- 6. expenses ---------------------------------------------------------
CREATE TABLE expenses (
  expense_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  group_id      INT UNSIGNED  NULL,              -- NULL = personal expense
  paid_by       INT UNSIGNED  NOT NULL,
  created_by    INT UNSIGNED  NOT NULL,
  category_id   SMALLINT UNSIGNED NOT NULL,
  description   VARCHAR(120)  NOT NULL,
  total_amount  DECIMAL(12,2) NOT NULL,
  split_method  ENUM('equal','percentage','exact','shares','adjustment') NOT NULL DEFAULT 'equal',
  expense_date  DATE          NOT NULL,
  notes         VARCHAR(500)  NULL,
  is_deleted    TINYINT(1)    NOT NULL DEFAULT 0,
  deleted_at    DATETIME      NULL,
  deleted_by    INT UNSIGNED  NULL,
  version       INT UNSIGNED  NOT NULL DEFAULT 1,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_expenses PRIMARY KEY (expense_id),
  CONSTRAINT fk_expenses_group FOREIGN KEY (group_id)
    REFERENCES expense_groups (group_id) ON DELETE RESTRICT,
  CONSTRAINT fk_expenses_paid_by FOREIGN KEY (paid_by)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_expenses_created_by FOREIGN KEY (created_by)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_expenses_category FOREIGN KEY (category_id)
    REFERENCES categories (category_id) ON DELETE RESTRICT,
  CONSTRAINT fk_expenses_deleted_by FOREIGN KEY (deleted_by)
    REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT chk_expenses_amount CHECK (total_amount > 0),
  CONSTRAINT chk_expenses_description CHECK (CHAR_LENGTH(TRIM(description)) > 0),
  CONSTRAINT chk_expenses_soft_delete CHECK (
    (is_deleted = 0 AND deleted_at IS NULL) OR (is_deleted = 1 AND deleted_at IS NOT NULL))
) ENGINE=InnoDB;

-- 7. expense_splits (expenses M:N users) ------------------------------
CREATE TABLE expense_splits (
  split_id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  expense_id        INT UNSIGNED  NOT NULL,
  user_id           INT UNSIGNED  NOT NULL,
  share_amount      DECIMAL(12,2) NOT NULL,     -- binding obligation
  share_percentage  DECIMAL(5,2)  NULL,         -- input for 'percentage'
  share_units       DECIMAL(6,2)  NULL,         -- input for 'shares'
  share_adjustment  DECIMAL(12,2) NULL,         -- input for 'adjustment'
  CONSTRAINT pk_expense_splits PRIMARY KEY (split_id),
  CONSTRAINT uq_expense_splits_expense_user UNIQUE (expense_id, user_id),
  CONSTRAINT fk_expense_splits_expense FOREIGN KEY (expense_id)
    REFERENCES expenses (expense_id) ON DELETE CASCADE,
  CONSTRAINT fk_expense_splits_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT chk_expense_splits_amount CHECK (share_amount >= 0),
  CONSTRAINT chk_expense_splits_pct CHECK (share_percentage IS NULL OR share_percentage BETWEEN 0 AND 100),
  CONSTRAINT chk_expense_splits_units CHECK (share_units IS NULL OR share_units > 0)
) ENGINE=InnoDB;

-- 8. settlements ------------------------------------------------------
CREATE TABLE settlements (
  settlement_id   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  group_id        INT UNSIGNED  NOT NULL,
  payer_id        INT UNSIGNED  NOT NULL,
  payee_id        INT UNSIGNED  NOT NULL,
  amount          DECIMAL(12,2) NOT NULL,
  settlement_date DATE          NOT NULL,
  method          ENUM('cash','upi','bank_transfer','other') NOT NULL DEFAULT 'cash',
  note            VARCHAR(255)  NULL,
  status          ENUM('pending','confirmed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  created_by      INT UNSIGNED  NOT NULL,
  responded_at    DATETIME      NULL,
  request_token   CHAR(36)      NOT NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_settlements PRIMARY KEY (settlement_id),
  CONSTRAINT uq_settlements_token UNIQUE (request_token),
  CONSTRAINT fk_settlements_group FOREIGN KEY (group_id)
    REFERENCES expense_groups (group_id) ON DELETE RESTRICT,
  CONSTRAINT fk_settlements_payer FOREIGN KEY (payer_id)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_settlements_payee FOREIGN KEY (payee_id)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_settlements_created_by FOREIGN KEY (created_by)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT chk_settlements_amount CHECK (amount > 0),
  CONSTRAINT chk_settlements_parties CHECK (payer_id <> payee_id)
) ENGINE=InnoDB;

-- 9. budgets ----------------------------------------------------------
CREATE TABLE budgets (
  budget_id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED  NULL,
  group_id        INT UNSIGNED  NULL,
  category_id     SMALLINT UNSIGNED NULL,       -- NULL = all categories
  name            VARCHAR(80)   NOT NULL,
  limit_amount    DECIMAL(12,2) NOT NULL,
  period_type     ENUM('weekly','monthly','yearly','custom') NOT NULL,
  start_date      DATE          NOT NULL,
  end_date        DATE          NOT NULL,
  alert_threshold TINYINT UNSIGNED NOT NULL DEFAULT 80,
  is_active       TINYINT(1)    NOT NULL DEFAULT 1,
  created_by      INT UNSIGNED  NOT NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_budgets PRIMARY KEY (budget_id),
  CONSTRAINT fk_budgets_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT fk_budgets_group FOREIGN KEY (group_id)
    REFERENCES expense_groups (group_id) ON DELETE RESTRICT,
  CONSTRAINT fk_budgets_category FOREIGN KEY (category_id)
    REFERENCES categories (category_id) ON DELETE RESTRICT,
  CONSTRAINT fk_budgets_created_by FOREIGN KEY (created_by)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT chk_budgets_limit CHECK (limit_amount > 0),
  CONSTRAINT chk_budgets_period CHECK (end_date >= start_date),
  CONSTRAINT chk_budgets_threshold CHECK (alert_threshold BETWEEN 1 AND 100),
  CONSTRAINT chk_budgets_owner CHECK ((user_id IS NULL) <> (group_id IS NULL))
) ENGINE=InnoDB;

-- 10. notifications ---------------------------------------------------
CREATE TABLE notifications (
  notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  type            ENUM('group_invite','expense_added','expense_updated','expense_deleted',
                       'settlement_request','settlement_confirmed','settlement_rejected',
                       'budget_warning','member_removed') NOT NULL,
  title           VARCHAR(120) NOT NULL,
  message         VARCHAR(255) NOT NULL,
  entity_type     ENUM('group','expense','settlement','budget','invitation') NULL,
  entity_id       INT UNSIGNED NULL,     -- polymorphic pointer, no FK (documented)
  is_read         TINYINT(1)   NOT NULL DEFAULT 0,
  read_at         DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_notifications PRIMARY KEY (notification_id),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT chk_notifications_entity CHECK ((entity_type IS NULL) = (entity_id IS NULL))
) ENGINE=InnoDB;

-- 11. receipts --------------------------------------------------------
CREATE TABLE receipts (
  receipt_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  expense_id    INT UNSIGNED NOT NULL,
  file_path     VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type     VARCHAR(50)  NOT NULL,
  file_size     INT UNSIGNED NOT NULL,
  uploaded_by   INT UNSIGNED NOT NULL,
  uploaded_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_receipts PRIMARY KEY (receipt_id),
  CONSTRAINT uq_receipts_path UNIQUE (file_path),
  CONSTRAINT fk_receipts_expense FOREIGN KEY (expense_id)
    REFERENCES expenses (expense_id) ON DELETE CASCADE,
  CONSTRAINT fk_receipts_uploaded_by FOREIGN KEY (uploaded_by)
    REFERENCES users (user_id) ON DELETE RESTRICT,
  CONSTRAINT chk_receipts_mime CHECK (mime_type IN ('image/jpeg','image/png','application/pdf')),
  CONSTRAINT chk_receipts_size CHECK (file_size BETWEEN 1 AND 5242880)
) ENGINE=InnoDB;

-- 12. tags ------------------------------------------------------------
CREATE TABLE tags (
  tag_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name   VARCHAR(30)  NOT NULL,
  CONSTRAINT pk_tags PRIMARY KEY (tag_id),
  CONSTRAINT uq_tags_name UNIQUE (name),
  CONSTRAINT chk_tags_lower CHECK (BINARY name = BINARY LOWER(name))
) ENGINE=InnoDB;

-- 13. expense_tags (expenses M:N tags) --------------------------------
CREATE TABLE expense_tags (
  expense_id INT UNSIGNED NOT NULL,
  tag_id     INT UNSIGNED NOT NULL,
  CONSTRAINT pk_expense_tags PRIMARY KEY (expense_id, tag_id),
  CONSTRAINT fk_expense_tags_expense FOREIGN KEY (expense_id)
    REFERENCES expenses (expense_id) ON DELETE CASCADE,
  CONSTRAINT fk_expense_tags_tag FOREIGN KEY (tag_id)
    REFERENCES tags (tag_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 14. audit_logs (written by triggers only; no FK by design) ----------
CREATE TABLE audit_logs (
  log_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  table_name  VARCHAR(64)  NOT NULL,
  record_id   INT UNSIGNED NOT NULL,
  action      ENUM('INSERT','UPDATE','DELETE','SOFT_DELETE','RESTORE') NOT NULL,
  old_data    JSON         NULL,
  new_data    JSON         NULL,
  changed_by  INT UNSIGNED NULL,
  changed_at  DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  CONSTRAINT pk_audit_logs PRIMARY KEY (log_id)
) ENGINE=InnoDB;
