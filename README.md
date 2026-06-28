# Hệ thống Quản lý Nhân khẩu Thôn 09

Ứng dụng Web quản lý hộ dân và nhân khẩu cho Thôn 09 xã Hồng Phong, đã chuyển sang chạy độc lập trên Linux Hosting bằng PHP/MySQL. Dự án không còn phụ thuộc Google Apps Script, Google Sheets, Google Drive hoặc `google.script.run`.

## Nền tảng

- PHP 8.2 trở lên.
- MySQL hoặc MariaDB.
- Apache hoặc Nginx trên Linux Hosting thông thường.
- HTML5, CSS3, Bootstrap 5, JavaScript ES6 và Fetch API.
- Backend MVC, REST API, PDO và JSON API.

## Cấu trúc chính

- `app/`: lõi ứng dụng, controller, model, service dùng chung.
- `assets/css`: giao diện.
- `assets/js`: xử lý màn hình và gọi REST API.
- `views`: giao diện Web.
- `database/database.sql`: cấu trúc cơ sở dữ liệu MySQL/MariaDB.
- `config/database.php`: cấu hình kết nối cơ sở dữ liệu.
- `docs`: tài liệu phân tích, triển khai và checklist.
- `uploads`: thư mục phục vụ file phát sinh khi triển khai.
- `index.php`: điểm vào ứng dụng.
- `.htaccess`: điều hướng URL và bảo vệ thư mục nhạy cảm.

## Chức năng

- Đăng nhập bằng tài khoản và mật khẩu.
- Dashboard tổng quan, thống kê và biểu đồ.
- Quản lý hộ dân.
- Quản lý nhân khẩu.
- Tìm kiếm, phân trang, thêm, sửa, xóa mềm, xóa nhiều.
- Đồng bộ chủ hộ và số thành viên theo mã hộ.
- Báo cáo thống kê, báo cáo người có công, hộ nghèo, hộ cận nghèo, tàn tật.
- Xuất Excel, xuất PDF và in phiếu.
- Quản lý người dùng, vai trò và phân quyền.
- Nhật ký hệ thống.
- Sao lưu và phục hồi dữ liệu SQL.

## Phân quyền

- `SUPER_ADMIN`: quản trị tối cao.
- `ADMIN`: toàn quyền quản trị và vận hành.
- `OFFICER`: quản lý hộ dân, nhân khẩu, báo cáo và import/export theo nghiệp vụ.
- `VIEWER`: chỉ xem dashboard, hộ dân, nhân khẩu và báo cáo.

Mọi API nghiệp vụ đều kiểm tra token đăng nhập và quyền trước khi xử lý.

## Triển khai nhanh

1. Upload toàn bộ source lên hosting.
2. Trỏ document root của website vào thư mục chứa `index.php`.
3. Tạo database MySQL/MariaDB rỗng.
4. Import file `database/database.sql`.
5. Chỉnh thông tin kết nối tại `config/database.php`, hoặc khai báo biến môi trường `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET`.
6. Mở website và tạo tài khoản quản trị đầu tiên qua API `/api/auth/setup` nếu database chưa có admin.
7. Đăng nhập, kiểm tra Dashboard, Hộ dân, Nhân khẩu, Báo cáo, Người dùng, Nhật ký và Sao lưu.

Chi tiết xem `docs/DEPLOY_LINUX_HOSTING.md` và `docs/PRODUCTION_CHECKLIST.md`.

## Cấu hình database

Mặc định file `config/database.php` đọc biến môi trường trước, sau đó dùng giá trị mẫu. Khi đưa lên hosting, nên chỉnh trực tiếp theo thông tin database của hosting nếu không có quyền cấu hình biến môi trường.

```php
return [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'quan_ly_nhan_khau_thon09',
    'username' => 'ten_user_database',
    'password' => 'mat_khau_database',
    'charset' => 'utf8mb4',
];
```

## Lưu ý vận hành

- Không seed mật khẩu mặc định trong `database.sql` để tránh rủi ro bảo mật.
- Sau khi tạo tài khoản quản trị đầu tiên, nên tạo bản sao lưu SQL ngay.
- Thao tác xóa là xóa mềm để giữ lịch sử dữ liệu.
- File PDF dùng bộ tạo PDF PHP thuần, phù hợp shared hosting không cần cài thêm dịch vụ.
- Trước khi bàn giao, chạy theo checklist trong `docs/PRODUCTION_CHECKLIST.md`.
