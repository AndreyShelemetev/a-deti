#!/usr/bin/env bash
# Проверяет оркестрацию выкладки с подменённым Compose, без Docker и SSH-сервера.
set -euo pipefail
root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
temp=$(mktemp -d /tmp/a-deti-deploy-test.XXXXXXXX)
cleanup() {
  local status=$?
  if (( status )); then
    for output in "$temp"/*/output; do [[ ! -f "$output" ]] || cat "$output" >&2; done
  fi
  rm -rf -- "$temp"
  exit "$status"
}
trap cleanup EXIT
export TEST_NEW_SHA=bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
export TEST_OLD_SHA=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
export TEST_OLD_IMAGE="ghcr.io/example/a-deti:$TEST_OLD_SHA"
export TEST_NEW_IMAGE="ghcr.io/example/a-deti@sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc"
mkdir -p "$temp/bin"
cat > "$temp/bin/docker" <<'SH'
#!/usr/bin/env bash
echo 'Прямой вызов Docker в mock-тесте запрещён.' >&2
exit 99
SH
cat > "$temp/bin/curl" <<'SH'
#!/usr/bin/env bash
if [[ "$TEST_FAILURE" == health && "${BUILD:-}" == "$TEST_NEW_SHA" ]]; then
  printf '{"status":"ok","build":"%s"}' "$TEST_OLD_SHA"
  exit 0
fi
printf '{"status":"ok","build":"%s"}' "${BUILD:-local}"
SH
printf '#!/usr/bin/env bash\nexit 0\n' > "$temp/bin/sleep"
printf '#!/usr/bin/env bash\nexit 0\n' > "$temp/bin/flock"
chmod +x "$temp/bin/"*
export PATH="$temp/bin:$PATH"

for scenario in success backup migration health interrupt proxy-config proxy-runtime; do
  case_dir="$temp/$scenario"
  release="$case_dir/release"
  target="$case_dir/site"
  mkdir -p "$release/ops" "$release/docker" "$target/ops" "$target/docker" "$target/.secrets"
  export TEST_LOG="$case_dir/compose.log" TEST_FAILURE="$scenario"
  printf 'old-compose\n' > "$target/compose.yaml"
  printf 'new-compose\n' > "$release/compose.yaml"
  printf 'old-caddy\n' > "$target/docker/Caddyfile"
  printf 'new-caddy\n' > "$release/docker/Caddyfile"
  printf 'VALUE=not-shell-$(touch %s/sentinel)\n' "$case_dir" > "$target/.env"
  printf 'private-fixture\n' > "$target/.secrets/db_password"
  printf 'APP_IMAGE=%s\nBUILD=%s\n' "$TEST_OLD_IMAGE" "$TEST_OLD_SHA" > "$target/.release.env"
  cp "$root/ops/deploy.sh" "$release/ops/deploy.sh"
  printf '# previous deploy script\n' > "$target/ops/deploy.sh"
  cat > "$release/ops/common.sh" <<'SH'
#!/usr/bin/env bash
# new common
compose() {
  printf '%s|%s\n' "${APP_IMAGE:-$TEST_OLD_IMAGE}" "$*" >> "$TEST_LOG"
  if [[ "${1:-}" == --profile ]]; then shift 2; fi
  case "$*" in
    'config --format json') printf '{"services":{"app":{"image":"%s","environment":{"BUILD":"%s"}}}}\n' "$TEST_OLD_IMAGE" "$TEST_OLD_SHA" ;;
    'ps --status running -q app'|'ps --status running -q proxy') printf 'existing-container\n' ;;
    'port app 80') printf '127.0.0.1:18080\n' ;;
    'run --rm --no-deps app php bin/console.php migrate')
      if [[ "$TEST_FAILURE" == interrupt ]]; then kill -TERM "$$"; fi
      [[ "$TEST_FAILURE" != migration ]] ;;
    'run --rm --no-deps -T proxy caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile') [[ "$TEST_FAILURE" != proxy-config ]] ;;
    'up -d --no-deps --force-recreate --wait proxy')
      [[ "$TEST_FAILURE" != proxy-runtime || "${APP_IMAGE:-}" != "$TEST_NEW_IMAGE" ]] ;;
    *) return 0 ;;
  esac
}
SH
  sed 's/# new common/# old common/' "$release/ops/common.sh" > "$target/ops/common.sh"
  cat > "$release/ops/backup.sh" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
[[ "$TEST_FAILURE" != backup ]] || exit 17
printf 'consistent database fixture\n' | gzip > "$1"
SH
  cp "$release/ops/backup.sh" "$target/ops/backup.sh"
  if bash "$release/ops/deploy.sh" "$TEST_NEW_IMAGE" "$TEST_NEW_SHA" "$target" > "$case_dir/output" 2>&1; then result=0; else result=$?; fi
  [[ ! -e "$case_dir/sentinel" ]]
  grep -Fqx "VALUE=not-shell-\$(touch $case_dir/sentinel)" "$target/.env"
  grep -Fqx 'private-fixture' "$target/.secrets/db_password"
  if [[ "$scenario" == success ]]; then
    [[ "$result" == 0 ]]
    grep -Fqx "APP_IMAGE=$TEST_NEW_IMAGE" "$target/.release.env"
    grep -Fqx "BUILD=$TEST_NEW_SHA" "$target/.release.env"
    grep -Fqx 'new-compose' "$target/compose.yaml"
    grep -Fq '|run --rm --no-deps app php bin/console.php migrate' "$TEST_LOG"
    grep -Fq '|--profile https up -d --no-deps --force-recreate --wait proxy' "$TEST_LOG"
    gzip -t "$target"/backups/*.sql.gz
  else
    [[ "$result" != 0 ]]
    grep -Fqx "APP_IMAGE=$TEST_OLD_IMAGE" "$target/.release.env"
    grep -Fqx "BUILD=$TEST_OLD_SHA" "$target/.release.env"
    grep -Fqx 'old-compose' "$target/compose.yaml"
    grep -Fqx 'old-caddy' "$target/docker/Caddyfile"
    grep -Fq '# old common' "$target/ops/common.sh"
    grep -Fq "$TEST_OLD_IMAGE|up -d --no-deps --wait app" "$TEST_LOG"
    if [[ "$scenario" == backup || "$scenario" == proxy-config ]]; then
      ! grep -Fq '|run --rm --no-deps app php bin/console.php migrate' "$TEST_LOG"
    else
      gzip -t "$target"/backups/*.sql.gz
    fi
  fi
  ! grep -Eq '\|.*(down|volume (rm|prune)|import|restore)' "$TEST_LOG"
  printf 'Проверка выкладки %s: OK\n' "$scenario"
done
