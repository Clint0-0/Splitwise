# ARCHITECTURE_DECISIONS.md — SplitWise+

Format: ADR-n · Decision · Reason · Rejected alternatives. All ADRs approved 2026-11-08.

## Application architecture

```text
Browser (Bootstrap 5 + JS + Chart.js)
   │  HTML pages (server-rendered)   │  JSON (charts, live split preview)
   ▼                                 ▼
public/index.php  ── front controller ── Router
   │
   ├─ Middleware: Session → Auth → CSRF → GroupMember / Admin guard
   ▼
Controllers (thin: read request, call service, render view / JSON)
   ▼
Services (business rules)
   SplitCalculator · BalanceService · DebtSimplifier · SettlementService
   BudgetService · NotificationService · UploadService
   ▼
Models / repositories (PDO, prepared statements only)
   ▼
MySQL 8.0  (views, triggers, procedures, functions do integrity + reporting)
```

| ADR | Decision | Reason | Rejected |
|---|---|---|---|
| 1 | MVC-inspired PHP, own tiny router + `spl_autoload_register` | Spec forbids frameworks; easy to explain at viva | Laravel (unapproved), page-per-file PHP (no separation) |
| 2 | Services layer between controllers and models | Split/balance logic must be unit-testable and reused by pages and JSON endpoints | logic in controllers |
| 3 | Server-rendered pages + small JSON endpoints for charts/previews | Simple, works without SPA framework | React/Vue (unapproved) |
| 4 | Balances derived, never stored | Rule 11; one source of truth; Σ=0 invariant holds by construction | `balances` table updated by triggers (drift risk, harder to audit) |
| 5 | Integer-paise arithmetic in PHP, DECIMAL(12,2) in DB | No float error; deterministic rounding | PHP floats; BCMath (extra extension, unnecessary) |
| 6 | Expense write path = PHP PDO transaction; settlement write path = `sp_record_settlement` | Split math lives in one testable place (PHP); settlement check needs row locks close to data | all logic in procedures (JSON parsing, hard to test) / all in PHP (weaker DB-programming demo) |
| 7 | Soft delete expenses; immutable confirmed settlements; audit triggers | Historical accuracy, explainable history | hard delete + cascade |
| 8 | `@app_user_id` session variable set by PDO per connection, read by triggers | Audit rows know who acted without passing user id to every statement | no actor in audit |
| 9 | Settlement scope = one group | Balances are per group; avoids cross-group netting ambiguity | global user-to-user ledger |
| 10 | Greedy debt simplification in PHP, suggestions not stored | Simple, correct (Σ preserved), ≤ n−1 transfers | storing suggestions (stale immediately) |
| 11 | Pending settlement count toward outstanding limit | Blocks duplicate requests | ignore pending |
| 12 | Uploads in `storage/receipts/` (outside `public/`), served through an authorized controller | Prevents direct access and script execution | uploads under `public/` |
| 13 | Config via `.env` read by small loader; `.env.example` committed | No secrets in Git | hard-coded `config/database.php` credentials |
| 14 | Frontend vendor libs stored locally | Viva may have no internet | CDN only |
| 15 | MySQL 8.0 (not XAMPP MariaDB) | Spec + lab syntax are MySQL; CHECK/JSON/trigger behaviour differs | MariaDB-compatible subset |
| 16 | Plain-PHP test runner + SQL test scripts | No dependency manager needed | PHPUnit via Composer |
| 18 | Root `.htaccess` + `Require all denied` in non-public folders | XAMPP users often drop the repo into `htdocs`; without it `.env`, source and receipts are downloadable (verified on Apache) | relying on everyone configuring a VirtualHost |
| 19 | Real environment variables override `.env` | test runners start the app as `splitwise_app` without editing files | single source |
| 20 | Group membership/role checks in services, not route middleware | the rule needs the group id and is reused by several controllers | per-route middleware with parameter parsing |
| 21 | Expenses involving a former member are locked (no edit/delete/restore) | members leave only at a zero balance; changing those expenses later would move money they can no longer see or settle | re-opening membership automatically; allowing edits that silently drop their share |
| 22 | Leave/remove take `FOR UPDATE`, expense writes take `FOR SHARE` on the group's membership rows | a member cannot leave between a balance check and a new/edited expense (same lock as `sp_record_settlement`) | no locking (race), table locks (too coarse) |
| 23 | Login throttling in `storage/throttle` files: 5 fails per account+IP, 20 per IP, 15 min | a session-based counter is bypassed by dropping the cookie; account+IP key avoids locking real users out from elsewhere | new DB table (changes the approved 14-table schema) |
| 24 | Mock e-mail: after each POST, new notifications (PHP- or trigger-made) are appended to `storage/logs/mail.log` for opted-in users; cursor file prevents duplicates | spec allows mock e-mail, no paid services; catches trigger notifications too | hooking every notification call site (misses triggers) |
| 25 | Archived groups: expenses/invites/edits blocked, **settlements still allowed** | archiving must not trap money that is still owed | fully read-only |
| 17 | `expense_groups` table name | `GROUPS` reserved in MySQL 8 | backtick-quoting `groups` everywhere |

