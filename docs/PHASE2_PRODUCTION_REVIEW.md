# Phase 2 Production Review

Ngay review: 2026-07-28

Branch: `feature/control-center-phase2`

Ket luan: PASS

Phase 2 du dieu kien cho buoc xem xet merge, nhung khong duoc chuyen sang Phase 3 truoc khi nguoi phu trach xac nhan.

## 1. Pham vi review

Commit Phase 2:

- `33b16bc Add control center service endpoints`
- `cc404a8 Build control center portal layout`
- `e5965df Add control center phase2 smoke tests`
- `33b7974 Keep control center monitoring read only`

File thay doi trong Phase 2:

- `app/Controllers/ControlCenterController.php`
- `app/Services/ControlCenterService.php`
- `app/Repositories/ControlCenterRepository.php`
- `index.php`
- `views/control-center.php`
- `tests/control-center-phase2.test.js`

Khong thay doi:

- Dashboard tenant.
- Nhan khau.
- Ho gia dinh.
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
- Business Module.
- Business Service.
- Business Controller.
- Database hien tai.

## 2. Service Layer

Ket qua: PASS

Danh gia:

- `ControlCenterController` chi xu ly request/response HTTP va goi `ControlCenterService`.
- `ControlCenterService` dieu phoi use-case dashboard, units, accounts, monitoring va fallback khi dependency khong kha dung.
- `ControlCenterRepository` la lop duy nhat doc du lieu tu database cho Control Center.
- View `views/control-center.php` khong query database.
- Controller khong query database truc tiep.

Rui ro con lai:

- `ControlCenterService` hien co fallback rong khi DB khong kha dung. Dieu nay dung cho production-safety, nhung can kiem thu tren staging co DB that de xac nhan so lieu tong hop.

## 3. Read Only

Ket qua: PASS

API da review:

- `GET /api/control-center/status`
- `GET /api/control-center/dashboard`
- `GET /api/control-center/units`
- `GET /api/control-center/accounts`
- `GET /api/control-center/monitoring`

Ket qua:

- Khong co endpoint POST/PUT/PATCH/DELETE cho Control Center trong Phase 2.
- Khong co SQL `INSERT`, `UPDATE`, `DELETE FROM`, `CREATE`, `ALTER`, `DROP`, `TRUNCATE`, `REPLACE` trong `ControlCenterRepository`.
- `monitoring` khong tao thu muc storage/log; chi tinh path va kiem tra `is_dir`/`is_writable`.
- `Database::diagnostics()` chi kiem tra ket noi va doc metadata ket noi.

Ghi chu:

- Ket qua search co false positive `updated_at` va `status <> 'DELETED'`; day la ten cot/gia tri loc read-only, khong phai thao tac ghi.

## 4. Aggregate Only

Ket qua: PASS

Dashboard tong chi lay du lieu tong hop:

- `COUNT(*)`
- `COUNT(DISTINCT ...)`
- `GROUP BY`
- Ty le BHYT duoc tinh tu tong `COUNT` va tong co BHYT.

Khong load:

- Danh sach nhan khau.
- Danh sach ho.
- Danh sach GIS.
- Danh sach business.
- Du lieu ca nhan chi tiet.

Rui ro con lai:

- Mot so dinh nghia tong hop nhu tre em, nguoi cao tuoi va lao dong dang nam trong query tong hop cua `ControlCenterRepository`. Day khong lam lo du lieu ca nhan, nhung nen duoc dua ve config/policy chung neu sau nay can chuan hoa toan he thong.

## 5. Dependency

Ket qua: PASS

Kiem tra:

- `ControlCenterController` khong phu thuoc `DashboardController`, `PersonController`, `HouseholdController`, `GisController`, `ImportController`, `ExportController`.
- `ControlCenterService` khong phu thuoc business controller/service.
- `ControlCenterRepository` khong goi business model/controller.
- `views/control-center.php` chi goi `/api/control-center/*`.

