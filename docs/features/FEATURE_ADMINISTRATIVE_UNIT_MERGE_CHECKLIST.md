# Merge Checklist: Administrative Unit Management

Ngay tao: 2026-07-28

Release: Release 4

Epic: Community Control Center

Feature: Administrative Unit Management

Branch: `feature/control-center-phase3`

Ket qua: PASS

## Commits To Merge

- `19d489e Add control center write authorization guard`
- `732e6a6 Add administrative unit management API`
- `29c521c Add administrative unit management UI`

Ghi chu: checklist nay duoc commit rieng sau khi tao, nen commit tai lieu review duoc xac nhan bang `git log` truoc khi merge thay vi tu tham chieu hash ben trong chinh file nay.

## Files To Merge

- `app/Core/Authorization/ControlCenterAuthorizationException.php`
- `app/Core/Authorization/ControlCenterAuthorizationFactory.php`
- `app/Core/Authorization/ControlCenterAuthorizationInterface.php`
- `app/Controllers/AdministrativeUnitController.php`
- `app/Repositories/AdministrativeUnitRepository.php`
- `app/Services/AdministrativeUnitService.php`
- `app/Services/ControlCenterAuditService.php`
- `app/Services/ControlCenterSuperAdminAuthorization.php`
- `index.php`
- `tests/administrative-unit-management.test.js`
- `tests/control-center-authorization.test.php`
- `views/control-center.php`
- `docs/features/FEATURE_ADMINISTRATIVE_UNIT_PRODUCTION_REVIEW.md`
- `docs/features/FEATURE_ADMINISTRATIVE_UNIT_MERGE_CHECKLIST.md`

## Scope Confirmed

PASS.

- Danh sach don vi.
- Them don vi.
- Sua don vi.
- Khoa/kich hoat.
- Domain.
- Logo.
- Trang thai.
- Health Status.
- Security prerequisite guard toi thieu cho write API.

## Out Of Scope Confirmed

PASS.

Khong trien khai:

- User Management.
- Permission System day du.
- Role System.
- SSO.
- Dashboard tong nang cao.
- Monitoring nang cao.
- Notification.
- AI.
- Business Modules.
- Database migration.

## Production Safety

PASS.

- Khong thay doi Tenant Portal.
- Khong thay doi Business Modules.
- Khong thay doi database schema.
- Khong thay doi API nghiep vu tenant.
- Domain goc van bi chan khi truy cap API tenant.
- Tenant domain khong truy cap duoc API Control Center.

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

## Rollback

Rollback bang cach revert cac commit Feature:

- `29c521c Add administrative unit management UI`
- `732e6a6 Add administrative unit management API`
- `19d489e Add control center write authorization guard`

Khong co migration database can rollback.

## Technical Debt

- Legacy read-only `ControlCenterController::units()` co the duoc don dep sau khi Feature on dinh.
- Administrative Unit hien map vao `villages` hien co, chua co bang administrative_units rieng.

Khong sua cac debt nay trong Feature hien tai.

## Merge Recommendation

PASS.

San sang review merge sau khi duoc phe duyet. Khong merge, khong tag, khong push khi chua co xac nhan.
