#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/common.sh"
umask 077
site=http://localhost
email=admin@example.com
while [[ $# -gt 0 ]]; do
  case "$1" in
    --local) shift ;;
    --site) site="${2:?Нужен домен}"; shift 2 ;;
    --email) email="${2:?Нужна почта}"; shift 2 ;;
    *) echo 'Использование: bash ops/setup.sh --local | --site example.org --email admin@example.org' >&2; exit 1 ;;
  esac
done
if [[ "$site" != http://localhost && ! "$site" =~ ^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$ ]]; then
  echo 'Укажите доменное имя без пути и протокола.' >&2; exit 1
fi
if [[ ! "$email" =~ ^[a-zA-Z0-9._+%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
  echo 'Проверьте почту для уведомлений сертификата.' >&2; exit 1
fi
if [[ -e "$AT_ENV_FILE" || -e "$AT_RELEASE_FILE" || -e "$PROJECT_DIR/.secrets" ]]; then
  echo 'Настройки уже существуют. Они сохранены; повторная генерация не выполняется.' >&2; exit 1
fi
mkdir -m 700 "$PROJECT_DIR/.secrets"
openssl rand -hex 32 > "$PROJECT_DIR/.secrets/db_password"
openssl rand -hex 32 > "$PROJECT_DIR/.secrets/db_root_password"
sed -e "s|^SITE_ADDRESS=.*|SITE_ADDRESS=$site|" -e "s|^ACME_EMAIL=.*|ACME_EMAIL=$email|" "$PROJECT_DIR/.env.example" > "$AT_ENV_FILE"
chmod 600 "$AT_ENV_FILE" "$PROJECT_DIR/.secrets/"*
echo 'Созданы .env и случайные пароли БД в .secrets/. Они исключены из Git.'
