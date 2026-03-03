#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
NAS_HOST="${NAS_HOST:-admin@192.168.1.18}"
NAS_APP_DIR="${NAS_APP_DIR:-/volume1/web/ccr-rnf}"
PHP_BIN="${PHP_BIN:-php82}"
WEB_USER="${WEB_USER:-http}"
RUN_BUILD=1
RUN_TESTS=1
RUN_REMOTE_BOOTSTRAP=1

usage() {
  cat <<'USAGE'
Usage:
  scripts/synology/deploy_ds220j.sh [options]

Options:
  --nas-host <user@host>        NAS SSH target (default: admin@192.168.1.18)
  --nas-app-dir <path>          App path on NAS (default: /volume1/web/ccr-rnf)
  --php-bin <binary>            PHP binary on NAS (default: php82)
  --web-user <user>             Web user on NAS (default: http)
  --skip-build                  Skip frontend build step
  --skip-tests                  Skip php artisan test step
  --sync-only                   Only rsync files, skip remote bootstrap
  -h, --help                    Show this help

Environment variables:
  NAS_HOST, NAS_APP_DIR, PHP_BIN, WEB_USER
USAGE
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "ERROR: command not found: $1" >&2
    exit 1
  fi
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --nas-host)
      NAS_HOST="$2"
      shift 2
      ;;
    --nas-app-dir)
      NAS_APP_DIR="$2"
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
    --skip-build)
      RUN_BUILD=0
      shift
      ;;
    --skip-tests)
      RUN_TESTS=0
      shift
      ;;
    --sync-only)
      RUN_REMOTE_BOOTSTRAP=0
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

require_cmd rsync
require_cmd ssh
require_cmd php

if (( RUN_BUILD == 1 )); then
  require_cmd npm
fi

cd "$ROOT_DIR"

echo "[1/4] Local preflight"
if [[ ! -f ".env.synology" ]]; then
  echo "ERROR: .env.synology not found in repository root." >&2
  exit 1
fi

if (( RUN_BUILD == 1 )); then
  echo " - Building frontend assets"
  if [[ ! -d "node_modules" ]]; then
    npm ci
  fi
  npm run build
fi

if (( RUN_TESTS == 1 )); then
  echo " - Running tests"
  php artisan test --stop-on-failure
fi

echo "[2/4] Sync files to NAS"
rsync -avh --delete \
  --exclude='.git/' \
  --exclude='node_modules/' \
  --exclude='.env' \
  --exclude='storage/logs/*.log' \
  --exclude='storage/framework/cache/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  "$ROOT_DIR/" "${NAS_HOST}:${NAS_APP_DIR}/"

if (( RUN_REMOTE_BOOTSTRAP == 0 )); then
  echo "[3/4] Remote bootstrap skipped (--sync-only)"
  echo "[4/4] Done"
  exit 0
fi

echo "[3/4] Run NAS post-deploy bootstrap"
ssh -tt "$NAS_HOST" "chmod +x '${NAS_APP_DIR}/scripts/synology/nas_post_deploy.sh' && '${NAS_APP_DIR}/scripts/synology/nas_post_deploy.sh' --app-dir '${NAS_APP_DIR}' --php-bin '${PHP_BIN}' --web-user '${WEB_USER}'"

echo "[4/4] Done"
echo "Next:"
echo " - Ensure DSM Task Scheduler has queue worker and schedule:run commands."
echo " - Verify app from browser: ${APP_URL:-https://ccr-rnf.internal}"
