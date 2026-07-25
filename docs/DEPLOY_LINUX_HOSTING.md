# Huong dan trien khai Linux Hosting

Tai lieu nay ap dung cho mot tenant/thon bat ky khi trien khai chung source PHP/MySQL.

## Dieu kien

- Domain hoac subdomain rieng cho tenant.
- Database MySQL/MariaDB rieng cho tenant.
- PHP 8.2+ va cac extension trong `composer.json`.
- Document root tro toi thu muc chua `index.php`.

## Cau hinh

1. Upload source chung len hosting.
2. Tao file `.env` rieng tu `.env.example`.
3. Dien `APP_URL`, cac bien `TENANT_*` va `DB_*` cua tenant.
4. Import `database/schema.sql` vao database cua tenant.
5. Import `database/seed.sql`.
6. Chay migration can thiet theo thu tu ten file.
7. Tao admin dau tien va cap nhat module Settings.

## Kiem tra

```text
https://your-domain.example/api/health
```

Dang nhap website tenant va kiem tra Dashboard, Ho gia dinh, Nhan khau, GIS, Bao cao, Settings va Backup.

Chi copy source chung khi deploy. Khong copy `.env`, `config/database.php`, `uploads/`, `storage/` hoac backup SQL giua cac tenant.
