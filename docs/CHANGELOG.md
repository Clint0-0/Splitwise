# CHANGELOG — SplitWise+

## [0.9.2] — Groq scanning, pick from bill, "evens out" (2026-11-25)
### Added
- `SCAN_PROVIDER=groq`: Groq's OpenAI-compatible chat completions with a vision model (default `qwen/qwen3.8-27b`, `SCAN_MODEL` to change), JSON mode, `<think>` blocks stripped, photos over ~2.9 MB shrunk to JPEG (GD) to stay under Groq's 4 MB base64 limit, rate-limit (429) message.
- **Pick from bill**: after a scan every item row has a list of the lines read from the photo; choosing one fills name, qty and line total (and the "hard to read" flag); lines already used are marked. The lines are carried in hidden fields, so they survive a validation error; they are only display data — the item fields are validated on save as before.
- Scan reads the attached **receipt** photo when no separate bill photo was chosen.
- `tests/run_all.php` finds `C:\Program Files\MySQL\MySQL Server 8.x\bin\mysql.exe` by itself on Windows.
### Fixed
- Balances / dashboard / groups showed **"settled up"** for a group where your net is ₹0 but you still owe one member and are owed by another (Person by person listed the debt). Now: "evens out · N open debts".
- "Add another item" copied the "hard to read" warning from the row it cloned.
- Bug round: open-debt count computed in one pass (was one view scan per group row); a huge-resolution photo is refused before GD decodes it (memory); transparent PNGs get a white background when shrunk for Groq; editing a picked row's name/amount clears its "Pick from bill" choice so "(used)" stays true.
- Tests: unit 97, functional 179; axe 0 on the scanned form and balances (1366 / 390 px).

## [0.9.1] — bug fixes (2026-11-24)
### Fixed
- `tests/run_all.php --build` left out the categories (reference data); the file list now comes from `database/build.sh`.
- Password change ends your other sessions. Clear message for a negative discount. Receiver wording on the settle page. No crash without the curl extension.

## [0.9.0] — Phase 6 brief (revised strategy) implemented (2026-11-24)
### Added
- Item-wise bills: **units per person per item** (quantity / unequal shares; typing units ticks the person) and **charge lines** (tax / GST, service charge, discount, other/round-off) that must reconcile exactly with the total; `expense_charges`, `expense_item_shares.share_units`.
- UPI: amount pre-filled from the **server-computed outstanding** amount (same rule as `sp_record_settlement`), full or part payment, **Copy UPI ID** fallback, "I've paid: mark as sent" (pending) → receiver "Received" / "Not received"; group page "Pay" button.
- Scanning: bill **date** and **charges** extracted, per-line **uncertain** flags, sum-vs-total and date **warnings**, required **"I checked it" tick** before saving a scanned bill, **site-wide monthly cap** and admin usage/cost panel.
- Production: **password reset** (hashed single-use tokens, no account probing, throttled, `APP_URL` links, logs out everywhere), **SMTP** mailer, **error monitoring** (JSON log line + reference id, optional Sentry), **sample-free hosted build** + `tools/create_admin.php`, **backup** and **verified restore-test** scripts.
- Docs: `HOSTING_COMPARISON.md`, `OCR_PROVIDER_EVALUATION.md`, `UPI_DEVICE_TESTS.md`.
- End-to-end workflow test E2E1–E2E5 (scan → review → assign → tax → expense → balances → UPI → confirm).
### Changed
- 20 tables (17 business + 3 runtime: `password_resets` added), categories moved to `seeders/reference_data.sql`; migration part 2 renamed `2026_11_phase6b_units_charges_resets.sql`.
- ADR 35 no longer claims anything about regulation.
- Tests: unit 90, database 89, functional 172, deployment 34.

## [0.8.1] — Phase 6 bug fixes (2026-11-24)
### Fixed
- Long UPI IDs (101–129 chars) caused a 500; now a field error. No UPI panel when paying yourself.
- Bill scan: no PHP timeout on Windows during slow API calls; phones can choose an existing photo; reminder to re-choose the receipt.
- Clear quantity-limit message. Tests: unit 76, functional 146.

## [0.8.0] — Phase 6: itemized bills, Pay with UPI, bill scanning (2026-11-24, branch `feature/phase-6`)
The DBMS submission version is tagged `v1.0-submission` (= `main` @ b92fb91).
### Added
- **Split by item** (`split_method = 'itemized'`): bill lines in `expense_items`, who had each in `expense_item_shares`.
  Shared lines split equally; tax / service charge / discount (total − Σ lines) spread by each person's item subtotal; largest remainder, payer first. Composite FKs: an item share must belong to an item of the same expense **and** to a participant (`expense_splits`). View `v_expense_item_allocation`. Live preview in the form (matches PHP on 3,000 random bills).
