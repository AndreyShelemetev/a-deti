#!/usr/bin/env bash
# Shared by local installation, backups and deployment; never source .env files.
set -euo pipefail
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
AT_ENV_FILE="${AT_ENV_FILE:-$PROJECT_DIR/.env}"
AT_RELEASE_FILE="${AT_RELEASE_FILE:-$PROJECT_DIR/.release.env}"
DOCKER_BIN="${DOCKER_BIN:-docker}"

compose() {
  local args=(--project-directory "$PROJECT_DIR" -f "$PROJECT_DIR/compose.yaml" --env-file "$AT_ENV_FILE")
  if [[ ! -f "$AT_ENV_FILE" ]]; then
    echo 'Нет .env: сначала выполните bash ops/setup.sh --local.' >&2
    return 1
  fi
  if [[ -f "$AT_RELEASE_FILE" ]]; then args+=(--env-file "$AT_RELEASE_FILE"); fi
  "$DOCKER_BIN" compose "${args[@]}" "$@"
}

site_url() {
  printf 'http://%s' "$(compose port app 80)"
}
