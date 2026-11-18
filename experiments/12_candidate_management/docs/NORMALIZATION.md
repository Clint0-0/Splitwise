# Experiment 12 — Normalization up to 3NF

## 0. Starting point: the recruitment spreadsheet (unnormalized, UNF)
| Name | Email | Phone | DOB | Qualification | Applications |
|---|---|---|---|---|---|
| Neha Kulkarni | neha.k@… | 9876543210 | 2002-04-14 | B.E. Comp | Junior PHP Developer (Engineering) 2026-10-05 Interviewed; Database Administrator (Engineering) 2026-10-06 Applied |
| Rohit Verma | rohit.v@… | 9123456780 | 2001-11-02 | B.Sc. IT | Junior PHP Developer (Engineering) 2026-10-08 Shortlisted |

Problems: the *Applications* cell holds a **repeating group** (several values in one cell) — it cannot be searched or counted reliably.

## 1. First Normal Form (1NF)
Rule: every cell holds one atomic value; no repeating groups; each row identifiable by a key.
Split the repeating group so each row is one application:

**R1 (candidate_email, full_name, phone, dob, qualification, position_title, department, applied_on, status)**
Key: **(candidate_email, position_title)** — one person applies to one position once.

R1 is in 1NF, but the same candidate data is repeated for each application and department is repeated for each applicant of a job → update, insert and delete anomalies:
- *Update*: Neha changes phone → must change every one of her rows.
- *Insert*: a new position cannot be recorded until someone applies.
- *Delete*: deleting Rohit's only application deletes everything known about Rohit.

## 2. Functional dependencies in R1
- FD1 `candidate_email → full_name, phone, dob, qualification`
- FD2 `phone → candidate_email` (phone is also unique per person)
- FD3 `position_title → department`
- FD4 `(candidate_email, position_title) → applied_on, status`

## 3. Second Normal Form (2NF)
Rule: 1NF + no **partial dependency** (no non-key attribute depends on only part of a composite key).
FD1 depends only on `candidate_email` (part of the key) and FD3 only on `position_title` (the other part) → partial dependencies. Decompose:

- **Candidate (candidate_email, full_name, phone, dob, qualification)** — from FD1
- **Position (position_title, department)** — from FD3
- **Application (candidate_email, position_title, applied_on, status)** — from FD4

Every non-key attribute now depends on the whole key of its table. Anomalies gone: phone stored once; positions can exist without applicants; deleting an application leaves the candidate.

## 4. Third Normal Form (3NF)
Rule: 2NF + no **transitive dependency** (non-key → non-key).
- Candidate: all attributes depend directly on the key; `phone` is itself a candidate key (FD2), not a non-key attribute, so `phone → …` is allowed. No transitive dependency.
- Position: `department` depends directly on `position_title`. In the earlier single table, `position_title → department` sat **behind** the application key — `(email, title) → title → department` — a transitive chain; giving positions their own table removed it.
- Application: `applied_on` and `status` depend directly on the full key.

All three relations are in 3NF (also BCNF: every determinant is a candidate key).

## 5. Physical design choices (do not change the normal form)
- **Surrogate keys** `candidate_id`, `position_id`, `application_id` (AUTO_INCREMENT): emails and job titles can change; integer keys stay stable and are cheap to join. The natural keys stay enforced as `UNIQUE` (`email`, `phone`, `title`, `(candidate_id, position_id)`).
- `department` kept as text in `positions`: making a `departments` table would only be needed if departments had their own attributes — they don't (avoids an unnecessary table).
- `highest_qualification` is one attribute; a multi-valued education history would need its own table (out of scope, see REQUIREMENTS).

## 6. Final schema (implemented in `database/candidate_management.sql`)
```
positions    (position_id PK, title UK, department, is_open)
candidates   (candidate_id PK, full_name, email UK, phone UK, date_of_birth?, highest_qualification, created_at, updated_at?)
applications (application_id PK, candidate_id FK→candidates ON DELETE CASCADE,
              position_id FK→positions ON DELETE RESTRICT, applied_on, status, UK(candidate_id, position_id))
```
