#!/usr/bin/env bash
# Prove a backup can be restored: load it into a THROW-AWAY database, compare it with
# the source, then drop the scratch copy. Never touches the source database.
#
#   MYSQL_ARGS="..." tools/restore_test.sh backups/splitwise_plus_YYYYMMDD_HHMMSS.sql.gz [SOURCE_DB]
# Exit 0 = restore OK. Needs an account that may CREATE/DROP the scratch database and
# create triggers/routines (on managed MySQL: the admin user, not splitwise_app).
set -euo pipefail
FILE="${1:?backup file}"; SRC="${2:-splitwise_plus}"
[[ "$SRC" =~ ^[A-Za-z0-9_]{1,64}$ ]] || { echo "bad database name" >&2; exit 2; }
SCRATCH="restore_test_$(date +%s)"
MYSQL="mysql ${MYSQL_ARGS:-} --default-character-set=utf8mb4 -N -B"
cleanup() { $MYSQL -e "DROP DATABASE IF EXISTS \`$SCRATCH\`" || true; }
trap cleanup EXIT
$MYSQL -e "CREATE DATABASE \`$SCRATCH\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
gunzip -c "$FILE" | $MYSQL "$SCRATCH"

q() { $MYSQL -e "$1"; }
fail=0
for t in $(q "SELECT table_name FROM information_schema.tables WHERE table_schema='$SCRATCH' AND table_type='BASE TABLE' ORDER BY 1"); do
  a=$(q "SELECT COUNT(*) FROM \`$SRC\`.\`$t\`" 2>/dev/null || echo missing); b=$(q "SELECT COUNT(*) FROM \`$SCRATCH\`.\`$t\`")
  # app_sessions/app_kv change every second on a live site: report, don't fail
  if [[ "$a" != "$b" && "$t" != app_sessions && "$t" != app_kv ]]; then echo "ROWS DIFFER $t: source=$a restored=$b"; fail=1; fi
done
for kind in "TABLES:table_schema:table_type='BASE TABLE'" "VIEWS:table_schema:1" "TRIGGERS:trigger_schema:1" "ROUTINES:routine_schema:1"; do
  IFS=: read -r view col cond <<< "$kind"
  a=$(q "SELECT COUNT(*) FROM information_schema.$view WHERE $col='$SRC' AND $cond"); b=$(q "SELECT COUNT(*) FROM information_schema.$view WHERE $col='$SCRATCH' AND $cond")
  [[ "$a" == "$b" ]] || { echo "COUNT DIFFERS $view: source=$a restored=$b"; fail=1; }
done
bad=$(q "SELECT COUNT(*) FROM (SELECT group_id FROM \`$SCRATCH\`.v_group_member_balances GROUP BY group_id HAVING SUM(net_balance) <> 0) x")
[[ "$bad" == 0 ]] || { echo "BALANCES: $bad group(s) do not sum to 0"; fail=1; }
a=$(q "SELECT COALESCE(SUM(CRC32(content)),0) FROM \`$SRC\`.receipts"); b=$(q "SELECT COALESCE(SUM(CRC32(content)),0) FROM \`$SCRATCH\`.receipts")
[[ "$a" == "$b" ]] || { echo "RECEIPT BYTES DIFFER"; fail=1; }
if [[ $fail == 0 ]]; then echo "RESTORE OK: $FILE -> $SCRATCH (tables, views, triggers, routines, row counts, balances, receipt bytes match)"; fi
exit $fail
