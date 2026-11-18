-- Experiment 12 — least-privilege login for the PHP front end (lab database only).
-- Change the password before running, then put the same value in the
-- CANDIDATE_DB_PASSWORD environment variable (or config/local.php, git-ignored).
CREATE USER IF NOT EXISTS 'candidate_app'@'localhost' IDENTIFIED BY 'Cand_Change_Me_123!';
CREATE USER IF NOT EXISTS 'candidate_app'@'127.0.0.1' IDENTIFIED BY 'Cand_Change_Me_123!';
GRANT SELECT, INSERT, UPDATE, DELETE ON splitwise_candidate_lab.* TO 'candidate_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON splitwise_candidate_lab.* TO 'candidate_app'@'127.0.0.1';
-- no DDL, no access to splitwise_plus or any other database
