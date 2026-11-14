#!/usr/bin/env bash
# Integration checks use their own Compose project, secrets and disposable volume.
set -euo pipefail
ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
cd "$ROOT"
if ! command -v docker >/dev/null 2>&1 && [[ -x /Applications/Docker.app/Contents/Resources/bin/docker ]]; then
    export PATH="/Applications/Docker.app/Contents/Resources/bin:$PATH"
fi
command -v docker >/dev/null 2>&1 || { echo 'Docker is required.' >&2; exit 1; }
TEMP_DIR=$(mktemp -d "${TMPDIR:-/tmp}/a-deti-deployment.XXXXXXXX")
export AT_ENV_FILE="$TEMP_DIR/.env"
export AT_RELEASE_FILE="$TEMP_DIR/.release.env"
export AT_BACKUP_DIR="$TEMP_DIR/backups"
export AT_SECRETS_DIR="$TEMP_DIR/secrets"
export COMPOSE_PROJECT_NAME="adetici_$(date +%s)_$$"
export APP_IMAGE="a-deti:ci-$(date +%s)-$$"
mkdir -p "$AT_SECRETS_DIR" "$AT_BACKUP_DIR"
umask 077
openssl rand -hex 24 > "$AT_SECRETS_DIR/db_password"
openssl rand -hex 24 > "$AT_SECRETS_DIR/db_root_password"
cat > "$AT_ENV_FILE" <<EOF
COMPOSE_PROJECT_NAME=$COMPOSE_PROJECT_NAME
APP_IMAGE=$APP_IMAGE
BUILD=integration-test
APP_PORT=${AT_TEST_PORT:-18080}
HTTP_PORT=${AT_TEST_HTTP_PORT:-18081}
HTTPS_PORT=${AT_TEST_HTTPS_PORT:-18443}
AT_SECRETS_DIR=$AT_SECRETS_DIR
MARIADB_DATABASE=atour
MARIADB_USER=atour
SITE_ADDRESS=http://localhost
ACME_EMAIL=none@example.com
EOF
# shellcheck source=../ops/common.sh
source "$ROOT/ops/common.sh"
cleanup() {
    local status=$?
    if (( status != 0 )); then
        echo 'Integration check failed; container logs:' >&2
        compose --profile https logs --tail=65 app db proxy >&2 || true
    fi
    # A unique generated project name ensures these are only the test volumes.
    compose --profile https down --volumes --remove-orphans >/dev/null 2>&1 || true
    rm -rf -- "$TEMP_DIR"
    return "$status"
}
trap cleanup EXIT
assert_eq() {
    [[ "$1" == "$2" ]] || { printf 'Expected %q, got %q\n' "$2" "$1" >&2; exit 1; }
}
console() { compose run --rm --no-deps -T app php bin/console.php "$@"; }
query() { compose run --rm --no-deps -T app php -r 'require_once "inc/standalone/bootstrap.php"; '"$1"; }
snapshot() {
    query '$p=db(); echo json_encode([
        $p->query("SELECT COUNT(*) FROM tours")->fetchColumn(),
        $p->query("SELECT title FROM tours ORDER BY id LIMIT 1")->fetchColumn(),
        $p->query("SELECT v FROM atour_settings WHERE k=\"home.ticketText\"")->fetchColumn(),
        $p->query("SELECT COUNT(*) FROM requests WHERE name=\"Integration fixture\"")->fetchColumn(),
        $p->query("SELECT HEX(data) FROM media WHERE name=\"integration.bin\"")->fetchColumn(),
        $p->query("SELECT password_hash FROM admin_users WHERE login=\"integration-admin\"")->fetchColumn()
    ], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);'
}
http_status() { curl --silent --show-error --output "$TEMP_DIR/http-body" --write-out '%{http_code}' "$BASE_URL$1"; }

printf 'Building isolated application image\n'
compose build --pull app
compose up -d --wait db
printf 'Initializing an empty database\n'
console init --seed
console status
export AT_ADMIN_PASSWORD='Integration-only-password-2026!'
compose run --rm --no-deps -T -e AT_ADMIN_PASSWORD app php bin/console.php admin:create --login=integration-admin
unset AT_ADMIN_PASSWORD
query '$p=db();
    $p->exec("UPDATE tours SET title=\"Restored custom tour\" WHERE id=(SELECT id FROM (SELECT MIN(id) AS id FROM tours) AS first_tour)");
    $p->exec("UPDATE atour_settings SET v=\"Restored custom home content\" WHERE k=\"home.ticketText\"");
    $p->exec("UPDATE atour_settings SET v=\"6\" WHERE k=\"schema_version\"");
    $p->prepare("INSERT INTO requests (kind,name,email,message) VALUES (?,?,?,?)")
      ->execute(["REQUEST","Integration fixture","deployment@example.invalid","Keep during backup and deploy"]);
    $binary="\x00\xff\x01\xc3\x28\x00test\n";
    $p->prepare("INSERT INTO media (mime,name,size,data) VALUES (?,?,?,?)")
      ->execute(["application/octet-stream","integration.bin",strlen($binary),$binary]);'
BEFORE=$(snapshot)
console migrate
console init --seed
assert_eq "$(snapshot)" "$BEFORE"
# A second creation request must not replace the account or its password.
export AT_ADMIN_PASSWORD='Another-integration-only-password!'
compose run --rm --no-deps -T -e AT_ADMIN_PASSWORD app php bin/console.php admin:create --login=integration-admin
unset AT_ADMIN_PASSWORD
assert_eq "$(snapshot)" "$BEFORE"

