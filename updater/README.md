# Updater (обновление сайта из админки)

Маленький сервис рядом с сайтом (под pm2: процесс `a-deti-updater` из `ecosystem.config.cjs`; в Docker: контейнер `updater`). Админка (`/admin/update/`) обращается к нему по внутреннему адресу
(`UPDATER_URL`, по умолчанию `http://updater:4100` в docker-compose) с токеном `UPDATER_TOKEN`.

По команде «Обновить» сервис запускает `deploy.sh <ref>` в каталоге репозитория:

- `DEPLOY_MODE=pm2` (по умолчанию): `git fetch` → `git checkout` → `pnpm install` → `prisma migrate deploy` → `next build` → `pm2 reload`.
- `DEPLOY_MODE=docker`: `git fetch` → `git checkout` → `docker compose build web` → `docker compose up -d web`
  (миграции применяются при старте контейнера `web`).

Логи каждого запуска лежат в `updater/logs/`. Сервис не должен быть доступен снаружи: не публикуйте порт 4100.
