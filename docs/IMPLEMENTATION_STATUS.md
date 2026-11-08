# IMPLEMENTATION_STATUS.md — SplitWise+

**Current phase:** 3 — Backend · **State:** complete on branch `feature/backend`, awaiting review before Phase 4
**Last updated:** 2026-11-09

## Repository
`https://github.com/Clint0-0/Splitwise` was empty on 2026-11-08. Phase 1 + 2 work is committed locally on branch `develop` (see CHANGELOG). Pushing needs write access (see the Phase 2 review message).

## Phase 1 — Design (approved 2026-11-08)
All decisions approved as proposed: MySQL 8.0 alongside XAMPP (A1), adjustment split as "custom" (A2), 14 tables (A10), plain-PHP test runner (A12), settlement flow (A6) and other rules A3–A11.

## Phase 2 — Database (done)
| Deliverable | File(s) | Verified |
|---|---|---|
| Database + 14 tables, PK/FK/UNIQUE/CHECK/DEFAULT | `database/schema/01_*.sql`, `02_tables.sql` | ✓ |
| 15 secondary indexes + EXPLAIN evidence | `03_indexes.sql`, `queries/07_explain.sql` | ✓ |
| 3 functions | `functions/functions.sql` | ✓ |
| 11 views + Exp 9 demo | `views/views.sql`, `views/demo_views.sql` | ✓ |
| 11 triggers (all 6 timing/event pairs) | `triggers/triggers.sql` | ✓ |
| 5 procedures (transactions, locking, idempotency) | `procedures/procedures.sql` | ✓ |
| Sample data (9 users, 5 groups, 23 expenses, all 5 split methods, 10 settlements in 4 states, 6 budgets) | `seeders/sample_data.sql` | ✓ balances hand-verified |
| Query collection Q1–Q50 | `queries/01–06` | ✓ all execute |
| ACID / transaction demo | `transactions/demo_acid.sql` | ✓ |
| Combined import file | `database/splitwise_plus.sql` via `build.sh` | ✓ |
| DB test suite: 74/74 pass | `tests/database/` | ✓ |
| Experiment 8 trigger operations demo | `database/triggers/demo_triggers.sql` | ✓ |
| Least-privilege app account | `database/security/app_user.sql` | ✓ tests A1–A7 |
| ER diagrams as images (conceptual Chen + generated physical) | `docs/diagrams/`, `tools/generate_er.py` | ✓ |
| Per-laptop install check | `database/verify_install.sql` | ✓ all PASS here |
| Testing report | `docs/TESTING_REPORT.md` | ✓ |

Phase 2 acceptance criteria: tables create ✓ · FKs work ✓ · constraints reject invalid records ✓ · sample data inserts ✓ · queries execute ✓ · triggers behave ✓ · views correct ✓ · transactions preserve consistency ✓ · financial calculations validated ✓.

