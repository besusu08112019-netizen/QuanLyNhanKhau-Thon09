# Sprint 1 - Phan tich migration sang PHP/MySQL

## Muc tieu Sprint 1

Sprint nay chi thuc hien phan tich va thiet ke nen du lieu moi. Khong chuyen backend, khong chuyen frontend va khong thay doi ung dung Google Apps Script dang chay.

Dau ra cua Sprint 1:

- Liet ke thanh phan hien tai cua he thong.
- Liet ke toan bo diem phu thuoc Google Apps Script/Google Sheets.
- Thiet ke co so du lieu MySQL/MariaDB tai `database/database.sql`.
- Xac dinh ranh gioi cho Sprint 2.

## Tong quan source hien tai

He thong hien la Google Apps Script WebApp, to chuc gan voi kien truc Repository -> Service -> API -> UI.

### Server

- `src/server/domain/Constants.gs`: khai bao module, action, role, table, schema sheet.
- `src/server/domain/Entity.gs`: tao ID, audit fields, serialize/deserialize row.
- `src/server/infrastructure/SheetRepository.gs`: lop du lieu dung Google Sheets.
- `src/server/infrastructure/HouseholdRepository.gs`: repository ho dan.
- `src/server/infrastructure/PersonRepository.gs`: repository nhan khau.
- `src/server/infrastructure/UserRepository.gs`: repository nguoi dung.
- `src/server/infrastructure/ImportRepository.gs`: doc Google Spreadsheet nguon import.
- `src/server/application/*Service.gs`: nghiep vu ho dan, nhan khau, dashboard, bao cao, import, log, phan quyen, backup, cau hinh.
- `src/server/interface/ApiController.gs`: router action-string cho `google.script.run`.
- `src/server/interface/WebApp.gs`: render HTML Service.

### Frontend

- `src/html/Index.html`: shell chinh, login, include cac module.
- `src/js/App.html`: dashboard va dieu huong chinh.
- `src/js/HouseholdModule.html`: quan ly ho dan.
- `src/js/PersonModule.html`: quan ly nhan khau.
- `src/js/PersonDetailEnhancement.html`: hien thi chi tiet nhan khau va thao tac xoa nhieu.
- `src/js/ReportModule.html`: bao cao, in, export.
- `src/js/AdminModule.html`: nguoi dung, phan quyen, logs, backup.
- `src/js/ImportModule.html`: import du lieu va file mau Excel.
- `src/css/App.html`: style AdminLTE/Material hien tai.

## Bang du lieu hien tai trong Google Sheets

Cac bang dang duoc mo phong bang sheet:

- `households`
- `citizens`
- `movements`
- `users`
- `permissions`
- `logs`
- `backups`
- `settings`

## Phu thuoc Google Apps Script can loai bo

### Runtime va WebApp

- `HtmlService`: render WebApp va template include.
- `doGet`: entry point WebApp.
- File `.gs` va HTML Service partials.

Phuong an thay the trong PHP:

- `index.php` lam entry point.
- `views/` gom layout va partial HTML chuan.
- Apache rewrite qua `.htaccess`.

### Goi API tu frontend

- `google.script.run.withSuccessHandler(...).withFailureHandler(...).api(action, payload)`.

Phuong an thay the:

- `fetch('/api/...', { method, headers, body })`.
- JSON response thong nhat: `{ ok: true, data }` hoac `{ ok: false, error }`.

### Database

- `SpreadsheetApp.openById`.
- `SpreadsheetApp.create`.
- `Sheet.getRange().getValues()`.
- `Sheet.appendRow()`.
- `Range.setValues()`.
- Header sync va migration cot trong sheet.

Phuong an thay the:

- MySQL/MariaDB + PDO.
- Repository PHP dung SQL co index, foreign key va transaction.
- Soft delete bang `deleted_at`, `deleted_by`, `status`.

### Properties va cache

- `PropertiesService.getScriptProperties()` luu spreadsheet ID, folder ID, password hash.
- `CacheService.getScriptCache()` luu session token.

Phuong an thay the:

- `config/database.php` va `config/app.php`.
- Bang `user_sessions` luu token, thoi han, IP/user agent.
- Password hash luu trong bang `users.password_hash` bang `password_hash()` cua PHP.

### Lock va transaction

- `LockService.getScriptLock()`.

Phuong an thay the:

- PDO transaction: `beginTransaction`, `commit`, `rollBack`.
- Rang buoc unique va foreign key trong MySQL.

### Google user identity

- `Session.getActiveUser().getEmail()` hoac ham tuong duong qua `Entity.currentEmail()`.
- Auto tao user theo email Google.

Phuong an thay the:

- Dang nhap bang tai khoan/mat khau noi bo.
- Session token noi bo.
- Khong tao user tu Google.

### Utilities

- `Utilities.getUuid()`.
- `Utilities.computeDigest()`.
- `Utilities.formatDate()`.
- `Utilities.newBlob()`.

Phuong an thay the:

- PHP `random_bytes`, `bin2hex`, helper tao ID.
- PHP `password_hash`, `password_verify`.
- `DateTimeImmutable` voi timezone `Asia/Ho_Chi_Minh`.
- Thu vien PHP tuong duong cho Excel/PDF o cac sprint sau.

### Import tu Google Spreadsheet

- `ImportRepository.readSheet(spreadsheetId, sheetName)` doc Google Sheets bat ky.

