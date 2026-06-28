# Hướng dẫn triển khai Linux Hosting cho nhankhauthon09.com

Tài liệu này dùng cho bản PHP/MySQL của Hệ thống Quản lý Nhân khẩu Thôn 09. Bản hiện tại không sử dụng Google Apps Script, Google Sheets hoặc bất kỳ dịch vụ Google nào.

## 1. Yêu cầu hosting

- Domain: `nhankhauthon09.com`.
- PHP 8.2 trở lên.
- MySQL 8 hoặc MariaDB 10.4+.
- PHP extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `simplexml`, `json`.
- Apache có bật `mod_rewrite`, hoặc Nginx có cấu hình rewrite tương đương.
- Website trỏ vào thư mục chứa `index.php`, thường là `public_html`.
- Charset/collation database: `utf8mb4_unicode_ci`.

## 2. Trỏ domain

Trong trang quản lý DNS của domain, trỏ bản ghi:

```text
A     @      IP_HOSTING
A     www    IP_HOSTING
```

Nếu hosting yêu cầu nameserver riêng, đổi nameserver theo thông tin nhà cung cấp hosting. Sau khi đổi DNS, thời gian nhận có thể từ vài phút đến 24 giờ.

## 3. Upload source

Upload toàn bộ mã nguồn lên thư mục chạy web của hosting, ví dụ `public_html`, giữ nguyên cấu trúc:

- `app/`
- `assets/`
- `config/`
- `database/`
- `docs/`
- `uploads/`
- `views/`
- `index.php`
- `.htaccess`
- `composer.json`
- `.env.example`

Không đặt riêng các file trong `app`, `config`, `database`, `docs` làm public link. File `.htaccess` đã chặn truy cập trực tiếp các thư mục nhạy cảm khi dùng Apache.

## 4. Cấu hình PHP trên hosting

Trong hosting panel, đặt tối thiểu:

```text
PHP version: 8.2+
upload_max_filesize: 20M
post_max_size: 25M
memory_limit: 256M
date.timezone: Asia/Ho_Chi_Minh
```

Đặt quyền thư mục:

```text
uploads/ 0755 hoặc 0775
```

Nếu hosting dùng user riêng cho PHP, bảo đảm PHP có quyền ghi vào `uploads/`.

## 5. Tạo database

1. Tạo database mới trong hosting panel.
2. Chọn charset/collation `utf8mb4_unicode_ci` nếu hosting cho phép.
3. Import file `database/database.sql` bằng phpMyAdmin hoặc công cụ import của hosting.
4. Import tiếp file `database/migrations/2026_06_28_admin_panel.sql` để bổ sung Admin Panel, vai trò mở rộng và bảng file đính kèm.

File SQL không tạo mật khẩu mặc định. Tài khoản quản trị đầu tiên được tạo sau khi import database.

## 6. Cấu hình kết nối database

Mở file `config/database.php` và chỉnh theo thông tin hosting cấp:

```php
return [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'ten_database',
    'username' => 'ten_user_database',
    'password' => 'mat_khau_database',
    'charset' => 'utf8mb4',
];
```

Nếu hosting hỗ trợ biến môi trường, dùng nội dung `.env.example` làm mẫu và đặt các biến:

- `APP_NAME`
- `APP_URL=https://nhankhauthon09.com`
- `APP_KEY` bằng một chuỗi bí mật dài, không dùng giá trị mặc định.
- `APP_TIMEZONE=Asia/Ho_Chi_Minh`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_CHARSET=utf8mb4`

## 7. Kiểm tra website

Mở trình duyệt và truy cập:

```text
https://nhankhauthon09.com/api/health
```

Nếu hệ thống trả về trạng thái `ok`, phần điều hướng và PHP đang hoạt động.

## 8. Tạo tài khoản quản trị đầu tiên

Sau khi import database mới, tạo tài khoản quản trị đầu tiên bằng API:

```bash
curl -X POST https://nhankhauthon09.com/api/auth/setup \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@nhankhauthon09.com","displayName":"Quản trị hệ thống","password":"MatKhauManh123"}'
```

Nếu không dùng được `curl`, có thể dùng Postman hoặc công cụ gọi API trong hosting. Mật khẩu tối thiểu 8 ký tự.

Sau khi tạo thành công, mở `https://nhankhauthon09.com` và đăng nhập bằng email/mật khẩu vừa tạo.

## 9. Kiểm tra sau đăng nhập

Kiểm tra lần lượt:

- Dashboard tải số liệu và biểu đồ Chart.js.
- Hộ dân: thêm, sửa, tìm kiếm, xóa mềm, upload ảnh qua API.
- Nhân khẩu: thêm, sửa, tìm kiếm, xóa mềm, đồng bộ chủ hộ, upload ảnh/giấy tờ qua API.
- Tạm trú, Tạm vắng và Biến động nhân khẩu tải dữ liệu đúng.
- Báo cáo: xem trước, xuất Excel, xuất PDF, in.
- Người dùng: tạo cán bộ, cộng tác viên, chỉ xem, chỉ nhập liệu, không được xóa, không được xuất dữ liệu.
- Phân quyền: xem và lưu ma trận quyền.
- Cấu hình hệ thống: lưu tên hệ thống, logo, ảnh nền, thông tin thôn/xã, số điện thoại, email.
- Nhật ký: có ghi thao tác đăng nhập và thao tác dữ liệu.
- Sao lưu: tạo và tải file SQL.
- Khôi phục: chỉ Admin thao tác sau khi đã có backup an toàn.

## 10. Nginx rewrite tương đương

Nếu hosting dùng Nginx, cấu hình tối thiểu cần có dạng:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ ^/(app|config|database|docs|vendor|controllers|models)/ {
    deny all;
}
```

Hosting dùng Apache chỉ cần giữ file `.htaccess` đi kèm.

## 11. Lưu ý vận hành

- Bắt buộc đổi `APP_KEY` khi đưa lên production.
- Tạo backup SQL trước khi import dữ liệu thật.
- Không chia sẻ tài khoản Admin cho người dùng thường.
- Định kỳ tải backup về máy hoặc lưu vào nơi an toàn của đơn vị.
- Khi cập nhật source, backup database trước rồi mới upload phiên bản mới.
- Nếu báo lỗi trắng trang, bật log lỗi PHP trong hosting panel để xem nguyên nhân.