## Phase 3 — Backend (done, branch `feature/backend`)
| Deliverable | Where | Verified |
|---|---|---|
| Core: front controller, router (digits-only params, CSRF on every POST), session hardening, PDO (utf8mb4, real prepares, `@app_user_id`, app time zone), views, flash/old input | `public/index.php`, `app/core/` | ✓ |
| Auth: register, login (generic errors, 5-try lockout, rehash), logout, profile, password change | `AuthService`, `AuthController`, `ProfileController` | ✓ H1–H10, P1–P4 |
| Groups: create/edit/archive, invite by username/email, accept/decline/cancel, remove, roles, leave (zero balance, ≥1 admin) | `GroupService` | ✓ G1–G6, L1–L4, P5–P6 |
| Expenses: 5 split methods in integer paise, one PDO transaction + split-gap check, tags, receipts, soft delete/restore, optimistic-lock edits, search/filter/pagination, history | `SplitCalculator`, `ExpenseService`, `UploadService` | ✓ E1–E25, R1–R5, unit tests |
| Balances: group, overall, pairwise (original/settled/remaining) from views; debt simplification | `BalanceService`, `DebtSimplifier` | ✓ S8–S9, unit tests |
| Settlements via `sp_record_settlement` / `sp_respond_settlement` (idempotency token, payer→pending, payee confirms) | `SettlementService` | ✓ S1–S7 |
| Budgets (personal share / group total, auto period end, one warning per period), notifications | `BudgetService`, `NotificationController` | ✓ B1–B5, N1 |
| Analytics JSON for Chart.js (real data) + page | `AnalyticsController`, `public/assets/js/analytics.js` | ✓ A1–A3 |
| Admin: stats, users (deactivate/reactivate), groups, audit-log viewer | `AdminController` | ✓ D1–D5 |
| Security: XSS escaping, SQLi-safe queries, CSRF, IDOR checks, upload MIME sniffing, secrets blocked under XAMPP | throughout, `.htaccess` | ✓ H3–H4, H7, Z1–Z5, E13–E14, R3–R5, Apache probe |
| Minimal functional screens (all 16) with live split preview | `app/views/`, `public/assets/js/expense-form.js` | ✓ (Phase 4 restyles) |
| Tests: unit 41/41, functional 88/88, DB 74/74 | `tests/` | ✓ |

## Notes for Phase 4 (frontend)
- Screens already work end-to-end with plain Bootstrap 5.3.8 (stored locally in `public/assets/vendor/`). Phase 4 = visual design, layout polish, responsiveness review, accessibility pass — not new wiring.
- Keep form field names, routes and the CSRF hidden field unchanged (functional tests depend on them; re-run `tests/functional/run.sh` after UI changes).
- `expense-form.js` mirrors `SplitCalculator`; if one changes, change both.

## Known issues / notes (from Phase 2)
- PHP must reject money with more than 2 decimals (MySQL rounds silently).
- PHP must `SET NAMES utf8mb4` (PDO DSN `charset=utf8mb4`) and `SET @app_user_id = <user>` per request so audit rows record the actor.
- App connects as `splitwise_app` (never root). Settlement writes must use the procedures (direct INSERT is denied).
- **Team action:** each member imports on their own Windows/XAMPP + MySQL 8.0 laptop and runs `database/verify_install.sql` — every row must say PASS.
- LICENSE not added (team to choose).

## Roadmap
| Phase | Main output | Lead | State |
|---|---|---|---|
| 1 Design | docs/ | all | ✅ approved |
| 2 Database | schema, seed, objects, queries, tests | M1 | ✅ done |
| 3 Backend | core, auth, groups, expenses+splits, balances, settlements, budgets, notifications, admin, JSON endpoints | M3 (M1 reviews SQL) | ✅ done (review pending) |
| 4 Frontend | visual design of the 16 working screens, responsiveness, accessibility | M2 | next |
| 5 Testing | financial scenarios, security, regression | all | — |
| 6 Docs & viva | report, setup guide, slides, viva Q&A | M3 coordinates | — |

## Team allocation
| Member | Owns | Also |
|---|---|---|
| **M1 — Database & SQL** | everything under `database/`, `docs/DATABASE_SCHEMA.md`, `SQL_EXPERIMENT_MAPPING.md`, DB tests | reviews every SQL statement in models |
| **M2 — Frontend** | layout, design system, all screens, split configurator JS, charts, responsiveness | client-side validation; screenshots |
| **M3 — Backend & integration** | core, middleware, controllers, services (SplitCalculator, BalanceService, DebtSimplifier, SettlementService…), uploads, PDO transactions | docs coordination, README, setup guide |

## Next tasks (Phase 4)
1. Design system: colours, typography, spacing, components (cards, tables, badges, empty states).
2. Restyle layout + all 16 screens; mobile layouts for expense form and group page.
3. Accessibility pass (labels, contrast, keyboard, focus states).
4. Re-run all three test suites.
