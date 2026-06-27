# He thong Quan ly Nhan khau Thon 09

Google Apps Script WebApp dung Google Sheets lam co so du lieu van hanh cho Thon 09. Ung dung phuc vu quan ly ho khau, nhan khau, bien dong cu tru, bao cao, PDF, sao luu, phan quyen, nguoi dung va nhat ky he thong.

## Nen tang

- Google Apps Script V8
- Google Sheets database
- Google Drive backup va PDF storage
- HtmlService WebApp
- Material Design ket hop AdminLTE

## Kien truc

Du an duoc to chuc theo Clean Architecture:

- `src/server/domain`: schema, enum, validation, chuan hoa entity.
- `src/server/application`: use case nghiep vu.
- `src/server/infrastructure`: Google Sheets, Drive, Session, LockService.
- `src/server/interface`: WebApp entrypoint va API controller.
- `src/html`, `src/css`, `src/js`: giao dien nguoi dung.

## Module nghiep vu

1. Dashboard
2. Household
3. Citizen
4. Movement
5. Report
6. PDF
7. Backup
8. Permission
9. User
10. Logs
11. Settings

## Dung luong thiet ke

- 1.000 ho
- 3.000 nhan khau

## Phan quyen

He thong dung tai khoan Google dang truy cap WebApp va bang `users` de xac dinh vai tro:

- `SUPER_ADMIN`: tai khoan quan tri dau tien, co toan quyen.
- `ADMIN`: quan tri van hanh, duoc cap quyen theo bang `permissions`.
- `OFFICER`: can bo cap nhat nghiep vu theo quyen duoc cau hinh.
- `VIEWER`: chi xem va xuat cac du lieu duoc phep.

Tat ca API deu di qua `SecurityService.requirePermission` truoc khi thuc thi nghiep vu.

## Trien khai

1. Cai dat clasp va dang nhap tai khoan Google co quyen quan tri Script ID.
2. Chay `clasp push` de dua ma nguon len Apps Script.
3. Trong Apps Script Editor, chay `setup()` mot lan de tao database sheets, seed quyen mac dinh, tao tai khoan quan tri dau tien va cau hinh he thong ban dau.
4. Deploy WebApp theo chinh sach truy cap cua don vi.
5. Vao module Backup de tao backup dau tien va kich hoat backup hang ngay neu can.

## Van hanh

- Tat ca thao tac ghi du lieu di qua API server va duoc ghi nhat ky.
- Xoa du lieu la xoa mem de bao toan lich su.
- User co the bi khoa/mo khoa trong module User.
- Permission duoc cau hinh theo vai tro, module va action.
- Audit Log ho tro tim kiem, loc theo ngay, module, action, level va email.
- Backup tao ban sao Spreadsheet va ghi lai metadata trong bang `backups`.
- Restore tao ban sao tu file backup va chuyen `DATABASE_SPREADSHEET_ID` sang ban da khoi phuc.
- PDF va Excel duoc tao tu template/server-side va luu vao Drive.
- Settings luu thong tin don vi, ten thon, cau hinh chung va tham so he thong trong bang `settings`.

## Hieu nang

- Repository doc Google Sheets theo lo trong mot lan goi API va cache noi bo theo request.
- Dashboard va Report tai su dung Repository/Service san co, han che doc trung du lieu.
- Cac danh sach nghiep vu co tim kiem va phan trang de van hanh on dinh voi khoang 3.000 nhan khau.