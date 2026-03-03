# CCR-RNF — Deployment Guide (Synology DS220j - Internal Server)

> Panduan lengkap migrasi CCR-RNF dari XAMPP ke Synology DS220j sebagai server internal production.

---

## Prasyarat

| Item | Keterangan |
|------|------------|
| NAS | Synology DS220j, DSM terbaru |
| PHP | 8.2+ (install via Package Center) |
| DB | MariaDB 10 (install via Package Center) |
| Web | Web Station (install via Package Center) |
| Backup | Hyper Backup (install via Package Center) |

---

## Step 1 — Install Packages di DSM

Masuk **DSM**: `http://192.168.1.18:5000` → **Package Center**, install:

1. ✅ Web Station
2. ✅ PHP 8.2
3. ✅ MariaDB 10
4. ✅ phpMyAdmin *(opsional)*
5. ✅ Hyper Backup
6. ✅ VPN Server *(opsional, untuk akses luar)*

---

## Step 2 — Setting PHP

**Web Station → Script Language Settings → PHP 8.2 → Edit**

```ini
memory_limit = 384M
upload_max_filesize = 16M
post_max_size = 64M
max_execution_time = 240
max_input_time = 240
max_input_vars = 20000
max_file_uploads = 120
```

Extensions aktifkan: `pdo_mysql`, `mbstring`, `openssl`, `xml`, `xmlreader`, `xmlwriter`, `gd`, `zip`, `zlib`, `curl`, `fileinfo`, `bcmath`, `dom`, `simplexml`, `iconv`, `ctype`

> Opsional (jika profile FPM bisa diatur): `pm.max_children=2`, `pm.start_servers=1`, `pm.min_spare_servers=1`, `pm.max_spare_servers=2`, `pm.max_requests=200`

---

## Step 3 — Buat Database

1. Buka `http://192.168.1.18/phpMyAdmin`
2. Buat database: `ccr_rnf` (collation: `utf8mb4_unicode_ci`)
3. Buat user: `ccr_user` dengan **All privileges** pada `ccr_rnf`
4. Import file `ccr_rnf.sql`

---

## Step 4 — Persiapan di Laptop (Sebelum Upload)

```bash
# 1. Build frontend (WAJIB - DS220j tidak bisa npm run build)
cd ~/Projects/ccr-rnf
npm install
npm run build

# 2. Clear cache
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# 3. Export database
mysqldump -u root ccr_rnf > ~/Desktop/ccr_rnf.sql
```

---

## Step 5 — Upload Project ke NAS

### Via File Station / SCP
Upload **seluruh project** (termasuk `vendor/` dan `public/build/`) ke:
```
/volume1/web/ccr-rnf/
```

### Via rsync (disarankan, aman untuk dotfile)
```bash
rsync -avh --delete \
  --exclude='.git/' \
  --exclude='node_modules/' \
  --exclude='.env' \
  ~/Projects/ccr-rnf/ admin@192.168.1.18:/volume1/web/ccr-rnf/
```

### Via SCP (opsional)
```bash
scp -r ~/Projects/ccr-rnf/. admin@192.168.1.18:/volume1/web/ccr-rnf/
```

> ⚠️ **JANGAN** upload `node_modules/`
> ⚠️ **JANGAN** upload `.env` lokal — gunakan `.env.synology` sebagai basis

---

## Step 6 — Setup Environment

```bash
# SSH ke NAS
ssh admin@192.168.1.18

# Copy .env
cd /volume1/web/ccr-rnf
sudo cp .env.synology .env

# Edit .env — isi nilai yang kosong:
sudo vi .env
# Isi: APP_KEY, DB_PASSWORD, MAIL_PASSWORD, WEB_PUSH_VAPID_*, SOFFICE_BINARY
```

---

## Step 7 — Set Permission (WAJIB)

```bash
# Ownership
sudo chown -R http:http /volume1/web/ccr-rnf

# Folder writable
sudo chmod -R 775 /volume1/web/ccr-rnf/storage
sudo chmod -R 775 /volume1/web/ccr-rnf/bootstrap/cache

# Storage link
cd /volume1/web/ccr-rnf
sudo -u http php82 artisan storage:link

# Generate key
sudo -u http php82 artisan key:generate

# Verifikasi binary PDF converter (WAJIB)
which soffice
sudo -u http php82 -r "echo getenv('SOFFICE_BINARY') ?: 'SOFFICE_BINARY not set';"

# Cache config (production)
sudo -u http php82 artisan config:cache
sudo -u http php82 artisan route:cache
sudo -u http php82 artisan view:cache
```

### Step 7b — Jalankan Scheduler & Queue Worker (WAJIB)

```bash
# Scheduler (setiap menit via DSM Task Scheduler)
php82 /volume1/web/ccr-rnf/artisan schedule:run

# Queue worker (background service / task auto-restart)
php82 /volume1/web/ccr-rnf/artisan queue:work database \
  --queue=ccr-heavy,ccr-notify,default \
  --sleep=2 --tries=2 --timeout=180 \
  --max-jobs=200 --max-time=3600 --memory=192
```

---

## Step 8 — Setup Virtual Host

**Web Station → Web Service Portal → Create**:

| Setting | Nilai |
|---------|-------|
| Hostname | `192.168.1.18` |
| Port | `80` |
| Document root | `/volume1/web/ccr-rnf/public` |
| PHP | PHP 8.2 profile yang sudah diset |

---

## Step 9 — Test

Buka: `https://ccr-rnf.internal`

Checklist:
- [ ] Login berhasil
- [ ] Dashboard tampil
- [ ] Buat CCR baru
- [ ] Upload foto
- [ ] Download Word
- [ ] Download Excel
- [ ] Download PDF
- [ ] Preview PDF tampil (tidak timeout)
- [ ] Akses dari PC lain di LAN

---

## Step 10 — Setup Backup Otomatis (Khusus CCR)

1. **Hyper Backup → Create → Data Backup Task**
2. Destination: **Local** → `/volume1/backup/ccr-rnf`
3. Source: ✅ `web/ccr-rnf` + ✅ MariaDB `ccr_rnf`
4. Schedule: **Daily, 01:00**
5. Rotation: **7 versi terakhir**

> ⚠️ 1 HDD = backup di disk sama. Minimal download ke laptop/USB 1×/minggu!

---

## Troubleshooting

### Error 500
```bash
cd /volume1/web/ccr-rnf
sudo -u http php artisan config:clear
sudo -u http php artisan cache:clear
sudo chmod -R 775 storage bootstrap/cache
cat storage/logs/laravel.log | tail -50
```

### "Class not found"
```bash
sudo -u http composer dump-autoload
sudo -u http php artisan config:cache
```

### PDF timeout / lambat
Compress image sebelum masuk PDF. Resize foto max 1200px.

Jika preview/export belum siap dan server balas `503 + Retry-After`, itu normal pada mode queue-first DS220j. Ulangi request beberapa detik kemudian.

---

## SOP Restore Darurat

1. Stop Web Station
2. **Hyper Backup** → pilih versi stabil
3. Restore folder + database
4. Clear cache: `php artisan config:clear && php artisan cache:clear`
5. Test via browser
6. Downtime: ± 10–20 menit

---

## Security Checklist

- [ ] `APP_DEBUG=false`
- [ ] Password DB kuat (12+ karakter)
- [ ] Auto Block aktif (**Control Panel → Security**)
- [ ] Firewall aktif (port 80, 5000, VPN saja)
- [ ] SSH dimatikan setelah setup
- [ ] Akses luar via VPN (JANGAN buka port 80 ke internet)
