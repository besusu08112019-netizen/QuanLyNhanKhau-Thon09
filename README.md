# Hệ thống Quản lý Nhân khẩu Thôn 09

Ứng dụng web quản lý hộ gia đình, nhân khẩu, GIS, báo cáo, import/export, phân quyền, nhật ký và sao lưu dữ liệu cho Thôn 09 xã Hồng Phong.

## Nền tảng

- PHP 8.2 trở lên.
- MySQL hoặc MariaDB với charset `utf8mb4`.
- Apache/cPanel hoặc Linux hosting có rewrite về `index.php`.
- Frontend HTML5, CSS, Bootstrap 5, JavaScript ES6 và Fetch API.
- Backend MVC nhẹ, REST API, PDO prepared statements và JSON API.

## Cấu trúc chính

- `app/`: controller, model, service và core framework.
- `assets/css`, `assets/js`: giao diện và logic frontend.
- `views/app.php`: shell giao diện chính.
- `database/database.sql`: schema cơ sở dữ liệu nền.
- `database/migrations/`: migration bổ sung theo sprint.
- `sample-data/`: file mẫu import CSV/XLSX.
- `config/database.php`: cấu hình database production, không commit file này.
- `docs/`: tài liệu triển khai, kiểm thử, release và audit.
- `uploads/`: dữ liệu phát sinh khi vận hành, bị chặn truy cập trực tiếp qua `.htaccess`.
- `.cpanel.yml`: cấu hình cPanel Git Version Control.

## Chức năng

- Đăng nhập, đăng xuất, session token và CSRF token.
- Dashboard tổng quan, thống kê và biểu đồ.
- Quản lý hộ gia đình và nhân khẩu với tìm kiếm, phân trang, CRUD, xóa mềm và khôi phục.
- Hồ sơ hộ, hồ sơ nhân khẩu, popup chi tiết và lịch sử biến động.
- GIS, khu vực bản đồ, tìm kiếm vị trí hộ và xuất bản đồ.
- Import dữ liệu hộ/nhân khẩu từ CSV/XLSX theo tên cột.
- Báo cáo, export Excel/PDF và in biểu mẫu.
- Tài khoản, vai trò, phân quyền, nhật ký, sao lưu và khôi phục SQL.
- Cấu hình giao diện, logo, ảnh nền và thông tin hệ thống.

## Phân quyền

- `SUPER_ADMIN`: toàn quyền hệ thống.
- `ADMIN`: quản lý nghiệp vụ chính theo quyền mặc định.
- `OFFICER`: xem, thêm, sửa nghiệp vụ được phân quyền.
- `VIEWER`: chỉ xem dashboard, hộ, nhân khẩu và báo cáo.

Mọi API nghiệp vụ đều kiểm tra token đăng nhập và quyền trước khi xử lý. Các request thay đổi dữ liệu yêu cầu CSRF token hợp lệ.

## Triển khai nhanh

1. Trỏ document root của website vào thư mục chứa `index.php`.
2. Tạo database MySQL/MariaDB rỗng với charset `utf8mb4`.
3. Import `database/database.sql`.
4. Chạy các migration trong `database/migrations/` theo thứ tự tên file nếu nâng cấp từ bản cũ.
5. Tạo `config/database.php` từ `config/database.example.php` hoặc cấu hình biến môi trường `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET`.
6. Đảm bảo `APP_KEY` được cấu hình trong môi trường production. Nếu không, ứng dụng sẽ tạo secret runtime trong `uploads/.app_key`.
7. Mở website, tạo tài khoản quản trị đầu tiên nếu database chưa có admin.
8. Chạy checklist trong `docs/PRODUCTION_CHECKLIST.md` trước khi bàn giao.

## Build và kiểm thử local

```powershell
npm.cmd run build:assets
npm.cmd run check:js
npm.cmd run test:browser
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## Tài liệu liên quan

- `docs/DEPLOY_LINUX_HOSTING.md`: hướng dẫn triển khai Linux/cPanel.
- `docs/PRODUCTION_CHECKLIST.md`: checklist release production.
- `docs/security-audit-2026-07-05.md`: báo cáo security audit gần nhất.
- `docs/RELEASE_NOTES_2026-07-05.md`: release notes bản hiện tại.

## Lưu ý vận hành

- Không commit `config/database.php`, backup SQL hoặc dữ liệu upload production.
- Tạo backup SQL trước khi import dữ liệu thật, chạy migration hoặc khôi phục dữ liệu.
- Không xóa cứng dữ liệu nếu chưa có backup và phê duyệt nghiệp vụ.
- Kiểm tra tiếng Việt, console, API và responsive sau mỗi lần deploy.
- Chỉ kết luận Production Ready khi deployment production, migration và backup đã được xác minh thành công trên môi trường thật.

## Release v1.0.0

- Changelog: [CHANGELOG.md](CHANGELOG.md)
- Production runbook: [docs/RELEASE_V1.0.0.md](docs/RELEASE_V1.0.0.md)
