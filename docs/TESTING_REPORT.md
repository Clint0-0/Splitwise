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


## Phase 5 — System testing (2026-11-09)

### One command for everything
`php tests/run_all.php` runs every suite on **Windows (XAMPP) or Linux** — no bash needed. It imports the database, runs the install check and every SQL script, the database suite, starts the app on PHP's built-in server **as the least-privilege `splitwise_app` account**, runs the functional suite and the money scenarios, and restores clean sample data. It also warns when `splitwise_plus.sql` is out of date (`--build` regenerates it).

Final regression run, with the database reached **over TCP with a password-protected admin account** (the way a Windows/XAMPP laptop connects):

| Suite | Result |
|---|---|
| Unit (money, splits, simplifier) | 41 / 41 PASS |
| SQL scripts (11: install check, Q1–Q50 files, EXPLAIN, view/trigger/ACID demos) | 11 / 11 PASS |
| Database (schema, triggers, procedures, privileges) | 74 / 74 PASS |
| Functional (HTTP end-to-end) | 103 / 103 PASS |
| Money scenarios (`docs/TEST_SCENARIOS.md`) | 23 / 23 PASS |
| Accessibility (axe-core, 36 page views) | 0 violations |
| **Total automated checks** | **252 tests + 11 scripts, all passing** |

### Money scenarios
`tests/functional/run_scenarios.php` drives five stories with brand-new users: the brief's own examples (₹1,500 dinner; ₹500 → ₹300 → ₹200 partial settlement; 50/30/20 of ₹1,200; ₹10,000 budget at ₹7,500), settling everything and then editing / deleting / restoring history, rounding, a debt cycle, and guard rails. Amounts were worked out by hand first (tables in `docs/TEST_SCENARIOS.md`); every step passed on the first run.

### Changes made in Phase 5
| # | Change | Reason |
|---|---|---|
| 14 | Per-IP login limit raised 20 → 100 failures / 15 min (per account+IP stays 5) | many students can share one hostel/college Wi-Fi address; 20 could lock a whole hostel out |
| 15 | Functional tests can reach MySQL over TCP (`DB_ROOT_TCP`, `DB_HOST/PORT`, `APP_DB_PASS`) | they assumed the Linux socket; Windows needs TCP |
| 16 | `tests/functional/lib.php` shared helpers | used by the scenario suite |

### Not covered here (needs a team laptop)
| Item | Why not here | How it is covered |
|---|---|---|
| Windows + XAMPP Apache + MySQL 8.0 | sandbox is Linux | `docs/TEAM_TEST_CHECKLIST.md` (install, `run_all.php`, 12-step click-through) |
| Firefox / Edge / Safari rendering | browser downloads are blocked from the sandbox (only Chrome available) | checklist runs the click-through in Chrome **and** a second browser |
| Real users on phones | — | checklist step 10 (phone width) |


### Phase 5 — bug-fix round (2026-11-09)
Found by reviewing the Phase 5 code; each fix was exercised for real.

