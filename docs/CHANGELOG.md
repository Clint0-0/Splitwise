# CHANGELOG — SplitWise+

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
