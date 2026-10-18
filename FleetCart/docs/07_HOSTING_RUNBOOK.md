# Hosting, backup and HTTPS runbook (Egypt store)

Target: one Linux VPS (2 vCPU / 4 GB), Ubuntu 24.04, PHP 8.3, MariaDB 10.11+ or MySQL 8, Nginx, Node only at build time.
**Prerequisite: a licensed FleetCart package (see 04 §5). Do not deploy the nulled copy.**

## 1. Server

```bash
apt install nginx mariadb-server php8.3-fpm php8.3-{mysql,mbstring,xml,curl,zip,gd,intl,bcmath,redis} redis-server certbot python3-certbot-nginx unzip git
```

Nginx server block (root `public/`, `try_files $uri /index.php?$query_string`, deny dotfiles, `client_max_body_size 20m`). Serve `/build/` with long cache headers.

## 2. Deploy

```bash
git clone <private repo> /var/www/store && cd /var/www/store/FleetCart
composer install --no-dev --optimize-autoloader
npm ci && npm run build && rm -rf node_modules
cp .env.example .env && php artisan key:generate
# create DB + user (utf8mb4), put credentials in .env, then run the web installer ONCE at /install
php artisan egypt:install
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
```

`.env` for production: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>`, `SESSION_SECURE_COOKIE=true`, a **new** `APP_KEY`, real `MAIL_*`, `QUEUE_CONNECTION=database` or redis (+ a worker under systemd), `CACHE_DRIVER=file` or redis.
After deploying code: `php artisan optimize:clear && php artisan migrate --force && config:cache route:cache view:cache`.

Scheduler: `* * * * * www-data php /var/www/store/FleetCart/artisan schedule:run`.

## 3. HTTPS

`certbot --nginx -d example.com -d www.example.com`, redirect HTTP→HTTPS, HSTS after a week of clean traffic. Add `Sitemap: https://example.com/sitemap.xml` to `public/robots.txt` (the file is static and domain-specific).

## 4. Backups

Nightly (cron, 03:00):

```bash
mysqldump --single-transaction --routines <db> | gzip > /backup/db-$(date +%F).sql.gz
tar czf /backup/files-$(date +%F).tgz /var/www/store/FleetCart/storage/app/public /var/www/store/FleetCart/.env
find /backup -mtime +14 -delete
```

Copy `/backup` off the server (object storage / second region) and **test a restore monthly** into a scratch DB (`gunzip | mariadb scratch`, then `php artisan about`). Keep `.env` out of git.

## 5. Hardening checklist

- Admin behind IP allow-list or a VPN; strong unique admin passwords; remove demo/test admins.
- `fail2ban` for SSH and nginx auth, rate limit `/login`, `/admin/login`, `/checkout` in nginx (`limit_req`).
- `ufw allow 22,80,443`; DB listens on localhost only.
- No `/install` after setup (it redirects when installed); delete `public/samples`.
- Rotate any credential that ever lived in git (see 04 §5).
- Monitoring: uptime check on `/` and `/ar/checkout`, log alert on `storage/logs/laravel.log` growth, disk usage alert.
- Run `composer audit` and `npm audit` before each release.

## 6. Go-live checklist

1. `php artisan egypt:install` ran; Admin > Egypt store shows every readiness item green.
2. Real catalogue with Arabic and English text, photos, correct stock per size.
3. Shipping prices reviewed per governorate; free-shipping threshold decided.
4. Tracking IDs entered; test events seen in GA4 DebugView, Meta Events Manager (test code) and TikTok Events.
5. A test COD order placed from a phone in Arabic; confirmation, e-mail/SMS and admin order checked.
6. Terms, privacy and returns pages written (Arabic + English) and assigned in Storefront settings.
7. Backups running and one restore tested.
