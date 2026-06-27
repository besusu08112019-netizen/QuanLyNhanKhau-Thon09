# He thong Quan ly Nhan khau Thon 09

Google Apps Script WebApp dung Google Sheets lam co so du lieu van hanh cho Thon 09. Ung dung phuc vu quan ly ho khau, nhan khau, import du lieu ban dau, bien dong cu tru, bao cao, PDF, sao luu, phan quyen, nguoi dung va nhat ky he thong.

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
- `src/server/infrastructure`: Google Sheets, Drive, Session, LockService va Repository.
- `src/server/interface`: WebApp entrypoint va API controller.
- `src/html`, `src/css`, `src/js`: giao dien nguoi dung.

## Module nghiep vu

1. Dashboard
2. Household
3. Citizen
4. Movement
5. Import
6. Report
7. PDF
8. Backup
9. Permission
10. User
11. Logs
12. Settings

## Dung luong thiet ke

- 1.000 ho
- 3.000 nhan khau

## Phan quyen

He thong dung tai khoan Google dang truy cap WebApp va bang `users` de xac dinh vai tro:

- `SUPER_ADMIN`: tai khoan quan tri dau tien, co toan quyen.
- `ADMIN`: toan quyen quan tri va van hanh he thong.
- `OFFICER`: quan ly Ho dan, Nhan khau, Import, Bien dong; xem Dashboard, Report va xuat bieu mau duoc phep.
- `VIEWER`: chi doc Dashboard, Ho dan, Nhan khau va Report.

Tat ca API deu di qua `SecurityService.requirePermission` truoc khi thuc thi nghiep vu. Module User, Permission, Settings, Backup va Logs chi cho Admin/SUPER_ADMIN.

## Import du lieu ban dau

- Ho tro import Ho gia dinh va Nhan khau tu Google Spreadsheet ID va ten Sheet.
- Mapping theo ten cot, khong phu thuoc thu tu cot.
- Co buoc preview de kiem tra tong dong, dong hop le va chi tiet loi.
- Import ghi batch cho ban ghi moi va ghi audit log voi spreadsheet, sheet, tong dong, thanh cong, that bai.
- Ho gia dinh co tuy chon bo qua hoac cap nhat khi Ma ho da ton tai.

## Trien khai

1. Cai dat clasp va dang nhap tai khoan Google co quyen quan tri Script ID.
2. Chay `clasp push` de dua ma nguon len Apps Script.
3. Trong Apps Script Editor, chay `setup()` mot lan de tao database sheets, seed quyen mac dinh, tao tai khoan quan tri dau tien va cau hinh he thong ban dau.
4. Deploy WebApp theo chinh sach truy cap cua don vi.
5. Vao module Backup de tao backup dau tien va kich hoat backup hang ngay neu can.
6. Theo doi `docs/PRODUCTION_CHECKLIST.md` de kiem thu truoc khi ban giao.

## Van hanh

- Tat ca thao tac ghi du lieu di qua API server va duoc ghi nhat ky.
- Xoa du lieu la xoa mem de bao toan lich su.
- User co the bi khoa/mo khoa, doi vai tro va doi mat khau ung dung trong module User.
- Permission duoc cau hinh theo vai tro, module va action, nhung khong vuot qua chinh sach role production.
- Audit Log ho tro tim kiem, loc theo ngay, module, action, level va email.
- Backup tao ban sao Spreadsheet va ghi lai metadata trong bang `backups`.
- Restore tao ban sao tu file backup va chuyen `DATABASE_SPREADSHEET_ID` sang ban da khoi phuc.
- PDF va Excel duoc tao tu template/server-side va luu vao Drive.
- Settings luu thong tin don vi, ten thon, cau hinh chung va tham so he thong trong bang `settings`.

## Hieu nang

- Repository doc Google Sheets theo lo trong mot lan goi API va cache noi bo theo request.
- Import doc source spreadsheet mot lan, validate bang map bo nho va ghi batch cho ban ghi moi.
- Danh sach lon dung phan trang server-side.
- Dashboard va Report tai su dung Repository/Service san co, han che doc trung du lieu.
- Cac module UI hien loading, thong bao thanh cong/loi va xu ly loi than thien.