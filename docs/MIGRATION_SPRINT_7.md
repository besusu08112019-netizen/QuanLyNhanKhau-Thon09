# Sprint 7 - Phan quyen, Bao mat va Sao luu

## Pham vi da thuc hien

Sprint 7 hoan thien cac phan quan tri can thiet cho ban PHP/MySQL tren Linux Hosting.

Da tao va cap nhat:

- `app/Models/User.php`: CRUD nguoi dung, tim kiem, khoa/mo khoa, doi mat khau, phan quyen.
- `app/Controllers/UserController.php`: API quan ly nguoi dung chi danh cho Admin/Super Admin.
- `app/Models/AuditLog.php`: phan trang, tim kiem audit log.
- `app/Controllers/LogController.php`: API xem nhat ky.
- `app/Models/Backup.php`: tao backup SQL, lich su backup, phuc hoi SQL.
- `app/Controllers/BackupController.php`: API sao luu/phuc hoi.
- `assets/js/admin.js`: giao dien nguoi dung, nhat ky, sao luu.
- `assets/js/report.js`: nap them admin UI.
- `index.php`: route quan tri.

## API nguoi dung

- `GET /api/users`
- `POST /api/users`
- `GET /api/users/{id}`
- `PUT /api/users/{id}`
- `DELETE /api/users/{id}`
- `POST /api/users/{id}/lock`
- `POST /api/users/{id}/unlock`
- `GET /api/roles`

## API nhat ky

- `GET /api/logs`

Ho tro tim kiem theo nguoi thuc hien, noi dung, entity id, module, action va khoang ngay.

## API sao luu

- `GET /api/backups`
- `POST /api/backups`: tao va tai file `.sql`.
- `POST /api/backups/restore`: phuc hoi tu noi dung SQL trong body JSON `{ "sql": "..." }`.

## Phan quyen

- Admin/Super Admin: quan tri nguoi dung, xem log, backup/restore.
- Can bo: giu quyen nghiep vu ho dan, nhan khau, bao cao theo logic hien co.
- Chi xem: chi doc dashboard, ho dan, nhan khau va bao cao.

## Audit log

Da ghi log cho:

- Dang nhap/dang xuat.
- Them/sua/xoa ho dan va nhan khau.
- Xuat Excel/PDF, in bao cao.
- Tao/sua/xoa/khoa/mo khoa nguoi dung.
- Tao backup va restore.

## Luu y trien khai

- Backup SQL tai ve truc tiep tu trinh duyet, phu hop shared hosting.
- Restore SQL la thao tac nguy hiem, chi nen dung boi Admin va nen tao backup truoc khi restore.
- PDF duoc tao bang PHP thuan; Excel va man hinh in giu tieng Viet co dau day du.
