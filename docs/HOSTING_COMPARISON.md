# Hosting comparison: Vercel vs Railway vs Render (Phase 6)

Checked 2026-11-24 against public pricing/docs pages (links at the end). Prices change: confirm them in each console before paying. Nothing has been deployed yet; this is a desk evaluation against the existing `Dockerfile` (FrankenPHP, PHP 8.3) and the **mandatory MySQL 8.0** database.

## What SplitWise+ needs from a host
- Run the existing Docker image (FrankenPHP + Caddy, web root `/app/public`).
- A **MySQL 8.0** server (not MariaDB, not PostgreSQL) that allows triggers, stored procedures and functions (the import needs `CREATE ROUTINE`/`TRIGGER` and, with binary logging, `log_bin_trust_function_creators`).
- No local disk for app state: sessions, counters and receipts are already in MySQL (Phase 5.5), so any number of instances works.
- Requests up to ~45 s for a bill scan (one vision-API call, see `docs/OCR_PROVIDER_EVALUATION.md`).
- Outbound SMTP or HTTPS for e-mail, outbound HTTPS for the scan API and Sentry.
- No background worker is required today (notification e-mails are sent at the end of a POST request).

## Comparison

| | Vercel | Railway | Render |
|---|---|---|---|
| Runs our Dockerfile | Yes, as a container-backed **Vercel Function** (FrankenPHP guide exists); image stored in Vercel Container Registry | Yes, any Dockerfile or image | Yes, Docker web service |
| PHP / FrankenPHP | Official KB guide "Deploy PHP on Vercel with Docker" (FrankenPHP) | Generic Docker | Generic Docker |
| MySQL 8.0 | **Not provided**: needs an outside managed MySQL (extra account, public TLS connection) | One-click MySQL service on a persistent volume in the same project (private network). Pin the image to **8.0**: templates may default to newer majors | **No managed MySQL** (managed Postgres only): run MySQL yourself as a private service with a disk, or use an outside provider |
| Persistent storage | Read-only filesystem, writable `/tmp` only (fine: our state is in MySQL) | Volumes, $0.15/GB-month | Persistent disks $0.25/GB-month (paid services only) |
| Database backups | Depends on the outside MySQL provider | Volume backups (scheduled/on-demand) and point-in-time recovery for MySQL, **turned on by you** | DIY for self-run MySQL |
| Background workers | Containers can also run workers (per Vercel docs) | Any service can be a worker | Background workers from $7/month |
| Request duration | Function max duration applies (Hobby: 300 s default and max) | Always-on service, no function timeout | Always-on service |
| Cold starts / sleep | Scales per request | Always-on (Free plan services sleep when idle) | Free web services spin down after 15 min idle |
| Outbound SMTP | Not checked | Not checked | Free web services block SMTP ports 25/465/587 (paid ones don't, per third-party summary: verify) |
| Price to start | Hobby/Pro plan + Active CPU billing, **plus** a separate MySQL provider | Hobby $5/month incl. $5 usage; usage beyond that is billed | Starter web service $7/month + a MySQL service with disk, or an outside MySQL |
| Moving parts for us | App on Vercel + DB elsewhere + TLS CA + allow-listing | App + MySQL in one project, private network | App + self-run MySQL or outside DB |

## Recommendation: Railway for staging (and probably production)
It is the simplest reliable option **for this architecture**: the existing Dockerfile and a MySQL 8.0 service live in one project on a private network, volume backups / point-in-time recovery are a toggle, and an always-on container has no function time limit for scans. Vercel needs a second provider for MySQL; Render has no managed MySQL.

Caveats before relying on it:
1. Confirm the MySQL service runs **8.0.x** (`SELECT VERSION();`), not 8.4/9.x; pin the image tag.
2. Turn on backups/PITR, **and** run `tools/backup.sh` to storage outside Railway (a provider-only backup is lost with the account). Test with `tools/restore_test.sh`.
3. Set a usage limit in Railway's billing settings so a bug can't run up costs.
4. Keep the app user least-privilege (`build_hosted.php` output), not Railway's root user.

## Staging plan (not done: needs your accounts)
1. You create the Railway project and MySQL 8.0 service in the console (credentials stay there).
2. Import with `database/build_hosted.php --db=<name> --no-sample` output as the MySQL admin, then `hosted_app_user.sql`; run `hosted_verify.sql` (all PASS). For a demo-data staging copy, build without `--no-sample` and set `DEMO_PASSWORD`.
3. Deploy the image; set the variables from `docs/DEPLOYMENT.md` §5 in Railway's variables UI (never in git): `APP_ENV=production`, `APP_URL`, `DB_*`, `DB_SSL` as required, `MAIL_DRIVER=smtp` + `SMTP_*`, `SENTRY_DSN` (optional), `SCAN_PROVIDER=off` until the scan evaluation is done.
4. `php tools/create_admin.php …` once (Railway shell), then log in.
5. Check `/healthz`, `/readyz`, the 20 workflows in `docs/DEPLOYMENT.md` §9, a password reset e-mail, a backup + restore test.
6. Keep staging private (not linked publicly) until the financial features pass the manual checks in `docs/UPI_DEVICE_TESTS.md`.

## Sources
- Vercel: [Deploy PHP on Vercel with Docker](https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker), [Does Vercel support Docker deployments?](https://vercel.com/kb/guide/does-vercel-support-docker-deployments), [Function limitations](https://vercel.com/docs/functions/limitations), [Supported runtimes / filesystem](https://vercel.com/docs/concepts/functions/supported-languages)
- Railway: [Pricing plans](https://docs.railway.com/pricing/plans), [Pricing FAQs](https://docs.railway.com/reference/pricing/faqs), [Databases](https://docs.railway.com/guides/databases), [Volume backups](https://docs.railway.com/volumes/backups), [Usage-based pricing blog (volume $/GB)](https://blog.railway.com/p/usage-based-vs-fixed-pricing-2026)
- Render: [Pricing](https://render.com/pricing.md), [Docker hosting](https://render.com/hosting/docker), [Cost article](https://render.com/articles/how-much-does-cloud-application-hosting-cost-for-small-businesses), third-party summaries ([temps.sh](https://temps.sh/blog/render-pricing-2026), [expresstech.io](https://expresstech.io/?p=10023)) for instance prices and the SMTP-port note
