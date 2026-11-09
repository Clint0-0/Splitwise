# DEPLOYMENT.md — SplitWise+ on Vercel (FrankenPHP container) + managed MySQL 8.0

Phase 5.5. Status at the end of this phase: **local deployment configuration completed; nothing is deployed.**
No Vercel project, no hosted database and no public URL exist yet. See [§10 Validation status](#10-validation-status).

> Facts about Vercel and the database providers below come from general knowledge, **not** from documentation
> fetched during this phase (the fetch of Vercel's guide was not approved in the build session).
> Everything marked **(verify)** must be checked against the current official page before you rely on it.

---

## 1. Architecture

```
 browser ──HTTPS──▶ Vercel edge (TLS, X-Forwarded-For/Proto)
                        │ plain HTTP, $PORT
                        ▼
              container: FrankenPHP (Caddy + PHP 8.3)
              web root /app/public  →  public/index.php (front controller)
                        │ PDO, TLS (DB_SSL=true), 5 s connect timeout
                        ▼
              managed MySQL 8.0  (database + least-privilege user, REQUIRE SSL)
              ├── 14 business tables, views, triggers, procedures (unchanged)
              ├── app_sessions  — PHP sessions shared by every container
              ├── app_kv        — login-throttle counters, mock-mail cursor
              └── receipts.content — receipt files (LONGBLOB)
```

**Why everything shared lives in MySQL:** containers are started and stopped on demand and do not share a disk.
The Phase 5 build kept five things on local disk; all five were moved:

| Was on disk | Problem on Vercel | Now |
|---|---|---|
| PHP file sessions | user logged out when the next request hits another container | `app_sessions` via `App\Core\DbSessionHandler` |
| `storage/throttle/*` login counters | attacker gets fresh counters on every container | `app_kv`, atomic upsert, SHA-256 keys |
| `storage/logs/mail.cursor` | each container re-sends every notification | `app_kv` + `GET_LOCK` |
| `storage/receipts/*` | uploaded receipts vanish when the container stops | `receipts.content` LONGBLOB (max 5 MB) |
| `storage/logs/php-error.log` | lost, unreadable | stderr → Vercel runtime logs (`APP_ENV=production`) |

Redis / object storage were not added: MySQL is already required, the traffic is a student project's, and one fewer
service means one fewer set of credentials. Trade-off: receipts make the database bigger (5 MB cap each), and DB
sessions have no per-session lock (worst case: a lost flash message on two simultaneous requests).

## 2. Container (FrankenPHP)

Files: `Dockerfile`, `Caddyfile`, `deploy/php.ini`, `.dockerignore`.

| Item | Value |
|---|---|
| Base image | `dunglas/frankenphp:1-php8.3-bookworm` **(verify tag on Docker Hub)** |
| Extensions | `pdo_mysql`, `opcache` (mbstring, fileinfo, openssl, json, ctype are built in) |
| php.ini | `php.ini-production` + `deploy/php.ini` (upload 6M / post 8M, `expose_php=Off`, errors to stderr, OPcache without timestamp checks) |
| Web root | `/app/public` only; `app/`, `config/`, `storage/` are outside it |
| Blocked | dotfiles, `/router.php` (404); Caddy admin API off; `auto_https off` (TLS ends at the edge) |
| User | `www-data` (non-root) |
| Port | `$PORT` (default 8080) |
| Baked-in env | only non-secret defaults: `APP_ENV=production`, `APP_DEBUG=false`, `APP_TRUST_PROXY=true`, `SESSION_DRIVER=database`, `MAIL_LOG=stderr` |
| Not in the image | `.env`, `.git`, `tests/`, `docs/`, `database/`, `tools/`, `build/` |

Local build/run (needs Docker Hub access — blocked in the build sandbox, so **not yet run**):
```bash
docker build -t splitwise-plus .
docker run --rm -p 8080:8080 --env-file .env.production splitwise-plus     # .env.production is git-ignored
curl -s localhost:8080/healthz   # {"status":"ok"}
curl -s localhost:8080/readyz    # {"status":"ready"} once the DB variables are right
```

**Vercel specifics (verify all against Vercel's current "PHP / FrankenPHP / containers" documentation):**
- Use Vercel's officially documented container / FrankenPHP route, **not** the community `vercel-php` runtime.
- No `vercel.json` is committed, because its exact keys for container deployments could not be verified. Add one only
  as the official guide shows.
- Request body limit: Vercel caps request bodies (about 4.5 MB on serverless functions **(verify for containers)**).
  If the cap applies, a 5 MB receipt will be rejected by the platform before PHP sees it — then lower
  `UPLOAD_MAX_BYTES` and the message in `UploadService` to the platform limit.
- Region: choose the one closest to the database (e.g. Mumbai `bom1` **(verify)** if the DB is in Mumbai).

## 3. Managed MySQL 8.0 — comparison

Hard requirements (from the schema, not preferences):
1. Real **MySQL 8.0** (8.0.16+ for CHECK constraints) — not a "MySQL-compatible" engine.
2. **Triggers, stored functions and procedures** (11 triggers, 3 functions and 5 procedures are core to the design).
3. Foreign keys enforced (InnoDB).
4. `CREATE USER` / `GRANT` for the least-privilege app user.
5. Creating routines/triggers as a non-SUPER admin while binary logging is on → `log_bin_trust_function_creators=1`
   settable (local test: our server has `log_bin=ON`, `log_bin_trust_function_creators=OFF`; root imports only work
   because root has SUPER).
6. TLS connections; a region near Mumbai; a free or student-credit tier.

| Provider | MySQL 8.0 | Triggers/procs | `log_bin_trust_function_creators` | TLS | Mumbai region | Cost for a student | Notes |
|---|---|---|---|---|---|---|---|
| **AWS RDS for MySQL** | yes | yes | yes, via parameter group | yes | yes (ap-south-1) | 12-month free tier on db.t3/t4g.micro **(verify current free-tier terms)** | most documented; parameter group edit needed |
| **Azure Database for MySQL – Flexible Server** | yes | yes | yes, server parameter | yes (on by default) | yes (Central India) | free 12 months B1ms / Azure for Students credit **(verify)** | good fit if the college has Azure for Students |
| **Google Cloud SQL for MySQL** | yes | yes | yes, database flag | yes | yes (asia-south1) | trial credit only **(verify)** | no permanent free tier |
| **Aiven for MySQL** | yes | yes | **verify** | yes | **verify** | free plan exists **(verify limits and regions)** | simplest sign-up; check flag support first |
| **DigitalOcean Managed MySQL** | yes | yes | **verify** | yes | yes (BLR1) | paid only **(verify)** | |
| Railway (MySQL template) | yes (container) | yes | yes (you own the server) | **verify** | **verify** | trial credit **(verify)** | not a managed DB service; backups are your job |
| ✗ PlanetScale / Vitess, TiDB Serverless | MySQL protocol | **no / limited triggers, procedures, FKs** | — | yes | — | — | **excluded**: the schema needs triggers + procedures |

**Recommendation:** **Azure Database for MySQL Flexible Server (Central India)** if the team has Azure for Students,
otherwise **AWS RDS for MySQL (ap-south-1)** on the free tier. Both are genuine MySQL 8.0, let you set
`log_bin_trust_function_creators`, enforce TLS and sit in India. **Before signing up, verify** the current free-tier
terms and that MySQL 8.0 (not only 8.4) can still be selected.

## 4. Migration plan (local → managed)

The local database is never modified by any step below.

1. **Backup local data** (keep it outside the repo):
   ```bash
   mysqldump -u root -p --single-transaction --routines --triggers --events splitwise_plus > ~/backups/splitwise_plus_$(date +%F).sql
   ```
2. **Create the managed server** (MySQL 8.0, TLS required, public access limited if the provider allows), then set
   `log_bin_trust_function_creators = 1` (parameter group / server parameter / flag). Create an empty database, e.g. `splitwise_plus`.
3. **Build the hosted scripts locally** — passwords only through environment variables, never on the command line:
   ```bash
   read -rs APP_DB_PASSWORD && export APP_DB_PASSWORD      # the app user's password (16+ chars)
   read -rs DEMO_PASSWORD   && export DEMO_PASSWORD        # password for all demo users on the hosted copy
   php database/build_hosted.php --db=splitwise_plus      # writes build/ (git-ignored)
   unset APP_DB_PASSWORD DEMO_PASSWORD
   ```
   `build/hosted_schema.sql` = full schema + **fictional** sample data (no real personal data), all demo users get
   `DEMO_PASSWORD` instead of the published `Password@123`. `build/hosted_app_user.sql` = `'splitwise_app'@'%' REQUIRE SSL`
   with the same least-privilege grants as locally.
4. **Import as the provider's admin user, over TLS:**
   ```bash
   mysql --ssl-mode=VERIFY_IDENTITY --ssl-ca=provider-ca.pem -h HOST -u ADMIN -p splitwise_plus < build/hosted_schema.sql
   mysql --ssl-mode=VERIFY_IDENTITY --ssl-ca=provider-ca.pem -h HOST -u ADMIN -p < build/hosted_app_user.sql
   mysql ... -e "SOURCE database/verify_install.sql"     # expect 10 × PASS (the script names splitwise_plus; replace it if your DB name differs)
   rm -rf build/
   ```
5. **Rollback:** drop the hosted database and user; the local database is untouched. Re-running steps 3–4 is safe.

Tested locally (see §10): steps 3–4 against a **separate** local database `sw_hosted_test` with a TLS-only user.

## 5. Environment variables

Set in **Vercel → Project → Settings → Environment Variables** (separately for Preview and Production). Never in the
Dockerfile, never in Git, never in browser JavaScript. Do not paste them into chats.

| Variable | Example / default | Secret | Notes |
|---|---|---|---|
| `APP_ENV` | `production` | no | baked into the image; forces debug off, HSTS, stderr logs |
| `APP_URL` | `https://<your-project>.vercel.app` | no | |
| `APP_TRUST_PROXY` | `true` | no | trust right-most `X-Forwarded-For` and `X-Forwarded-Proto` |
| `DB_HOST` | provider host name | no | |
| `DB_PORT` | `3306` | no | |
| `DB_NAME` | `splitwise_plus` | no | |
| `DB_USER` | `splitwise_app` | no | |
| `DB_PASSWORD` | — | **yes** | the app user's password |
| `DB_SSL` | `true` | no | |
| `DB_SSL_CA` | path to CA file in the image, or empty | no | empty = system CA bundle (works for providers with public CAs) |
| `DB_SSL_VERIFY` | `true` | no | keep `true` in production |
| `DB_CONNECT_TIMEOUT` | `5` | no | seconds |
| `SESSION_LIFETIME` | `7200` | no | idle timeout, seconds |
| `UPLOAD_MAX_BYTES` | `5242880` | no | lower to the platform body limit if needed |

`DB_PASS` (old name) is still accepted. Real environment variables always override a `.env` file.

## 6. Sessions, uploads, logs

- **Sessions**: `SESSION_DRIVER=database`. Cookie `splitwise_sess`: `HttpOnly`, `SameSite=Lax`, `Secure` behind HTTPS.
  `session.use_strict_mode` — an unknown session id from a client is replaced (no fixation). Idle sessions older than
  `SESSION_LIFETIME` are refused on read and removed by garbage collection (1 % of requests).
- **Uploads**: validated by content (finfo), stored in MySQL, served only to group members via `/receipts/{id}`.
- **Logs**: PHP errors and mock e-mails go to stderr → Vercel runtime logs. No passwords or stack-trace arguments are
  logged (`zend.exception_ignore_args`). The browser only ever sees generic error pages.

## 7. Health and readiness

| Endpoint | Checks | Success | Failure |
|---|---|---|---|
| `GET /healthz` | PHP answers (no DB, no session) | `200 {"status":"ok"}` | — |
| `GET /readyz` | `SELECT 1` on MySQL | `200 {"status":"ready"}` | `503 {"status":"unavailable"}` (reason only in the server log) |

Both send `Cache-Control: no-store`, accept only GET/HEAD and reveal no versions, hosts or timings.

## 8. Security review (Phase 5.5)

| Area | Control | Tested by |
|---|---|---|
| Secrets | none in image/Git; `.env`, `.env.*`, `build/` git-ignored; `.dockerignore` excludes `.env` | C1, C4, C5, C7 |
| Transport | HSTS in production; Secure cookie behind proxy; MySQL TLS, app user `REQUIRE SSL` | E3, E4, H3, H4 |
| Headers | CSP `default-src 'self'; script-src 'self'; object-src 'none'; frame-ancestors 'none'…`, nosniff, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy; no `X-Powered-By` | P4, P5, E5 |
| Sessions | DB-backed, strict ids, server-side expiry, deleted on logout | P6–P9, E8 |
| Brute force | per account+IP and per IP, shared across instances; forged `X-Forwarded-For` entries ignored (right-most only) | T1–T3, E10, E10b |
| Errors | production ignores `APP_DEBUG`; DB outage → generic page; health bodies fixed | F1–F4 |
| Least privilege | app user unchanged except INSERT/UPDATE/DELETE on `app_sessions`, `app_kv` | DB A1–A9 |
| Demo data | hosted copy has no published password; data is fictional | H2, E9 |
| Kept from earlier phases | CSRF on every POST, prepared statements, output escaping, upload MIME by content, IDOR checks, optimistic locking | functional suite |

Residual risks / to do at deploy time: verify the platform body limit; only set `APP_TRUST_PROXY=true` behind a
proxy that appends the real client IP (Vercel does **(verify)**); rotate `DB_PASSWORD` if it ever leaks; set provider
backups (daily, 7 days).

## 9. Preview and production steps (to do with your accounts)

Credentials are entered **only** in the provider consoles and Vercel's dashboard — never in chat or files in the repo.
1. Create the managed MySQL server (§3) and run the migration (§4).
2. In Vercel: import the GitHub repo; follow the official FrankenPHP/container guide so the `Dockerfile` is used **(verify)**.
3. Add the variables of §5 for **Preview**; push a branch → preview deployment.
4. Check `/healthz`, `/readyz`, then run the 20 workflows below on the preview URL; only then promote to Production
   with Production variables.

Workflows to verify on the deployed URL (none verified yet):
1 register · 2 login · 3 logout · 4 login throttle · 5 create group · 6 invite + accept · 7 equal expense ·
8 exact/percentage/shares expense · 9 personal expense · 10 edit with version check · 11 delete + restore ·
12 receipt upload + view (and 403 for non-member) · 13 record settlement · 14 confirm/reject · 15 balances + simplified
debts · 16 budgets + alerts · 17 analytics charts + tables · 18 notifications · 19 profile + password change ·
20 admin user toggle + activity log. Also: session survives across requests hitting different instances.

## 10. Validation status

| Item | Status |
|---|---|
| App runs with DB sessions, DB throttle, DB receipts locally (unit 41, DB 76, functional 115, scenarios 23) | **Passed** |
| Deployment suite `tests/deployment/run.sh` — config checks, hosted-style import, production mode behind simulated proxy, 2 instances sharing sessions, TLS-only DB user, DB-down behaviour (28 checks) | **Passed** (local, PHP built-in server, not FrankenPHP) |
| `docker build` / container run | **Blocked** — Docker Hub pulls refused in the build sandbox |
| Running FrankenPHP binary locally | **Blocked** — downloaded binary execution not permitted in the build sandbox |
| Vercel docs check of container setup / `vercel.json` / limits | **Not done** — fetch not approved; marked (verify) |
| Managed MySQL provisioning + import | **Blocked** — needs your provider account |
| Vercel preview / production deployment | **Blocked** — needs your Vercel account |
| 20 workflows on a deployed URL | **Not tested** — no deployment exists |

## 11. Troubleshooting

| Symptom | Likely cause |
|---|---|
| `/readyz` 503 | wrong `DB_*` values, DB firewall blocks Vercel, TLS required but `DB_SSL` not `true` — read the runtime log line `readyz: …` |
| Import fails "You do not have the SUPER privilege and binary logging is enabled" | set `log_bin_trust_function_creators=1` on the server |
| Logged out on every click | `SESSION_DRIVER` not `database`, or cookie `Secure` on plain HTTP — check `APP_TRUST_PROXY` |
| Everyone locked out after a few bad logins | `APP_TRUST_PROXY=false` behind a proxy: all visitors share the proxy IP |
| Receipt upload fails with no app message | platform request-size limit reached before PHP — lower `UPLOAD_MAX_BYTES` |
| `Packet too large` on receipt insert | provider `max_allowed_packet` below 8 MB (MySQL 8 default is 64 MB) |
