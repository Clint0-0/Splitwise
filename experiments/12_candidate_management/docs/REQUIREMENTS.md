# Experiment 12 — Requirements: Candidate Management System

## 1. Description
A small recruitment office keeps track of **candidates** who apply for **open positions**. Staff register a candidate with their contact details and qualification, record which position(s) they applied for, move each application through the hiring stages, correct details, and remove candidates who withdraw. The system replaces a spreadsheet where the same person appeared on several rows (one per job) and job details were retyped each time.

## 2. Users and functions
One role (recruitment staff); the lab app has no login, as the experiment covers modelling + CRUD, not authentication.

| # | Function | SQL |
|---|---|---|
| F1 | Register a candidate with a first application | INSERT ×2 in one transaction |
| F2 | List all candidates, search by name/email/phone, filter by position and status | SELECT + JOIN + subquery |
| F3 | View one candidate with all their applications | SELECT + JOIN |
| F4 | Update candidate details | UPDATE |
| F5 | Add another application / change an application's status / withdraw it | INSERT / UPDATE / DELETE |
| F6 | Delete a candidate (with confirmation); their applications go too | DELETE + ON DELETE CASCADE |

## 3. Data to store (and why)
| Data | Why needed | Assumption |
|---|---|---|
| Candidate ID | identify a person even if the name repeats | surrogate key, AUTO_INCREMENT |
| Full name | display, search | 2–100 letters, spaces, `.'-` |
| Email | contact; must identify one person | unique |
| Mobile number | contact; must identify one person | Indian mobile, 10 digits starting 6–9, unique, stored without +91 |
| Date of birth | eligibility checks | optional; age 16–80 |
| Highest qualification | screening | one free-text value (e.g. "B.E. Computer Engineering"); a full education history is out of scope |
| Position title, department, open/closed | what can be applied for | title unique; positions are reference data (seeded) |
| Application date, status | progress of each application | status ∈ Applied, Shortlisted, Interviewed, Selected, Rejected; date not in the future |

Deliberately **not** stored: address, resume file, salary expectations, interviewer notes — not required by the manual; each would add tables without demonstrating anything new.

## 4. Entities, relationships, keys
- **Candidate** (candidate_id PK; email UK; phone UK)
- **Position** (position_id PK; title UK)
- **Application** (application_id PK; candidate_id FK; position_id FK; UK(candidate_id, position_id)) — resolves the **M:N** relationship *Candidate applies for Position* and carries the relationship's own attributes (applied_on, status).

Cardinality: one candidate has 0..N applications (a candidate may exist briefly without one after withdrawing); one position has 0..N applications; each application belongs to exactly one candidate and one position. A candidate can apply to the same position only once.

## 5. Constraints
| Rule | Enforced by |
|---|---|
| unique email, unique phone | `UNIQUE` + friendly form error (MySQL 1062) |
| phone format, email shape, name length | `CHECK` in MySQL **and** server-side PHP validation |
| status in fixed list | `ENUM` + PHP whitelist |
| no duplicate application | `UNIQUE (candidate_id, position_id)` |
| deleting a candidate removes their applications | `ON DELETE CASCADE` |
| a position with applications cannot be deleted | `ON DELETE RESTRICT` (close it with `is_open = 0`) |
| only open positions can receive new applications | PHP (form lists open positions only, POST re-checked) |
