# Phase 1 Merge Checklist

Ngay kiem tra: 2026-07-28

Branch: `feature/control-center-phase1`

Ket luan: PASS

Checklist nay chi danh gia kha nang merge Phase 1 vao `main`. Khong merge, khong push, khong squash va khong rebase.

## 1. Phan loai git status truoc khi lam sach

### Thuoc Phase 1 da commit

Nhung file nay nam trong diff `main..HEAD` va dung pham vi Phase 1:

- `.env.example`
- `app/Core/PortalContext.php`
- `index.php`
- `tests/control-center-phase1.test.js`
- `tests/portal-context.test.php`
- `views/control-center.php`

### Thuoc Phase 1 chua commit

Nhung file nay la tai lieu review/merge va cap nhat trang thai kien truc sau khi Phase 0 da duoc phe duyet:

- `README.md`
- `docs/COMMUNITY_CONTROL_CENTER_ARCHITECTURE.md`
- `docs/COMMUNITY_CONTROL_CENTER_ARCHITECTURE_FREEZE.md`
- `docs/COMMUNITY_CONTROL_CENTER_ARCHITECTURE_REVIEW_CHECKLIST.md`
- `docs/PHASE1_PRODUCTION_REVIEW.md`
- `docs/PHASE1_MERGE_CHECKLIST.md`

### Khong thuoc Phase 1

Nhung file nay khong duoc dua vao merge/PR Phase 1:

- `assets/js/admin.utf8.min.js`
- `assets/js/complaints.min.js`
- `assets/js/csrf.min.js`
- `assets/js/digital-profile.min.js`
- `assets/js/documents.min.js`
- `assets/js/finance.min.js`
- `assets/js/gis-platform.min.js`
- `assets/js/household-business.min.js`
- `assets/js/household-photo-camera-fix.min.js`
- `assets/js/household-photo-capture.min.js`
- `assets/js/household-photo-gps.min.js`
- `assets/js/i18n.min.js`
- `assets/js/import.min.js`
- `assets/js/module-dashboards.min.js`
- `assets/js/notifications.min.js`
- `assets/js/operation-center.min.js`
- `assets/js/photo-gallery.min.js`
- `assets/js/public-assets.min.js`
- `assets/js/pwa.min.js`
- `assets/js/sprint10.min.js`
- `assets/js/sprint9.min.js`
- `assets/js/system-admin.min.js`
- `database/migrations/20260727_150000_age_based_citizen_defaults.sql`

### File cu chua commit

Danh sach tren duoc xac dinh la thay doi co san ngoai pham vi Phase 1 va khong duoc commit trong branch nay.

### File phat sinh ngoai pham vi

- `tools/run_age_based_defaults.php`

File nay la untracked ngoai pham vi Phase 1 va khong duoc dua vao merge/PR.

## 2. Hanh dong lam sach branch

Da dua cac file khong thuoc Phase 1 ra khoi working tree bang stash rieng:

```text
stash@{0}: On feature/control-center-phase1: exclude non-phase1 dirty files before phase1 merge review
```

Khong sua noi dung cac file ngoai pham vi.

Khong commit cac file ngoai pham vi.

Khong dua cac file ngoai pham vi vao Phase 1.

## 3. Danh sach commit Phase 1

- `a6db79f Add portal context foundation`
- `d8c8be9 Add control center shell routing`
- `957e8f9 Guard tenant APIs on control center`
- `2b0f9f2 Add control center phase1 smoke tests`

## 4. Danh sach file thay doi Phase 1 da commit

Diff `main..HEAD`:

- `.env.example`
- `app/Core/PortalContext.php`
- `index.php`
- `tests/control-center-phase1.test.js`
- `tests/portal-context.test.php`
- `views/control-center.php`

## 5. Danh sach file tai lieu can commit truoc merge

- `README.md`
- `docs/COMMUNITY_CONTROL_CENTER_ARCHITECTURE.md`
- `docs/COMMUNITY_CONTROL_CENTER_ARCHITECTURE_FREEZE.md`
- `docs/COMMUNITY_CONTROL_CENTER_ARCHITECTURE_REVIEW_CHECKLIST.md`
- `docs/PHASE1_PRODUCTION_REVIEW.md`
- `docs/PHASE1_MERGE_CHECKLIST.md`

Day la tai lieu ho tro merge Phase 1, khong thay doi runtime, controller, service, database, migration, business module, import/export, PDF hoac Excel.

## 6. Ket qua smoke test

Da chay:

```powershell
php -l index.php
php -l app\Core\PortalContext.php
php -l tests\portal-context.test.php
php tests\portal-context.test.php
node --check tests\control-center-phase1.test.js
node tests\control-center-phase1.test.js
```

Ket qua:

- PASS PHP syntax.
- PASS PortalContext unit test.
- PASS Control Center Phase 1 smoke test.

## 7. Ket qua Production Review

Tai lieu:

- `docs/PHASE1_PRODUCTION_REVIEW.md`

Ket luan:

- PASS.

Pham vi da xac nhan:

- `PortalContext` khong anh huong Tenant khi `PLATFORM_ADMIN_ENABLED=false`.
- Control Center shell khong load module tenant.
- Domain goc khong truy cap API tenant.
- Khong sua business modules.
- Khong sua database.
- Khong sua controller/service nghiep vu.
- Rollback bang env flag kha dung.

Rui ro con lai:

- Local khong co DB day du nen full login/logout/session/token/permission can smoke test lai tren staging hoac production-like environment truoc deploy.

## 8. Rollback Plan

Rollback nhanh:

```env
PLATFORM_ADMIN_ENABLED=false
```

Ky vong:

- Domain goc tro ve hanh vi tenant-compatible hien tai.
- `TenantContext::boot()` chay nhu production hien tai.
- `views/app.php` render nhu truoc.
- Router nghiep vu tenant hoat dong nhu truoc.

Rollback bang git neu can:

- Revert `2b0f9f2` de bo smoke test.
- Revert `957e8f9` de bo API guard.
- Revert `d8c8be9` de bo Control Center shell routing.
- Revert `a6db79f` de bo PortalContext foundation.
- Revert commit tai lieu merge/review neu commit sau checklist nay.

## 9. Merge Plan

Dieu kien truoc merge:

- Working tree khong con file ngoai pham vi Phase 1.
- Chi commit cac file Phase 1 va tai lieu review/merge.
- Smoke test pass.
- Production Review PASS.
- Merge Checklist PASS.
- Nguoi phu trach xac nhan merge.

Ke hoach merge:

1. Commit rieng cac tai lieu review/merge.
2. Kiem tra lai `git status`.
3. Kiem tra lai `git diff --name-only main..HEAD`.
4. Chi merge vao `main` khi duoc xac nhan.
5. Khong push neu chua duoc xac nhan.

## 10. Trang thai cuoi

Ket luan: PASS

Phase 1 san sang de nguoi phu trach xem xet merge vao `main`, voi dieu kien khong dua cac file da stash ngoai pham vi vao merge/PR.
