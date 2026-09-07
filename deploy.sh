#!/usr/bin/env bash
# Deploys the given git ref (branch or tag). Called by the updater sidecar or manually: ./deploy.sh main
set -euo pipefail
REF="${1:-main}"
MODE="${DEPLOY_MODE:-docker}"
REMOTE="${GIT_REMOTE:-origin}"
cd "${REPO_DIR:-$(dirname "$0")}"

echo "== Deploying '$REF' (mode: $MODE) =="
git config --global --add safe.directory "$(pwd)" >/dev/null 2>&1 || true
git fetch --prune "$REMOTE" "$REF"
if git show-ref --verify --quiet "refs/remotes/$REMOTE/$REF"; then
  git checkout -q -B "$REF" "$REMOTE/$REF"
  git reset -q --hard "$REMOTE/$REF"
else
  git checkout -q -f "$REF"   # tag or commit
fi
echo "== Now at $(git rev-parse --short HEAD): $(git log -1 --format=%s) =="

case "$MODE" in
  docker)
    docker compose build web
    docker compose up -d --no-deps web
    docker image prune -f >/dev/null 2>&1 || true
    ;;
  pm2)
    corepack enable >/dev/null 2>&1 || true
    pnpm install --frozen-lockfile
    pnpm prisma migrate deploy
    pnpm exec next build
    pm2 reload "${PM2_APP:-a-deti}" --update-env || pm2 start pnpm --name "${PM2_APP:-a-deti}" -- start
    ;;
  dry)
    echo "dry run: pretending to build…"; sleep 2; echo "dry run: done"
    ;;
  *)
    echo "Unknown DEPLOY_MODE: $MODE" >&2; exit 2
    ;;
esac
echo "== Deploy finished =="
