# TESTING_REPORT.md — SplitWise+

## Phase 2 — Database testing

| Item | Value |
|---|---|
| Date | 2026-11-08 (re-run 2026-11-09 after gap fixes) |
| DBMS | MySQL 8.0.46 (Ubuntu build), `sql_mode` default (`ONLY_FULL_GROUP_BY, STRICT_TRANS_TABLES, …`) |
| Client | PHP 8.3 PDO (`ATTR_EMULATE_PREPARES=false`), mysql CLI with `--default-character-set=utf8mb4` |
| How to reproduce | `tests/database/run.sh` (imports `database/splitwise_plus.sql`, runs all SQL scripts, runs `tests/database/run_db_tests.php`, re-imports) |
| Result | **All 12 SQL scripts executed without error (verify_install: all PASS). 74 / 74 passed.** |

Results below are copied from the actual run output, not written by hand.

### Test groups
- **S** structure (tables, views, triggers, routines, no float columns, UTF-8)
- **F** financial correctness (hand-computed balances, Σ = 0 per group, rounding, pairwise reconciliation, spec example)
- **C** constraints (UNIQUE, CHECK, FK, generated-column unique, strict-mode behaviour)
- **T** triggers (all six timing/event pairs)
- **V** views (updatable vs non-updatable, CHECK OPTION)
- **Q** subquery NULL pitfall
- **P** stored procedures (validation, idempotency, atomic rollback)
- **X** concurrency (row lock on settlements)
- **A** least-privilege app account (tests run as `splitwise_app` over TCP)
- **W** committing flows (confirm settlement, payee-recorded settlement)

### Hand-verified expected balances (sample data)
| Group | Member | Paid | Owed (shares) | Sent | Received | Net |
|---|---|---:|---:|---:|---:|---:|
| Goa Trip 2026 | Aarav | 28000.00 | 13008.33 | 0 | 5000.00 | **+9991.67** |
| | Priya | 19000.00 | 12570.84 | 0 | 3000.00 | **+3429.16** |
| | Rohan | 4750.00 | 16037.50 | 5000.00 | 0 | **−6287.50** |
| | Sneha | 2400.00 | 12533.33 | 3000.00 | 0 | **−7133.33** |
| Flat 302 | Priya | 110780.00 | 46983.50 | 0 | 37400.00 | **+26396.50** |
| | Kabir | 4449.00 | 36182.51 | 21600.00 | 0 | **−10133.51** |
| | Ananya | 4120.50 | 36183.49 | 15800.00 | 0 | **−16262.99** |
| College Friends | Rohan / Aarav / Kabir / Vikram | | | | | **+450 / +100 / +600 / −1150** |
| Weekend Trek | all three (debt cycle) | 500 each | 500 each | | | **0** |

Pending (₹2,000, ₹4,000), rejected (₹1,000) and cancelled (₹500) settlements and the soft-deleted ₹1,200 expense are correctly excluded.

### Issues found and fixed during testing
| # | Issue | Fix |
|---|---|---|
| 1 | mysql client defaulted to `latin1`: `₹`, `–`, `é` were stored double-encoded (tests F6, T15 failed) | `SET NAMES utf8mb4;` at the top of every script; test S6 guards it |
| 2 | Assumed strict mode rejects `10.005` in `DECIMAL(12,2)`; MySQL actually rounds to `10.01` with a note | Test C14 now documents this; PHP must reject > 2 decimals (Phase 3 requirement) |
| 3 | Assumed only 2 views updatable; MySQL marks 4 (inner-join view, merged derived table) | Docs corrected; tests V4/V5 pin the real behaviour |

### Results
| Script | Result |
|---|---|
| `database/verify_install.sql (all checks PASS)` | OK |
| `database/queries/01_basic_select.sql` | OK |
| `database/queries/02_filtering_and_sorting.sql` | OK |
| `database/queries/03_joins.sql` | OK |
| `database/queries/04_aggregates.sql` | OK |
| `database/queries/05_subqueries.sql` | OK |
| `database/queries/06_business_reports.sql` | OK |
| `database/queries/07_explain.sql` | OK |
| `database/views/demo_views.sql` | OK |
| `database/triggers/demo_triggers.sql` | OK |
| `database/transactions/demo_acid.sql` | OK |
| `database/security/app_user.sql` | OK |

