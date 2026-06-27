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

## Dung luong thiet ke

- 1.000 ho
- 3.000 nhan khau

## Trien khai

1. Cai dat clasp va dang nhap tai khoan Google co quyen quan tri Script ID.
2. Chay `clasp push` de dua ma nguon len Apps Script.
3. Trong Apps Script Editor, chay `setup()` mot lan de tao database sheets va tai khoan quan tri dau tien.
4. Deploy WebApp theo chinh sach truy cap cua don vi.

## Van hanh

- Tat ca thao tac ghi du lieu di qua API server va duoc ghi nhat ky.
- Xoa du lieu la xoa mem de bao toan lich su.
- Backup tao ban sao Spreadsheet va ghi lai metadata trong bang `backups`.
- PDF duoc tao tu template server-side va luu vao Drive.