| # | Bug | Fix | How it was verified |
|---|---|---|---|
| 17 | `run_all.php` waited a fixed 1.2 s for the app; on a slow laptop the first requests could fail, and a busy port gave confusing test failures | Polls `/login` for up to 10 s; if the server dies or never answers, prints its log and says "Is the port in use? Try --app-port" | Ran with port 8765 deliberately occupied: clear message, exit 1 |
| 18 | If the runner crashed, the test web server kept running | Shutdown handler always stops it | No `php -S` process left after any run |
| 19 | A failed re-import mid-run was ignored, so later suites could run on stale data | Re-imports are checked; the run stops with a message | Code path reviewed |
| 20 | Root password was passed as `-pPASSWORD` (visible to other processes; mysql printed a warning on every call) | Password written to a temporary option file (mode 600, `--defaults-extra-file`), deleted at exit | Full run with password `Wi"n R\oot #26;` (quote, backslash, space, #, ;): all suites pass, 0 warnings, temp file gone |
| 21 | Tests took "today" from the test machine's PHP time zone, the app from India time — on a machine set ahead of India the app could reject the tests' dates as future | Test helpers use `APP_TIMEZONE` (default Asia/Kolkata), same as the app | Code path; all suites pass |
| 22 | Git for Windows checks files out with CRLF by default | `.gitattributes` pins LF (CRLF breaks the `.sh` runners under Git Bash/WSL). Also checked: MySQL imports a CRLF copy of `splitwise_plus.sql` cleanly (10/10 install checks, no `\r` in data) | Imported a CRLF copy |

Re-run after the fixes (password admin over TCP, as on Windows): **unit 41/41, SQL scripts 11/11, database 74/74, functional 103/103, scenarios 23/23 — all passing.** Linux shortcut runners also pass.

## Phase 5.5 — UI refinement + deployment preparation (2026-11-09)

### Results (all run in the build sandbox, MySQL 8.0.46, PHP 8.x built-in server)
| Suite | Result |
|---|---|
| `php tests/run_all.php` (password admin over TCP, as on Windows): unit / SQL scripts / database / functional / scenarios | **41/41, 11/11, 76/76, 115/115, 23/23 — all pass** |
| Linux runners `tests/database/run.sh`, `tests/functional/run.sh` | pass |
| `tests/deployment/run.sh` | **28/28** |
| axe-core (WCAG 2.1 AA + best practice), all screens, desktop + phone | **0 violations** |
| Screenshots 1440 / 1366 / 1024 / 768 / 390 px, 18 screens | reviewed; fixes below |

### New tests
- Functional **X1–X12** (first named P1–P12, renamed because P1–P6 were already the profile tests): `/healthz`, `/readyz`, 405 on POST, CSP header, no inline scripts on 11 pages, session row in `app_sessions`, row deleted on logout, attacker-chosen session id replaced, idle session expired server-side, throttle counters in `app_kv` with hashed keys, mail cursor in `app_kv`, cookie flags on local HTTP. **R1** now checks receipt bytes in MySQL.
- Database **A8–A9**: app user can write `app_sessions`/`app_kv` and store receipt bytes.
- Deployment **C1–C7** (Dockerfile/Caddyfile/.dockerignore/.gitignore/.env.example/git-tracked secrets), **H1–H4** (hosted-style import into a separate DB, demo passwords replaced, TLS-only user refused without TLS), **E1–E12** (production mode behind a simulated HTTPS proxy: HSTS, Secure cookie, login with hosted demo password, session shared by two instances, throttle by real IP and not dodgeable with forged X-Forwarded-For, no secrets in logs), **F1–F4** (DB unreachable: `/readyz` 503 generic, `/healthz` 200, generic 500 page even with `APP_DEBUG=true`, failure logged without password).

### Issues found and fixed in Phase 5.5
| # | Issue | Fix |
|---|---|---|
| 23 | Five runtime stores on local disk would break on Vercel (sessions, throttle, mail cursor, receipts, error log) | moved to MySQL / stderr |
| 24 | Two-column grid at 1024 px made IOU slips and the hero figure overflow | two columns from 1200 px; clamp() font sizes |
| 25 | Group members table clipped the balance column at half width | paid/share moved under the name |
| 26 | Dashboard group list had no inner padding | inset rows |
| 27 | Budget form used an inline `<script>` (incompatible with a strict CSP) | moved to `budget-form.js` |
| 28 | Session started before the error handler: DB outage would print a raw fatal error | `Session::start()` inside the `try` |
| 29 | First `client_ip()` draft used the left-most `X-Forwarded-For` entry (spoofable) | right-most entry; test E10b |
| 30 | Built-in server sent `X-Powered-By: PHP/x.y` | `header_remove()` in the front controller (image also has `expose_php=Off`) |
| 31 | Summary band text ran together for screen-reader text extraction ("group₹0.00settled up") | whitespace between term, figure and caption |

### Not tested / blocked
| Item | Status | Why |
|---|---|---|
| `docker build`, container run, FrankenPHP serving | **Blocked** | Docker Hub pulls refused; executing a downloaded FrankenPHP binary not permitted in the sandbox |
| Managed MySQL import over the internet | **Blocked** | needs a provider account (simulated locally with a TLS-only user in a separate DB) |
| Vercel preview/production, 20 workflows on deployed URL | **Not tested** | no Vercel account/credentials; no deployment exists |
| Official Vercel docs check | **Not done** | fetch not approved in the session; items marked (verify) in `DEPLOYMENT.md` |

### Phase 5.5 — bug-fix round (2026-11-09)
| # | Bug | Impact | Fix | Verified by |
|---|---|---|---|---|
| 32 | `.env.example` had comments after values; the `.env` reader kept them (`SESSION_DRIVER=database   # …`) | a copied `.env` silently used file sessions, wrong DB password, `MAIL_LOG` = a file named "# mock…"; Docker `--env-file` has the same problem | reader strips unquoted ` # comments` (quoted values keep `#`); template comments moved to their own lines | unit Env ×5, deployment C6a |
| 33 | `DB_SSL=true` with no CA found connected without a CA option | could end up on an unencrypted / unverified connection | fail closed with a clear config error | unit "fails closed" |
| 34 | Caddy matcher `/*/.*` only blocks dotfiles one folder deep | `/a/b/.secret` would be served | `*/.*` (any depth) | deployment C3 |
| 35 | Deployment test deleted the developer's `build/` folder | could wipe generated hosted scripts | `build_hosted.php --out=DIR`, test uses a temp folder | `build/` untouched after run |
| 36 | Stale `build/demo_password.txt` kept when a new `DEMO_PASSWORD` was given | misleading password file | removed on such runs | code path |
| 37 | Hosted install check pointed at `splitwise_plus` | wrong DB checked | `build/hosted_verify.sql` generated for the hosted name; deployment test runs it | deployment run (no FAIL rows) |
| 38 | `UPLOAD_MAX_BYTES` above 5 MB accepted | 500 error from the DB CHECK on a big receipt | env can only lower the limit | code path |
| 39 | Session write errors at shutdown could print a fatal error after the page | stray error text, no log context | write/updateTimestamp catch, log and return false | code path |
| 40 | Rejected/cancelled payments shown with +/− like real money | misleading list | struck through, muted, no sign, screen-reader "not counted" | screenshots |
| 41 | Deleted-expense total never struck through (`.num:first-of-type` never matched) | visual bug | selector on `td[data-label="Total"]` | screenshot |
| 42 | Dates wrapped in table cells at 768–1024 px | cramped rows | `white-space: nowrap` for date cells ≥768 px | screenshots |
| 43 | Profile said mock mail goes to `storage/logs/mail.log` (wrong in production) | wrong info | "written to the server log" | page |
| 44 | Dockerfile set unused `SERVER_NAME` | confusing config | removed | — |
| 45 | Deployment test only worked with passwordless socket root and a fixed CA path | not portable | `DB_ROOT_USER/PASS/TCP`, CA from `@@ssl_ca` or `MYSQL_CA` | run |

Re-run after the fixes: **unit 53/53, SQL scripts 11/11, database 76/76, functional 115/115, scenarios 23/23 (run_all over TCP + Linux runners), deployment 29/29, axe 0 violations, browser console 0 errors/warnings on all 18 screens (CSP blocks nothing the app needs; budget form script works).**

## DBMS Experiments 10–12 (2026-11-18, branch `feature/dbms-experiments-10-12`)

### Regression — SplitWise+ (before and after the experiment work)
| Suite | Before (main @ 0341915) | After |
|---|---|---|
| Unit | 53/53 | 53/53 |
| SQL scripts — Exp 1–9 queries, trigger/view demos, install check (`run_all.php`) | — | 11/11 |
| Database (schema, triggers, procedures, privileges) | 76/76 | 76/76 |
| Functional (auth, groups, expenses with all 5 split types, balances, settlements, budgets, analytics) | 115/115 | 115/115 |
| Money scenarios | 23/23 | 23/23 |
| Deployment readiness | 29/29 | 29/29 |
No file under `app/`, `config/`, `public/` or `database/` changed; `splitwise_plus` schema fingerprint identical before/after the Exp 10 run (E10-13).

### New experiment tests
| Suite | Result | Kind |
|---|---|---|
| `tests/experiments/test_exp10.php` — expected errors in order, RESTRICT/CASCADE/SET NULL end states, ALTER/RENAME/DROP FK results, warehouse program, manual examples, production untouched | **17/17** (after manual reconciliation; 15/15 before) | Executed locally (MySQL 8.0.46) |
| `tests/experiments/exp11_dry_run.js` — CRUD script run against an in-memory stand-in, JSON consistency, no credentials | **11/11** | Local dry run — **not** MongoDB; Atlas run **blocked** (needs account) |
| `experiments/12_candidate_management/tests/run_tests.php` — DB privileges/constraints, HTTP CRUD, duplicates, missing/invalid input, CSRF, headers, DB-down page | **31/31** | Executed locally (PHP 8.3 built-in server, least-privilege login, throw-away test DB) |
| axe-core on Exp 12 pages (1280 + 390 px, incl. error state) | **0 violations** | Executed locally |

Measured fact recorded in Exp 10: MySQL 8.0.46 **accepts** `ON DELETE SET DEFAULT` in `CREATE TABLE` but InnoDB rejects the parent delete with 1451 (it never sets the default).

### Not tested / blocked
| Item | Status |
|---|---|
| Exp 11 on MongoDB Atlas / Compass | **Blocked** — needs the team's Atlas account (guide in `ATLAS_SETUP.md`) |
| Exp 12 under XAMPP/Apache (`.htaccess` rule for `experiments/*/public/`) | **Not tested** — no Apache here; built-in server only |
| Experiment tests on Windows | **Not tested** — runners are bash; Windows steps documented in each README |

### Experiments 10–12 — bug-fix round (2026-11-18)
| # | Bug | Impact | Fix | Verified by |
|---|---|---|---|---|
| 46 | Exp 12 test runner dropped/recreated the **lab** database `splitwise_candidate_lab` and reset `candidate_app`'s password to the default | running the tests wiped a student's lab data and broke their configured login | tests use a throw-away DB `splitwise_candidate_lab_test` + user `candidate_test`, both dropped afterwards | lab DB and users unchanged after `tests/run.sh` |
| 47 | Exp 12 stored `created_at`/`updated_at` in the MySQL server zone (UTC here) but displayed them as local time | "Registered 13:15" for an 18:45 IST registration | session `time_zone` set to PHP's offset on connect | H19 |
| 48 | Exp 12 fields posted as arrays (`full_name[]=x`) became the string "Array" (PHP warning) and passed validation | junk candidate named "Array" | `str_param()` — non-string input treated as missing | H18 |
| 49 | Exp 12 phone input `maxlength="14"` was shorter than the documented `+91 99887-76655` (15) | the hinted format could not be typed | `maxlength="16"` | H20 |
| 50 | Exp 12 invalid UTF-8 made `preg_replace` return null → deprecation warnings | noisy logs | cast to string → "required" error | code path |
| 51 | Exp 11 script used `use("CollegeDB")` (playground helper, not guaranteed in every mongosh/Compass shell) and `const` (pasting it twice in one Compass shell fails with "already declared") | script could fail in Compass | `db = db.getSiblingDB("CollegeDB")`, `var` | dry run M1 now executes the script twice in one context |
| 52 | Exp 10 README: `MYSQL_ARGS="-uroot -p"` asks for the password 10 times without saying so | confusing | note + `mysql_config_editor --login-path` option | doc |

Re-run after fixes: Exp 10 15/15 · Exp 11 dry run 11/11 · Exp 12 **31/31** · unit 53/53 · database 76/76 · functional 115/115 · scenarios 23/23 · deployment 29/29. Exp 12 evidence screenshots retaken after the time-zone fix.

### Reconciliation with the lab manuals (2026-11-18)
Manuals `Experiment_No_10/11/12_NEP.docx` compared line by line with the implementation.
| Exp | Gap found | Change |
|---|---|---|
| 10 | manual's own theory examples (tasks/checklists, fkdemo categories/products incl. ON UPDATE SET NULL) were not run | new `09_manual_fkdemo_examples.sql` (sandbox `splitwise_lab_exp10_fkdemo`), statements as printed |
| 10 | PROGRAM tables differed from the manual (column names, AUTO_INCREMENT, `employeeWarehouse(employeeID, employeeName, role…)`) | `08` now uses the manual's exact DDL and inserts (`allocationID, employeeNumber, warehouseID`; Margao/Mapusa) |
| 10 | manual error: 4-value INSERT into `warehouse` after `DROP COLUMN capacity` → 1136 | shown as an expected error, then corrected insert |
| 10 | manual error: SET NULL example drops `categories` before `products` → 3730 | shown as an expected error, then correct order |
| 10 | 5 post-lab questions not answered | `POST_LAB_QUESTIONS.md` |
| 10 | manual uses MySQL Workbench 8.0 CE | README: Workbench run steps + GUI evidence items |
| 11 | none (steps, documents, filters, post-lab questions identical); manual inserts one document at a time | guide lists one-at-a-time first |
| 12 | none (manual gives only the 6 section headings + conclusion; all present) | README notes the mapping |
Exp 10 suite after the change: **17/17**, 13 expected errors in order `1451 1451 1451 1452 1830 1451 3819 1452 3730 1136 1452 1451 3730`.

### Bug-fix round after the manual reconciliation (2026-11-18)
| # | Bug | Impact | Fix | Verified by |
|---|---|---|---|---|
| 53 | Exp 10 `01` dropped only `splitwise_lab_exp10`; the new `splitwise_lab_exp10_fkdemo` from an earlier run survived until script `09` | on every re-run the `01` outputs ("Expected: 2 rows / 1 row") were wrong — a student's screenshot would not match the comments | `01` drops both sandboxes first; comments updated | new check E10-18 (fails on the old script, passes now) |
Exp 10 suite: **18/18**.
| 54 | Exp 10 scripts used the mysql-CLI-only `\G` terminator in `SHOW CREATE TABLE … \G` | the manual's tool, MySQL Workbench, cannot run `\G` → syntax errors | replaced by `;` (CLI shows a wide table instead) | E10-10 checks no `\G` remains; scripts also re-run with `--safe-updates` (Workbench's default safe mode): same 13 expected errors, no 1175 |