| ID | Test | Result |
|---|---|---|
| S1 | 14 base tables exist | PASS |
| S2 | 11 views exist | PASS |
| S3 | 11 triggers covering all 6 timing/event pairs | PASS |
| S4 | 5 procedures + 3 functions exist | PASS |
| S5 | No FLOAT/DOUBLE columns anywhere | PASS |
| S6 | Text stored as real UTF-8 (no double-encoded "Ã"/"â€" sequences) | PASS |
| F1 | Every group: sum of net balances = 0 | PASS |
| F2 | All 17 member balances equal hand-computed values | PASS |
| F3 | Every expense: shares sum to total (gap = 0) | PASS |
| F4 | fn_member_net_balance agrees with view for every member | PASS |
| F5 | fn_budget_spent agrees with v_budget_utilization | PASS |
| F6 | Equal 1000/3: payer gets 333.34, others 333.33 | PASS |
| F7 | Equal 2780/3: leftover paise to payer then lowest id | PASS |
| F8 | Pairwise debts reconcile to net balances for every member | PASS |
| F9 | Overall balances across all users sum to 0 | PASS |
| F10 | Soft-deleted expense excluded from balances (Goa total = 54150) | PASS |
| F11 | Spec example: B owes 500 -> pays 300 -> owes 200; A +1000 -> +700; pair outstanding 200 | PASS |
| C1 | Duplicate email rejected | PASS |
| C2 | Zero/negative expense amount rejected | PASS |
| C3 | Settlement to self rejected | PASS |
| C4 | Budget with both user and group owner rejected | PASS |
| C5 | Budget end before start rejected | PASS |
| C6 | Second pending invitation for same user/group rejected | PASS |
| C7 | New pending invite allowed after previous one was answered | PASS |
| C8 | FK: unknown category rejected | PASS |
| C9 | FK RESTRICT: user with history cannot be deleted | PASS |
| C10 | Percentage over 100 rejected | PASS |
| C11 | Receipt with disallowed MIME type rejected | PASS |
| C12 | Upper-case tag rejected | PASS |
| C13 | Active member with left_at set rejected | PASS |
| C14 | DECIMAL(12,2) silently rounds 10.005 -> 10.01 (so PHP must validate decimals) | PASS |
| C15 | Amount above DECIMAL(12,2) range rejected in strict mode | PASS |
| T1 | BEFORE INSERT expenses: future date rejected | PASS |
| T2 | BEFORE INSERT expenses: non-member payer rejected | PASS |
| T3 | BEFORE INSERT expenses: archived group rejected | PASS |
| T4 | BEFORE INSERT splits: non-member participant rejected | PASS |
| T5 | BEFORE INSERT splits: personal expense split to another user rejected | PASS |
| T6 | BEFORE INSERT splits: share above total rejected | PASS |
| T7 | BEFORE UPDATE settlements: confirmed is final | PASS |
| T8 | BEFORE UPDATE settlements: amount of pending cannot change | PASS |
| T9 | BEFORE DELETE settlements: confirmed cannot be deleted | PASS |
| T10 | BEFORE DELETE settlements: pending one may be deleted | PASS |
| T11 | AFTER INSERT settlements: payee notified of pending request | PASS |
| T12 | AFTER UPDATE settlements: payer told of rejection; responded_at set | PASS |
| T13 | AFTER UPDATE expenses: soft delete logged as SOFT_DELETE | PASS |
| T14 | AFTER DELETE splits: 3 old splits of the edited expense archived | PASS |
| T15 | AFTER INSERT splits: participants (not payer/creator) notified of new expense | PASS |
| T16 | AFTER DELETE expenses: hard delete audited (admin cleanup) | PASS |
| T17 | audit_logs records the acting user from @app_user_id | PASS |
| V1 | WITH CHECK OPTION blocks soft delete through v_active_expenses | PASS |
| V2 | Aggregate view is not updatable | PASS |
| V3 | Simple view is updatable and writes through to base table | PASS |
| V4 | information_schema: updatable = 2 simple views + inner-join view + merged-derived-table view | PASS |
| V5 | Computed column of an "updatable" view cannot be updated | PASS |
| Q1 | NOT IN with NULL in subquery returns 0 rows; NOT EXISTS returns Office Team | PASS |
| P1 | sp_record_settlement: over-settlement rejected | PASS |
| P2 | sp_record_settlement: third party cannot record | PASS |
| P3 | sp_record_settlement: pending amounts count toward limit (Sneha has 2000 pending) | PASS |
| P4 | sp_record_settlement: non-member rejected | PASS |
| P5 | sp_record_settlement: same request_token returns existing id, no new row | PASS |
| P6 | sp_respond_settlement: only payee can confirm | PASS |
| P7 | sp_add_equal_expense: non-member participant -> whole expense rolled back | PASS |
| P8 | sp_add_equal_expense: malformed participant list rejected | PASS |
| X1 | Concurrent settlement in same group blocks on FOR UPDATE lock | PASS |
| X2 | Blocked call left no partial row | PASS |
| A1 | App user can read tables and views | PASS |
| A2 | App user can EXECUTE procedures | PASS |
| A3 | App user can add expense + splits; DEFINER triggers still write audit_logs | PASS |
| A4 | App user cannot write settlements directly (must use procedures) | PASS |
| A5 | App user cannot delete or edit audit_logs | PASS |
| A6 | App user cannot run DDL (CREATE TABLE) | PASS |
| A7 | App user reaches settlement rules via sp_record_settlement (blocked by rule, not by permission) | PASS |
| W1 | Aarav confirms Sneha's pending 2000: Sneha -5133.33, Aarav +7991.67, group sum 0 | PASS |
| W2 | Payee-recorded settlement is confirmed immediately; Kabir settled in College Friends | PASS |

