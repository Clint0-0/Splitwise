#!/usr/bin/env bash
# Rebuild + import the database, run every SQL script, run the PHP test suite,
# then re-import so the sample data is clean again.
# Usage: tests/database/run.sh   (needs mysql client + php with pdo_mysql)
# Set MYSQL_ARGS for credentials, e.g. MYSQL_ARGS="-uroot -psecret".
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MYSQL="mysql --default-character-set=utf8mb4 ${MYSQL_ARGS:--uroot}"
fail=0

"$ROOT/database/build.sh" >/dev/null
$MYSQL < "$ROOT/database/splitwise_plus.sql" || { echo "IMPORT FAILED"; exit 1; }
echo "Import OK"

for f in "$ROOT"/database/queries/*.sql "$ROOT"/database/views/demo_views.sql "$ROOT"/database/transactions/demo_acid.sql; do
  if $MYSQL < "$f" >/dev/null 2>/tmp/sqlerr; then echo "SQL  OK   ${f#$ROOT/}"; else echo "SQL  FAIL ${f#$ROOT/}: $(cat /tmp/sqlerr)"; fail=1; fi
done

php "$ROOT/tests/database/run_db_tests.php" || fail=1

$MYSQL < "$ROOT/database/splitwise_plus.sql" && echo "Re-imported clean sample data"
exit $fail