- **Pay with UPI**: `users.upi_id` (CHECK on the format), Profile field, `upi://pay` button + QR code on "Record a payment" when you pay someone with a UPI ID. No payment gateway; the receiver still confirms. QR library `qrcode-generator` 1.4.4 (MIT) vendored.
- **Bill scanning** behind `SCAN_PROVIDER` (off | mock | anthropic): "Read items" fills the item rows, total and shop name from a photo; never saves; photo not stored; per-user daily limit in `app_kv`; mock refused in production; API key only from the environment.
- Migration `database/migrations/2026_11_phase6_itemized_upi.sql` for existing databases (non-destructive; verified to give the same schema as a fresh import).
- Demo UPI IDs for aarav/priya on the unused handle `@example`.
### Changed
- 16 → 18 tables, 11 → 12 views; `verify_install.sql`, `app_user.sql`, `build_hosted.php` updated.
- Tests: unit 53 → 75, database 76 → 86, functional 120 → 142 (IT1–IT8, U1–U6, SN1–SN8); deployment check H1 counts 18 tables.
### Fixed (found while building)
- Pressing Enter in the expense form would have run "Read items" (first submit button); a hidden default "save" button now comes first.

## [0.7.4] — Full-project audit fixes (2026-11-19)
### Fixed
- Front-end libraries (Bootstrap, icons, Chart.js, fonts) are now in the repo — fresh clones were unstyled.
- "Owed to you" / "You owe" on Dashboard and Balances now match the "Who owes whom" list.
- Huge `?page=` values, `_csrf[]`, array-valued query/form fields and impossible dates no longer cause 500s or warnings.
- Settlement "back" redirect can't be pointed at another site (`//host`).
- Group members table readable on phones (balance + admin menu visible, no page overflow).
### Changed
- Functional platform checks renamed X1–X12 (were duplicate P-IDs); new X13–X17 (115 → 120).

## [0.7.3] — Exp 10 re-run fix (2026-11-18)
### Fixed
- `01_create_database.sql` also drops the fkdemo sandbox, so re-runs show the documented output; test E10-18 (17 → 18 checks).

## [0.7.2] — Experiments reconciled with the lab manuals (2026-11-18)
### Changed
- Exp 10: PROGRAM (`08`) uses the manual's exact statements (incl. the manual's 4-value insert after dropping `capacity`, shown as error 1136, then corrected); new `09_manual_fkdemo_examples.sql` runs the manual's theory examples (tasks/checklists, fkdemo RESTRICT/CASCADE/SET NULL, DROP FOREIGN KEY); verification/cleanup renumbered to `10`/`11`; `POST_LAB_QUESTIONS.md`; Workbench instructions. Test 15 → 17 checks.
- Exp 11 guide: one-document-at-a-time insert first (as in the manual). Exp 11/12 READMEs note the manual check.

## [0.7.1] — Experiments 10–12 bug fixes (2026-11-18)
### Fixed
- Exp 12 tests no longer touch the lab database/login (throw-away `splitwise_candidate_lab_test` + `candidate_test`).
- Exp 12 timestamps stored in Asia/Kolkata; array-valued form fields rejected; phone field fits `+91 …`; null-safe input cleaning.
- Exp 11 CRUD script uses `db.getSiblingDB()` and `var`, so it runs in any mongosh/Compass shell and can be pasted twice.
- Exp 10 README explains password prompts / `--login-path`.
### Added
- Exp 12 tests H18–H20 (28 → 31); Exp 11 dry run executes the script twice.

## [0.7.0] — DBMS Experiments 10–12 (2026-11-18)
### Added
- `experiments/10_mysql_ddl_fk/`: DDL and foreign-key sandbox (`splitwise_lab_exp10`), RESTRICT/NO ACTION/CASCADE/SET NULL/SET DEFAULT demos on cut-down SplitWise+ tables, warehouse/inventory practical, `run.sh` with confirmation.
- `experiments/11_mongodb_atlas_compass/`: Atlas + Compass guide, `CollegeDB.Students` sample documents, mongosh CRUD script, Compass filters, post-lab answers.
- `experiments/12_candidate_management/`: standalone Candidate Management System (own DB `splitwise_candidate_lab`, 3NF schema, ER diagram generated from the schema, PHP/PDO CRUD front end, real screenshots).
- Tests: `tests/experiments/test_exp10.php` (15), `tests/experiments/exp11_dry_run.js` (11, local dry run), `experiments/12_candidate_management/tests/run_tests.php` (28), runner `tests/experiments/run.sh`.
### Changed
- `docs/SQL_EXPERIMENT_MAPPING.md` renamed to `docs/DBMS_EXPERIMENT_MAPPING.md` and extended to all 12 experiments (Exp 1–9 content kept).
- Root `.htaccess`: under `experiments/` only a `public/` folder is served.
### Not changed
- SplitWise+ application code, production schema and `database/splitwise_plus.sql`.

