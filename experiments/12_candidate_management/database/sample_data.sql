-- Experiment 12 — fictional sample data (no real people)
USE splitwise_candidate_lab;
INSERT INTO positions (position_id, title, department, is_open) VALUES
  (1, 'Junior PHP Developer',  'Engineering', 1),
  (2, 'Database Administrator','Engineering', 1),
  (3, 'UI/UX Designer',        'Design',      1),
  (4, 'QA Engineer',           'Quality',     1),
  (5, 'HR Executive',          'People',      0);
INSERT INTO candidates (candidate_id, full_name, email, phone, date_of_birth, highest_qualification) VALUES
  (1, 'Neha Kulkarni',  'neha.k@example.test',   '9876543210', '2002-04-14', 'B.E. Computer Engineering'),
  (2, 'Rohit Verma',    'rohit.v@example.test',  '9123456780', '2001-11-02', 'B.Sc. Information Technology'),
  (3, 'Sana Shaikh',    'sana.s@example.test',   '8899001122', NULL,         'B.Des. Interaction Design'),
  (4, 'Arjun Menon',    'arjun.m@example.test',  '7012345678', '2000-07-21', 'M.C.A.');
INSERT INTO applications (candidate_id, position_id, applied_on, status) VALUES
  (1, 1, '2026-10-05', 'Interviewed'),
  (1, 2, '2026-10-06', 'Applied'),
  (2, 1, '2026-10-08', 'Shortlisted'),
  (3, 3, '2026-10-12', 'Selected'),
  (4, 4, '2026-10-15', 'Rejected');