74 / 74 passed

### EXPLAIN evidence (`database/queries/07_explain.sql`)
| Query | Plan with index | Without index |
|---|---|---|
| Group history `WHERE group_id=1 AND is_deleted=0 ORDER BY expense_date DESC` | `ref` on `idx_expenses_group_deleted_date`, backward index scan, no filesort | `ALL` (full scan) + `Using filesort` |
| User's shares `WHERE user_id=3` | `ref` on `idx_expense_splits_user` | — |
| Unread notifications | `ref` on `idx_notifications_user_read_date`, no filesort | — |
| Membership check | `const` on `uq_group_members_group_user`, `Using index` | — |

### Known limitations
- Sample data is small (23 expenses), so EXPLAIN shows plan shape, not timing gains.
- Trigger notifications are not deduplicated if an expense is edited many times quickly (acceptable for an in-app feed).


## Phase 3 — Backend testing

| Item | Value |
|---|---|
| Date | 2026-11-09 |
| Stack | PHP 8.3 (built-in server for tests; Apache 2.4 + mod_php for the sub-folder check), MySQL 8.0.46 |
| DB account used by the app | `splitwise_app` (least privilege) — never root |
| Reproduce | `php tests/unit/run_unit_tests.php` · `tests/functional/run.sh` · `tests/database/run.sh` |
| Result | **Unit 41 / 41 passed · Functional (HTTP end-to-end) 88 / 88 passed · Database 74 / 74 passed** |

### Unit tests (`tests/unit/run_unit_tests.php`) — money, split methods, debt simplification
Includes two property tests: 3,000 random splits always sum exactly to the total; 2,000 random groups are fully settled by the suggested transfers using at most n−1 payments.

