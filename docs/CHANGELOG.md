# CHANGELOG — SplitWise+

## [0.6.0] — Phase 5.5 UI refinement + deployment preparation (2026-11-09)
### Changed
- Dashboard, group and balances pages: greeting hero replaced by a summary band (net balance in words, owed, owing, this month's spending); IOU slips are one ruled ledger; expenses and settlements are data tables that become labelled rows on phones; two-column layout only from 1200 px; smaller page titles; group members table no longer clips.
- Sessions stored in MySQL (`app_sessions`) instead of files; login-throttle counters and mock-mail cursor in `app_kv`; receipt bytes in `receipts.content`. Combined import now has **16 tables**.
- `DB_PASSWORD` (old `DB_PASS` still read); production mode forces debug off and logs to stderr.
- `docs/DESIGN_SYSTEM.md` renamed to `docs/UI_DESIGN_SYSTEM.md`.
### Added
- `Dockerfile` (FrankenPHP, PHP 8.3), `Caddyfile`, `deploy/php.ini`, `.dockerignore`; `database/build_hosted.php`.
- `/healthz`, `/readyz`; Content-Security-Policy, HSTS (production), Permissions-Policy; TLS options and connect timeout for MySQL; `APP_TRUST_PROXY` (right-most X-Forwarded-For).
- Tests: functional P1–P12, DB A8–A9, `tests/deployment/` (28 checks).
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
