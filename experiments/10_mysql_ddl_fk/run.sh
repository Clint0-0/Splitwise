#!/usr/bin/env bash
# Runs Experiment 10 scripts 01-09 in order against the SANDBOX database
# splitwise_lab_exp10 and saves the full output (statements + results + expected
# errors) to output/run_<timestamp>.txt.
#   ./run.sh            asks for confirmation first
#   ./run.sh --yes      no prompt (used by the test suite)
#   ./run.sh --cleanup  also runs 10_cleanup_drop_database.sql at the end
# MySQL login: MYSQL_ARGS (default "-uroot"), e.g. MYSQL_ARGS="-uroot -p".
set -uo pipefail
cd "$(dirname "$0")"
MYSQL="mysql --default-character-set=utf8mb4 ${MYSQL_ARGS:--uroot}"
YES=0; CLEAN=0
for a in "$@"; do case "$a" in --yes) YES=1;; --cleanup) CLEAN=1;; esac; done

echo "Target database: splitwise_lab_exp10 (dropped and recreated). splitwise_plus is NOT touched."
$MYSQL -N -e "SHOW DATABASES LIKE 'splitwise_lab_exp10%'" || { echo "Cannot connect to MySQL"; exit 1; }
if [ $YES -eq 0 ]; then read -rp "Type YES to continue: " ok; [ "$ok" = "YES" ] || { echo "Aborted."; exit 1; }; fi

mkdir -p output
OUT="output/run_$(date +%Y%m%d_%H%M%S).txt"
for f in 0[1-9]_*.sql; do
  echo "################ $f" >>"$OUT"
  # --force: continue after the statements that are EXPECTED to fail (marked "EXPECT ERROR")
  $MYSQL --force -v -v -v --table <"$f" >>"$OUT" 2>&1
done
[ $CLEAN -eq 1 ] && $MYSQL <10_cleanup_drop_database.sql >>"$OUT" 2>&1
echo "Output: $(pwd)/$OUT"
echo "Expected errors seen: $(grep -c '^ERROR' "$OUT") (should equal the number of 'EXPECT ERROR' markers: $(grep -c 'EXPECT ERROR' 0[1-9]_*.sql | awk -F: '{s+=$2} END {print s}'))"
