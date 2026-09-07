#!/usr/bin/env bash
# Deploys the given git ref (branch, tag or commit). Called by the updater service or manually: ./deploy.sh main
set -euo pipefail
REF="${1:-main}"
MODE="${DEPLOY_MODE:-pm2}"
REMOTE="${GIT_REMOTE:-origin}"
APP="${PM2_APP:-a-deti}"
cd "${REPO_DIR:-$(dirname "$0")}"

echo "== Deploying '$REF' (mode: $MODE) in $(pwd) =="
git config --global --add safe.directory "$(pwd)" >/dev/null 2>&1 || true
BEFORE="$(git rev-parse HEAD)"
git fetch --prune "$REMOTE" "$REF" 2>&1 || git fetch --prune "$REMOTE"
if git show-ref --verify --quiet "refs/remotes/$REMOTE/$REF"; then
  git checkout -q -B "$REF" "$REMOTE/$REF"
  git reset -q --hard "$REMOTE/$REF"
else
  git checkout -q -f "$REF"   # tag or commit
fi
AFTER="$(git rev-parse HEAD)"
echo "== Now at $(git rev-parse --short HEAD): $(git log -1 --format=%s) =="
export NEXT_PUBLIC_APP_COMMIT="$AFTER"

case "$MODE" in
  pm2)
    export NEXT_TELEMETRY_DISABLED=1 CI=1
    corepack enable >/dev/null 2>&1 || true
    echo "== pnpm install =="
    pnpm install --frozen-lockfile --prefer-offline
    echo "== prisma migrate deploy =="
    pnpm exec prisma migrate deploy
    echo "== next build =="
    pnpm exec next build
    echo "== restarting $APP =="
    if pm2 describe "$APP" >/dev/null 2>&1; then
      pm2 reload "$APP" --update-env
    else
      pm2 start ecosystem.config.cjs --only "$APP"
    fi
    pm2 save >/dev/null 2>&1 || true
    if git diff --name-only "$BEFORE" "$AFTER" | grep -q '^updater/'; then
      echo "!! updater/ changed: run 'pm2 restart ${APP}-updater' on the server to apply it"
    fi
    ;;
  docker)
    docker compose build web
    docker compose up -d --no-deps web
    docker image prune -f >/dev/null 2>&1 || true
    ;;
  dry)
    echo "dry run: pretending to build…"; sleep 2; echo "dry run: done"
    ;;
  *)
    echo "Unknown DEPLOY_MODE: $MODE" >&2; exit 2
    ;;
esac
echo "== Deploy finished =="