## Full-project audit (2026-11-19)
Method: fresh clone from GitHub (all suites), every page at 1366 / 1024 / 768 / 390 / 360 px for console errors, failed requests, horizontal overflow and broken images; interactive flows (mobile drawer, dropdowns, analytics charts painted, expense split preview, budget form); 3,000 random split cases comparing the browser preview (`expense-form.js`) with the server (`SplitCalculator`) → **0 mismatches**; malformed query/POST input on every list and form.

| # | Bug | Impact | Fix | Verified by |
|---|---|---|---|---|
| 55 | Front-end libraries never committed (`.gitignore` `vendor/` matched `public/assets/vendor/`) | fresh clones rendered unstyled: both menus at once, no icons, no charts | rule narrowed to `/vendor/`; files + `LICENSES.md` committed (fixed and merged earlier the same night) | deployment C8 |
| 56 | Dashboard / Balances "Owed to you" and "You owe" summed per-**group** nets | Priya saw "You owe ₹0.00" right above "You owe Aarav Sharma ₹3,516.67"; Owed to you ₹29,825.66 instead of ₹33,342.33 | totals now summed from the same person-to-person debts the list shows (net unchanged) | X17 (fails on old code: all 5 users) |
| 57 | `?page=99999999999999999999` on expenses, notifications, settlements, admin lists → 500 (OFFSET overflow → TypeError) | crash page | `Request::page()` clamps to 1–100000 | X13 (fails on old code) |
| 58 | `_csrf[]=x` → 500 TypeError instead of 419 | crash page | `verifyCsrf(mixed)` | X15 (fails on old code) |
| 59 | Settlement action `back=//host` accepted → protocol-relative open redirect | redirect to another site | only `/` + non-`/` local paths | X16 (fails on old code) |
| 60 | Array-valued fields (`q[]=`, `description[]=`, `group_id[]=` …) → PHP "Array to string" warnings, `(int)` of an array = 1 | log noise; group/payee id silently became 1 | `Request::queryStr/queryInt`, `allPost()` blanks unexpected arrays (only `participants`, `share` stay lists) | X14 |
| 61 | Impossible dates (`2026-13-45`, `2026-02-30`) passed to SQL as filters | odd/empty results, MySQL warnings | `checkdate()` before use (expenses filter, analytics) | X14 |
| 62 | Group page members table on phones: balance column cut off behind a horizontal scroll, admin ⋯ menu hidden, page 11 px wider than the screen | could not see balances / manage members on a phone | balance words wrap, tighter cells, scroll box contains its hidden labels | browser sweep: 0 overflow; screenshot |
| 63 | Functional test IDs P1–P6 (profile) duplicated by P1–P12 (Phase 5.5 platform) | confusing report | platform checks renamed X1–X12 | unique IDs |

