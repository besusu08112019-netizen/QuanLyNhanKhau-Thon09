# Phase 2 Merge Checklist

Ngay lap checklist: 2026-07-28

Ket luan: PASS

Tai lieu nay la mau chuan cho cac Phase sau. Khong merge, khong push, khong tag khi chua co xac nhan cua nguoi phu trach.

## 1. Thong tin Phase

- Phase: Phase 2 - Community Control Center Foundation.
- Branch: `feature/control-center-phase2`.
- Base branch: `feature/control-center-phase1`.
- Commit dau Phase 2: `33b16bc Add control center service endpoints`.
- Commit cuoi Phase 2 tai thoi diem checklist: `d57ab18 Sync phase2 review with master specification`.
- Tag du kien sau khi merge vao `main`: `phase2-community-control-center`.

Danh sach commit Phase 2:

- `33b16bc Add control center service endpoints`
- `cc404a8 Build control center portal layout`
- `e5965df Add control center phase2 smoke tests`
- `33b7974 Keep control center monitoring read only`
- `c5a969b Add phase2 production review`
- `d57ab18 Sync phase2 review with master specification`

## 2. Pham vi trien khai

Phase 2 da trien khai dung pham vi chinh thuc theo Master Development Specification v1.0:

- Community Control Center Layout rieng.
- Header rieng cho Control Center.
- Sidebar rieng cho Control Center.
- Navigation rieng cho Control Center.
- Footer rieng cho Control Center.
- Dashboard tong read-only.
- Quan ly don vi hanh chinh read-only.
- Quan ly tai khoan he thong read-only.
- Monitoring co ban read-only.
- Service layer rieng cho Control Center:
  - `ControlCenterController`
  - `ControlCenterService`
  - `ControlCenterRepository`
- API Control Center read-only:
  - `GET /api/control-center/status`
  - `GET /api/control-center/dashboard`
  - `GET /api/control-center/units`
  - `GET /api/control-center/accounts`
  - `GET /api/control-center/monitoring`
- Smoke test Phase 2.
- Production Review Phase 2.

File se merge:

- `app/Controllers/ControlCenterController.php`
- `app/Repositories/ControlCenterRepository.php`
- `app/Services/ControlCenterService.php`
- `docs/PHASE2_PRODUCTION_REVIEW.md`
- `docs/PHASE2_MERGE_CHECKLIST.md`
- `index.php`
- `tests/control-center-phase2.test.js`
- `views/control-center.php`

## 3. Nhung gi khong thay doi

Phase 2 khong thay doi:

- Business Modules.
- Business Controllers.
- Business Services.
- Business Logic.
- Database.
- Migration.
- Tenant Portal UX.
- Tenant Dashboard.
- Citizen/Nhan khau.
- Household/Ho gia dinh.
- GIS.
- Dang vien.
- Xe co.
- Nong nghiep.
- Bao cao.
- AI.
- Import.
- Export.
- PDF.
- Excel.
- API nghiep vu tenant.
- Session tenant.
- Permission tenant.
- SSO.
- Feature Flags.
- Notification.
- Event Consumer.
- Multi Database.

## 4. Kiem thu

Smoke Test da chay:

```powershell
php -l index.php
php -l app\Controllers\ControlCenterController.php
php -l app\Services\ControlCenterService.php
php -l app\Repositories\ControlCenterRepository.php
php -l views\control-center.php
node --check tests\control-center-phase2.test.js
node tests\control-center-phase2.test.js
```

Ket qua Smoke Test: PASS.

Regression Test da chay:

```powershell
php tests\portal-context.test.php
node tests\control-center-phase1.test.js
```

Kiem tra bo sung domain goc:

- `/api/citizens`: blocked.
- `/api/households`: blocked.
- `/api/dashboard`: blocked.
- `/api/gis/areas`: blocked.

Ket qua Regression Test: PASS.

Production Review:

- Tai lieu: `docs/PHASE2_PRODUCTION_REVIEW.md`.
- Ket qua: PASS.
- Roadmap da dong bo voi Master Development Specification v1.0.
- Khong co Architecture Change.
- Khong can ADR.

## 5. Rollback

Rollback nhanh bang config:

```env
PLATFORM_ADMIN_ENABLED=false
```

Ket qua ky vong:

- Domain goc quay ve hanh vi tenant-compatible.
- Tenant Portal tiep tuc render `views/app.php`.
- Tenant subdomain tiep tuc hoat dong nhu truoc.
- API nghiep vu tenant khong bi thay doi.

Rollback bang git neu can:

- Revert `d57ab18 Sync phase2 review with master specification`
- Revert `c5a969b Add phase2 production review`
- Revert `33b7974 Keep control center monitoring read only`
- Revert `e5965df Add control center phase2 smoke tests`
- Revert `cc404a8 Build control center portal layout`
- Revert `33b16bc Add control center service endpoints`

Khong can rollback database vi Phase 2 khong co migration va khong thay doi schema.

## 6. Rui ro con lai va Technical Debt

Rui ro con lai:

- Local khong co DB day du, nen can smoke test them tren staging hoac production-like environment truoc deploy.
- Endpoint Control Center Phase 2 hien read-only nhung chua co auth rieng; auth/permission/SSO thuoc Phase 3 theo Master Spec v1.0.
- Cac so lieu aggregate can duoc doi chieu voi du lieu that tren moi truong co DB.

Technical Debt ghi nhan, khong sua trong Phase nay:

- Mapping role hien tai la mapping tam tu role cu sang role platform:
  - `SUPER_ADMIN -> SYSTEM_ADMIN`
  - `ADMIN -> VILLAGE_ADMIN`
  - `OFFICER -> STAFF`
  - `VIEWER -> VIEWER`
  - `COMMUNE_ADMIN` chua co source role cho den khi Phase 3 xu ly user/role model.
- Dashboard aggregate co dinh mot so dinh nghia tong hop trong repository; can dua ve policy/config neu duoc su dung rong hon.
- Dashboard aggregate dung nhieu query `COUNT`; can benchmark truoc khi toi uu.
- `index.php` van la router tap trung lon; tach route registration theo PortalContext/ModuleRegistry la viec cua phase tuong ung, khong lam trong Phase 2.

Technical Debt Policy ap dung tu Phase 3:

- BLOCKER: sua ngay.
- TECHNICAL DEBT: ghi Backlog, khong sua trong Phase hien tai.
- NICE TO HAVE: dua vao Roadmap, khong mo rong pham vi Phase.

## 7. Merge Recommendation

Ket luan: PASS.

Khuyen nghi:

- Co the merge `feature/control-center-phase2` vao `main` sau khi nguoi phu trach xac nhan.
- Giu nguyen lich su commit.
- Khong squash.
- Khong rebase.
- Sau merge moi push `main`.
- Sau push `main`, tao tag `phase2-community-control-center`.
- Push tag.
- Tao branch `feature/control-center-phase3`.
- Khong trien khai Phase 3 ngay sau khi tao branch.
- Sau khi tao branch Phase 3, chi tao `docs/PHASE3_IMPLEMENTATION_PLAN.md`.

Trang thai hien tai:

- Cho phe duyet merge.
- Chua merge.
- Chua push.
- Chua tag.
