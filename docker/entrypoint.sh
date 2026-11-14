#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == apache2-foreground && -n "${AT_DB_PASSWORD_FILE:-}" ]]; then
  # Local Compose secrets retain host permissions. The web worker must be able
  # to read its secret without making the host's private file world-readable.
  install -d -o root -g www-data -m 0750 /run/atour
  install -o root -g www-data -m 0640 "$AT_DB_PASSWORD_FILE" /run/atour/db_password
  export AT_DB_PASSWORD_FILE=/run/atour/db_password
fi
exec docker-php-entrypoint "$@"
