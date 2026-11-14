#!/usr/bin/env bash
# Обновляет только код и конфигурацию Compose; импорт данных выполняется отдельно.
set -Eeuo pipefail
umask 077

fail() { printf '%s\n' "$*" >&2; exit 1; }
[[ $# == 3 ]] || fail 'Использование: ops/deploy.sh IMAGE SHA DEPLOY_DIR'
image=$1
sha=$2
deploy_dir=$3
[[ "$sha" =~ ^[a-f0-9]{40}$ ]] || fail 'Неверная версия выпуска.'
[[ "$image" =~ ^ghcr\.io/[a-z0-9][a-z0-9._/-]*(@sha256:[a-f0-9]{64}|:[a-f0-9]{40})$ ]] || fail 'Неверный адрес образа GHCR.'
[[ "$image" == *@sha256:* || "$image" == *":$sha" ]] || fail 'Тег образа не совпадает с версией выпуска.'
[[ "$deploy_dir" =~ ^/[a-zA-Z0-9_-]+(/[a-zA-Z0-9_.-]+)+$ ]] || fail 'Нужен абсолютный каталог развёртывания без пробелов.'
[[ "/$deploy_dir/" != *'/../'* && "/$deploy_dir/" != *'/./'* ]] || fail 'Недопустимый каталог развёртывания.'
[[ -f "$deploy_dir/.env" && -f "$deploy_dir/ops/common.sh" ]] || fail 'Сначала выполните первоначальную настройку сервера.'
for command in docker curl python3 flock gzip; do command -v "$command" >/dev/null || fail "Не установлена команда $command."; done
source_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
mkdir -p "$deploy_dir/.deploy" "$deploy_dir/backups"
exec 9> "$deploy_dir/.deploy/deploy.lock"
flock -n 9 || fail 'Другая выкладка уже выполняется.'

# Только Compose разбирает dotenv; shell никогда не исполняет содержимое .env.
source "$deploy_dir/ops/common.sh"
unset APP_IMAGE BUILD
previous=$(compose config --format json | python3 -c 'import json,sys; a=json.load(sys.stdin)["services"]["app"]; print(a["image"]); print(a.get("environment",{}).get("BUILD") or "local")')
previous_image=${previous%%$'\n'*}
previous_build=${previous#*$'\n'}
[[ "$previous_image" =~ ^[a-zA-Z0-9][a-zA-Z0-9._/@:-]*$ && "$previous_build" =~ ^[a-zA-Z0-9._-]+$ ]] || fail 'Не удалось прочитать предыдущую версию.'
timestamp=$(date -u +%Y%m%dT%H%M%SZ)
rollback_dir="$deploy_dir/.deploy/rollback-$timestamp-$sha"
mkdir -p "$rollback_dir"
files=(compose.yaml docker/Caddyfile)
for path in "$source_dir"/ops/*.sh; do
  [[ -f "$path" ]] || continue
  name=${path##*/}
  [[ "$name" =~ ^[a-z0-9][a-z0-9_-]*\.sh$ ]] || fail 'Недопустимое имя служебного скрипта.'
  files+=("ops/$name")
done
for path in "${files[@]}"; do
  [[ -f "$source_dir/$path" ]] || fail "Нет файла выпуска $path."
  if [[ -f "$deploy_dir/$path" ]]; then
    mkdir -p "$rollback_dir/$(dirname -- "$path")"
    cp -p -- "$deploy_dir/$path" "$rollback_dir/$path"
  fi
done
[[ ! -f "$deploy_dir/.release.env" ]] || cp -p -- "$deploy_dir/.release.env" "$rollback_dir/.release.env"
had_app=$(compose ps --status running -q app)
had_proxy=$(compose --profile https ps --status running -q proxy)
promoted=0

wait_http() {
  local endpoint attempt response
  endpoint=$(compose port app 80) || return 1
  [[ "$endpoint" =~ ^127\.0\.0\.1:[0-9]+$ ]] || return 1
  for attempt in {1..30}; do
    if response=$(curl --fail --silent --max-time 3 "http://$endpoint/healthz.php"); then
      if printf '%s' "$response" | python3 -c '
import json,sys
try:
    response=json.load(sys.stdin)
    sys.exit(0 if response.get("status")=="ok" and response.get("build")==sys.argv[1] else 1)
except (ValueError,AttributeError):
    sys.exit(1)' "$BUILD"; then return 0; fi
    fi
    sleep 2
  done
  return 1
}

rollback() {
  local status=$1 path failed=0
  trap - ERR INT TERM HUP
  set +e
  if (( promoted )); then
    printf 'Выкладка не прошла проверку. Восстанавливаем предыдущий код.\n' >&2
    for path in "${files[@]}"; do
      if [[ -f "$rollback_dir/$path" ]]; then
        cp -p -- "$rollback_dir/$path" "$deploy_dir/$path" || failed=1
      else
        rm -f -- "$deploy_dir/$path" || failed=1
      fi
    done
    if [[ -f "$rollback_dir/.release.env" ]]; then
      cp -p -- "$rollback_dir/.release.env" "$deploy_dir/.release.env" || failed=1
    else
      rm -f -- "$deploy_dir/.release.env" || failed=1
    fi
    export APP_IMAGE="$previous_image" BUILD="$previous_build"
    source "$deploy_dir/ops/common.sh"
    if [[ -n "$had_app" ]]; then
      compose up -d --no-deps --wait app && wait_http || failed=1
    else
      compose stop app || failed=1
    fi
    if [[ -n "$had_proxy" ]]; then
      compose --profile https up -d --no-deps --force-recreate --wait proxy || failed=1
    else
      compose --profile https stop proxy || failed=1
    fi
    if (( failed )); then printf 'Автоматический откат требует проверки оператором. Резервная копия БД и файлы отката сохранены.\n' >&2; fi
    printf 'Схема БД автоматически не откатывается; резервные копии лежат в backups/.\n' >&2
  fi
  exit "$status"
}
trap 'rollback "$?"' ERR
trap 'rollback 130' INT
trap 'rollback 143' TERM
trap 'rollback 129' HUP

promoted=1
if [[ "$source_dir" != "$deploy_dir" ]]; then
  for path in "${files[@]}"; do
    mkdir -p "$deploy_dir/$(dirname -- "$path")"
    cp -p -- "$source_dir/$path" "$deploy_dir/$path"
  done
fi
source "$deploy_dir/ops/common.sh"
export APP_IMAGE="$image" BUILD="$sha"
compose config --quiet
compose pull app
compose --profile https run --rm --no-deps -T proxy caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
compose up -d --wait db
# Короткое окно обслуживания: старый код не пишет в БД во время её изменения.
compose stop app
bash "$deploy_dir/ops/backup.sh" "$deploy_dir/backups/before-$timestamp-$sha.sql.gz"
gzip -t "$deploy_dir/backups/before-$timestamp-$sha.sql.gz"
compose run --rm --no-deps app php bin/console.php migrate
compose up -d --no-deps --wait app
wait_http
compose --profile https up -d --no-deps --force-recreate --wait proxy
release_tmp=$(mktemp "$deploy_dir/.release.env.XXXXXX")
printf 'APP_IMAGE=%s\nBUILD=%s\n' "$image" "$sha" > "$release_tmp"
mv -f -- "$release_tmp" "$deploy_dir/.release.env"
trap - ERR INT TERM HUP
printf 'Выпуск %s установлен. Резервная копия БД сохранена.\n' "$sha"
