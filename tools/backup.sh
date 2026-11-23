#!/usr/bin/env bash
# Consistent, compressed logical backup of the SplitWise+ database (tables, views,
# triggers, procedures, functions, receipt bytes).
#
#   MYSQL_ARGS="--defaults-extra-file=$HOME/.splitwise-backup.cnf" tools/backup.sh [DB] [DIR] [KEEP_DAYS]
#   defaults: DB=splitwise_plus  DIR=backups  KEEP_DAYS=14
#
# Put credentials in an option file (mode 600), never on the command line:
#   [client]
#   host=...  port=3306  user=...  password=...  ssl-mode=VERIFY_IDENTITY  ssl-ca=/path/ca.pem
# --single-transaction: InnoDB snapshot, the app keeps running during the backup.
# Keep copies OFF the database server too (another region/bucket). Test restores with
# tools/restore_test.sh — a backup that was never restored is not a backup.
set -euo pipefail
DB="${1:-splitwise_plus}"; DIR="${2:-backups}"; KEEP="${3:-14}"
[[ "$DB" =~ ^[A-Za-z0-9_]{1,64}$ ]] || { echo "bad database name" >&2; exit 2; }
mkdir -p "$DIR"; chmod 700 "$DIR"
OUT="$DIR/${DB}_$(date +%Y%m%d_%H%M%S).sql.gz"
# no --databases: the dump has no CREATE DATABASE/USE, so it can be restored under any name
mysqldump ${MYSQL_ARGS:-} --single-transaction --quick --routines --triggers --events --hex-blob \
  --set-gtid-purged=OFF --default-character-set=utf8mb4 --no-tablespaces "$DB" | gzip -9 > "$OUT.part"
mv "$OUT.part" "$OUT"; chmod 600 "$OUT"
gzip -t "$OUT"
find "$DIR" -name "${DB}_*.sql.gz" -mtime +"$KEEP" -delete
echo "Backup written: $OUT ($(du -h "$OUT" | cut -f1))"
