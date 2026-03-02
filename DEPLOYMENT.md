# CCR-RNF — Deployment Checklist (Hostinger Shared Hosting)

## Langkah Deploy / Update

### 1. Upload Files
Upload semua file project ke `public_html/` di Hostinger via **File Manager** atau **Git**.

> ⚠️ **JANGAN** upload file `.env` dari local. Gunakan `.env.production` sebagai basis.

### 2. Setup Environment
```bash
# Di Hostinger terminal / SSH:
cp .env.production .env
php artisan key:generate
```

Isi nilai yang kosong di `.env`:
| Variable | Contoh |
|----------|--------|
| `DB_DATABASE` | `u123456_ccr_rnf` |
| `DB_USERNAME` | `u123456_ccr` |
| `DB_PASSWORD` | `passwordKuatDisini123` |
| `APP_URL` | `https://yourdomain.com` |
| `MAIL_HOST` | `smtp.hostinger.com` |
| `MAIL_USERNAME` | `noreply@yourdomain.com` |
| `MAIL_PASSWORD` | `mailPasswordDisini` |

### 3. Database Migration
```bash
php artisan migrate --force
```

### 4. Cache Config (WAJIB setelah setiap update)
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Jalankan perintah ini **setiap kali update deploy**.

### 5. Cron Job (Hostinger Panel)
Di **Hostinger hPanel → Cron Jobs**, tambahkan:

```
* * * * * cd /home/u123456/domains/yourdomain.com/public_html && php artisan schedule:run >> /dev/null 2>&1
```

> Ganti path sesuai path project kamu di Hostinger.

### 6. Folder Permission
```bash
chmod -R 775 storage bootstrap/cache
```

---

## Checklist Keamanan

| # | Item | Cara Cek |
|---|------|----------|
| 1 | `APP_DEBUG=false` | Buka halaman error → tidak boleh tampil stack trace |
| 2 | `SESSION_ENCRYPT=true` | Sudah di `.env` |
| 3 | `SESSION_SECURE_COOKIE=true` | Pastikan domain pakai HTTPS |
| 4 | HTTPS aktif | Hostinger → SSL → Force HTTPS |
| 5 | Security headers | Cek **securityheaders.com** setelah deploy |
| 6 | Health check | Buka `https://yourdomain.com/health` → harus `{"status":"ok"}` |

---

## Verifikasi Setelah Deploy

```bash
# 1. Cek health
curl https://yourdomain.com/health

# 2. Cek konfigurasi ter-cache
php artisan config:show app.debug
# Harus: false

# 3. Cek schedule terdaftar
php artisan schedule:list
```

---

## Hosting-Specific Notes (Hostinger)

| Feature | Support | Alternatif |
|---------|---------|------------|
| Redis | ❌ | Pakai `CACHE_STORE=file` |
| Supervisor | ❌ | Pakai `QUEUE_CONNECTION=sync` |
| Horizon | ❌ | Tidak perlu |
| Persistent worker | ❌ | Cron job saja |
| SSH | ✅ | Gunakan untuk artisan commands |
| Cron job | ✅ | 1 entry: schedule:run |
| MySQL | ✅ | Bawaan Hostinger |
| SSL/HTTPS | ✅ | Free SSL dari Hostinger |
| Daily backup | ✅ | Via Hostinger panel (manual) |

---

## Troubleshooting

### "500 Internal Server Error"
```bash
php artisan config:clear
php artisan cache:clear
chmod -R 775 storage bootstrap/cache
```

### "Class not found" setelah update
```bash
composer dump-autoload
php artisan config:cache
```

### Session hilang / logout terus
Pastikan `SESSION_DRIVER=database` dan tabel `sessions` sudah ada:
```bash
php artisan session:table
php artisan migrate
```
