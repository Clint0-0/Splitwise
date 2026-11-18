#!/usr/bin/env bash
# Experiment 12 tests. Uses its OWN throw-away database splitwise_candidate_lab_test and
# login candidate_test (both dropped at the end), so your lab database
# splitwise_candidate_lab and its candidate_app password are never touched.
# Root login for setup: MYSQL_ARGS (default -uroot). Never touches splitwise_plus.
set -uo pipefail
E="$(cd "$(dirname "$0")/.." && pwd)"
MYSQL="mysql --default-character-set=utf8mb4 ${MYSQL_ARGS:--uroot}"
DB=splitwise_candidate_lab_test
PW="cand-test-$(head -c 6 /dev/urandom | od -An -tx1 | tr -d ' \n')"
cleanup() {
  kill ${S:-} ${S2:-} 2>/dev/null
  $MYSQL -e "DROP DATABASE IF EXISTS $DB; DROP USER IF EXISTS 'candidate_test'@'localhost', 'candidate_test'@'127.0.0.1';"
}
trap cleanup EXIT
sed "s/splitwise_candidate_lab/$DB/g" "$E/database/candidate_management.sql" | $MYSQL || { echo "SCHEMA IMPORT FAILED"; exit 1; }
sed "s/splitwise_candidate_lab/$DB/g" "$E/database/sample_data.sql" | $MYSQL || { echo "SAMPLE DATA FAILED"; exit 1; }
$MYSQL -e "DROP USER IF EXISTS 'candidate_test'@'localhost', 'candidate_test'@'127.0.0.1';"
sed "s/splitwise_candidate_lab/$DB/g; s/candidate_app/candidate_test/g; s/Cand_Change_Me_123!/$PW/g" "$E/database/app_user.sql" | $MYSQL || { echo "TEST USER FAILED"; exit 1; }
env CANDIDATE_DB_NAME=$DB CANDIDATE_DB_USER=candidate_test CANDIDATE_DB_PASSWORD="$PW" \
  php -S 127.0.0.1:8091 -t "$E/public" >/tmp/cms_test_server.log 2>&1 & S=$!
env CANDIDATE_DB_NAME=$DB CANDIDATE_DB_USER=candidate_test CANDIDATE_DB_PASSWORD="wrong-password" \
  php -S 127.0.0.1:8092 -t "$E/public" >/tmp/cms_test_server_bad.log 2>&1 & S2=$!
sleep 1
CMS_DB=$DB CMS_APP_USER=candidate_test CANDIDATE_DB_PASSWORD="$PW" php "$E/tests/run_tests.php"
