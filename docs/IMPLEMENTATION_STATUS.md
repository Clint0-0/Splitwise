# IMPLEMENTATION_STATUS.md — SplitWise+

**Current phase:** 5.5 — UI refinement + deployment preparation · **State:** code and local validation done on branch `feature/ui-refinement-vercel-deployment`; **real deployment blocked** (needs Vercel + MySQL provider accounts) — Phase 5.5 is **not fully complete**. Phase 6 not started.
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
| Bug-fix round (former-member lock, leave race, throttle, mock mail, upload limits, archived settling) | see CHANGELOG 0.3.1 | ✓ K1–K6, P5b, T1–T8 |
| Tests: unit 41/41, functional 103/103, DB 74/74 | `tests/` | ✓ |

## Phase 4 — Frontend (done, branch `feature/frontend`)
| Deliverable | Where | Verified |
|---|---|---|
| Design system "Hisaab": rupee-note palette, Baloo 2 + Mukta (local), tokens, components | `docs/UI_DESIGN_SYSTEM.md` (was DESIGN_SYSTEM.md), `public/assets/css/app.css`, `public/assets/vendor/fonts/` | ✓ |
| New shell: left rail (desktop), top bar + drawer (mobile), skip link, favicon | `app/views/layouts/`, `app/views/partials/nav.php` | ✓ |
| All 16 screens restyled; balances as sentences + IOU slips; expense lists as rows | `app/views/**` | ✓ screenshots |
| Charts rebuilt with the dataviz method (single-hue bars, validated 2-colour pair, legends, "Show as table") | `public/assets/js/analytics.js` | ✓ palette validator |
| Accessibility: axe-core WCAG 2.1 AA + best practice on 18 screens × 2 viewports | `tools/a11y_scan.py` | ✓ 0 violations |
| Screenshots of every screen (desktop + mobile) | `docs/screenshots/`, `tools/screenshots.py` | ✓ |
| Tests still green: unit 41/41, functional 103/103, DB 74/74 | `tests/` | ✓ |

## Phase 5 — Testing (done, branch `feature/testing`)
| Deliverable | Where | Verified |
|---|---|---|
| Money scenario suite (23 checks, brief's examples + history changes) | `tests/functional/run_scenarios.php`, `docs/TEST_SCENARIOS.md` | ✓ 23/23 |
| One cross-platform runner for all suites | `tests/run_all.php` | ✓ all suites pass over TCP |
| Team laptop checklist (install, tests, 2-browser click-through) | `docs/TEAM_TEST_CHECKLIST.md` | ☐ **each member to run** |
| Login limit per IP raised for shared Wi-Fi | `LoginThrottle` | ✓ |
| Final regression | `docs/TESTING_REPORT.md` | ✓ 252 tests + 11 scripts, 0 a11y violations |

## Phase 5.5 — UI refinement + Vercel deployment preparation (branch `feature/ui-refinement-vercel-deployment`)
| Deliverable | Where | Status |
|---|---|---|
| UI audit at 1440/1366/1024/768/390; hero → summary band, slip stack → ledger, lists → data tables, laptop breakpoint fix | `app/views/**`, `app.css`, `docs/UI_DESIGN_SYSTEM.md`, `docs/PHASE_5_5_AUDIT.md` | ✓ screenshots, axe 0 violations |
| Sessions, throttle counters, mail cursor, receipts moved from disk to MySQL (`app_sessions`, `app_kv`, `receipts.content`) | `database/schema/04_infrastructure.sql`, `DbSessionHandler`, `LoginThrottle`, `MockMailer`, `UploadService` | ✓ tests |
| Env config (DB_PASSWORD, TLS, timeout, APP_ENV, APP_TRUST_PROXY, SESSION_*) | `Database.php`, `bootstrap.php`, `.env.example` | ✓ tests |
| `/healthz`, `/readyz`, CSP, HSTS, X-Powered-By removed | `Health.php`, `public/index.php` | ✓ tests |
| Dockerfile (FrankenPHP), Caddyfile, php.ini, .dockerignore | repo root, `deploy/` | ✓ static checks · ☐ **build blocked** (Docker Hub) |
| Hosted-DB import builder (any DB name, demo passwords replaced, TLS-only app user) | `database/build_hosted.php` | ✓ imported into a separate local DB |
| Deployment tests (29) | `tests/deployment/` | ✓ 29/29 local |
| Managed MySQL comparison + recommendation, migration plan, env table, security review | `docs/DEPLOYMENT.md` | ✓ written · provider facts marked (verify) |
| Managed MySQL created + data migrated | — | ☐ **blocked — needs provider account** |
| Vercel preview + production, 20 workflows on deployed URL | — | ☐ **blocked / not tested** |

## Notes for Phase 5 (testing) — kept for reference
- Phase 3/4 suites are automated; Phase 5 adds the financial scenario walk-throughs, manual cross-browser check on a team laptop (Windows + XAMPP + MySQL 8.0), and the final regression run.

## Notes for Phase 4 (frontend) — kept for reference
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
| 4 Frontend | visual design of the 16 working screens, responsiveness, accessibility | M2 | ✅ done (review pending) |
| 5 Testing | financial scenarios, security, regression | all | ✅ done; laptop checklist pending |
| 5.5 UI + deploy prep | UI refinement, Docker/FrankenPHP, MySQL-backed state, deployment docs/tests | M2 (UI), M3 (deploy), M1 (hosted DB) | 🟡 local done; real deployment blocked |
| 6 Docs & viva | report, setup guide, slides, viva Q&A | M3 coordinates | — |

## Team allocation
| Member | Owns | Also |
|---|---|---|
| **M1 — Database & SQL** | everything under `database/`, `docs/DATABASE_SCHEMA.md`, `SQL_EXPERIMENT_MAPPING.md`, DB tests | reviews every SQL statement in models |
| **M2 — Frontend** | layout, design system, all screens, split configurator JS, charts, responsiveness | client-side validation; screenshots |
| **M3 — Backend & integration** | core, middleware, controllers, services (SplitCalculator, BalanceService, DebtSimplifier, SettlementService…), uploads, PDO transactions | docs coordination, README, setup guide |

## Next tasks
1. Review Phase 5.5; on a machine with Docker Hub access run `docker build -t splitwise-plus .` and the container checks in `docs/DEPLOYMENT.md` §2.
2. Create the managed MySQL 8.0 server and Vercel project (owner enters credentials in the consoles only), follow `docs/DEPLOYMENT.md` §4 + §9, verify the 20 workflows on the preview URL.
3. **Every member:** re-import (`database/splitwise_plus.sql` now has 16 tables) and run `docs/TEAM_TEST_CHECKLIST.md`.
4. Phase 6 only after approval.