| Test | Result |
|---|---|
| Money: "1500.5" -> 150050 paise | PASS |
| Money: "₹1,23,456.78" accepted | PASS |
| Money: 3 decimals rejected | PASS |
| Money: negative rejected by default | PASS |
| Money: zero rejected by default | PASS |
| Money: text rejected | PASS |
| Money: SQL-ish input rejected | PASS |
| Money: too large rejected | PASS |
| Money: fromPaise(-5) = "-0.05" | PASS |
| Money: round-trip DB decimal | PASS |
| inr(): Indian grouping | PASS |
| Equal: spec ₹1500 / 3 = 500 each | PASS |
| Equal: ₹1000/3 -> payer 333.34 (same as sp_add_equal_expense) | PASS |
| Equal: ₹2780/3 -> payer, then lowest id get the 2 leftover paise | PASS |
| Equal: payer not a participant -> leftover to lowest ids | PASS |
| Equal: single participant gets everything | PASS |
| Equal: no participants rejected | PASS |
| Percentage: spec 50/30/20 of ₹1200 = 600/360/240 | PASS |
| Percentage: inputs stored as "50.00" | PASS |
| Percentage: 33.33/33.33/33.34 of ₹1000 | PASS |
| Percentage: ₹1 sums exactly to 100 paise | PASS |
| Percentage: 99% rejected | PASS |
| Percentage: 101% rejected | PASS |
| Percentage: 3 decimals rejected | PASS |
| Exact: 800+800+800 = 2400 | PASS |
| Exact: short total rejected with amount | PASS |
| Exact: over total rejected | PASS |
| Exact: zero share allowed (payer not charged) | PASS |
| Shares: 1:1:2:1 of ₹18000 = 3600/3600/7200/3600 | PASS |
| Shares: fractional units 1.5:1:0.5 of ₹1000 | PASS |
| Shares: zero units rejected | PASS |
| Adjustment: ₹6000, Priya +600 -> 1350/1950/1350/1350 | PASS |
| Adjustment: negative adjustment (discount) works | PASS |
| Adjustment: share pushed below zero rejected | PASS |
| Adjustment: adjustments above total rejected | PASS |
| Property: 3000 random splits always sum exactly to the total, never negative | PASS |
| Simplify: cycle A->B->C->A (all nets 0) -> no transfers | PASS |
| Simplify: chain A owes B 500, B owes C 500 -> A pays C 500 | PASS |
| Simplify: Goa group -> 3 transfers | PASS |
| Simplify: rejects nets that do not sum to 0 | PASS |
| Property: 2000 random groups — transfers zero every balance, ≤ n−1 transfers | PASS |

41 / 41 passed

### Functional tests (`tests/functional/run_functional_tests.php`) — the real app over HTTP
A scripted browser (cookies, CSRF tokens, forms, multipart uploads) drives the running application; results are checked in the database.
Groups: **H** auth · **Z** authorisation · **G** groups/invitations · **E** expenses & splits · **R** receipts · **S** settlements · **L** leave/remove rules · **B/N** budgets & notifications · **A** analytics · **P** profile & archive · **D** admin · **M** misc.

