# Module Quỹ đất nông nghiệp

## Mục tiêu

Module Quỹ đất nông nghiệp quản lý số liệu tổng hợp diện tích đất nông nghiệp của toàn thôn theo từng khu đất và từng năm thống kê. Module độc lập với hộ gia đình, nhân khẩu, thửa đất GIS và module Sản xuất nông nghiệp.

## Kiến trúc

- Model: `App\Models\AgriculturalLandZone`
- Controller: `App\Controllers\AgriculturalLandZoneController`
- API khu đất: `/api/agricultural-land`
- API loại sử dụng đất: `/api/agricultural-land/usage-types`
- UI: `#agriculturalLandScreen`
- Asset: `assets/js/agricultural-land.js`
- Database:
  - `agricultural_land_zones`: khu đất, năm thống kê, trạng thái, chỉ tiêu quỹ đất cố định và trường chuẩn bị GIS.
  - `land_usage_types`: cấu hình loại sử dụng đất theo từng thôn.
  - `agricultural_land_zone_usage_areas`: diện tích theo khu và loại sử dụng đất.
  - `agricultural_land_settings`: đơn vị mặc định theo thôn.

## Nguyên tắc dữ liệu

Mỗi bản ghi khu đất thuộc một `report_year`. Dashboard mặc định dùng năm hiện tại và chỉ thống kê các khu `ACTIVE`.

Diện tích nhập từ UI có thể dùng `m²`, `sào`, `mẫu` hoặc `ha`. Backend lưu chuẩn bằng mét vuông trong các cột `_m2` hoặc `area_m2`, sau đó quy đổi theo đơn vị hiển thị.

Dashboard không lưu tổng. Tất cả chỉ số và biểu đồ được tính động từ dữ liệu khu đất và bảng diện tích theo loại sử dụng đất.

## Loại sử dụng đất động

Tên loại sử dụng đất không hard-code trong bảng khu đất. Quản trị có thể thêm, sửa hoặc ngừng sử dụng loại đất qua modal "Loại đất". Code chỉ dựa vào `usage_type_id`, không phụ thuộc tên như lúa, ngô hay thủy sản.

Khi một thôn chưa có cấu hình loại đất, model seed bộ mặc định để có dữ liệu khởi tạo. Sau đó quản trị có thể thay đổi mà không cần sửa mã nguồn.

## Mở rộng GIS

Bảng `agricultural_land_zones` đã có các trường nullable để mở rộng sau:

- `latitude`
- `longitude`
- `polygon_json`
- `photo_url`
- `irrigation_note`
- `production_group_name`
- `main_crop_type`
- `annual_note`

Hiện tại UI chưa tích hợp GIS, các trường GIS chỉ chuẩn bị database.

## Phân quyền

- `VIEWER`: chỉ đọc.
- `OFFICER`: chỉ đọc.
- `ADMIN` và `SUPER_ADMIN`: thêm, sửa, xóa, cấu hình loại đất, xuất và in báo cáo.

Export Excel/PDF/Print đi qua `ReportController` và kiểm tra quyền nguồn `agricultural_land:export` hoặc `agricultural_land:print`.

## Báo cáo

Hỗ trợ:

- Danh sách khu đất.
- Báo cáo toàn thôn.
- Báo cáo theo khu.
- Báo cáo theo năm.
- So sánh giữa các năm.

Các báo cáo sử dụng cơ chế export hiện có, không truy cập module hộ dân, nhân khẩu, GIS hoặc sản xuất nông nghiệp.

## Rủi ro

- Quy đổi `sào` và `mẫu` đang dùng chuẩn mặc định: 1 sào = 360 m², 1 mẫu = 3.600 m².
- Nếu từng thôn dùng chuẩn quy đổi khác, cần mở rộng UI quản trị cho `agricultural_land_settings.sao_m2` và `mau_m2`.
- Loại sử dụng đất bị ngừng sử dụng vẫn giữ dữ liệu lịch sử, nhưng không hiện trong form nhập mới trừ khi khu cũ đã có dữ liệu của loại đó.

## Rollback

1. Revert commit triển khai module.
2. Nếu migration đã chạy và cần gỡ dữ liệu, backup trước rồi drop:
   - `agricultural_land_zone_usage_areas`
   - `land_usage_types`
   - `agricultural_land_zones`
   - `agricultural_land_settings`
3. Xóa quyền `permissions.module = 'agricultural_land'` nếu cần làm sạch cấu hình.

Không có dữ liệu nghiệp vụ khác bị liên kết hoặc thay đổi bởi module này.
