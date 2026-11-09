# CHANGELOG — SplitWise+

## [0.4.0] — Phase 4 frontend (2026-11-09)
### Added
- "Hisaab" design system (`docs/DESIGN_SYSTEM.md`): rupee-note palette (lavender ₹100, marigold ₹200), Baloo 2 + Mukta by Ek Type stored locally, CSS tokens and components.
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
