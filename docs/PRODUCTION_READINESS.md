# Production Readiness Review

Ngay review: 2026-07-26

Ket luan: PASS

## 1. Pham vi

Review nay ap dung cho thay doi AI UI tren shell chinh:

- Nut `Tro ly AI` tren topbar.
- Nut micro noi.
- Cua so hoi thoai AI co nhap text, nhap giong noi, thu nho, dong va xoa lich su.
- Trang thai san sang, dang nghe, dang xu ly va dang tra loi.
- Ket noi endpoint AI Foundation hien co, khong viet lai backend va khong sua module nghiep vu.

## 2. File thay doi

- `views/app.php`
- `index.php`
- `.htaccess`
- `assets/css/app.css`
- `assets/css/app.min.css`
- `assets/js/ai-speech.js`
- `assets/js/ai-speech.min.js`
- `assets/js/ai-conversation.js`
- `assets/js/ai-conversation.min.js`
- `tests/ai-speech.test.js`
- `tests/ai-conversation.test.js`
- `tests/navigation-cleanup.test.js`
- `service-worker.js`
- `docs/AI_UI_ORCHESTRATION.md`
- `docs/PRODUCTION_READINESS.md`
- `docs/RELEASE_AUDIT.md`

## 3. Debug, TODO va test artifacts

Ket qua scan tren cac file thay doi:

- Khong co `console.log` trong source runtime moi.
- Khong co `debugger`.
- Khong co `TODO`, `FIXME`, `HACK`.
- Khong them mock data, test account, debug endpoint hoac API test vao runtime.
- Cac dong `console.log` con lai chi nam trong test runner output cua cac file test.

## 4. Development config va .env

- Khong thay doi `.env`, `.env.*`, `.cpanel.yml` hoac cau hinh deployment.
- Thay doi UI khong them bien moi va khong can secret moi.
- Production artifact validator da pass, bao gom cac rule loai tru file cam.

## 5. Cache va PWA

- `npm.cmd run build:production` da sinh lai asset minified va `dist/production`.
- `service-worker.js` da bump `PWA_VERSION` len `tenant-pwa-v20260726-ai-ui-2`.
- `index.php` da bump `APP_ASSET_VERSION` len `ai-ui-20260726-2` va dua cac AI JS vao danh sach `versioned_asset`.
- `views/app.php` dung duong dan asset sach; cache busting do `versioned_asset()` xu ly tap trung.
- PWA/browser regression da pass voi test cache hien co.
- UI AI khong them polling, worker rieng hoac cache runtime moi.
- Khi nguoi dung khong mo AI, chi co them markup/button va listener nhe tren shell.

## 6. CSP va security headers

- Khong them inline script moi.
- Khong them external domain moi.
- UI speech dung Web Speech Recognition tren browser.
- UI conversation chi goi `/api/ai/ask` thong qua `window.api` neu co, fallback fetch van gui token/CSRF hien co.
- `Permissions-Policy` duoc dieu chinh tu `microphone=()` sang `microphone=(self)` trong `index.php` va `.htaccess` de Web Speech hoat dong tren cung origin.
- Khong thay doi CSP hoac external security headers khac.

## 7. AI permission review

- `assets/js/ai-speech.js` khong goi network, chi phat event transcript noi bo.
- `assets/js/ai-conversation.js` chi goi `/api/ai/ask`.
- Backend AI Foundation van la noi enforce permission va read-only orchestration.
- UI khong truy cap database truc tiep va khong bo qua RBAC.
- `localStorage` chi luu lich su hoi thoai ngan gon phia client, toi da 20 message.

## 8. Kiem thu da chay

- `npm.cmd run build:assets` - PASS.
- `npm.cmd run test:ai-speech` - PASS.
- `npm.cmd run test:ai-conversation` - PASS.
- `npm.cmd run test:navigation-cleanup` - PASS.
- `npm.cmd run check:js` - PASS.
- `npm.cmd run test:ai-epic12` - PASS.
- `npm.cmd run test:browser` - PASS, 265 passed, 5 skipped.

## 9. Rui ro con lai

- Trinh duyet khong ho tro Web Speech Recognition se disable micro, nhung van cho nhap text.
- Viec cap quyen micro phu thuoc trinh duyet/HTTPS/PWA policy; header production da cho phep microphone trong same-origin.
- CSP hien tai cua ung dung van chap nhan rang buoc san co; thay doi nay khong lam tang surface external.
- 5 Playwright tests bi skip theo dieu kien hien co, khong phai loi moi cua AI UI.

## 10. Rollback

Neu can rollback rieng thay doi AI UI, revert cac file trong muc file thay doi o tren. Backend AI Foundation khong bi thay doi.

## 11. Ket luan

PASS. AI UI da san sang production theo pham vi thay doi hien tai.
