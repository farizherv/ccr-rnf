# Synology DS220j Automation Scripts

Scripts in this folder automate the deployment runbook for CCR-RNF on Synology.

## 1) Deploy from laptop/macOS

```bash
cd /path/to/ccr-rnf
chmod +x scripts/synology/deploy_ds220j.sh scripts/synology/nas_post_deploy.sh

scripts/synology/deploy_ds220j.sh \
  --nas-host admin@192.168.1.18 \
  --nas-app-dir /volume1/web/ccr-rnf \
  --php-bin php82 \
  --web-user http
```

What it does:
- build frontend locally (`npm run build`)
- run tests (`php artisan test`)
- `rsync` project to NAS
- run post-deploy bootstrap script on NAS

## 2) Run only sync (without remote bootstrap)

```bash
scripts/synology/deploy_ds220j.sh --sync-only
```

## 3) Run post-deploy manually on NAS

```bash
cd /volume1/web/ccr-rnf
chmod +x scripts/synology/nas_post_deploy.sh
./scripts/synology/nas_post_deploy.sh --app-dir /volume1/web/ccr-rnf --php-bin php82 --web-user http
```

## Notes

- `.env` will be created from `.env.synology` if missing.
- `SOFFICE_BINARY` must be valid on NAS for PDF preview.
- Queue worker must stay alive in DSM Task Scheduler:
  - `php82 /volume1/web/ccr-rnf/artisan queue:work database --queue=ccr-heavy,ccr-notify,default --sleep=2 --tries=2 --timeout=180 --max-jobs=200 --max-time=3600 --memory=192`
- Scheduler must run every minute:
  - `php82 /volume1/web/ccr-rnf/artisan schedule:run`