Re-run after fixes: unit 53/53 · database 76/76 · functional **120/120** · scenarios 23/23 · deployment 30/30 · Exp 10 18/18 · Exp 11 dry run 11/11 · Exp 12 31/31 · axe 0 violations · browser sweep 0 issues (only the intended 404 pages).
Known, not changed: admin tables (users/groups/activity) need sideways scrolling on phones — inside a labelled scroll region, admin work is desktop-first.

## Phase 6 — itemized bills, Pay with UPI, bill scanning (2026-11-24, branch `feature/phase-6`)
| Suite | Before | After | New checks |
|---|---|---|---|
| Unit | 53 | 75 | itemized maths (tax, discount, odd paisa, limits, 3,000-bill property test), UPI link building/injection, scanner reply cleaning, provider flags, Messages API request/response against a local fake API |
| Database | 76 | Functional | 120 | 142 | IT1–IT8 itemized flow, U1–U6 UPI, SN1–SN8 bill scan (mock) |
| Scenarios / deployment / Exp 10 / Exp 11 / Exp 12 | 23 / 30 / 18 / 11 / 31 | same | H1 now expects 18 tables |

Also checked by hand in Chrome (desktop 1280 and phone 390 px): no horizontal overflow, no console errors; JS item preview = PHP on 3,000 random bills; rendered QR decodes (OpenCV) to the exact `upi://pay` link; axe 0 violations on every screen plus the itemized expense, itemized edit and UPI settle pages; migration on a v1.0 database gives a schema identical to a fresh import.

