# Báo cáo rà soát Production

Ngày rà soát: 2026-06-28

## Phạm vi

Rà soát phiên bản PHP/MySQL của hệ thống Quản lý Nhân khẩu Thôn 09 để chuẩn bị chạy trên Linux Hosting, Apache, PHP 8.2 và MySQL 8 cho domain `nhankhauthon09.com`.

## Lỗi và rủi ro đã xử lý

- Bổ sung CSRF token cho các API thay đổi dữ liệu để giảm rủi ro giả mạo request khi hệ thống chạy public.
- API dùng `requirePermission()` hiện kiểm tra CSRF trước khi thực hiện thao tác ghi dữ liệu.
- Đăng xuất yêu cầu CSRF token hợp lệ trước khi thu hồi session token.
- Frontend tự lưu CSRF token sau đăng nhập và tự gửi token ở các request `POST`, `PUT`, `PATCH`, `DELETE`.
- Import Excel/CSV gửi CSRF token khi upload file.
- Tạo backup SQL gửi CSRF token khi thực hiện thao tác nhạy cảm.
- Bổ sung `APP_KEY` và timezone từ biến môi trường để cấu hình production an toàn hơn.
- Bổ sung vai trò `COLLABORATOR` vào màn hình ma trận phân quyền để đồng bộ với migration database.
- Bổ sung `composer.json` để khai báo PHP 8.2 và các extension cần có trên hosting.
- Bổ sung `.env.example` cho domain `nhankhauthon09.com`.
- Cập nhật hướng dẫn triển khai Linux Hosting riêng cho `nhankhauthon09.com`.

## File đã chỉnh sửa hoặc tạo mới

- `app/Core/BaseController.php`
- `app/Controllers/AuthController.php`
- `app/Models/User.php`
- `app/Models/Permission.php`
- `config/app.php`
- `views/app.php`
- `assets/js/csrf.js`
- `assets/js/admin.js`
- `assets/js/import.js`
- `composer.json`
- `.env.example`
- `docs/DEPLOY_LINUX_HOSTING.md`
- `docs/PRODUCTION_AUDIT_REPORT.md`

## Thay đổi database

- Không thay đổi cấu trúc database trong lần rà soát này.
- Tiếp tục sử dụng migration hiện có: `database/migrations/2026_06_28_admin_panel.sql`.
- Khi triển khai mới, cần import `database/database.sql`, sau đó import migration admin panel.

## Chức năng cần kiểm thử trực tiếp trên hosting

- Đăng nhập và nhận CSRF token.
- Đăng xuất.
- Dashboard và biểu đồ.
- Quản lý hộ dân: thêm, sửa, xóa, tìm kiếm, phân trang.
- Quản lý nhân khẩu: thêm, sửa, xóa, tìm kiếm, phân trang.
- Import Excel/CSV.
- Export Excel.
- Xuất PDF và in phiếu.
- Upload ảnh/giấy tờ.
- Nhật ký hệ thống.
- Quản lý người dùng.
- Phân quyền.
- Sao lưu và khôi phục.

## Giới hạn kiểm thử trong môi trường hiện tại

- Môi trường hiện tại không cho phép clone repository qua mạng và không có MySQL/PHP Linux runtime thực để chạy end-to-end.
- Chưa có thông tin FTP/SFTP/cPanel/SSH và tài khoản MySQL của hosting nên chưa thể upload trực tiếp lên `nhankhauthon09.com`.
- Cần kiểm thử thực tế trên hosting sau khi có thông tin truy cập để xác nhận không còn lỗi PHP, SQL, routing, session, upload và authentication.

## Checklist triển khai thực tế

- Trỏ DNS `nhankhauthon09.com` về IP hosting.
- Upload source vào `public_html` hoặc document root tương ứng.
- Import `database/database.sql` và migration admin panel.
- Cấu hình `config/database.php` hoặc biến môi trường database.
- Đặt `APP_KEY` production riêng, không dùng giá trị mặc định.
- Chọn PHP 8.2+ và bật extension bắt buộc.
- Đặt quyền ghi cho `uploads/`.
- Truy cập `/api/health` để kiểm tra routing.
- Tạo tài khoản quản trị đầu tiên qua `/api/auth/setup`.
- Kiểm thử toàn bộ chức năng chính trước khi nhập dữ liệu thật.
