#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/common.sh"
dump="${1:?Использование: bash ops/restore.sh /path/database.sql.gz}"
[[ -f "$dump" && -s "$dump" ]] || { echo 'Файл дампа не найден или пуст.' >&2; exit 1; }
case "$dump" in
  *.sql.gz) gzip -t "$dump" ;;
  *.sql) ;;
  *) echo 'Поддерживаются .sql и .sql.gz.' >&2; exit 1 ;;
esac
compose up -d --wait db
tables="$(compose exec -T db sh -ec '
  export MYSQL_PWD="$(cat /run/secrets/db_password)"
  exec mariadb --user="$MARIADB_USER" --batch --skip-column-names "$MARIADB_DATABASE" \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()"
')"
if [[ "$tables" != 0 ]]; then
  echo 'В базе уже есть таблицы. Импорт отменён. Используйте отдельную пустую базу/проект Compose.' >&2
  exit 1
fi
compose stop app
read_dump() { if [[ "$dump" == *.gz ]]; then gzip -dc "$dump"; else cat "$dump"; fi; }
read_dump | compose exec -T db sh -ec '
  export MYSQL_PWD="$(cat /run/secrets/db_password)"
  exec mariadb --user="$MARIADB_USER" --default-character-set=utf8mb4 "$MARIADB_DATABASE"
'
compose run --rm --no-deps app php bin/console.php migrate
echo 'База импортирована, схема обновлена. Исходное наполнение повторно не загружалось.'
echo 'Для запуска: docker compose up -d --wait app'