printf 'Checking public routes, media and admin authentication\n'
compose up -d --wait app
PORT=$(compose port app 80)
BASE_URL="http://$PORT"
assert_eq "$(http_status /healthz.php)" 200
assert_eq "$(http_status /)" 200
grep -qi '<!doctype html' "$TEMP_DIR/http-body"
assert_eq "$(http_status /tour/)" 200
assert_eq "$(http_status /assets/style.css)" 200
assert_eq "$(http_status /site-admin)" 200
grep -q 'Вход в управление сайтом' "$TEMP_DIR/http-body"
for path in /data/seed.json /inc/init.php /db.php.example /.env /compose.yaml /Dockerfile /ops/common.sh /tests/deployment.sh; do
    CODE=$(http_status "$path")
    [[ "$CODE" == 403 || "$CODE" == 404 ]] || { echo "Private file accessible: $path ($CODE)" >&2; exit 1; }
done
curl --fail --silent --show-error --cookie-jar "$TEMP_DIR/cookies" \
    --data-urlencode do=login --data-urlencode login=integration-admin \
    --data-urlencode 'password=Integration-only-password-2026!' \
    --output "$TEMP_DIR/login-body" --dump-header "$TEMP_DIR/login-headers" "$BASE_URL/site-admin"
grep -qE '^HTTP/[0-9.]+ 303' "$TEMP_DIR/login-headers"
curl --fail --silent --show-error --cookie "$TEMP_DIR/cookies" --output "$TEMP_DIR/admin-body" "$BASE_URL/site-admin?tab=tours"
if grep -q 'Вход в управление сайтом' "$TEMP_DIR/admin-body"; then
    echo 'Admin session was not retained.' >&2; exit 1
fi
MEDIA_ID=$(query 'echo db()->query("SELECT id FROM media WHERE name=\"integration.bin\"")->fetchColumn();')
curl --fail --silent --show-error "$BASE_URL/media.php?id=$MEDIA_ID" > "$TEMP_DIR/media.bin"
EXPECTED_HEX=$(query 'echo db()->query("SELECT HEX(data) FROM media WHERE name=\"integration.bin\"")->fetchColumn();')
ACTUAL_HEX=$(od -An -v -tx1 "$TEMP_DIR/media.bin" | tr -d ' \n' | tr '[:lower:]' '[:upper:]')
assert_eq "$ACTUAL_HEX" "$EXPECTED_HEX"

printf 'Checking reverse proxy configuration, readiness and public forwarding\n'
compose --profile https run --rm --no-deps -T proxy caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
compose --profile https up -d --no-deps --wait proxy
# This is the same readiness command used by the production Compose healthcheck.
compose --profile https exec -T proxy wget -q -O /dev/null http://127.0.0.1:2019/config/
PROXY_URL="http://127.0.0.1:${AT_TEST_HTTP_PORT:-18081}"
PROXY_STATUS=$(curl --silent --show-error --header 'Host: localhost' --output "$TEMP_DIR/proxy-health" --write-out '%{http_code}' "$PROXY_URL/healthz.php")
assert_eq "$PROXY_STATUS" 200
grep -q '"status":"ok"' "$TEMP_DIR/proxy-health"
grep -q '"build":"integration-test"' "$TEMP_DIR/proxy-health"
assert_eq "$(curl --silent --show-error --header 'Host: localhost' --output "$TEMP_DIR/proxy-home" --write-out '%{http_code}' "$PROXY_URL/")" 200
grep -qi '<!doctype html' "$TEMP_DIR/proxy-home"

printf 'Checking consistent backup, import refusal and round-trip restoration\n'
bash "$ROOT/ops/backup.sh" "$TEMP_DIR/roundtrip.sql.gz"
gzip -t "$TEMP_DIR/roundtrip.sql.gz"
if bash "$ROOT/ops/restore.sh" "$TEMP_DIR/roundtrip.sql.gz" > "$TEMP_DIR/refused-import" 2>&1; then
    echo 'Restore unexpectedly accepted a nonempty database.' >&2; exit 1
fi
assert_eq "$(snapshot)" "$BEFORE"
assert_eq "$(http_status /healthz.php)" 200
# Only the unique disposable integration database is cleared here.
compose stop app
query '$p=db(); $p->exec("SET FOREIGN_KEY_CHECKS=0"); foreach($p->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {$p->exec("DROP TABLE `".str_replace("`","``",$t)."`");}'
bash "$ROOT/ops/restore.sh" "$TEMP_DIR/roundtrip.sql.gz"
console migrate
assert_eq "$(snapshot)" "$BEFORE"
compose up -d --wait app
assert_eq "$(http_status /healthz.php)" 200
compose restart app db
compose up -d --wait db app
assert_eq "$(snapshot)" "$BEFORE"
assert_eq "$(http_status /healthz.php)" 200
query 'db()->exec("UPDATE atour_settings SET v=\"999\" WHERE k=\"schema_version\"");'
if console migrate; then
    echo 'Migration unexpectedly accepted a future schema version.' >&2; exit 1
fi
assert_eq "$(http_status /healthz.php)" 503
query 'db()->exec("UPDATE atour_settings SET v=\"9\" WHERE k=\"schema_version\"");'
assert_eq "$(snapshot)" "$BEFORE"
# An intentionally empty restored catalog is content too; web/CLI must not reseed it.
query 'db()->exec("DELETE FROM tours");'
console init --seed
assert_eq "$(http_status /)" 200
assert_eq "$(query 'echo db()->query("SELECT COUNT(*) FROM tours")->fetchColumn();')" 0
assert_eq "$(query 'echo db()->query("SELECT COUNT(*) FROM requests WHERE name=\"Integration fixture\"")->fetchColumn();')" 1
printf 'PASS: fresh install, safe migration, routes, admin, proxy health/forwarding, BLOB backup/restore and persistent restart\n'
