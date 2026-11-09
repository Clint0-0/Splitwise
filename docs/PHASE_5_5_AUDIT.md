# PHASE_5_5_AUDIT.md — project audit before UI refinement and deployment

Date: 2026-11-09 · Baseline: `main` @ 4b6405f · Branch: `feature/ui-refinement-vercel-deployment`

## 1. Baseline (before any change)
Unit 41/41, database 74/74, functional 103/103, scenarios 23/23; clean working tree.

## 2. Deployment blockers found
| # | Finding | Severity on Vercel | Resolution |
|---|---|---|---|
| D1 | PHP file sessions (`session.save_path`) | users logged out between containers | `app_sessions` + `DbSessionHandler` |
| D2 | Login throttle in `storage/throttle/*` | brute-force limit resets per container | `app_kv`, atomic upsert |
| D3 | Mock-mail cursor `storage/logs/mail.cursor` | duplicate mock e-mails per container | `app_kv` + `GET_LOCK` |
| D4 | Receipts in `storage/receipts/` | uploads lost when a container stops | `receipts.content` LONGBLOB |
| D5 | Error log file `storage/logs/php-error.log` | unreadable / lost | stderr in production |
| D6 | DB password variable `DB_PASS`, no TLS / timeout options | managed MySQL needs TLS; slow DB could hang requests | `DB_PASSWORD`, `DB_SSL*`, `DB_CONNECT_TIMEOUT` |
| D7 | `Secure` cookie only from `$_SERVER['HTTPS']`; IP from `REMOTE_ADDR` | behind the edge both are wrong (no Secure flag; every visitor = proxy IP → shared lockouts) | `is_https()` / `client_ip()` with `APP_TRUST_PROXY` |
| D8 | `Session::start()` outside the error handler | DB outage → raw fatal error | moved inside `try` |
| D9 | No health/readiness endpoint | platform/monitor cannot tell "up" from "DB reachable" | `/healthz`, `/readyz` |
| D10 | No CSP; one inline script | weaker XSS defence | strict CSP, script moved to a file |
| D11 | `splitwise_plus.sql` drops/creates a fixed DB name and ships the published demo password | managed DBs give you a pre-made DB; a public URL must not accept `Password@123` | `database/build_hosted.php` |

## 3. UI audit (screenshots at 1440 / 1366 / 1024 / 768 / 390)
| # | Finding | Resolution |
|---|---|---|
| U1 | Greeting hero with one large number (landing-page pattern) on dashboard, group, balances | summary band with 4–5 working figures; monthly spending added |
| U2 | IOU slips as a stack of identical floating cards | one ruled ledger |
| U3 | Long expense and settlement lists as card rows on wide screens | data tables (money right-aligned, tabular figures); labelled rows < 768 px |
| U4 | Two columns at 1024 px squeezed slips and figures | two columns ≥ 1200 px only |
| U5 | Page titles 2.5 rem | `clamp(1.6rem, …, 2rem)` |
| U6 | Members table clipped balance at half width | balance column + paid/share line |
| U7 | Dashboard group list without inner padding | inset rows |
Kept (already distinctive and accessible): rupee-note palette, Baloo 2 + Mukta, sentence-style balances, marigold = settle-up rule, chart tables. axe-core: 0 violations before and after.

## 4. Security review summary
No existing control was weakened. Added: CSP, HSTS (production), Permissions-Policy, removed `X-Powered-By`, strict DB sessions with server-side expiry, proxy-aware Secure cookie and client IP (spoof-resistant), TLS-only hosted DB user, production debug lock, `zend.exception_ignore_args`. The app user's privileges grow only by INSERT/UPDATE/DELETE on `app_sessions` and `app_kv`. Full table: `DEPLOYMENT.md` §8.

## 5. What could not be done here
Docker image build, FrankenPHP run, Vercel docs check, managed MySQL, Vercel deployment and deployed-URL workflows — reasons in `DEPLOYMENT.md` §10. These are reported as blocked or not tested, **not** as passed.
