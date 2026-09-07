#!/usr/bin/env bash
# One-shot server bootstrap for Ubuntu 22.04/24.04 (no Docker): Node 22, pnpm, pm2, PostgreSQL, the site and the updater.
# Usage (as a user with sudo):  bash deploy/install.sh [/srv/a-deti] [branch]
set -euo pipefail
REPO_URL="${REPO_URL:-https://github.com/AndreyShelemetev/a-deti.git}"
DIR="${1:-/srv/a-deti}"
BRANCH="${2:-main}"
DB_PASS="${DB_PASS:-$(openssl rand -hex 12)}"

echo "== 1/6 system packages =="
sudo apt-get update -qq
sudo apt-get install -y -qq git curl ca-certificates gnupg postgresql postgresql-contrib nginx >/dev/null

echo "== 2/6 Node.js 22 + pnpm + pm2 =="
if ! command -v node >/dev/null || [ "$(node -v | cut -c2-3)" -lt 22 ]; then
  curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - >/dev/null
  sudo apt-get install -y -qq nodejs >/dev/null
fi
sudo corepack enable
sudo npm install -g pm2@latest >/dev/null

echo "== 3/6 PostgreSQL database =="
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='adeti'" | grep -q 1 || sudo -u postgres psql -c "CREATE ROLE adeti WITH LOGIN PASSWORD '$DB_PASS';"
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='adeti'" | grep -q 1 || sudo -u postgres psql -c "CREATE DATABASE adeti OWNER adeti;"

echo "== 4/6 source code =="
if [ ! -d "$DIR/.git" ]; then
  sudo mkdir -p "$DIR" && sudo chown "$USER" "$DIR"
  git clone --branch "$BRANCH" "$REPO_URL" "$DIR"
fi
cd "$DIR"
if [ ! -f .env ]; then
  cp .env.example .env
  sed -i "s#^DATABASE_URL=.*#DATABASE_URL=\"postgresql://adeti:${DB_PASS}@localhost:5432/adeti?schema=public\"#" .env
  sed -i "s#^AUTH_SECRET=.*#AUTH_SECRET=\"$(openssl rand -hex 32)\"#" .env
  sed -i "s#^UPDATER_TOKEN=.*#UPDATER_TOKEN=\"$(openssl rand -hex 24)\"#" .env
  sed -i "s#^UPDATER_URL=.*#UPDATER_URL=\"http://127.0.0.1:4100\"#" .env
  sed -i "s#^UPDATER_DEFAULT_REF=.*#UPDATER_DEFAULT_REF=\"$BRANCH\"#" .env
  sed -i "s#^REPO_DIR=.*#REPO_DIR=\"$DIR\"#" .env
  echo "!! Edit $DIR/.env: NEXT_PUBLIC_SITE_URL, ADMIN_EMAIL, ADMIN_PASSWORD (and SMTP if needed)"
fi
mkdir -p logs

echo "== 5/6 build =="
export NEXT_TELEMETRY_DISABLED=1 CI=1
pnpm install --frozen-lockfile
pnpm exec prisma migrate deploy
pnpm exec prisma db seed
NEXT_PUBLIC_APP_COMMIT="$(git rev-parse HEAD)" pnpm exec next build

echo "== 6/6 pm2 =="
pm2 start ecosystem.config.cjs
pm2 save
sudo env PATH="$PATH:$(dirname "$(command -v node)")" pm2 startup systemd -u "$USER" --hp "$HOME" >/dev/null || true
echo
echo "Done. Site: http://127.0.0.1:3000  Admin: /admin/  (nginx config example: deploy/nginx.conf.example)"
