# Experiment 12 — CRUD test results

Run: `bash experiments/12_candidate_management/tests/run.sh` · 2026-11-18 · MySQL 8.0.46, PHP 8.3.6 built-in server · app connected as least-privilege `candidate_app`.
The script recreates `splitwise_candidate_lab` with sample data, runs the checks below, then restores the sample data. Output copied verbatim:

```
PASS  D1  App user candidate_app connects to splitwise_candidate_lab
PASS  D2  App user has no access to the SplitWise+ database
PASS  D3  App user cannot run DDL (DROP TABLE refused)
PASS  D4  3 tables; FKs: applications→candidates CASCADE, applications→positions RESTRICT
PASS  D5  Duplicate email rejected by UNIQUE (1062)
PASS  D6  Duplicate phone rejected by UNIQUE (1062)
PASS  D7  Invalid phone rejected by CHECK (3819)
PASS  D8  Invalid status rejected by ENUM in strict mode (1265)
PASS  D9  Same candidate + position twice rejected (1062)
PASS  D10  Position with applications cannot be deleted (RESTRICT, 1451)
PASS  D11  Missing required column rejected (NOT NULL, 1364)
PASS  H1  Listing: 200, 4 sample candidates, links to view/edit/delete
PASS  H2  Register form: labels, required fields, CSRF token, only open positions
PASS  H3  Missing required fields: 200 with 6 field errors, nothing inserted
PASS  H4  Invalid input: bad name, email, phone, age, closed position, future date, bad status all reported
PASS  H5  Create: 303 to view.php, row stored normalised (name spaces, lower-case email, phone without +91), application stored
PASS  H6  Detail page: success message, details, application, HTML in input shown escaped
PASS  H7  Duplicate email via form: friendly field error, no row, no half-created application
PASS  H8  Search by name, filter by position+status, injection text treated as data, empty state
PASS  H9  Update: 303, new values saved, optional DOB cleared to NULL, updated_at set
PASS  H10  Update with another candidate's phone: field error, value unchanged
PASS  H11  Add second application; change status; status change via another candidate's URL ignored
PASS  H12  Withdraw: GET shows confirmation only; POST deletes that one application
PASS  H13  Delete: GET = confirmation page (nothing deleted); POST with a wrong CSRF token refused (400)
PASS  H14  Delete: POST removes candidate and (CASCADE) their applications; listing shows message and 4 candidates
PASS  H15  Unknown or malformed id: 404 page, no SQL error text
PASS  H16  Security headers: CSP, nosniff, frame DENY; session cookie HttpOnly
PASS  H17  Wrong DB password: friendly 500 page, no credentials or SQLSTATE shown

28 / 28 passed
```

| Area required by the brief | Checks |
|---|---|
| Database connection | D1, H17 (wrong password → friendly page) |
| Database constraints | D4–D11 (UNIQUE, CHECK, ENUM, NOT NULL, FK RESTRICT/CASCADE), D2–D3 (privileges) |
| Candidate creation | H2, H5, H6 |
| Candidate listing / search | H1, H8 |
| Candidate detail | H6, H15 |
| Candidate modification | H9, H10, H11 |
| Candidate deletion | H12, H13, H14 |
| Duplicate unique values | D5, D6, D9, H7, H10 |
| Missing required fields | D11, H3 |
| Invalid input | D7, D8, H4, H8 (injection text), H6 (HTML escaped) |
| Security | H13 (CSRF), H16 (headers), H11 (cross-candidate change ignored) |

Also checked: axe-core accessibility scan of list, register (normal + errors), detail, edit, delete-confirm and empty-search pages at 1280 px and 390 px → **0 violations**; browser console → 0 errors.
