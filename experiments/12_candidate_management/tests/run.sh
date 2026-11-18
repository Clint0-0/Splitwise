#!/usr/bin/env bash
# Experiment 12 tests: (re)creates splitwise_candidate_lab with sample data, starts the
# lab app on 127.0.0.1:8091 as the least-privilege user and runs run_tests.php.
# Root login for setup: MYSQL_ARGS (default -uroot). Never touches splitwise_plus.
set -uo pipefail
E="$(cd "$(dirname "$0")/.." && pwd)"
MYSQL="mysql --default-character-set=utf8mb4 ${MYSQL_ARGS:--uroot}"
PW="cand-test-$(head -c 6 /dev/urandom | od -An -tx1 | tr -d ' \n')"
$MYSQL < "$E/database/candidate_management.sql" && $MYSQL < "$E/database/sample_data.sql" || { echo "SCHEMA IMPORT FAILED"; exit 1; }
sed "s/Cand_Change_Me_123!/$PW/" "$E/database/app_user.sql" | $MYSQL || { echo "APP USER FAILED"; exit 1; }
$MYSQL -e "ALTER USER 'candidate_app'@'localhost' IDENTIFIED BY '$PW'; ALTER USER 'candidate_app'@'127.0.0.1' IDENTIFIED BY '$PW';"
CANDIDATE_DB_PASSWORD="$PW" php -S 127.0.0.1:8091 -t "$E/public" >/tmp/cms_test_server.log 2>&1 & S=$!
CANDIDATE_DB_PASSWORD="wrong-password" php -S 127.0.0.1:8092 -t "$E/public" >/tmp/cms_test_server_bad.log 2>&1 & S2=$!
trap 'kill $S $S2 2>/dev/null' EXIT
sleep 1
CANDIDATE_DB_PASSWORD="$PW" php "$E/tests/run_tests.php"; rc=$?
# leave the lab DB with clean sample data and the documented default password
$MYSQL < "$E/database/candidate_management.sql" && $MYSQL < "$E/database/sample_data.sql" && $MYSQL < "$E/database/app_user.sql"
$MYSQL -e "ALTER USER 'candidate_app'@'localhost' IDENTIFIED BY 'Cand_Change_Me_123!'; ALTER USER 'candidate_app'@'127.0.0.1' IDENTIFIED BY 'Cand_Change_Me_123!';"
exit $rc
