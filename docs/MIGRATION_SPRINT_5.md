# Sprint 5 - Bao cao va Xuat Excel

## Pham vi da thuc hien

Sprint 5 chuyen module Bao cao sang PHP/MySQL va bo sung xuat Excel khong phu thuoc dich vu Google.

Da tao va cap nhat:

- `app/Models/Report.php`: tao du lieu bao cao.
- `app/Controllers/ReportController.php`: API xem truoc, in du lieu va xuat Excel.
- `index.php`: dang ky route bao cao.
- `views/app.php`: giao dien Bao cao.
- `assets/js/report.js`: goi API, xem truoc, in va tai file Excel.

## API Bao cao

- `GET /api/reports/summary`
- `GET /api/reports/population`
- `GET /api/reports/household`
- `GET /api/reports/export-excel`
- `GET /api/reports/print`
- `GET /api/reports/export-pdf` tra ve 501 va se hoan thien o Sprint 6.

## Loai bao cao

- Bao cao tong hop.
- Danh sach ho dan.
- Danh sach nhan khau.
- Bao cao theo gioi tinh.
- Bao cao theo do tuoi.
- Bao cao theo cu tru.
- Danh sach nguoi co cong, ho ngheo, ho can ngheo, tan tat.
- Bao cao bien dong dan cu.

## Bo loc

- Khoang thoi gian.
- Cu tru: Thuong tru/Tam tru.
- Hien tai: O nha/Di vang.
- Trang thai: Con song/Da chet.

## Xuat Excel

- Xuat file `.xls` tu HTML table co BOM UTF-8.
- Chay duoc tren Linux Hosting shared hosting khong can Composer.
- Ten file tu dong theo ngay gio.
- Ghi audit log thao tac xuat Excel.

## Dieu kien sang Sprint 6

- Xem truoc duoc tung loai bao cao.
- Tai Excel thanh cong.
- Du lieu bao cao doc tu MySQL, khong dung Google Sheets.
- API bao cao kiem tra quyen truoc khi xu ly.
