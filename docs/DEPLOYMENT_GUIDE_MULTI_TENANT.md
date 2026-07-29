# Deployment Guide Multi-tenant

Tai lieu nay huong dan trien khai mot thon moi tu cung mot repository/source code.

## Nguyen tac

- Chi duy tri mot repository va mot source code.
- Moi thay doi source code deploy mot lan phai tu dong ap dung cho tat ca tenant.
- Moi thon co database rieng, domain/subdomain rieng va `.env` rieng.
- Khong tao branch hoac ban source rieng cho tung thon.
- Website cua thon nao chi cau hinh toi database cua thon do.
- Ten thon, logo, dia chi, lien he, banner va mau giao dien lay tu `.env` hoac bang `settings`.
- Khong copy, merge hoac dong bo database, uploads, storage, cache, session, logo, background hoac tenant config giua cac tenant khi deploy source.

## Tao thon moi

1. Tao database MySQL/MariaDB moi voi charset `utf8mb4`.
2. Import `database/schema.sql` vao database moi.
3. Import `database/seed.sql`.
4. Neu dang nang cap tu ban cu, chay cac file trong `database/migrations/` theo thu tu ten file.
5. Tao `.env` rieng cho hosting/domain cua thon tu `.env.example`.
6. Cau hinh toi thieu:

```env
APP_NAME="He thong Quan ly Hanh chinh"
APP_URL=https://thon-moi.example
APP_KEY=tao-chuoi-bi-mat-dai-rieng-cho-thon

TENANT_UNIT_NAME="Thon moi - Xa ..."
TENANT_HAMLET_NAME="Thon moi"
TENANT_COMMUNE_NAME="Xa ..."
TENANT_ADDRESS="..."
TENANT_PHONE="..."
TENANT_EMAIL="..."
TENANT_WEBSITE=https://thon-moi.example

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=database_cua_thon_moi
DB_USERNAME=user_cua_thon_moi
DB_PASSWORD=mat_khau_rieng
DB_CHARSET=utf8mb4
```

7. Tro document root cua domain/subdomain vao thu muc co `index.php`.
8. Dang nhap bang tai khoan quan tri dau tien hoac goi API setup neu database chua co admin.
9. Vao module Cau hinh/Settings cap nhat logo, banner, mau giao dien, thong tin lien he va nguoi ky bao cao.

## Kiem tra cach ly du lieu

- Kiem tra `.env` tren tung hosting chi co `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` cua thon do.
- Khong dung chung database user neu hosting cho phep tao user rieng.
- Khong copy `config/database.php` hoac `.env` cua thon nay sang thon khac ma chua doi DB.
- Goi `/api/health` va `/api/system/diagnostics` neu co quyen de xac nhan database dang ket noi dung.

## Deploy tinh nang moi

1. Code mot lan tren source chung.
2. Push mot lan len GitHub.
3. Build/deploy cung artifact len cac hosting.
4. Moi hosting giu nguyen `.env`, `uploads/`, `storage/` va database rieng.
5. Chay migration tren tung database tenant neu release co thay doi schema.
6. Kiem tra Thon 09, Thon 10 va tenant-test cung nhan mot source version, module version, Dashboard version va Policy Engine version.
7. Kiem tra dang nhap, Dashboard, Health Check va module moi tren tat ca tenant.
8. Chi danh dau deployment PASS khi tat ca tenant cung version va cung co module moi. Neu mot tenant thieu tinh nang moi, day la loi deployment.

## Settings can cap nhat cho moi thon

- `systemName`
- `unitName`
- `hamletName`
- `communeName`
- `logoUrl`
- `backgroundUrl` hoac `backgroundImages`
- `themeColor`
- `backgroundColor`
- `address`
- `phone`
- `email`
- `website`
- `copyright`
- `reportSigner`

Neu DB chua co settings, he thong dung fallback tu `.env`. Sau khi admin cap nhat trong UI, bang `settings` cua tenant se la nguon cau hinh chinh.
