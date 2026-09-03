#!/bin/sh
set -e
if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
  echo "Applying database migrations…"
  ./node_modules/.bin/prisma migrate deploy
fi
exec "$@"