Phuong an thay the:

- Upload file `.xlsx`, `.xls`, `.csv` vao `uploads/imports`.
- Parse bang thu vien PHP o Sprint 5.
- Mapping theo ten cot giu nguyen nghiep vu hien tai.

### Export/PDF/Backup

- Tao file Google Drive/PDF/Excel qua Apps Script va folder ID.
- Backup sheet/Google Drive.

Phuong an thay the:

- Export Excel/PDF tao file trong `uploads/exports`.
- Backup MySQL bang file `.sql` hoac `.zip` trong `uploads/backups`.
- Bang `backups` luu metadata file backup.

## API hien tai can chuyen sang REST

### Auth

- `auth.login` -> `POST /api/auth/login`
- `auth.logout` -> `POST /api/auth/logout`
- `user.me` -> `GET /api/auth/me`

### Dashboard

- `dashboard.summary` -> `GET /api/dashboard/summary`
- `dashboard.populationChart` -> `GET /api/dashboard/population-chart`
- `dashboard.householdChart` -> `GET /api/dashboard/household-chart`
- `dashboard.ageChart` -> `GET /api/dashboard/age-chart`

### Ho dan

- `household.page` -> `GET /api/households`
- `household.list` -> `GET /api/households/list`
- `household.create` -> `POST /api/households`
- `household.update` -> `PUT /api/households/{id}`
- `household.delete` -> `DELETE /api/households/{id}`

### Nhan khau

- `person.safePage`, `person.page` -> `GET /api/persons`
- `person.get` -> `GET /api/persons/{id}`
- `person.create` -> `POST /api/persons`
- `person.update` -> `PUT /api/persons/{id}`
- `person.delete` -> `DELETE /api/persons/{id}`
- `person.restore` -> `POST /api/persons/{id}/restore`

### Bien dong

- `movement.list` -> `GET /api/movements`
- `movement.create` -> `POST /api/movements`
- `movement.update` -> `PUT /api/movements/{id}`
- `movement.delete` -> `DELETE /api/movements/{id}`

### Bao cao/PDF/Import/Backup/Admin

- `report.*` -> `/api/reports/*`
- `pdf.citizen` -> `/api/pdf/citizen/{id}`
- `import.*` -> `/api/import/*`
- `backup.*` -> `/api/backups/*`
- `user.*`, `role.list`, `permission.*` -> `/api/admin/*`
- `logs.*` -> `/api/logs`
- `settings.*` -> `/api/settings`

## Thiet ke database MySQL

File chi tiet: `database/database.sql`.

### Bang chinh

- `users`: tai khoan noi bo, role, password hash, trang thai.
- `user_sessions`: token dang nhap thay cho CacheService.
- `households`: ho dan, dia chi, dien ho, chu ho.
- `citizens`: nhan khau, lien ket ho, CCCD, cu tru, hien tai.
- `movements`: bien dong dan cu.
- `permissions`: cau hinh quyen theo role/module/action.
- `audit_logs`: nhat ky he thong.
- `backups`: metadata ban sao luu.
- `settings`: cau hinh he thong.
- `import_batches`, `import_errors`: theo doi import file.
- `export_files`: metadata file xuat Excel/PDF.

### Quan he chinh

- `citizens.household_id` -> `households.id`.
- `households.head_citizen_id` -> `citizens.id` nullable.
- `movements.citizen_id` -> `citizens.id`.
- `movements.household_id` -> `households.id`.
- Audit fields `created_by`, `updated_by`, `deleted_by` -> `users.id` nullable.

### Index quan trong

- Unique `households.household_code`.
- Unique nullable `citizens.identity_number`.
- Index `citizens.household_id`.
- Index `citizens.full_name`.
- Index `citizens.presence_status`, `citizens.residency_status`, `citizens.status`.
- Index logs theo `created_at`, `module`, `action`, `actor_user_id`.

## Quyet dinh mapping du lieu

| Google Sheet | MySQL |
| --- | --- |
| `households.householdCode` | `households.household_code` |
| `households.address` | `households.address` |
| `households.headCitizenName` | `households.head_citizen_name` |
| `citizens.citizenCode` | `citizens.citizen_code` |
| `citizens.householdId` | `citizens.household_id` qua lookup `household_code` khi import |
| `citizens.permanentAddress` | `citizens.residency_status` voi gia tri `PERMANENT`, `TEMPORARY` |
| `citizens.presenceStatus` | `citizens.presence_status` voi gia tri `AT_HOME`, `AWAY` |
| `users.role` | `users.role` |
| `logs` | `audit_logs` |
| `settings` | `settings` |
| `backups` | `backups` |

## Viec khong lam trong Sprint 1

- Chua viet PHP MVC.
- Chua sua giao dien sang Fetch API.
- Chua tao upload/import Excel PHP.
- Chua tao PDF PHP.
- Chua dung Apps Script hien co.
- Chua deploy hosting.

## Dieu kien de sang Sprint 2

Sprint 1 duoc xem la hoan thanh khi:

- Tai lieu phan tich da duoc commit.
- `database/database.sql` da co schema MySQL/MariaDB.
- Thiet ke bang phu hop voi cac module hien co.
- Nguoi dung xac nhan chuyen sang Sprint 2.

Sprint 2 se bat dau bang viec tao khung PHP MVC, PDO, REST router, auth noi bo va repository/service cho ho dan + nhan khau.