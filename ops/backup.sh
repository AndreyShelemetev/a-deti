#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/common.sh"
umask 077
backup_dir="${AT_BACKUP_DIR:-$PROJECT_DIR/backups}"
output="${1:-$backup_dir/database-$(date -u +%Y%m%dT%H%M%SZ)-$$.sql.gz}"
[[ "$output" == *.sql.gz ]] || { echo 'Нужен путь с окончанием .sql.gz' >&2; exit 1; }
[[ ! -e "$output" ]] || { echo 'Архив уже существует; перезапись отменена.' >&2; exit 1; }
mkdir -p "$(dirname "$output")"
partial="$(mktemp "${output}.partial.XXXXXX")"
trap 'rm -f "$partial"' EXIT
compose exec -T db sh -ec '
  export MYSQL_PWD="$(cat /run/secrets/db_password)"
  exec mariadb-dump --user="$MARIADB_USER" --single-transaction --quick --hex-blob \
    --default-character-set=utf8mb4 --routines --events --triggers --skip-comments "$MARIADB_DATABASE"
' | gzip > "$partial"
gzip -t "$partial"
mv "$partial" "$output"
echo "Резервная копия: $output"
