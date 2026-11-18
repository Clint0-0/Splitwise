#!/usr/bin/env bash
# Runs the isolated tests for DBMS Experiments 10-12 (Linux; needs MySQL root over the
# local socket, PHP, Node). None of them touches the splitwise_plus database.
#   E10  experiments/10_mysql_ddl_fk in sandbox splitwise_lab_exp10
#   E11  local dry run of the MongoDB CRUD script (no Atlas contact)
#   E12  candidate management app on splitwise_candidate_lab (DB + HTTP)
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
rc=0
echo "=== Experiment 10 — DDL + foreign keys (sandbox)";   php "$ROOT/tests/experiments/test_exp10.php" | tail -n +1 || rc=1
echo; echo "=== Experiment 11 — MongoDB CRUD script, local dry run"; node "$ROOT/tests/experiments/exp11_dry_run.js" || rc=1
echo; echo "=== Experiment 12 — Candidate Management CRUD";   bash "$ROOT/experiments/12_candidate_management/tests/run.sh" || rc=1
exit $rc