| # | Bug found while building | Fix |
|---|---|---|
| 64 | Enter in any expense field would submit to "Read items" (first submit button in the form) | hidden default "save" button placed first (SN1 checks it) |
| 65 | UPI validation was first added to the sign-up method instead of the profile update (sign-up crashed) | moved; H8–H10 + U1/U2 cover both |

| 66 | UPI ID of 101–129 chars passed the format check, then hit the VARCHAR(100) column: HTTP 500 | length check in profile + `UpiLink` (U7) |
| 67 | "Pay with UPI" shown when you picked yourself as the receiver | hidden when payer = payee (U8) |
| 68 | Windows/XAMPP: `max_execution_time` 30 s is wall-clock, shorter than the 45 s API timeout → fatal error on a slow scan | `set_time_limit(75)` before the call |
| 69 | `capture="environment"` made Android open the camera only, so an existing bill photo couldn't be picked | attribute removed |
| 70 | Quantity ≥ 100000 said only "Value is too large." | "Quantity can be at most 99999.99." (IT9) |
| 71 | A receipt chosen before "Read items" silently vanished (file fields reset on reload) | the scan message says to choose it again (SN9) |

Bug round 2 totals: unit 76, functional 146 (others unchanged). Page sweep at 1440/1366/1024/768/390/320 px on the itemized, UPI and profile pages: no overflow, no console errors.

