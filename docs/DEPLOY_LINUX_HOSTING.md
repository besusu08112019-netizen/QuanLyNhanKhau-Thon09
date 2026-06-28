# Hướng dẫn triển khai Linux Hosting

Tài liệu này dùng cho bản PHP/MySQL của Hệ thống Quản lý Nhân khẩu Thôn 09. Bản hiện tại không sử dụng Google Apps Script, Google Sheets hoặc bất kỳ dịch vụ Google nào.

## 1. Yêu cầu hosting

- PHP 8.2 trở lên.
- MySQL 5.7+/MariaDB 10.4+.
- Apache có bật `mod_rewrite`, hoặc Nginx có cấu hình rewrite tương đương.
- Có quyền tạo database và import file SQL.
- Website trỏ vào thư mục chứa `index.php`.

## 2. Upload source

Upload toàn bộ mã nguồn lên hosting, giữ nguyên cấu trúc thư mục:

- `app/`
- `assets/`
- `config/`
- `database/`
- `docs/`
- `uploads/`
- `views/`
- `index.php`
- `.htaccess`

Không đặt riêng các file trong `app`, `config`, `database`, `docs` làm public link. File `.htaccess` đã chặn truy cập trực tiếp các thư mục nhạy cảm khi dùng Apache.

## 3. Tạo database

1. Tạo database mới trong hosting panel.
2. Chọn charset/collation `utf8mb4_unicode_ci` nếu hosting cho phép.
3. Import file `database/database.sql` bằng phpMyAdmin hoặc công cụ import của hosting.

File SQL không tạo mật khẩu mặc định. Tài khoản quản trị đầu tiên được tạo sau khi import database.

## 4. Cấu hình kết nối database

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

Nếu hosting hỗ trợ biến môi trường, có thể dùng:

- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_CHARSET`

## 5. Kiểm tra website

Mở trình duyệt và truy cập domain đã upload. Có thể kiểm tra nhanh API:

```text
https://ten-mien-cua-ban.vn/api/health
```

Nếu hệ thống trả về trạng thái `ok`, phần điều hướng và PHP đang hoạt động.

## 6. Tạo tài khoản quản trị đầu tiên

Sau khi import database mới, tạo tài khoản quản trị đầu tiên bằng API:

```bash
curl -X POST https://ten-mien-cua-ban.vn/api/auth/setup \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","displayName":"Quản trị hệ thống","password":"MatKhauManh123"}'
```

Nếu không dùng được `curl`, có thể dùng Postman hoặc công cụ gọi API trong hosting. Mật khẩu tối thiểu 8 ký tự.

Sau khi tạo thành công, mở website và đăng nhập bằng email/mật khẩu vừa tạo.

## 7. Kiểm tra sau đăng nhập

Kiểm tra lần lượt:

- Dashboard tải số liệu.
- Hộ dân: thêm, sửa, tìm kiếm, xóa mềm.
- Nhân khẩu: thêm, sửa, tìm kiếm, xóa mềm, đồng bộ chủ hộ.
- Báo cáo: xem trước, xuất Excel, xuất PDF, in.
- Người dùng: tạo cán bộ và tài khoản chỉ xem.
- Nhật ký: có ghi thao tác đăng nhập và thao tác dữ liệu.
- Sao lưu: tạo và tải file SQL.

## 8. Nginx rewrite tương đương

Nếu hosting dùng Nginx, cấu hình tối thiểu cần có dạng:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ ^/(app|config|database|docs|uploads|vendor|controllers|models)/ {
    deny all;
}
```

Hosting dùng Apache chỉ cần giữ file `.htaccess` đi kèm.

## 9. Lưu ý vận hành

- Tạo backup SQL trước khi import dữ liệu thật.
- Không chia sẻ tài khoản Admin cho người dùng thường.
- Định kỳ tải backup về máy hoặc lưu vào nơi an toàn của đơn vị.
- Khi cập nhật source, backup database trước rồi mới upload phiên bản mới.
- Nếu báo lỗi trắng trang, bật log lỗi PHP trong hosting panel để xem nguyên nhân.
