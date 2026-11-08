# IMPLEMENTATION_STATUS.md — SplitWise+

**Current phase:** 2 — Database implementation · **State:** complete, awaiting review before Phase 3
**Last updated:** 2026-11-08

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

## Known issues / notes for Phase 3
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
| 3 Backend | core, auth, groups, expenses+splits, balances, settlements, budgets, notifications, admin, JSON endpoints | M3 (M1 reviews SQL) | next |
| 4 Frontend | layout + 16 screens, split UI with live preview, charts | M2 | — |
| 5 Testing | financial scenarios, security, regression | all | — |
| 6 Docs & viva | report, setup guide, slides, viva Q&A | M3 coordinates | — |

## Team allocation
| Member | Owns | Also |
|---|---|---|
| **M1 — Database & SQL** | everything under `database/`, `docs/DATABASE_SCHEMA.md`, `SQL_EXPERIMENT_MAPPING.md`, DB tests | reviews every SQL statement in models |
| **M2 — Frontend** | layout, design system, all screens, split configurator JS, charts, responsiveness | client-side validation; screenshots |
| **M3 — Backend & integration** | core, middleware, controllers, services (SplitCalculator, BalanceService, DebtSimplifier, SettlementService…), uploads, PDO transactions | docs coordination, README, setup guide |

## Next tasks (Phase 3)
1. `app/core` (Router, Database/PDO with utf8mb4 + `@app_user_id`, Env, Request/Response), `public/index.php`.
2. Auth (register/login/logout, CSRF, session hardening) + profile.
3. `SplitCalculator` (5 methods, integer paise, largest remainder — must match `sp_add_equal_expense`) with unit tests.
4. Groups/invitations, expenses (PDO transaction + `fn_expense_split_gap` check), balances (views), settlements (`sp_record_settlement`), debt simplifier, budgets, notifications, admin.
