# Giai đoạn 2 - Điều hành

Ngày thực hiện: 2026-07-24

## Module Công việc đã triển khai

- Thêm module `work_tasks` độc lập với Phản ánh - Kiến nghị và Trung tâm điều hành.
- Thêm database chuẩn hóa:
  - `work_task_categories`
  - `work_task_priorities`
  - `work_task_statuses`
  - `work_tasks`
  - `work_task_logs`
  - `work_task_attachments`
- Seed danh mục công việc: Thu quỹ, Kiểm tra hộ, Phát quà, Vệ sinh môi trường, Tuần tra, Kiểm tra công trình, Kiểm tra sản xuất, Khác.
- API đầy đủ cho danh sách, dashboard, catalogs, CRUD, nhật ký, đính kèm, export Excel/PDF.
- UI responsive dùng chung screen/module shell hiện tại:
  - Dashboard KPI.
  - Tìm kiếm, lọc, sắp xếp, phân trang server-side.
  - Tạo/sửa/xóa công việc theo quyền.
  - Chi tiết công việc, nhật ký xử lý, file đính kèm.
- RBAC backend/frontend cho `work_tasks`.
- Upload file qua `FileStorageService` với MIME whitelist và thư mục `uploads/work-tasks`.
- Audit Log cho thêm, sửa, xóa, cập nhật tiến độ, upload, xóa file, export.

## QA đã chạy

- `php -l app\Models\WorkTask.php`
- `php -l app\Controllers\WorkTaskController.php`
- `php -l index.php`
- PHP lint toàn bộ `app/`
- `node --check assets\js\work-tasks.js`
- `node --check assets\js\app.utf8.min.js`
- `npm.cmd run build:assets`
- `npm.cmd run check:js`
- `npm.cmd run test:platform`
- `npm.cmd run test:navigation-cleanup`
- `node tests\security-regression.test.js`
- `npm.cmd run validate:artifact`
- `npx.cmd playwright test`: 265 passed, 5 skipped
- `npx.cmd playwright test tests/browser/navigation-controller.spec.js tests/browser/responsive-ui.spec.js`: 70 passed, 5 skipped

## Module Lịch công tác đã triển khai

- Thêm module `work_calendar` độc lập.
- Thêm database chuẩn hóa:
  - `calendar_event_categories`
  - `calendar_events`
  - `calendar_event_attendees`
  - `calendar_event_attachments`
- Seed danh mục lịch: Họp, Hội nghị, Trực, Tiêm chủng, Phát quà, Sinh hoạt Chi bộ, Sinh hoạt đoàn thể, Khác.
- API đầy đủ cho danh sách, dashboard, catalogs, CRUD, danh sách tham dự, đính kèm, export Excel/PDF.
- UI responsive tại `/work-calendar`:
  - Dashboard KPI.
  - Lịch tháng.
  - Danh sách lịch công tác có tìm kiếm, lọc, sắp xếp, phân trang server-side.
  - Form tạo/sửa lịch, danh sách tham dự, file đính kèm.
  - Chi tiết lịch và preview/download file.
- RBAC backend/frontend cho `work_calendar`.
- Upload qua `FileStorageService` với MIME whitelist và thư mục `uploads/work-calendar`.
- Audit Log cho thêm, sửa, xóa, upload, xóa file, export.

## QA bổ sung sau Lịch công tác

- `php -l app\Models\WorkCalendar.php`
- `php -l app\Controllers\WorkCalendarController.php`
- `php -l index.php`
- `node --check assets\js\work-calendar.js`
- `node --check assets\js\app.utf8.min.js`
- PHP lint toàn bộ `app/`
- `npm.cmd run build:assets`
- `npm.cmd run check:js`
- `npm.cmd run test:platform`
- `npm.cmd run test:navigation-cleanup`
- `node tests\security-regression.test.js`
- `npm.cmd run validate:artifact`
- `npx.cmd playwright test tests/browser/navigation-controller.spec.js tests/browser/responsive-ui.spec.js`: 70 passed, 5 skipped

## Ghi chú

- Module mới dùng route `/work-tasks` và API `/api/work-tasks`.
- Không sửa trực tiếp dữ liệu module hiện có; liên kết nghiệp vụ đang lưu bằng `related_module` và `related_id` để tích hợp dần với các module dân cư/tài sản/sản xuất.
- Hạng mục còn lại của Giai đoạn 2 là Văn bản.
