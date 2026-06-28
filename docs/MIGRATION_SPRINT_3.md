# Sprint 3 - Chuyen Frontend sang Web chuan

## Pham vi da thuc hien

Sprint 3 thay trang giu cho Sprint 2 bang giao dien HTML5/CSS3/Bootstrap 5/JavaScript ES6 dung Fetch API.

Da tao va cap nhat:

- `views/app.php`: giao dien dang nhap va khung ung dung chinh.
- `assets/css/app.css`: bo cuc responsive, sidebar, topbar, card, bang, modal, thong bao.
- `assets/js/app.js`: xu ly dang nhap, dieu huong, goi REST API, tim kiem, phan trang, them/sua/xoa.
- `app/Models/Household.php`: API chi tiet ho tra them so thanh vien, so nguoi o nha va di vang.

## Chuc nang frontend da co

- Man hinh dang nhap hanh chinh.
- Menu Tong quan, Ho dan, Nhan khau, Bao cao.
- Tong quan doc du lieu tu `/api/dashboard/summary`.
- Danh sach ho dan, tim kiem, phan trang.
- Them, sua, xoa, xoa nhieu ho dan.
- Xem chi tiet ho dan va danh sach thanh vien theo Ma ho.
- Danh sach nhan khau, tim kiem, loc theo Ma ho, phan trang.
- Nhan khau sap xep theo Ma ho, chu ho len dau theo API backend.
- Them, sua, xoa, xoa nhieu nhan khau.
- Xem chi tiet nhan khau day du cac truong nghiep vu dang co.
- Danh muc chon cho dan toc, ton giao, nghe nghiep, quan he, hoc van, hon nhan.
- Hien thi ngay sinh theo dinh dang ngay/thang/nam.
- Loading khi goi API va thong bao thanh cong/loi.

## Nguyen tac da giu

- Khong dung Google Apps Script.
- Khong dung Google Sheets.
- Khong dung `google.script.run`.
- Giao tiep frontend/backend qua JSON REST API.
- Giu API Sprint 2, chi bo sung du lieu chi tiet ho dan de giao dien hien thi dung.

## Gioi han Sprint 3

- Bao cao nang cao, import/export Excel, PDF, in phieu, backup/restore va quan tri nguoi dung day du duoc chuyen trong Sprint 4-7.
- Bootstrap hien dang tai qua CDN; neu hosting can chay noi bo hoan toan thi se dua file thu vien vao `assets/vendor` o Sprint production.

## Dieu kien sang Sprint 4

- Dang nhap thanh cong bang tai khoan da tao tu `/api/auth/setup`.
- Mo duoc Dashboard sau dang nhap.
- Them/sua/xoa va tim kiem Ho dan hoat dong.
- Them/sua/xoa va tim kiem Nhan khau hoat dong.
- Bam vao Ho dan hien danh sach thanh vien dung theo Ma ho.
