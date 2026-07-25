# Release Checklist Linux Hosting

Chi deploy khi tat ca muc bat buoc ben duoi dat PASS.

## 1. Repository

- [ ] Working tree chi chua thay doi duoc review.
- [ ] Khong commit `.env`, `config/database.php`, `config/database.local.php`.
- [ ] Khong commit `uploads/` runtime, backup SQL, log, cache, `test-results/`, `playwright-report/`.
- [ ] Khong commit screenshot/json production verification co du lieu nguoi dan.
- [ ] `rg` khong con hard-code tenant cu/domain cu trong runtime files.

## 2. Build va kiem tra local

- [ ] `npm run check:js` PASS.
- [ ] PHP lint toan bo `.php` PASS.
- [ ] `node tests/security-regression.test.js` PASS.
- [ ] `npm run test:platform` PASS.
- [ ] `npm run test:navigation-cleanup` PASS.
- [ ] `npm run build:production` PASS.
- [ ] `npm run validate:artifact` PASS.

## 3. Database tenant moi

- [ ] Tao database MySQL/MariaDB moi, charset `utf8mb4`, collation `utf8mb4_unicode_ci`.
- [ ] Tao database user rieng cho tenant, khong dung chung voi tenant khac.
- [ ] Import `database/schema.sql` thanh cong.
- [ ] Import `database/seed.sql` thanh cong.
- [ ] Neu deploy upgrade, chay migration can thiet theo thu tu ten file.
- [ ] Xac nhan database khong co du lieu cua tenant khac.

## 4. Cau hinh hosting

- [ ] Domain/subdomain tro dung document root co `index.php`.
- [ ] Tao `.env` rieng tren hosting, khong commit.
- [ ] `APP_URL` dung domain HTTPS cua tenant.
- [ ] `APP_KEY` la secret rieng, dai va khong dung lai.
- [ ] `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` dung database tenant.
- [ ] `TENANT_UNIT_NAME`, `TENANT_HAMLET_NAME`, `TENANT_COMMUNE_NAME`, lien he va mau giao dien dung tenant.
- [ ] `uploads/`, `storage/cache/`, `storage/logs/` writable cho PHP user.
- [ ] `uploads/.htaccess` va `storage/.htaccess` co tren server.

## 5. Smoke test sau deploy

- [ ] Trang `/` tra HTTP 200 va hien dung ten tenant.
- [ ] `/manifest.json` tra HTTP 200.
- [ ] Dang nhap/tai khoan admin dau tien tao thanh cong.
- [ ] Dashboard load du lieu tu database tenant.
- [ ] Nhan khau CRUD smoke test.
- [ ] Ho gia dinh CRUD smoke test.
- [ ] GIS hien ban do, marker/layer va GPS/directions.
- [ ] Upload anh ho dan/cong trinh hoat dong.
- [ ] Upload van ban/tai lieu hoat dong.
- [ ] Import Excel preview va import test voi file mau hoat dong.
- [ ] Export PDF tu Bao cao/Phan anh/Cong viec/Lich/Thu chi hoat dong.
- [ ] PWA service worker install/update khong loi console.
- [ ] Backup SQL tao duoc va header la `Quan Ly Hanh Chinh backup`.

## 6. Cach ly tenant

- [ ] Mo `settings` trong UI va xac nhan logo/banner/lien he/mau la cua tenant hien tai.
- [ ] Goi API danh sach ho/nhan khau khong tra du lieu tenant khac.
- [ ] Kiem tra log server khong co loi DB connection toi database sai.
- [ ] Kiem tra backup download chi gom database tenant hien tai.

## 7. Quyet dinh release

- [ ] Tat ca muc tren PASS.
- [ ] Ghi lai thoi gian deploy, commit SHA, domain, database name.
- [ ] Tao backup sau deploy.
- [ ] Ban giao thong tin admin va huong dan cap nhat Settings cho don vi.