Not tested: the real Anthropic API (no key here), real UPI apps on phones (deep links behave differently per app; the QR is the fallback), deployment.

## Phase 6 — revised strategy round (2026-11-24)
| Suite | Before | After | New checks |
|---|---|---|---|
| Unit | 76 | 90 | uneven/fractional units, zero units, weighted 3,000-bill property test; scan charges/date/warnings; SMTP session against a local fake server; production refuses plain SMTP and keeps reset links out of logs; error-log scrubbing; Sentry envelope against a local fake endpoint |
| Database | 86 | 89 | I10 discount sign CHECK, I11 charges + cascade, I12 units CHECK; A10 now includes charges |
| 87 | Owner's question exposed it: Priya owes Aarav 100 and Rohan 100, Rohan owes Aarav 100; Priya pays Aarav 200. Every net is 0 (nothing left to pay), but leftover pair debts form a cycle, so all three would show "evens out" forever | open debts counted only while the group has a non-zero net; fully netted groups show "settled up" (BL4–BL5) |
| Functional | 146 | 172 | IT10–IT15 units + charges; U9–U13 server amount, part payment, copy ID, mark-as-sent, single confirmation; SN10–SN13 scan date/charges/uncertain/review tick/monthly cap/admin panel; PR1–PR6 password reset; **E2E1–E2E5 full workflow** |
| Deployment | 30 | 34 | B1 backup + verified restore, B2 restore test detects drift, B3 sample-free build + first-admin CLI, B4 production reset without APP_URL builds no link and logs no token |

