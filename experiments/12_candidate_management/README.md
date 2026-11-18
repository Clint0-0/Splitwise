# Experiment 12 — Candidate Management System (SQL + front end)

**Aim:** design and develop a Candidate Management System using SQL and a front-end interface, applying ER modelling, normalization up to 3NF and SQL operations to store, retrieve, update and delete candidate information.

Checked against the lab manual (`Experiment_No_12_NEP.docx`): it lists the six sections below and a conclusion naming MySQL, PHP, HTML and CSS with register / view / update / delete — all covered. The manual gives no attribute list, so the data model and its assumptions are in `docs/REQUIREMENTS.md`.

An **independent** PHP/MySQL mini-application. It has its own database `splitwise_candidate_lab`, its own least-privilege login `candidate_app`, and shares no code, tables or navigation with SplitWise+.

| Manual section | Where |
|---|---|
| 1. Description | `docs/REQUIREMENTS.md` |
| 2. ER diagram | `docs/ER_DIAGRAM.md`, `docs/er_diagram.png` (generated from the live schema) |
| 3. Database tables | `database/candidate_management.sql` (+ `sample_data.sql`, `app_user.sql`) |
| 4. Normalization up to 3NF | `docs/NORMALIZATION.md` |
| 5. SQL implementation | `database/crud_queries.sql` (INSERT/SELECT/UPDATE/DELETE/search) and `src/CandidateRepository.php` (same SQL with PDO prepared statements) |
| 6. Front-end implementation | `public/` — PHP + HTML + CSS |
| Tests | `tests/run_tests.php` (31 checks), results in `tests/CRUD_TESTS.md` |

## Schema (3NF)
```
positions    (position_id PK, title UK, department, is_open)
candidates   (candidate_id PK, full_name, email UK, phone UK, date_of_birth?, highest_qualification, created_at, updated_at?)
applications (application_id PK, candidate_id FK→candidates ON DELETE CASCADE,
              position_id FK→positions ON DELETE RESTRICT, applied_on, status ENUM, UK(candidate_id, position_id))
```

## Pages
| Page | Does | SQL |
|---|---|---|
| `index.php` | list, search (name/email/phone), filter by position and status, empty state | SELECT, JOIN, subquery, EXISTS |
| `create.php` | register candidate + first application (one transaction) | INSERT ×2 |
| `view.php` | details, applications, change status, add application | SELECT/JOIN, UPDATE, INSERT |
| `edit.php` | edit candidate details | UPDATE |
| `delete.php` | confirmation page → delete (applications removed by CASCADE) | DELETE |
| `application.php` | add / status / withdraw (withdraw has its own confirmation page) | INSERT, UPDATE, DELETE |

Validation runs on the server (PHP) for every field and again in MySQL (`NOT NULL`, `UNIQUE`, `CHECK`, `ENUM`, foreign keys); duplicate email/phone/application show a field message instead of an error. All POSTs carry a CSRF token; output is HTML-escaped; deletes need POST, so a link can never delete anything.

## Setup
Requires MySQL 8.0 and PHP 8.1+ with `pdo_mysql` (XAMPP is fine).
```bash
# 1. database (as root/admin) — creates ONLY splitwise_candidate_lab
mysql -u root -p < experiments/12_candidate_management/database/candidate_management.sql
mysql -u root -p < experiments/12_candidate_management/database/sample_data.sql
#    edit the password in database/app_user.sql first, then:
mysql -u root -p < experiments/12_candidate_management/database/app_user.sql

# 2. password for the app: environment variable, or copy config/local.example.php to config/local.php
export CANDIDATE_DB_PASSWORD='the password you set'

# 3. run
php -S 127.0.0.1:8090 -t experiments/12_candidate_management/public
#    open http://127.0.0.1:8090
```
XAMPP: with the repo in `htdocs`, open `http://localhost/Splitwise/experiments/12_candidate_management/public/` (the root `.htaccess` lets only that `public/` folder through) and put the password in `config/local.php`.

Other settings (env): `CANDIDATE_DB_HOST` (127.0.0.1), `CANDIDATE_DB_PORT` (3306), `CANDIDATE_DB_NAME` (splitwise_candidate_lab), `CANDIDATE_DB_USER` (candidate_app).

## Tests
```bash
bash experiments/12_candidate_management/tests/run.sh     # 31 checks in a throw-away DB splitwise_candidate_lab_test + user candidate_test (both dropped after); your lab DB is not touched
```

## Evidence checklist
Real screenshots of this app running locally are in `docs/screenshots/` (taken from the test data, see `tests/CRUD_TESTS.md`):
- [x] overview / candidate list — `01_list.png`
- [x] registration form with validation errors — `02_create_errors.png`
- [x] candidate created (detail page with success message) — `03_created.png`
- [x] candidate update — `04_edit.png`, `05_updated.png`
- [x] delete confirmation and result — `06_delete_confirm.png`, `07_deleted.png`
- [x] phone layout — `08_list_mobile.png`
- [x] ER diagram — `docs/er_diagram.png`; tables — `09_tables.txt` (`SHOW CREATE TABLE` output); normalization — `docs/NORMALIZATION.md`
