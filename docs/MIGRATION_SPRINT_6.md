# Sprint 6 - PDF va In phieu

## Pham vi da thuc hien

Sprint 6 bo sung xuat PDF va in bao cao cho ban PHP/MySQL.

Da tao va cap nhat:

- `app/Core/SimplePdf.php`: trinh tao PDF bang PHP thuan.
- `app/Controllers/ReportController.php`: bat API `exportPdf` va ghi audit log.
- `assets/js/report.js`: nut Xuat PDF tai file PDF, nut In mo trang in A4.

## API

- `GET /api/reports/export-pdf`
- `GET /api/reports/print`

## Ket qua

- Xuat PDF file `.pdf` theo loai bao cao dang chon.
- In bao cao bang trang A4 rieng, tu dong mo hop thoai in.
- Ghi audit log khi in va xuat PDF.
- Khong dung Google, khong dung Composer, khong phu thuoc thu vien PDF ben ngoai.

## Luu y ky thuat

PDF exporter duoc viet de chay tren shared hosting. De tranh phu thuoc font he thong, noi dung PDF duoc chuyen ve dang ASCII khong dau. Ban in tren trinh duyet va Excel van giu tieng Viet co dau.

## Dieu kien sang Sprint 7

- Xem truoc bao cao thanh cong.
- Nut In mo trang A4.
- Nut Xuat PDF tai file PDF.
- Nut Xuat Excel van hoat dong nhu Sprint 5.