| ID | Test | Result |
|---|---|---|
| H1 | Guest is redirected from /dashboard to /login | PASS |
| H2 | Wrong password: generic error, no login | PASS |
| H3 | SQL injection in login form does not log in | PASS |
| H4 | POST with a wrong CSRF token is rejected (419) | PASS |
| H5 | Login works and redirects to dashboard | PASS |
| H6 | Dashboard shows Aarav's real overall balance (+₹10,091.67) | PASS |
| H7 | Logged-in POST without CSRF token is rejected (419) | PASS |
| H8 | Registration logs the new user in | PASS |
| H9 | Password stored as bcrypt hash, email lower-cased | PASS |
| H10 | Duplicate username/email and weak password rejected with field errors | PASS |
| Z1 | Non-member gets 403 on another group | PASS |
| Z2 | Non-member gets 403 on another group's expense | PASS |
| Z3 | Non-member cannot add an expense to another group (403) | PASS |
| Z4 | Ordinary user gets 403 on /admin | PASS |
| Z5 | API never leaks other users' groups | PASS |
| G1 | Group created; creator is its admin | PASS |
| G2 | Duplicate pending invitation rejected | PASS |
| G3 | Inviting an unknown user rejected | PASS |
| G4 | Someone else cannot accept your invitation (403) | PASS |
| G5 | Invitation notification + pending invite shown to invitee | PASS |
| G6 | Accepted invitations create active memberships | PASS |
| E1 | Expense form lists the group members | PASS |
| E2 | Spec example: ₹1500 equal among 3 -> 500 each; tester owes ₹500 | PASS |
| E3 | Tags normalised (lower-case, dashes) and saved | PASS |
| E4 | Participants were notified by the trigger (not the creator) | PASS |
| E5 | Percentage not 100% rejected with the current total | PASS |
| E6 | Exact amounts not matching the total rejected | PASS |
| E7 | Amount with 3 decimals rejected by PHP (MySQL would round it) | PASS |
| E8 | Future date rejected | PASS |
| E9 | No partial expense rows were written by failed submissions | PASS |
| E10 | Shares 1.5:1:0.5 of ₹1000 = 500 / 333.33 / 166.67 | PASS |
| E11 | Adjustment split: base (900−100)/3 + adj -> 416.67 / 266.67 / 216.66 | PASS |
| E12 | Every expense written through the app has split gap 0 | PASS |
| E13 | XSS: description/notes are escaped in the page | PASS |
| E14 | SQL injection in search box returns no rows (treated as text) | PASS |
| E15 | Search finds the right expense | PASS |
| E16 | Expense count matches the database (14) and an out-of-range page is empty, not an error | PASS |
| E17 | Malformed group filter ignored safely | PASS |
| E18 | Personal expense: no group, single split to the owner | PASS |
| E19 | Someone else's personal expense is private (403) | PASS |
| E20 | Edit re-splits (1800/3 = 600) and bumps the version | PASS |
| E21 | Stale edit (old version) rejected — no lost update | PASS |
| E22 | Edit history: old splits archived, UPDATE audited with actor | PASS |
| E23 | Participant who is not creator/payer/admin cannot delete (403) | PASS |
| E24 | Creator soft-deletes: row kept, excluded from balances | PASS |
| E25 | Group admin restores a deleted expense | PASS |
| R1 | PNG receipt stored outside public/ with a random name | PASS |
| R2 | Participant can open the receipt | PASS |
| R3 | Non-member cannot open the receipt (403) | PASS |
| R4 | PHP file disguised as PNG rejected (MIME from content) and no expense saved | PASS |
| R5 | Receipts are not reachable by direct URL | PASS |
| S1 | Payer-recorded settlement is pending; double submit (same token) stored once | PASS |
| S2 | Pending settlement does not change the balance yet | PASS |
| S3 | Payer cannot confirm their own payment | PASS |
| S4 | Payee sees it on the dashboard to confirm | PASS |
| S5 | After confirmation the debt drops by exactly ₹300 | PASS |
| S6 | Over-settlement rejected with the procedure's message | PASS |
| S7 | Cannot record a payment between two other people | PASS |
| S8 | Group page shows simplified suggestions and pairwise debts | PASS |
| S9 | Goa group: 3 suggested transfers from the sample data | PASS |
| L1 | Cannot leave a group with an unsettled balance | PASS |
| L2 | Admin cannot remove a member who still owes money | PASS |
| L3 | Non-admin cannot remove members (403) | PASS |
| L4 | Last admin cannot demote themselves | PASS |
| B1 | Monthly budget end date computed from start | PASS |
| B2 | Crossing 80% (₹899 of ₹1000) creates exactly one budget warning | PASS |
| B3 | No duplicate warning for the same budget period | PASS |
| B4 | Budgets page shows spent / limit | PASS |
| B5 | Cannot edit someone else's budget (403) | PASS |
| N1 | Notifications listed and "mark all read" works | PASS |
| A1 | Analytics JSON uses real DB numbers (Goa: paid ₹28,000, share ₹13,008.33) | PASS |
| A2 | Monthly series is continuous (12 months incl. zero months) | PASS |
| A3 | Analytics page renders with Chart.js | PASS |
| P1 | Profile: another user's email rejected | PASS |
| P2 | Profile update saved | PASS |
| P3 | Password change needs the current password | PASS |
| P4 | Password changed (new hash verifies) | PASS |
| P5 | Archived group is read-only (new expense refused with 403) | PASS |
| P6 | Group admin can unarchive | PASS |
| D1 | System admin sees statistics | PASS |
| D2 | Activity log shows trigger-written audit rows | PASS |
| D3 | Deactivated user is logged out on their next request | PASS |
| D4 | Deactivated user cannot log in | PASS |
| D5 | Admin cannot deactivate themselves | PASS |
| M1 | Unknown page -> 404 page | PASS |
| M2 | Non-numeric id -> 404 (route only accepts digits) | PASS |
| M3 | Database totals still consistent: every group balance sums to 0 | PASS |
| M4 | Logout ends the session | PASS |

88 / 88 passed

### Apache / XAMPP sub-folder check (repo copied to `htdocs/Splitwise`)
| URL | Result |
|---|---|
| `/Splitwise/` , `/Splitwise/login` | 302 → `/Splitwise/public/…` |
| `/Splitwise/public/login` → log in → dashboard | 200, real balance shown |
| `/Splitwise/.env`, `/.git/config`, `/app/…`, `/config/…`, `/database/*.sql`, `/storage/receipts/…`, `/tests/…`, `/docs/…`, `README.md` | **403** |

