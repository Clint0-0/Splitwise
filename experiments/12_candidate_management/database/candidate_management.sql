-- =====================================================================
-- Experiment 12 — Candidate Management System: schema (3NF)
-- !! Drops and recreates the LAB database splitwise_candidate_lab only.
--    Never touches splitwise_plus. Run as root (or another admin).
-- =====================================================================
DROP DATABASE IF EXISTS splitwise_candidate_lab;
CREATE DATABASE splitwise_candidate_lab CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE splitwise_candidate_lab;

-- Job openings a candidate can apply for. department depends only on the position.
CREATE TABLE positions (
  position_id  SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title        VARCHAR(80)  NOT NULL,
  department   VARCHAR(60)  NOT NULL,
  is_open      TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT pk_positions PRIMARY KEY (position_id),
  CONSTRAINT uq_positions_title UNIQUE (title)
) ENGINE=InnoDB;

-- One row per person. Facts about the person only.
CREATE TABLE candidates (
  candidate_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name              VARCHAR(100) NOT NULL,
  email                  VARCHAR(150) NOT NULL,
  phone                  CHAR(10)     NOT NULL,          -- Indian mobile, 10 digits, stored without +91
  date_of_birth          DATE         NULL,
  highest_qualification  VARCHAR(100) NOT NULL,
  created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_candidates PRIMARY KEY (candidate_id),
  CONSTRAINT uq_candidates_email UNIQUE (email),
  CONSTRAINT uq_candidates_phone UNIQUE (phone),
  CONSTRAINT chk_candidates_phone CHECK (REGEXP_LIKE(phone, '^[6-9][0-9]{9}$')),
  CONSTRAINT chk_candidates_email CHECK (email LIKE '%_@_%._%'),
  CONSTRAINT chk_candidates_name  CHECK (CHAR_LENGTH(TRIM(full_name)) >= 2),
  INDEX idx_candidates_name (full_name)
) ENGINE=InnoDB;

-- A candidate applies to a position (M:N between candidates and positions).
-- applied_on and status describe the application, not the person or the job.
CREATE TABLE applications (
  application_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  candidate_id    INT UNSIGNED NOT NULL,
  position_id     SMALLINT UNSIGNED NOT NULL,
  applied_on      DATE NOT NULL,
  status          ENUM('Applied','Shortlisted','Interviewed','Selected','Rejected') NOT NULL DEFAULT 'Applied',
  CONSTRAINT pk_applications PRIMARY KEY (application_id),
  CONSTRAINT uq_applications_candidate_position UNIQUE (candidate_id, position_id),
  -- deleting a candidate removes their applications (they have no meaning alone)
  CONSTRAINT fk_applications_candidate FOREIGN KEY (candidate_id)
    REFERENCES candidates (candidate_id) ON DELETE CASCADE,
  -- a position with applications cannot be deleted (history kept); close it instead
  CONSTRAINT fk_applications_position FOREIGN KEY (position_id)
    REFERENCES positions (position_id) ON DELETE RESTRICT,
  INDEX idx_applications_status (status)
) ENGINE=InnoDB;
