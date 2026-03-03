#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/volume1/web/ccr-rnf"
PHP_BIN="php82"
WEB_USER="http"
RUN_MIGRATION=1

usage() {
  cat <<'USAGE'
Usage:
  scripts/synology/nas_post_deploy.sh [options]

Options:
  --app-dir <path>      CCR app path (default: /volume1/web/ccr-rnf)
  --php-bin <binary>    PHP binary (default: php82)
  --web-user <user>     Web user (default: http)
  --skip-migrate        Skip php artisan migrate --force
  -h, --help            Show this help
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --app-dir)
      APP_DIR="$2"
      shift 2
      ;;
    --php-bin)
      PHP_BIN="$2"
      shift 2
      ;;
    --web-user)
      WEB_USER="$2"
      shift 2
      ;;
    --skip-migrate)
      RUN_MIGRATION=0
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "ERROR: unknown option: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ ! -d "$APP_DIR" ]]; then
  echo "ERROR: app dir not found: $APP_DIR" >&2
  exit 1
fi

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "ERROR: PHP binary not found: $PHP_BIN" >&2
  exit 1
fi

if [[ ! -f "$APP_DIR/.env" ]]; then
  if [[ -f "$APP_DIR/.env.synology" ]]; then
    cp "$APP_DIR/.env.synology" "$APP_DIR/.env"
    echo "INFO: .env created from .env.synology"
  else
    echo "ERROR: .env and .env.synology are missing in $APP_DIR" >&2
    exit 1
  fi
fi

run_privileged() {
  if [[ "${EUID}" -eq 0 ]]; then
    "$@"
  elif command -v sudo >/dev/null 2>&1; then
    sudo "$@"
  else
    echo "ERROR: need root privileges for: $*" >&2
    exit 1
  fi
}

run_as_web_user() {
  if [[ "$(id -un)" == "$WEB_USER" ]]; then
    "$@"
  elif command -v sudo >/dev/null 2>&1; then
    sudo -u "$WEB_USER" "$@"
  elif [[ "${EUID}" -eq 0 ]]; then
    su -m "$WEB_USER" -c "$*"
  else
    echo "ERROR: cannot run as web user '$WEB_USER' (sudo not available)." >&2
    exit 1
  fi
}

echo "[1/5] Fix ownership and permission"
run_privileged chown -R "${WEB_USER}:${WEB_USER}" "$APP_DIR"
run_privileged chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "[2/5] Run Laravel setup"
cd "$APP_DIR"
run_as_web_user "$PHP_BIN" artisan key:generate --force
run_as_web_user "$PHP_BIN" artisan storage:link || true

if (( RUN_MIGRATION == 1 )); then
  run_as_web_user "$PHP_BIN" artisan migrate --force
fi

run_as_web_user "$PHP_BIN" artisan config:clear
run_as_web_user "$PHP_BIN" artisan cache:clear
run_as_web_user "$PHP_BIN" artisan route:clear
run_as_web_user "$PHP_BIN" artisan view:clear
run_as_web_user "$PHP_BIN" artisan config:cache
run_as_web_user "$PHP_BIN" artisan route:cache
run_as_web_user "$PHP_BIN" artisan view:cache

echo "[3/5] Verify core dependencies"
SOFFICE_ENV="$(grep -E '^SOFFICE_BINARY=' "$APP_DIR/.env" | tail -n 1 | cut -d'=' -f2- | tr -d '"' | xargs || true)"
if [[ -n "$SOFFICE_ENV" ]]; then
  if [[ ! -x "$SOFFICE_ENV" ]]; then
    echo "ERROR: SOFFICE_BINARY is set but not executable: $SOFFICE_ENV" >&2
    exit 1
  fi
  echo " - soffice (.env): $SOFFICE_ENV"
elif command -v soffice >/dev/null 2>&1; then
  echo " - soffice (PATH): $(command -v soffice)"
else
  echo "ERROR: soffice not found. PDF preview conversion will fail." >&2
  exit 1
fi

PHP_MODULES="$(run_as_web_user "$PHP_BIN" -m | tr '[:upper:]' '[:lower:]')"
for ext in gd mbstring pdo_mysql xml zip; do
  if ! grep -qx "$ext" <<<"$PHP_MODULES"; then
    echo "WARN: PHP extension '$ext' not detected in $PHP_BIN -m output." >&2
  fi
done

echo "[4/5] Print DSM Task Scheduler commands"
cat <<TASKS
Create these DSM tasks:

1) Every minute:
   $PHP_BIN $APP_DIR/artisan schedule:run

2) Startup/always-on worker:
   $PHP_BIN $APP_DIR/artisan queue:work database --queue=ccr-heavy,ccr-notify,default --sleep=2 --tries=2 --timeout=180 --max-jobs=200 --max-time=3600 --memory=192
TASKS

echo "[5/5] Done"
echo "Post-check:"
echo " - Open app login page and create 1 test report."
echo " - Trigger preview/export; first request may return 503 Retry-After while queue is processing."
