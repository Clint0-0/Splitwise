-- =====================================================================
-- Experiment 12 — SQL implementation (INSERT / SELECT / UPDATE / DELETE)
-- The same statements the PHP pages run (there with ? placeholders and PDO
-- prepared statements). Run after candidate_management.sql + sample_data.sql.
-- Ends with ROLLBACK so the sample data stays unchanged.
-- =====================================================================
USE splitwise_candidate_lab;
START TRANSACTION;

-- C1 Add a candidate and their first application (create.php)
INSERT INTO candidates (full_name, email, phone, date_of_birth, highest_qualification)
VALUES ('Kavya Reddy', 'kavya.r@example.test', '9988776655', '2003-01-30', 'B.Tech. Computer Science');
SET @new_id = LAST_INSERT_ID();
INSERT INTO applications (candidate_id, position_id, applied_on, status) VALUES (@new_id, 2, CURDATE(), 'Applied');

-- R1 List candidates with their latest application (index.php)
SELECT c.candidate_id, c.full_name, c.email, c.phone,
       COUNT(a.application_id)                                   AS applications,
       MAX(a.applied_on)                                         AS last_applied,
       (SELECT p.title FROM applications a2 JOIN positions p ON p.position_id = a2.position_id
         WHERE a2.candidate_id = c.candidate_id ORDER BY a2.applied_on DESC, a2.application_id DESC LIMIT 1) AS latest_position
  FROM candidates c LEFT JOIN applications a ON a.candidate_id = c.candidate_id
 GROUP BY c.candidate_id ORDER BY c.full_name;

-- R2 Search by name/email/phone, filter by position and status (index.php)
SELECT DISTINCT c.candidate_id, c.full_name, c.email
  FROM candidates c LEFT JOIN applications a ON a.candidate_id = c.candidate_id
 WHERE (c.full_name LIKE CONCAT('%', 'neha', '%') OR c.email LIKE CONCAT('%', 'neha', '%') OR c.phone LIKE CONCAT('%', 'neha', '%'))
   AND a.position_id = 1 AND a.status = 'Interviewed';

-- R3 One candidate's details + applications (view.php)
SELECT * FROM candidates WHERE candidate_id = 1;
SELECT a.application_id, p.title, p.department, a.applied_on, a.status
  FROM applications a JOIN positions p ON p.position_id = a.position_id
 WHERE a.candidate_id = 1 ORDER BY a.applied_on DESC;

-- U1 Update candidate information (edit.php)
UPDATE candidates SET phone = '9876500000', highest_qualification = 'M.E. Computer Engineering'
 WHERE candidate_id = 1;
-- U2 Move an application to the next stage (view.php)
UPDATE applications SET status = 'Selected' WHERE application_id = 1 AND candidate_id = 1;

-- D1 Delete a candidate (delete.php) — their applications go by ON DELETE CASCADE
DELETE FROM candidates WHERE candidate_id = @new_id;
SELECT COUNT(*) AS apps_left_for_deleted FROM applications WHERE candidate_id = @new_id;   -- 0
-- D2 Withdraw one application only
DELETE FROM applications WHERE application_id = 2 AND candidate_id = 1;

-- Report: applications per position and status
SELECT p.title, a.status, COUNT(*) AS n
  FROM applications a JOIN positions p ON p.position_id = a.position_id
 GROUP BY p.title, a.status ORDER BY p.title, a.status;

ROLLBACK;   -- keep sample data as it was