Ghi chu:

- `index.php` van import va dang ky controller nghiep vu tenant nhu truoc. Day la router hien huu cua Tenant Portal.
- Guard Control Center nam truoc phan khoi tao router dispatch nen domain goc khong dispatch vao controller nghiep vu tenant.

## 6. Portal Boundary

Ket qua: PASS

Luong Control Center hien tai:

```text
PortalContext
  -> ControlCenterController
      -> ControlCenterService
          -> ControlCenterRepository
              -> Database
```

Khong co luong:

```text
Control Center Portal
  -> Controller nghiep vu tenant
```

Kiem tra thuc te:

- Domain goc truy cap `/api/citizens`: bi chan.
- Domain goc truy cap `/api/households`: bi chan.
- Domain goc truy cap `/api/dashboard`: bi chan.
- Domain goc truy cap `/api/gis/areas`: bi chan.

## 7. Performance

Ket qua: PASS

Danh gia:

- Tenant bootstrap chi them import class va guard da co tu Phase 1; khi `PLATFORM_ADMIN_ENABLED=false`, Tenant van render theo duong cu.
- Control Center service/repository chi duoc khoi tao khi request domain goc vao `/api/control-center/*`.
- Control Center HTML load rieng `views/control-center.php`, khong load tenant app shell.
- Khong them dependency Composer/NPM.
- Khong them watcher, background job hoac long-lived process.
- Monitoring storage check da duoc giu read-only, khong tao thu muc.

So query uoc tinh khi goi cac API Control Center:

- `status`: 0 query.
- `dashboard`: nhieu query `COUNT` va metadata `INFORMATION_SCHEMA.TABLES`.
- `units`: 1 query tong hop co `GROUP BY`, cong metadata table check.
- `accounts`: 1 query `GROUP BY role`, cong metadata table check.
- `monitoring`: database diagnostics co toi da cac attempt ket noi DB theo cau hinh hien co.

Rui ro con lai:

- Dashboard hien tai dung nhieu query `COUNT` rieng le. Chap nhan cho Phase 2 read-only, nhung khi du lieu lon can benchmark va co the gom query trong phase toi uu sau.

## 8. Security

Ket qua: PASS

Kiem tra:

- Domain goc khong truy cap duoc API tenant.
- Chi `GET /api/control-center/*` duoc cho phep tren Control Center trong Phase 2.
- Khong them bypass permission cho API tenant.
- Khong them SSO, token sharing hoac session sharing.
- Control Center khong hien thi du lieu ca nhan.

Rui ro con lai:

- Cac endpoint Control Center Phase 2 hien chua co auth rieng. Day la gioi han da co tu Phase 1 shell va can duoc khoa bang authentication/authorization theo phase tiep theo truoc khi bat public production neu thong tin tong hop duoc xem la nhay cam.

## 9. Backward Compatibility

Ket qua: PASS

Da xac nhan:

- Tenant Portal van render `views/app.php`.
- Tenant subdomain `thon09.hongphongnb.com` van hien login view.
- Khi `PLATFORM_ADMIN_ENABLED=false`, domain goc quay ve tenant-compatible behavior.
- Khong sua UI/API/session/permission/business logic tenant.
- Khong sua module import/export/PDF/Excel.

Gioi han kiem thu:

- Local khong co DB day du, nen login/logout/session/token/permission runtime can smoke test lai tren staging hoac production-like environment.

## 10. Architecture Compliance

Ket qua: PASS voi rui ro roadmap can ghi nhan

Tuan thu:

- Community Control Center van la portal quan tri, khong la business system thu hai.
- Portal khong query database truc tiep.
- Controller moi khong query database truc tiep.
- Service/Repository la duong truy cap du lieu bat buoc.
- Business Modules khong bi copy, khong bi refactor.
- Database hien tai khong bi thay doi.

Rui ro roadmap:

- Architecture Freeze truoc do ghi roadmap Phase 2 la Control Center Shell, Phase 3 la Quan ly don vi, Phase 4 la Dashboard tong, Phase 6 la Monitoring.
- Yeu cau Phase 2 moi da mo rong acceptance criteria de gom Dashboard tong, Quan ly don vi, Quan ly tai khoan he thong va Monitoring co ban.
- Review nay xem day la scope Phase 2 da duoc nguoi phu trach phe duyet trong yeu cau trien khai, khong phai thay doi core architecture.
- Neu to chuc ap dung roadmap freeze theo nghia tuyet doi, can lap ADR hoac cap nhat roadmap truoc merge. Khong can sua runtime neu ADR chi xac nhan dieu chinh thu tu trien khai.

## 11. Technical Debt

Ket qua: PASS voi debt duoc ghi nhan

Debt:

- Mapping role Phase 2 dang map tu role hien co: `SUPER_ADMIN -> SYSTEM_ADMIN`, `ADMIN -> VILLAGE_ADMIN`, `OFFICER -> STAFF`, `VIEWER -> VIEWER`; `COMMUNE_ADMIN` chua co source role vi chua migration user model.
- Dashboard aggregate dang co dinh dinh nghia `tre em < 16` va lao dong theo cac flag hien co; nen chuan hoa vao config/policy neu tiep tuc su dung rong.
- Dashboard dung nhieu query `COUNT` rieng le; co the toi uu sau khi co benchmark tren DB production-like.
- `index.php` van la router tap trung lon cua tenant va Control Center guard nam truoc router. Day la cach toi thieu de khong refactor Phase 2, nhung ve dai han nen tach route registration theo PortalContext/ModuleRegistry khi toi phase da freeze tuong ung.

Khong phat hien:

- `TODO` runtime.
- `FIXME` runtime.
- `var_dump`.
- `print_r`.
- `die()`.
- `console.error` runtime.
- Duplicate controller/service nghiep vu.
- Dead endpoint Control Center.

Ghi chu:

- `console.log` chi co trong test `tests/control-center-phase2.test.js`.

## 12. Smoke Test

Da chay:

```powershell
php -l index.php
php -l app\Controllers\ControlCenterController.php
php -l app\Services\ControlCenterService.php
php -l app\Repositories\ControlCenterRepository.php
php -l views\control-center.php
node --check tests\control-center-phase2.test.js
node tests\control-center-phase2.test.js
```

Ket qua:

- PASS.

## 13. Regression Test

Da chay:

```powershell
php tests\portal-context.test.php
node tests\control-center-phase1.test.js
```

Kiem tra bo sung:

```text
/api/citizens: blocked
/api/households: blocked
/api/dashboard: blocked
/api/gis/areas: blocked
```

Ket qua:

- PASS.

## 14. Rollback Plan

Rollback nhanh:

```env
PLATFORM_ADMIN_ENABLED=false
```

Tac dung ky vong:

- Domain goc quay ve tenant-compatible behavior.
- Tenant Portal tiep tuc render `views/app.php`.
- Router/API nghiep vu tenant hoat dong nhu truoc tren subdomain tenant.

Rollback bang git neu can:

- Revert `33b7974 Keep control center monitoring read only`
- Revert `e5965df Add control center phase2 smoke tests`
- Revert `cc404a8 Build control center portal layout`
- Revert `33b16bc Add control center service endpoints`

## 15. Merge Recommendation

Ket luan: PASS

Khuyen nghi:

- Co the xem xet merge Phase 2 sau khi nguoi phu trach xac nhan.
- Khong push/merge tu dong.
- Khong chuyen sang Phase 3.
- Truoc deploy production can smoke test tren moi truong co DB that cho login/logout/session/token/permission va so lieu aggregate.
- Neu can tuan thu roadmap freeze theo thu tu tuyet doi, lap ADR ngan de xac nhan scope Phase 2 da duoc day som theo yeu cau moi.
