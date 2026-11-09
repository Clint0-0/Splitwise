#!/usr/bin/env bash
# Deployment-readiness tests (Phase 5.5), all LOCAL — no cloud account needed.
#  1. static checks of Dockerfile / Caddyfile / .dockerignore / .gitignore / env template
#  2. "hosted-style" database: database/build_hosted.php output imported into a SEPARATE
#     database (sw_hosted_test) with a separate TLS-only user — the real splitwise_plus
#     database is not touched
#  3. the app in PRODUCTION mode (APP_ENV=production, APP_TRUST_PROXY=true, TLS to MySQL)
#     behind a simulated proxy, plus a second instance to prove sessions are shared
#  4. failure behaviour: wrong DB password -> /readyz 503 and a generic error page
# Usage: tests/deployment/run.sh      (MYSQL_ARGS for root credentials, default -uroot)
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MYSQL="mysql --default-character-set=utf8mb4 ${MYSQL_ARGS:--uroot}"
DBN=sw_hosted_test
APPPW="dep-test-$(head -c 9 /dev/urandom | od -An -tx1 | tr -d ' \n')"
DEMOPW="Demo-$(head -c 6 /dev/urandom | od -An -tx1 | tr -d ' \n')-9"
TMP="$(mktemp -d)"
cleanup() { kill ${S1:-} ${S2:-} ${S3:-} 2>/dev/null; $MYSQL -e "DROP DATABASE IF EXISTS $DBN; DROP USER IF EXISTS 'sw_hosted'@'%';" 2>/dev/null; rm -rf "$TMP" "$ROOT/build"; }
trap cleanup EXIT

DEMO_PASSWORD="$DEMOPW" APP_DB_PASSWORD="$APPPW" php "$ROOT/database/build_hosted.php" --db=$DBN >/dev/null || { echo "BUILD FAILED"; exit 1; }
$MYSQL -e "CREATE DATABASE $DBN CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci" || exit 1
$MYSQL < "$ROOT/build/hosted_schema.sql" >/dev/null || { echo "HOSTED IMPORT FAILED"; exit 1; }
sed "s/'splitwise_app'@'%'/'sw_hosted'@'%'/g" "$ROOT/build/hosted_app_user.sql" | $MYSQL >/dev/null || { echo "HOSTED USER FAILED"; exit 1; }
# CA of the local server, readable copy (managed providers publish theirs)
cp /var/lib/mysql/ca.pem "$TMP/ca.pem" 2>/dev/null || $MYSQL -N -e "SELECT 1" >/dev/null

common=(APP_ENV=production APP_DEBUG=true APP_TRUST_PROXY=true DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=$DBN DB_USER=sw_hosted
        DB_SSL=true DB_SSL_CA="$TMP/ca.pem" DB_SSL_VERIFY=false MAIL_LOG=stderr)
env "${common[@]}" DB_PASSWORD="$APPPW" php -d upload_max_filesize=6M -d post_max_size=8M -S 127.0.0.1:8771 -t "$ROOT/public" "$ROOT/public/router.php" >"$TMP/s1.log" 2>&1 & S1=$!
env "${common[@]}" DB_PASSWORD="$APPPW" php -S 127.0.0.1:8772 -t "$ROOT/public" "$ROOT/public/router.php" >"$TMP/s2.log" 2>&1 & S2=$!
env "${common[@]}" DB_PASSWORD="wrong-password" php -S 127.0.0.1:8773 -t "$ROOT/public" "$ROOT/public/router.php" >"$TMP/s3.log" 2>&1 & S3=$!
sleep 1

ROOT="$ROOT" DBN=$DBN DEMOPW="$DEMOPW" APPPW="$APPPW" LOGDIR="$TMP" php "$ROOT/tests/deployment/run_deployment_tests.php"
