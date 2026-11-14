#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/common.sh"
case "${1:-}" in
  --seed) mode=seed ;;
  --import) mode=import; dump="${2:?Укажите .sql/.sql.gz}" ;;
  *) echo 'Использование: bash ops/install.sh --seed | --import /path/database.sql.gz' >&2; exit 1 ;;
esac
compose build --pull app
compose up -d --wait db
if [[ "$mode" == import ]]; then
  bash "$PROJECT_DIR/ops/restore.sh" "$dump"
else
  compose run --rm --no-deps app php bin/console.php init --seed
fi
compose up -d --wait app
echo "Сайт: $(site_url)/; админка: $(site_url)/site-admin"
echo 'Создать администратора: docker compose run --rm app php bin/console.php admin:create --login=admin'
