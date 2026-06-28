# Sprint 4 - Dashboard va Thong ke

## Pham vi da thuc hien

Sprint 4 hoan thien Dashboard cho ban PHP/MySQL, su dung du lieu tu bang `households` va `citizens`.

Da cap nhat:

- `app/Models/Dashboard.php`: Dashboard service co bo loc va tinh toan metrics/chart.
- `app/Controllers/DashboardController.php`: them API cho tung bieu do.
- `index.php`: dang ky route Dashboard.
- `views/app.php`: them bo loc Dashboard va khu vuc bieu do cu tru.
- `assets/js/app.js`: goi API Dashboard voi filter, render lai card va chart.

## API Dashboard

- `GET /api/dashboard/summary`
- `GET /api/dashboard/population-chart`
- `GET /api/dashboard/household-chart`
- `GET /api/dashboard/age-chart`

Tat ca API tren deu kiem tra quyen `dashboard/read` truoc khi xu ly.

## Chi so thong ke

- Tong so ho.
- Tong so nhan khau.
- Nam.
- Nu.
- Nhan khau dang hoat dong.
- Tam tru.
- Tam vang.

## Bieu do

- Co cau gioi tinh.
- Co cau do tuoi.
- Tinh trang ho.
- Co cau cu tru: Thuong tru/Tam tru.

## Bo loc

Dashboard ho tro loc theo:

- Khoang ngay tao du lieu.
- Trang thai ho.
- Trang thai cu tru: Thuong tru/Tam tru.
- Trang thai hien tai: O nha/Di vang.

## Hieu nang

- `summary` tra ve toan bo metrics va bieu do trong mot lan goi API.
- Query chi doc cac bang can thiet, khong doc lap qua vong lap.
- Cac bieu do rieng van co endpoint doc lap de tuong thich voi yeu cau Sprint 4.

## Dieu kien sang Sprint 5

- Dang nhap vao he thong.
- Dashboard tai duoc so lieu tong quan.
- Bo loc ngay, trang thai ho, cu tru va hien tai ap dung dung.
- Them/sua/xoa ho dan hoac nhan khau xong Dashboard cap nhat lai khi quay ve Tong quan.
