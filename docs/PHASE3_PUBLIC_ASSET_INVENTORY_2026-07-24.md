# Giai đoạn 3 - Mở rộng Công trình công cộng

Ngày thực hiện: 2026-07-24

## Đã triển khai

- Mở rộng kiểm kê tài sản trong module Công trình công cộng.
- Bổ sung trường: giá trị, ngày mua, hạn bảo hành, người quản lý, điện thoại quản lý, chu kỳ bảo trì.
- Bổ sung nhóm tài sản nền: thiết bị, máy móc, bàn ghế, cờ.
- Bổ sung lịch bảo trì tài sản/công trình:
  - Tài sản liên quan.
  - Nội dung bảo trì.
  - Ngày bảo trì.
  - Người phụ trách.
  - Chi phí.
  - Trạng thái.
  - Ghi chú.
- Lưu lịch sử thao tác qua audit log hiện có: tạo, sửa, xóa lịch bảo trì.
- API mới:
  - `GET /api/public-assets/{id}/maintenance`
  - `POST /api/public-assets/{id}/maintenance`
  - `PUT /api/public-assets/{id}/maintenance/{maintenanceId}`
  - `DELETE /api/public-assets/{id}/maintenance/{maintenanceId}`
- Migration: `database/migrations/20260724_130000_extend_public_asset_inventory_phase3.sql`.

## Kiểm thử đã chạy

- `php -l app\Models\PublicAsset.php`
- `php -l app\Controllers\PublicAssetController.php`
- `php -l index.php`
- `node --check assets\js\public-assets.js`
- `npm run build:assets`
- `npm run check:js`
- `npm run test:platform`
- `npm run test:navigation-cleanup`
- `node tests\security-regression.test.js`
- `npm run validate:artifact`
- `npx playwright test tests/browser/public-assets.spec.js`

Kết quả: tất cả pass.

## Kho ảnh

Đã triển khai tiếp trong cùng giai đoạn:

- Module Kho ảnh dùng chung.
- Album ảnh.
- Tag ảnh.
- Tìm kiếm theo tên, mô tả, album, tag.
- Lọc theo album, tag, nguồn ảnh, địa bàn, khoảng ngày.
- Upload nhiều ảnh.
- Preview ảnh qua endpoint có kiểm tra quyền.
- Xóa mềm và audit log.
- API mới:
  - `GET /api/photo-gallery`
  - `POST /api/photo-gallery/upload`
  - `GET /api/photo-gallery/dashboard`
  - `GET /api/photo-gallery/catalogs`
  - `GET /api/photo-gallery/albums`
  - `POST /api/photo-gallery/albums`
  - `GET /api/photo-gallery/{id}/preview`
  - `GET /api/photo-gallery/{id}/download`
  - `GET /api/photo-gallery/{id}`
  - `PUT /api/photo-gallery/{id}`
  - `DELETE /api/photo-gallery/{id}`
- Migration: `database/migrations/20260724_140000_create_photo_gallery.sql`.

## Trạng thái Giai đoạn 3

- Mở rộng Công trình công cộng: hoàn thành phần thiết bị/tài sản/bảo trì.
- Kho ảnh: hoàn thành phần nền tảng album/tag/tìm kiếm/upload/preview.
