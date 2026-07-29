# Tenant Completion Audit - 2026-07-25

## Ket luan

Trang thai: **CHUA PASS PRODUCTION**.

Ly do: da bo sung tenant guard cho cac luong loi cao, nhung scan van con nhieu module nghiep vu co INSERT/UPDATE/SELECT rieng chua duoc xac nhan 100% gan `village_id`. Khong nen deploy production truoc khi xu ly het danh sach blocker ben duoi.

## Pham vi da hoan thien trong dot audit nay

- GIS:
  - `GisArea`: list/find/save/delete/recalculate dung tenant.
  - `GisSearch`: search ho/nhan khau dung tenant.
  - `GisHouseholdLocation`: marker/detail/search/update toa do/clear/recalculate/photo subquery dung tenant.
- Dashboard/Statistics:
  - `PopulationStatistics` them tenant vao dieu kien ho/nhan khau dung chung.
  - `Dashboard` them tenant cho movements, GIS area, household business, file attachments.
- Upload/File:
  - `FileAttachment` them `village_id` khi tao file; find/list/search/update/delete chi trong tenant.
- Import/Movement:
  - `ImportController::citizenCodeExists()` loc theo `TenantContext::id()`.
  - `PopulationMovementService` them tenant guard cho read/update/insert movement.
  - `Movement` them tenant cho list/find/fallback/record/params.
- Notification:
  - `NotificationCenter` them tenant vao cac query dem complaint/task/calendar/document/backup va notification state.
- Ho so so:
  - `DigitalProfile` loc tenant cho thanh vien, family, movements, notes, audit logs; them tenant khi tao note neu schema co cot.
- Phan anh, cong viec, lich cong tac, van ban:
  - `Complaint`, `WorkTask`, `WorkCalendar`, `VillageDocument` them tenant cho list/find/stat/update/delete/insert chinh.
  - `VillageDocument` doi prefix tao ma tu `VB09-` sang `VB-`.

## File da sua trong audit tenant

- `app/Controllers/ImportController.php`
- `app/Models/Complaint.php`
- `app/Models/Dashboard.php`
- `app/Models/DigitalProfile.php`
- `app/Models/FileAttachment.php`
- `app/Models/GisArea.php`
- `app/Models/GisHouseholdLocation.php`
- `app/Models/GisSearch.php`
- `app/Models/Movement.php`
- `app/Models/NotificationCenter.php`
- `app/Models/PopulationStatistics.php`
- `app/Models/VillageDocument.php`
- `app/Models/WorkCalendar.php`
- `app/Models/WorkTask.php`
- `app/Services/PopulationMovementService.php`

## File da sua tu phase .env / multi-tenant foundation truoc do

- `.env.example`
- `app/Controllers/HouseholdBusinessController.php`
- `app/Controllers/SettingController.php`
- `app/Core/BaseModel.php`
- `app/Core/Database.php`
- `app/Core/TenantConfig.php`
- `app/Core/TenantContext.php`
- `app/Models/AuditLog.php`
- `app/Models/Backup.php`
- `app/Models/Citizen.php`
- `app/Models/Household.php`
- `app/Models/HouseholdBusiness.php`
- `app/Models/SystemAdmin.php`
- `app/Models/SystemSetting.php`
- `app/Models/User.php`
- `app/Services/FileStorageService.php`
- `config/app.php`
- `config/database.example.php`
- `config/env.php`
- `database/migrations/20260725_120000_environment_multi_tenant_foundation.sql`
- `database/schema.sql`
- `database/seed.sql`
- `docs/PHASE_ENVIRONMENT_MULTI_TENANT_FOUNDATION.md`
- `index.php`

## Blocker truoc production

Can tiep tuc gan tenant va review logic cho cac module sau. Scan van thay truy van INSERT/UPDATE/SELECT rieng, hoac model chua co dau vet `tenantWhere`/`addTenantInsert` day du:

- `app/Models/AgricultureProduction.php`
- `app/Models/Finance.php`
- `app/Models/House.php`
- `app/Models/HouseholdContribution.php`
- `app/Models/Livestock.php`
- `app/Models/PhotoGallery.php`
- `app/Models/PublicAsset.php`
- `app/Models/Report.php`
- `app/Models/Vehicle.php`
- Mot so bang phu cua `Complaint`, `WorkTask`, `WorkCalendar`, `VillageDocument` nhu attachments/history/attendees hien dang duoc rang buoc qua parent id, nhung chua co cot `village_id` rieng. Nen bo sung cot tenant neu can truy van truc tiep cac bang nay.

## Hard-code con ton tai

- Con nhieu token `tenant_a`/`TenantA` trong frontend, test, PWA va `.htaccess` duoi dang global namespace, event name, localStorage key, cache tag, test host.
- Cac token nay khong phai du lieu thon hien thi truc tiep, nhung van la no ky thuat branding/source-name. Neu muc tieu la khong con dau vet Thon 09 trong source, can co phase rieng doi namespace frontend co test day du.

## Kiem thu da chay

- `php -l` toan bo file PHP trong `app/`
- `php -l index.php`
- `php -l config/env.php`
- `php -l app/Core/TenantContext.php`
- `npm.cmd run check:js`
- `npm.cmd run test:platform`
- `npm.cmd run test:navigation-cleanup`
- `node tests/security-regression.test.js`
- `npm.cmd run build:production`
- `npm.cmd run validate:artifact`

Tat ca cac lenh tren da pass. Luu y: `npm` truc tiep bi PowerShell ExecutionPolicy chan, da chay bang `npm.cmd`.

## Khuyen nghi tiep theo

1. Tao phase rieng: `Tenant Completion - Remaining Business Modules`.
2. Them `village_id` vao schema/migration cho cac bang nghiep vu con lai va bang phu neu co truy van truc tiep.
3. Voi tung model con lai, bat buoc:
   - INSERT them `addTenantInsert()`.
   - SELECT/COUNT/FIND/SEARCH them `tenantWhere()`.
   - UPDATE/DELETE theo id them `tenantWhere()`.
   - Join sang `households`, `citizens`, bang nghiep vu lien quan them tenant guard tren alias.
4. Sau khi het blocker, chay lai audit va chi deploy khi bao cao chuyen sang PASS.