### Issues found and fixed in Phase 3
| # | Issue | Fix |
|---|---|---|
| 4 | Registration skipped the duplicate username/email check whenever another field (e.g. password) was invalid, so the user only learned about the duplicate on the next try | Uniqueness checked for every well-formed field (test H10) |
| 5 | Two simultaneous sign-ups with the same username would hit the UNIQUE key and show a 500 page | Duplicate-key error (1062) turned into a form error |
| 6 | With the repo inside XAMPP `htdocs`, `.env` (DB password), source code and uploaded receipts were reachable by URL | Root `.htaccess` (403 for secrets/source, redirect to `public/`) + `Require all denied` in `storage/`, `app/`, `config/`, `database/`, `tests/`, `tools/`, `docs/` |


## Phase 3 — bug-fix round (2026-11-09)

Found by a code review after the first Phase 3 run, fixed, and covered by new tests. Re-run results: **unit 41 / 41 · functional 103 / 103 · database 74 / 74**.

| # | Bug | Fix | Tests |
|---|---|---|---|
| 7 | Editing / deleting / restoring an old expense could change the balance of a member who had already left at zero (edit form also silently dropped them) | Expenses that involve a former member are locked; page explains why | K1–K5 |
| 8 | A member could leave at the same moment someone added an expense that included them | Leave/remove lock the group's membership rows `FOR UPDATE`; expense writes take `FOR SHARE` and re-check inside the transaction | K6 |
| 9 | Login lockout stored in the session — dropping the cookie reset it | File-based counters: 5 failures per account+IP, 20 per IP, 15 minutes | T1–T3 |
| 10 | "Email me" profile setting had no effect | Mock mailer appends notifications (including trigger-created ones) to `storage/logs/mail.log` for opted-in users, once each | T4–T6 |
| 11 | Upload larger than `post_max_size` showed "session expired" (PHP drops the whole form) | Detected before CSRF check; clear "upload too large" message | T7 |
| 12 | *(new, found while fixing #11)* PHP's default `upload_max_filesize` is 2 MB, but the form promised 5 MB and the error said "larger than 5 MB" | Effective limit = min(5 MB, PHP limits), shown in the form and errors; dev server + README set 6M/8M; verified on Apache (shows 2 MB under default mod_php) | T8 |
| 13 | Archived groups blocked settling debts that were still owed | Settlements allowed in archived groups; everything else stays read-only | P5b |

Expected log line during T7: PHP's own warning `POST Content-Length … exceeds the limit` (that is the oversized request being rejected).


## Phase 4 — Frontend checks (2026-11-09)

| Check | Tool | Result |
|---|---|---|
| Every screen renders, desktop 1366×900 and mobile 390×844 | `tools/screenshots.py` (Chrome via Playwright) | 36 screenshots in `docs/screenshots/`, no console errors |
| Accessibility, WCAG 2.0/2.1 A + AA + best practice | axe-core 4 injected into each of the 36 page views | **0 violations** (2 issue types found on first run and fixed: definition-list markup, keyboard access to scrollable tables) |
| Chart palette (two-series charts) | dataviz `validate_palette.js`, light surface | PASS lightness, chroma, CVD (ΔE 37.0), normal-vision (ΔE 40.3); contrast WARN for marigold → every chart has a "Show as table" view |
| Text contrast of design tokens | WCAG ratio | ink 15.6:1, violet 8.9:1, gets 5.4:1, owes 6.3:1, muted 6.6:1, ink on marigold 10.2:1 |
| Regression after the redesign | all suites | unit 41/41 · functional 103/103 · database 74/74 |

Three functional assertions were updated because the page wording changed on purpose (e.g. "gets back ₹10,091.67" → hero sentence "you get back ₹10,091.67"; "Sneha → Aarav" → "Sneha pays Aarav"); they still check the same numbers.

Visual issues found in screenshot review and fixed: navigation wrapping, rail colour ending mid-page, invisible avatar on the rail, member names wrapping, misaligned analytics columns, cramped expense form, truncated titles on phones, ₹0.00 shown in red.
