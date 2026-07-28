# Production Review: Administrative Unit Management

Ngay review: 2026-07-28

Release: Release 4

Epic: Community Control Center

Feature: Administrative Unit Management

Ket qua: PASS

## Scope Reviewed

- Security prerequisite: minimal Control Center write guard.
- API quan ly don vi hanh chinh:
  - `GET /api/control-center/units`
  - `GET /api/control-center/units/{id}`
  - `POST /api/control-center/units`
  - `PUT /api/control-center/units/{id}`
  - `PATCH /api/control-center/units/{id}/lock`
  - `PATCH /api/control-center/units/{id}/activate`
- UI Control Center cho danh sach, tim kiem, loc, them, sua, khoa, kich hoat.

## Architecture Compliance

PASS.

- Control Center van la Portal quan tri.
- Portal chi goi API/Service, khong query database truc tiep tu UI.
- Write API di qua Controller -> Service -> Repository -> Database.
- Feature phu thuoc `ControlCenterAuthorizationInterface`, khong phu thuoc truc tiep `SUPER_ADMIN`.
- Khong thay doi Tenant Portal.
- Khong thay doi Business Modules.
- Khong thay doi API nghiep vu tenant.
- Khong them User Management, Permission System, Role System hoac SSO.

## Security Review

PASS.

- Write API yeu cau authorization guard.
- Guard tam thoi chi cho phep source role `SUPER_ADMIN`, map thanh `SYSTEM_ADMIN`.
- Unsafe methods yeu cau CSRF theo co che hien co.
- Request khong token bi tu choi.
- Tenant domain khong truy cap duoc API Control Center.
- Domain goc van bi chan khi truy cap API tenant.

## Data Review

PASS.

- Khong thay doi schema.
- Su dung bang `villages` hien co.
- Khong xoa vat ly.
- Lock/kich hoat chi cap nhat status.
- Dashboard/nhan khau/ho/GIS/import/export khong bi thay doi.

## Performance Review

PASS voi rui ro thap.

- API list dung phan trang va aggregate count theo don vi.
- UI chi goi endpoint Control Center.
- Khong them autoload frontend cho Tenant Portal.
- Khong them bootstrap path cho Tenant Portal.

## Smoke Test

PASS.

- `php -l index.php`
- `php -l app\Controllers\AdministrativeUnitController.php`
- `php -l app\Services\AdministrativeUnitService.php`
- `php -l app\Repositories\AdministrativeUnitRepository.php`
- `php -l views\control-center.php`
- `node --check tests\administrative-unit-management.test.js`
- `node tests\administrative-unit-management.test.js`

## Regression Test

PASS.

- `php tests\control-center-authorization.test.php`
- `php tests\portal-context.test.php`
- `node tests\control-center-phase2.test.js`
- `node tests\control-center-phase1.test.js`

Ghi chu: local `.env` thieu DB keys nen test PHP in diagnostics hien co. Cac test van PASS.

## Technical Debt

- `ControlCenterController::units()` va read-only service/repository Phase 2 co the tro thanh legacy path sau API moi. Phan loai: TECHNICAL DEBT, khong sua trong Feature nay de tranh mo rong pham vi.
- Feature hien tai quan ly `VILLAGE` tren bang `villages` hien co. Mo hinh `Administrative Unit` day du se can migration co ke hoach rieng trong Feature/Release tuong lai. Phan loai: TECHNICAL DEBT, khong phai blocker.

## Production Risk

Rui ro: LOW.

- Tenant Portal khong thay doi.
- Business Modules khong thay doi.
- Database schema khong thay doi.
- Write API da co guard toi thieu.
- Co rollback bang revert cac commit Feature.

## Rollback Plan

Neu phat hien loi production:

1. Revert cac commit Feature tren branch/release:
   - `Add administrative unit management UI`
   - `Add administrative unit management API`
   - `Add control center write authorization guard`
2. Deploy lai artifact truoc Feature.
3. Xac nhan:
   - Tenant Portal load binh thuong.
   - Domain goc khong truy cap API tenant.
   - Control Center quay ve trang thai truoc Feature.

## Merge Recommendation

PASS.

Co the dua vao Merge Checklist va cho phe duyet merge. Khong tu dong merge neu chua duoc xac nhan.
