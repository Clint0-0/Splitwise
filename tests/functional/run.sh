#!/usr/bin/env bash
# End-to-end test run: fresh database + least-privilege app user, start the app on
# PHP's built-in server AS splitwise_app, run the HTTP tests, stop, re-import clean data.
# Usage: tests/functional/run.sh     (MYSQL_ARGS for root credentials, e.g. "-uroot -psecret")
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MYSQL="mysql --default-character-set=utf8mb4 ${MYSQL_ARGS:--uroot}"
PORT="${PORT:-8765}"

$MYSQL < "$ROOT/database/splitwise_plus.sql" || { echo "IMPORT FAILED"; exit 1; }
$MYSQL < "$ROOT/database/security/app_user.sql" >/dev/null || { echo "APP USER FAILED"; exit 1; }
rm -f "$ROOT"/storage/receipts/*.{png,jpg,pdf} 2>/dev/null

# real environment variables override .env (see App\Core\Env)
DB_HOST=127.0.0.1 DB_USER=splitwise_app DB_PASS='Change_Me_123!' DB_NAME=splitwise_plus APP_DEBUG=false \
  php -S 127.0.0.1:$PORT -t "$ROOT/public" "$ROOT/public/router.php" >/tmp/splitwise-server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null' EXIT
sleep 1

APP_URL="http://127.0.0.1:$PORT" php "$ROOT/tests/functional/run_functional_tests.php"
status=$?

$MYSQL < "$ROOT/database/splitwise_plus.sql" && echo "Re-imported clean sample data"
rm -f "$ROOT"/storage/receipts/*.{png,jpg,pdf} 2>/dev/null
exit $status
