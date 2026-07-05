# Production Release Checklist

Checklist này dùng cho bản PHP/MySQL chạy trên Linux hosting hoặc cPanel.

## 1. Git và Source

- [ ] `git status` sạch trước khi tag release.
- [ ] `main` trùng với `origin/main`.
- [ ] Không có commit local chưa push.
- [ ] Không có file tạm như `test-results/`, `playwright-report/`, `*.tmp`, `*.bak`, `*.log`.
- [ ] Không commit `config/database.php`, dữ liệu production trong `uploads/`, backup SQL hoặc credential thật.
- [ ] `.cpanel.yml` tồn tại và đúng cấu hình deploy hiện tại.

## 2. Build và Static Verification

- [ ] `npm.cmd run build:assets` PASS.
- [ ] `npm.cmd run check:js` PASS.
- [ ] PHP lint toàn bộ file `.php` PASS.
- [ ] `npm.cmd run test:browser` PASS trên desktop, tablet và mobile smoke profile.
- [ ] Không có `console.log`, `debugger`, `var_dump`, `print_r`, `die` debug trong source production.

## 3. Database và Migration

- [ ] Database production dùng `utf8mb4`.
- [ ] Đã import `database/database.sql` nếu triển khai mới.
- [ ] Đã chạy mọi migration trong `database/migrations/` theo thứ tự tên file nếu nâng cấp.
- [ ] Đã kiểm tra các bảng chính: `users`, `households`, `citizens`, `permissions`, `audit_logs`, `backups`.
- [ ] Đã kiểm tra index phục vụ tìm kiếm và GIS.
- [ ] Không có mojibake tiếng Việt trong dữ liệu thật.

## 4. Backup

- [ ] Tạo backup SQL trước khi deploy hoặc chạy migration.
- [ ] Tải được backup SQL qua module Sao lưu.
- [ ] File backup có header `Quan Ly Nhan Khau Thon 09 backup`.
- [ ] Restore chỉ thao tác bởi tài khoản đủ quyền và chỉ dùng backup do ứng dụng sinh ra.
- [ ] Không test restore trực tiếp trên production nếu chưa có phê duyệt mất dữ liệu.

## 5. Security

- [ ] `APP_KEY` production đã cấu hình hoặc `uploads/.app_key` đã được tạo và giữ ổn định.
- [ ] HTTPS hoạt động.
- [ ] Header `Content-Security-Policy`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` có hiệu lực.
- [ ] Thư mục `app`, `config`, `database`, `docs`, `uploads`, `vendor`, `controllers`, `models` không truy cập trực tiếp từ browser.
- [ ] API không token trả lỗi xác thực, không trả dữ liệu nghiệp vụ.
- [ ] Request thay đổi dữ liệu thiếu CSRF token bị chặn.
- [ ] Upload chỉ nhận loại file hợp lệ và không thực thi file upload.

## 6. Functional QA

- [ ] Login/logout.
- [ ] Dashboard.
- [ ] Hộ gia đình: danh sách, tìm kiếm, thêm, sửa, xóa mềm, xem hồ sơ.
- [ ] Nhân khẩu: danh sách, tìm kiếm, thêm, sửa, xóa mềm, khôi phục, xem chi tiết.
- [ ] GIS: tải bản đồ, khu vực, vị trí hộ, tìm kiếm, export.
- [ ] Báo cáo: xem, lọc, export Excel/PDF, in.
- [ ] Import: template, preview, process, validation lỗi.
- [ ] Tài khoản và phân quyền.
- [ ] Nhật ký.
- [ ] Sao lưu.
- [ ] Cài đặt và giao diện.

## 7. Responsive và Browser QA

- [ ] Desktop.
- [ ] Tablet 768, 820, 1024 px.
- [ ] Mobile 360, 375, 390, 412 px.
- [ ] Không horizontal overflow.
- [ ] Bottom navigation, FAB, header, popup, modal và form hoạt động đúng.
- [ ] Console không có JavaScript error.
- [ ] API responses thành công, không có lỗi 500 bất ngờ.

## 8. Deployment Verification

- [ ] Commit release đã push lên `origin/main`.
- [ ] Hosting đã nhận đúng commit release.
- [ ] Asset cache đã cập nhật, không còn file JS/CSS cũ.
- [ ] Production login thành công bằng tài khoản quản trị.
- [ ] Kiểm tra production sau deploy trên desktop, tablet và mobile.
- [ ] Không kết luận Production Ready nếu bất kỳ mục nào ở trên chưa PASS.