## Security decisions
- **Least-privilege DB account** `splitwise_app` (`database/security/app_user.sql`, run once as root): SELECT + EXECUTE on the database; INSERT/UPDATE/DELETE only on app-owned tables; **settlements, audit_logs, categories are read-only**, so settlements can only be written through `sp_record_settlement`/`sp_respond_settlement` and audit rows only by triggers (both run with DEFINER rights). No DDL, no GRANT. Verified by tests A1–A7.
- PDO: `ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`, `utf8mb4`.
- Every group-scoped route checks active membership in SQL (`group_members`), not in the UI.
- CSRF token per session, checked on every POST.
- `htmlspecialchars()` helper `e()` for all output.
- Generic error page in production mode; details logged to `storage/logs/`.
- Login: `session_regenerate_id(true)`, rate-limit by simple failed-attempt counter in session (no extra table).

## Proposed directory structure
```text
Splitwise/
├── app/
│   ├── controllers/   AuthController, DashboardController, GroupController, ExpenseController,
│   │                  SettlementController, BalanceController, BudgetController,
│   │                  AnalyticsController, NotificationController, ProfileController, AdminController
│   ├── services/      SplitCalculator, BalanceService, DebtSimplifier, SettlementService,
│   │                  BudgetService, NotificationService, UploadService
│   ├── models/        User, Group, GroupMember, Invitation, Expense, ExpenseSplit, Settlement,
│   │                  Budget, Category, Notification, Receipt, Tag, AuditLog
│   ├── middleware/    Auth, Csrf, GroupMember, Admin
│   ├── helpers/       money.php (paise ↔ string), view.php (e(), render), validation.php
│   ├── core/          Router, Database (PDO singleton), Request, Response, Env
│   └── views/         layouts/, partials/, auth/, dashboard/, groups/, expenses/, ...
├── config/            app.php, database.php (read from .env)
├── public/            index.php, .htaccess, assets/{css,js,images,vendor}
├── storage/           receipts/, logs/   (git-ignored contents)
├── database/
│   ├── schema/        01_create_database.sql, 02_tables.sql, 03_indexes.sql
│   ├── seeders/       sample_data.sql
│   ├── views/  triggers/  procedures/  functions/  transactions/  queries/
│   └── splitwise_plus.sql   (single combined import file, generated)
├── docs/              (this folder) + diagrams/
├── tests/             database/ (SQL assertions), unit/ (SplitCalculator, DebtSimplifier), functional/ (checklists)
├── .env.example  .gitignore  README.md  LICENSE
```
**As built (Phase 3):** the tree above is implemented, plus `app/views/partials`, `public/router.php` (PHP dev server), `public/assets/{css,js,vendor}`, `tests/unit`, `tests/functional`.

Change from the master prompt's suggested tree: added `app/services/`, `app/core/`, `storage/`, `tests/unit/`; dropped `database/migrations/` (ordered schema scripts suffice).

## Phase 5.5 decisions (deployment preparation)
| # | Decision | Reason | Rejected |
|---|---|---|---|
| 26 | Run on Vercel as a FrankenPHP container (`Dockerfile` + `Caddyfile`) | official-style PHP container path; keeps plain PHP + PDO | community `vercel-php` runtime; rewriting in Node |
| 27 | All shared runtime state in MySQL: sessions (`app_sessions`), throttle + mail cursor (`app_kv`), receipts (`receipts.content`) — **supersedes #12 and #23** | containers share no disk; MySQL already mandatory | Redis/KV, object storage (extra service + credentials) |
| 28 | Client IP = right-most `X-Forwarded-For` entry, only with `APP_TRUST_PROXY=true` | left entries are client-controlled | first entry (spoofable) |
| 29 | `/healthz` without DB, `/readyz` with `SELECT 1`, fixed JSON bodies | liveness vs readiness; no information leak | one endpoint printing DB errors |
| 30 | Hosted import generated by `build_hosted.php` (no fixed DB name, demo passwords replaced, `REQUIRE SSL` user) | managed DBs pre-create the database; public URL must not use a published password | importing `splitwise_plus.sql` as-is |
