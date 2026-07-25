# Pre-Production Audit 2026-07-25

## Ket luan

Trang thai: CONDITIONAL PASS.

Ly do chua danh dau full PASS: moi truong audit local khong co `mysql`/`mariadb` client/server, nen chua import that duoc `database/schema.sql` va `database/seed.sql`. Cac kiem tra code, artifact, PHP/JS, case-sensitive, runtime boot va hygiene da pass. Buoc import DB phai duoc chay tren Linux Hosting hoac mot MySQL/MariaDB sach truoc khi deploy production.

## Ket qua kiem tra

- `.gitignore`: da chan `.env`, `.env.*`, log, cache, backup, uploads runtime, test-results, Playwright report, production verification artifacts.
- `.env.example`: da co bien `APP_*`, `TENANT_*`, `DB_*` cho tenant moi.
- `database/schema.sql`: schema khong co `CREATE DATABASE`, `USE`, seed ho dan/nhan khau.
- `database/seed.sql`: chi seed `settings` va `permissions` mac dinh.
- Runtime directories: production artifact tao `uploads/`, `storage/cache/`, giu `uploads/.htaccess` va `storage/.htaccess`.
- Linux case-sensitive: App class filenames match `App\\` PSR-4 case.
- PHP Linux compatibility: PHP 8.2 syntax pass; required extensions present in local CLI check except Linux host still must verify theo `composer.json`.
- Asset paths: key static/runtime paths exist with exact names; production artifact validation pass.
- Sensitive files: da loai production verification screenshots/json co rui ro chua du lieu that.
- PWA/GIS assets: Leaflet asset test pass; service worker cache name da cap nhat.
- Artifact hygiene: `npm run validate:artifact` pass; artifact khong chua `.env`, `.sql`, `.log`, docs, tests, sample-data, backup.

## Lenh da chay

```powershell
npm.cmd run build:production
npm.cmd run validate:artifact
npm.cmd run check:js
node tests\security-regression.test.js
npm.cmd run test:platform
npm.cmd run test:navigation-cleanup
npx.cmd playwright test tests/browser/leaflet-assets.spec.js --project=desktop-chromium
php -l app\Core\TenantConfig.php
```

Da chay lint PHP toan bo project va tat ca file PHP pass syntax check.

## Mo phong deploy artifact

- Tao `.env` tam trong `dist/production`.
- Chay `php -S 127.0.0.1:8091` tai `dist/production`.
- Goi `/`: HTTP 200, HTML co tenant tu `.env`.
- Goi `/manifest.json`: HTTP 200.

## Cac gate con lai tren Linux Hosting

1. Tao database rong.
2. Import `database/schema.sql`.
3. Import `database/seed.sql`.
4. Cau hinh `.env` that.
5. Mo website, tao admin dau tien.
6. Kiem tra upload anh, upload van ban, import Excel, export PDF, GIS va PWA tren domain HTTPS that.
