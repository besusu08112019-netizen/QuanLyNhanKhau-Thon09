# Production Readiness Review

Ngay review: 2026-07-26

Ket luan: PASS

## 1. Pham vi

Review duoc thuc hien truoc commit cho toan bo source va production artifact hien tai, bao gom cac thay doi AI Epic 1-12 va cac gate release lien quan.

## 2. Van de phat hien va da sua

- Xoa `console.log('popupopen')` khoi `assets/js/gis-household-location.js`.
- Thay fallback `console.log` bang no-op trong `assets/js/household-business.js` va `assets/js/vehicles.js`.
- Build lai cac file minified lien quan.
- Doi comment `hack` trong `assets/vendor/leaflet/leaflet.css` thanh comment trung tinh, khong doi hanh vi CSS.
- Tang `tools/validate-production-artifact.js` de chan `console.log`, `debugger`, `TODO`, `FIXME`, `HACK` trong production artifact.
- Xoa cac log/debug runtime local khong tracked: `debug.log`, `tmp-bottom-nav-php.err.log`, `tmp-bottom-nav-php.log`, `storage/api-errors.log`, `.codex-npm-cache/_logs`, `.tmp/.env.backup.fresh-install`.

## 3. Debug, TODO, mock va test artifacts

Ket qua sau khi sua:

- Production artifact khong con `console.log(...)`.
- Production artifact khong con `debugger;`.
- Production artifact khong con marker `TODO`, `FIXME`, `HACK`.
- Production artifact khong co `.env*`, `.log`, `.bak`, `.sql`, `.map`, `*.spec.js`, `*.test.js`.
- Thu muc `tests`, `docs`, `tools`, `database`, `node_modules`, `vendor` khong duoc copy vao `dist/production`.
- Cac file trong `sample-data` la template import duoc app yeu cau va validator kiem tra bat buoc, khong phai mock runtime/test account.

## 4. API test va tai khoan test

- Khong co API test file trong production artifact.
- Khong co `tests/` trong production artifact.
- Khong phat hien tai khoan test duoc dua vao artifact.
- `database/seed.sql` va migration SQL khong duoc dua vao production artifact.

## 5. Development config va .env

- `.env` local van ton tai trong workspace de phuc vu may hien tai, nhung khong tracked boi Git va khong duoc dua vao `dist/production`.
- `.env.example` la template cau hinh, duoc giu lai trong source nhung khong vao production artifact.
- Validator production artifact chan `.env`, `.env.*`, `.deploy.env`, log, backup va SQL dump.
- Production artifact khong chua `package.json`, `package-lock.json`, `composer.json`, `composer.lock`, `tests`, `tools`, `docs`, `node_modules`, `vendor`.

## 6. Cache va PWA

- `.htaccess` dat cache dai han cho asset tinh: CSS, JS, image.
- `.htaccess` dat `no-store, no-cache, must-revalidate` cho API, service worker va manifest.
- `service-worker.js` precache cac asset AI/runtime can thiet theo `PWA_VERSION`.
- Playwright Leaflet/PWA cache test doc version dong tu `service-worker.js`, khong hardcode version cu.

## 7. CSP va security headers

Da xac nhan `index.php` va `.htaccess` co cac header chinh:

- `Content-Security-Policy`.
- `X-Content-Type-Options: nosniff`.
- `X-Frame-Options: SAMEORIGIN`.
- `Referrer-Policy: same-origin`.
- `Permissions-Policy`.
- `Strict-Transport-Security` khi chay HTTPS.

Luu y: CSP hien van can `'unsafe-inline'` cho script/style do ung dung hien tai con inline handler/style. Day la rui ro duoc chap nhan tam thoi, khong phai loi moi cua AI.

## 8. Quyen file va thu muc runtime

- `.htaccess` root chan truy cap truc tiep vao `ai`, `app`, `config`, `database`, `docs`, `storage`, `backups`, `tests`, `tools`, `sample-data`, `vendor`, `node_modules`.
- `storage/.htaccess` co `Require all denied`.
- `uploads/.htaccess` chan directory listing va deny script executable extensions.
- Production artifact chi tao runtime dirs can thiet: `storage/cache` va `uploads`.

## 9. AI permission review

Da xac nhan:

- `/api/ai/tools` yeu cau `dashboard:read`.
- `/api/ai/tools/execute` yeu cau permission cua tung tool qua `PermissionAwareAiToolInterface`.
- `/api/ai/ask` tao read-only permission context tu permission hien co cua user.
- `ToolExecutor` tra ve `permission_denied` khi permission checker hoac source permission cua tool tu choi.
- `InsightTool` khong bo qua permission cua nguon du lieu; neu thieu module source thi tra ve `permission_denied`.
- AI provider mac dinh disabled trong `ai/config/ai.php`; `external_api` mac dinh `false`.
- Cac tool AI hien tai read-only; OCR/Speech/TTS chay client-side va khong tu dong ghi database.

## 10. Kiem thu da chay

- `npm.cmd run test:ai-epic12` - PASS.
- `npm.cmd run test:browser` - PASS, 265 passed, 5 skipped.
- `npm.cmd run validate:artifact` - PASS.
- Quet `dist/production` cho debug/dev/test markers - PASS.
- Node syntax checks trong gate `check:js` - PASS.

## 11. Rui ro con lai

- `.env` local con trong workspace nhung da ignored va khong vao artifact. Khong commit file nay.
- CSP co `'unsafe-inline'` do rang buoc UI hien tai; nen lap epic hardening rieng de chuyen sang nonce/hash khi co thoi gian.
- 5 Playwright tests dang skip theo dieu kien hien co, khong phai loi production readiness moi.
- Test data trong source `tests/` va template trong `sample-data/` khong vao runtime test/API artifact; `sample-data` duoc giu nhu template import cho nguoi dung.

## 12. Ket luan

PASS.

Khong con van de production readiness nghiem trong trong production artifact sau khi sua va chay lai gate. Co the de xuat commit sau khi nguoi quan tri xac nhan.