## [0.6.1] — Phase 5.5 bug fixes (2026-11-09)
### Fixed
- `.env` reader strips trailing ` # comments`; `.env.example` comments on their own lines (a copied template had silently switched to file sessions).
- `DB_SSL=true` without a readable CA now refuses to connect instead of risking plain text.
- Caddy blocks dotfiles at any depth; `UPLOAD_MAX_BYTES` can no longer exceed the 5 MB DB rule; session write errors are logged, not printed.
- Settlements: rejected/cancelled amounts struck through without +/−; deleted expense totals struck through; table dates no longer wrap; profile mock-mail wording.
- `build_hosted.php --out`, generates `hosted_verify.sql`; deployment tests no longer touch `build/` and work with password/TCP root.
### Added
- Unit tests for Env parsing, proxy IP/HTTPS detection and DB TLS options (41 → 53); deployment check C6a (28 → 29).

## [0.6.0] — Phase 5.5 UI refinement + deployment preparation (2026-11-09)
### Changed
- Dashboard, group and balances pages: greeting hero replaced by a summary band (net balance in words, owed, owing, this month's spending); IOU slips are one ruled ledger; expenses and settlements are data tables that become labelled rows on phones; two-column layout only from 1200 px; smaller page titles; group members table no longer clips.
- Sessions stored in MySQL (`app_sessions`) instead of files; login-throttle counters and mock-mail cursor in `app_kv`; receipt bytes in `receipts.content`. Combined import now has **16 tables**.
- `DB_PASSWORD` (old `DB_PASS` still read); production mode forces debug off and logs to stderr.
- `docs/DESIGN_SYSTEM.md` renamed to `docs/UI_DESIGN_SYSTEM.md`.
### Added
- `Dockerfile` (FrankenPHP, PHP 8.3), `Caddyfile`, `deploy/php.ini`, `.dockerignore`; `database/build_hosted.php`.
- `/healthz`, `/readyz`; Content-Security-Policy, HSTS (production), Permissions-Policy; TLS options and connect timeout for MySQL; `APP_TRUST_PROXY` (right-most X-Forwarded-For).
- Tests: functional P1–P12 (renamed X1–X12 in 0.7.4), DB A8–A9, `tests/deployment/` (28 checks).
- `docs/DEPLOYMENT.md`, `docs/PHASE_5_5_AUDIT.md`.
### Removed
- `storage/receipts/` and `storage/throttle/` (no runtime data on local disk except the local mock-mail log).
### Not done (blocked)
- Container build, managed MySQL, Vercel deployment and deployed-URL workflow checks — see `docs/DEPLOYMENT.md` §10.

## [0.5.1] — Phase 5 bug fixes (2026-11-09)
### Fixed
- `tests/run_all.php`: waits until the app really answers (clear message if the port is busy); always stops the test server; stops if a re-import fails; passes the root password through a temporary option file instead of the command line.
- Tests use the app's time zone (`APP_TIMEZONE`, default Asia/Kolkata) for "today".
### Added
- `.gitattributes` keeping LF line endings on Windows.

## [0.5.0] — Phase 5 testing (2026-11-09)
### Added
- `tests/functional/run_scenarios.php` (23 checks) and `docs/TEST_SCENARIOS.md` — money stories with hand-worked amounts.
- `tests/run_all.php` — runs every suite on Windows or Linux, as the least-privilege app account.
- `docs/TEAM_TEST_CHECKLIST.md` — 15-minute install + test + two-browser click-through per laptop.
- `tests/functional/lib.php` shared HTTP test helpers.
### Changed
- Per-IP login limit 20 → 100 failures per 15 minutes (shared Wi-Fi).
- Functional tests can reach MySQL over TCP with a password (Windows).

## [0.4.0] — Phase 4 frontend (2026-11-09)
### Added
- "Hisaab" design system (`docs/DESIGN_SYSTEM.md`, now `UI_DESIGN_SYSTEM.md`): rupee-note palette (lavender ₹100, marigold ₹200), Baloo 2 + Mukta by Ek Type stored locally, CSS tokens and components.
- App shell with left rail on desktop and drawer on mobile; skip link; favicon.
- IOU slips for person-to-person debts; balances written as sentences; expense lists as rows that work on phones.
- Charts rebuilt per the dataviz method, each with a "Show as table" view.
- `tools/screenshots.py` and `docs/screenshots/` (18 screens × desktop/mobile).
### Fixed
- Navigation wrapped onto two lines on laptop widths; a missing icon in the nav.
- Accessibility: scrollable tables reachable by keyboard; definition list markup (axe-core: 0 violations).

## [0.3.1] — Phase 3 bug fixes (2026-11-09)
### Fixed
- Editing, deleting or restoring an old expense could change the balance of a member who had already left (settled) — such expenses are now locked; the edit form no longer silently drops former members.
- Race between "leave group" and a concurrent expense write — membership rows are now locked (FOR UPDATE / FOR SHARE).
- Login lockout lived in the session and was bypassed by dropping the cookie — now file-based per account+IP and per IP.
- "Email me" setting did nothing — mock mailer writes opted-in users' notifications to `storage/logs/mail.log`.
- Uploads over `post_max_size` showed "session expired" — now a clear "upload too large" message.
- Receipt limit message claimed 5 MB even when PHP only accepts 2 MB — the real effective limit is shown and enforced.
- Archived groups blocked paying off debts — settling is now allowed.
### Added
- 15 functional tests (K1–K6, P5b, T1–T8); suite now 103.

## [0.3.0] — Phase 3 backend (2026-11-09)
### Added
- MVC-inspired PHP app without a framework: `public/index.php`, `app/core` (Router, Request, Session, Auth, Database, View, Env), `app/models`, `app/services`, `app/controllers`, `app/views`, `config/routes.php`.
- All modules: auth/profile, groups/invitations/members, expenses (5 split methods, tags, receipts, soft delete, optimistic locking), balances + debt simplification, settlements (via stored procedures), budgets + warnings, notifications, analytics JSON + Chart.js page, admin.
- Bootstrap 5.3.8, Bootstrap Icons 1.13.2, Chart.js 4.6.1 stored locally (offline viva).
- Tests: `tests/unit` (41), `tests/functional` (88 HTTP end-to-end), runner scripts.
- `.htaccess` protection for XAMPP sub-folder installs.
### Fixed
- Registration duplicate checks skipped when other fields were invalid; duplicate-key race now a form error.

## [0.2.1] — Phase 2 gap fixes (2026-11-09)
### Added
- `database/triggers/demo_triggers.sql`: Experiment 8 operations (SHOW TRIGGERS, SHOW CREATE TRIGGER, all pairs firing, CREATE → DROP lifecycle).
- `database/security/app_user.sql`: least-privilege `splitwise_app` account; tests A1–A7.
- `database/verify_install.sql`: 10-check install verification for team laptops.
- `docs/diagrams/`: conceptual ER (Chen) and physical ER (generated from information_schema by `tools/generate_er.py`), PNG + SVG.
### Changed
- Test suite 67 → 74 tests; run.sh also runs verify_install, trigger demo, app-user script.

## [0.2.0] — Phase 2 database (2026-11-08)
### Added
- `database/schema`: database, 14 tables with PK/FK/UNIQUE/CHECK/DEFAULT, 15 secondary indexes.
- `database/functions`: `fn_member_net_balance`, `fn_expense_split_gap`, `fn_budget_spent`.
- `database/views`: 11 views incl. updatable `v_active_expenses` (CHECK OPTION), balances, view-on-view, pairwise debts, budget utilisation; `demo_views.sql`.
- `database/triggers`: 11 triggers covering BEFORE/AFTER × INSERT/UPDATE/DELETE.
- `database/procedures`: `sp_record_settlement`, `sp_respond_settlement`, `sp_add_equal_expense`, `sp_get_group_balances`, `sp_spending_summary`.
- `database/seeders/sample_data.sql`: realistic INR data with hand-verified balances.
- `database/queries`: Q1–Q50 + EXPLAIN; `database/transactions/demo_acid.sql`.
- `database/build.sh` → `database/splitwise_plus.sql` combined import.
- `tests/database`: 67 automated tests (all pass); `docs/TESTING_REPORT.md`.
- README, `.gitignore`, `.env.example`.
### Changed
- Docs updated to match implementation: 11 triggers (was 8 planned), real view updatability, DECIMAL rounding note, UTF-8 requirement.
### Fixed
- Double-encoded UTF-8 text when scripts were run with a latin1 client (`SET NAMES utf8mb4`).

## [0.1.0] — Phase 1 design (2026-11-08)
- Phase 1 design docs: PROJECT_SPECIFICATION, DATABASE_SCHEMA, ARCHITECTURE_DECISIONS, REQUIREMENTS_TRACEABILITY, SQL_EXPERIMENT_MAPPING, IMPLEMENTATION_STATUS. Approved.
