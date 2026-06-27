# Production Readiness Checklist

## Build va Deploy

- Chay `setup()` mot lan sau khi `clasp push` de tao sheets, seed quyen, cau hinh ban dau.
- Kiem tra `appsscript.json` co V8 runtime va cac scope: spreadsheets, drive, scriptapp, external_request, userinfo.email.
- Deploy Web App voi tai khoan quan tri he thong.
- Mo Web App bang tai khoan quan tri dau tien de xac nhan user `SUPER_ADMIN` duoc tao.

## Phan quyen

- Admin: toan quyen.
- Can bo: quan ly Ho dan, Nhan khau, Import, Bien dong; xem Dashboard/Report/PDF theo quyen.
- Chi xem: chi doc Dashboard, Ho dan, Nhan khau, Report.
- User, Permission, Settings, Backup, Logs chi cho Admin/SUPER_ADMIN.

## Kiem thu chuc nang

- Household: danh sach, tim kiem, phan trang, them, sua, xoa mem, validate ma ho.
- Person: danh sach, tim kiem, phan trang, them, sua, xoa mem, khoi phuc, validate CCCD va ma ho.
- Import Household: preview, mapping theo ten cot, validate Ma ho, bo qua/cap nhat ho trung, audit log.
- Import Person: preview, mapping theo ten cot, validate CCCD, Ma ho, Ho ten, Ngay sinh, Gioi tinh, audit log.
- Dashboard: cards, bieu do, bo loc, cap nhat sau khi du lieu thay doi.
- Report: preview, in, xuat PDF, xuat Excel, loc thoi gian/trang thai.
- User: user.page, user.get, them, sua, xoa, khoa, mo khoa, doi vai tro.
- Permission: xem va cap nhat quyen theo vai tro/module/action.
- Logs: tim kiem, loc, phan trang, log dang nhap/dang xuat/thao tac du lieu.
- Backup: tao backup, danh sach backup, restore, trigger backup hang ngay.
- Settings: cap nhat thong tin don vi, ten thon, tham so chung.

## Hieu nang

- Repository doc Google Sheets theo lo va cache trong tung request API.
- Import doc source spreadsheet mot lan, validate bang map bo nho va ghi batch cho ban ghi moi.
- Danh sach lon dung phan trang server-side.
- Dashboard/Report tai su dung service va doc du lieu theo lo.
- Han che goi API lap lai tren UI; moi thao tac hien loading va thong bao ket qua.

## Van hanh

- Tao backup dau tien truoc khi nhap du lieu that.
- Sau khi restore, tai lai Web App de su dung spreadsheet moi.
- Dinh ky kiem tra Logs va Backup.
- Khong sua truc tiep schema cac sheet production neu khong co ke hoach migrate.