Also: JS item preview = PHP on 3,000 random **weighted** bills; browser runs of units/charges, pay controls (clipboard copy, full-amount, labels) and the scanned-bill review at 390 px with no console errors; the preview table fits at 390 px.

| # | Bug found while building | Fix |
|---|---|---|
| 72 | Charge-row padding loop could never end (`count < count + 1`) | target computed before the loop |
| 73 | `$data['date'] ?? null && …` (?? binds looser than &&) would warn on every valid scan date | explicit `!empty(...) &&` + unit test |
| 74 | Password-reset expiry compared in different time zones by a test helper | the app stores and compares PHP-local times consistently; test compares against `created_at` |

| 75 | `tests/run_all.php` (the Windows/XAMPP runner) built `splitwise_plus.sql` from its own file list without `seeders/reference_data.sql`: `--build` would give a database with **no categories**, and plain runs warned "out of date" | the list is now read from `database/build.sh`; output verified identical; full `run_all.php` run: all suites pass |
| 76 | Changing your password on the Profile page left your other logins (e.g. a stolen session) active | other sessions of that user are deleted, the current one kept (P7) |
| 77 | A discount typed as "-10" got "at most 2 decimals" (server) / "needs a non-zero amount" (preview) | clear "type it without a minus sign" message in both (IT14) |
| 78 | Recording money you received showed "Pay the full amount" | "Use the full amount" for the receiver |
| 79 | With PHP's curl extension off (possible on XAMPP), `SENTRY_DSN` would crash the error page and a scan would be a fatal error | both check for curl first; monitoring failures are swallowed |
| 80 | Owner report: Priya "owes Aarav ₹3,516.67" (Person by person) but Goa Trip said "settled up" (Group by group). Not a money error: her group net was ₹0 because others owed her the same amount; the label hid open debts | `open_debts` count per member from `v_pairwise_debts`; net 0 with open debts shows "evens out · N open debts" (BL1–BL3) |
| 81 | "Add another item" cloned the last row's "hard to read" note and orange border | cleared on the new row |
| 82 | `run_all.php` on Windows failed with "could not start: mysql" (installer does not add MySQL to PATH; XAMPP's client is MariaDB and cannot use caching_sha2_password) | auto-detects the MySQL 8.x client in Program Files |
| 83 | `open_debts` ran a correlated subquery over `v_pairwise_debts` for every group row (the view is re-aggregated each time) | one derived table, joined once |
| 84 | Shrinking a photo for Groq decoded it at full size: a 100-megapixel image (or a crafted header) could exhaust PHP memory | size read from the header first, > 40 MP refused with a message (unit test) |
| 85 | Transparent PNG shrunk to JPEG turned black; editing a picked row kept the select pointing at the old bill line | white background; select cleared when the row no longer matches |
| 86 | I1–I9 (composite FKs, CHECKs, UNIQUE line, cascade, allocation view, UPI CHECK), A10 (app user may write items) |

| 86 | Owner report: real Groq scan on XAMPP → only "Couldn't read the bill right now", no way to see why | CA list auto-detected on Windows (verification kept on), 400 → retry without JSON mode, exact reason in the log and (local debug) on screen, `tools/scan_check.php` self-test; unit 101 |

Bug round 3 checks: migrations v1.0 → part 1 → part 2 give exactly the fresh schema; malformed input on all new endpoints (arrays, huge values, bad tokens, bad ids) → no 500s; login/forgot/reset pages fit 390–1280 px. Totals: unit 90, DB 89, functional 173, scenarios 23, deployment 34, Exp 10/11/12 18/11/31, axe 0.

Not tested (needs the owner): real deployment, real SMTP delivery, real scan API on real bills (`docs/OCR_PROVIDER_EVALUATION.md` plan), real UPI apps (`docs/UPI_DEVICE_TESTS.md`).
