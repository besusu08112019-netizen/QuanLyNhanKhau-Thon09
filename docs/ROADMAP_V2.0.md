# Roadmap v2.0 - Chính quyền số thôn/xã

## Mục tiêu

Phiên bản v1.0 đã hoàn thành và đang vận hành ổn định. v2.0 tập trung mở rộng hệ thống theo hướng nền tảng Chính quyền số cấp thôn/xã, không sửa đổi các chức năng ổn định của v1.0 nếu không có yêu cầu rõ ràng.

Tất cả module mới phải tương thích với dữ liệu, kiến trúc, phân quyền và quy trình vận hành hiện có của v1.0.

## Nguyên tắc phát triển

- Không phá vỡ tương thích với v1.0.
- Không làm mất dữ liệu hiện có.
- Mỗi tính năng mới là một module độc lập, có migration khi thay đổi cơ sở dữ liệu.
- Tái sử dụng Service, Model, API, phân quyền và audit log hiện có.
- Không tạo mã trùng lặp khi có thể mở rộng abstraction hiện hữu.
- Mọi API mới phải có authentication, authorization, permission check và audit phù hợp.
- AI chỉ hỗ trợ tìm kiếm, phân tích và gợi ý; không tự động thay đổi dữ liệu.
- Mọi tính năng mới phải có kiểm thử trước khi hợp nhất.

## Phase 1 - Cổng dịch vụ công cho người dân

### Mục tiêu

Xây dựng cổng thông tin để người dân có thể tự thực hiện một số thao tác trực tuyến theo quyền được cấp.

### Chức năng

- Tra cứu thông tin hộ gia đình theo quyền được cấp.
- Tra cứu thông tin nhân khẩu theo quyền được cấp.
- Gửi yêu cầu cập nhật thông tin.
- Gửi thông báo chuyển đến.
- Gửi thông báo chuyển đi.
- Đăng ký tạm trú.
- Đăng ký tạm vắng.
- Theo dõi trạng thái xử lý hồ sơ.

### Ghi chú kiến trúc

- Tách portal công dân khỏi giao diện quản trị nội bộ.
- Không cho phép người dân ghi trực tiếp vào dữ liệu gốc; mọi thay đổi phải qua yêu cầu xử lý và phê duyệt.
- Bổ sung bảng lưu yêu cầu, trạng thái, lịch sử xử lý và file đính kèm nếu cần.
- Tận dụng module hộ gia đình, nhân khẩu, hồ sơ số, timeline và audit log hiện có.

## Phase 2 - Ứng dụng di động

### Mục tiêu

Xây dựng ứng dụng Android và iOS hỗ trợ cán bộ làm việc trên hiện trường.

### Chức năng

- Xem Dashboard.
- GIS.
- Tra cứu hộ.
- Tra cứu nhân khẩu.
- Chỉ đường GPS.
- Chụp ảnh tại hiện trường.
- Upload tài liệu.
- Làm việc ngoại tuyến.

### Ghi chú kiến trúc

- API mobile dùng chung permission matrix với web.
- Offline phải có hàng đợi đồng bộ, phát hiện xung đột và nhật ký đồng bộ.
- Không lưu token hoặc dữ liệu nhạy cảm không mã hóa trên thiết bị.

## Phase 3 - AI hỗ trợ quản lý

### Mục tiêu

Tích hợp AI để hỗ trợ cán bộ trong tìm kiếm, phân tích, tổng hợp và phát hiện bất thường.

### Chức năng

- Tìm kiếm bằng ngôn ngữ tự nhiên.
- Tóm tắt hồ sơ công dân.
- Phát hiện dữ liệu bất thường.
- Gợi ý hồ sơ còn thiếu.
- Gợi ý báo cáo phù hợp.
- Trả lời câu hỏi về số liệu thống kê.

### Ràng buộc an toàn

- AI không tự động thêm, sửa, xóa, phê duyệt hoặc thay đổi dữ liệu.
- Câu trả lời AI phải có nguồn dữ liệu hoặc truy vấn tham chiếu khi phù hợp.
- Không gửi dữ liệu nhạy cảm ra dịch vụ bên ngoài nếu chưa có cấu hình và phê duyệt triển khai.

## Phase 4 - Tích hợp bản đồ nâng cao

### Mục tiêu

Mở rộng GIS mà không thay đổi dữ liệu GIS hiện có.

### Chức năng

- Quản lý tuyến đường.
- Quản lý công trình.
- Quản lý nhà văn hóa.
- Quản lý trường học.
- Quản lý trạm y tế.
- Quản lý điểm nguy cơ.
- Lớp dữ liệu chuyên đề.

### Ghi chú kiến trúc

- Dữ liệu GIS hiện có được giữ nguyên.
- Các lớp dữ liệu mới có bảng riêng, API riêng và permission riêng.
- Cho phép bật/tắt lớp dữ liệu trên bản đồ.

## Phase 5 - Quản lý tài sản công

### Mục tiêu

Quản lý tài sản công cấp thôn/xã và liên kết vị trí với GIS.

### Chức năng

- Nhà văn hóa.
- Thiết bị.
- Đất công.
- Cơ sở hạ tầng.
- Cây xanh.
- Hệ thống chiếu sáng.
- Camera an ninh.

### Ghi chú kiến trúc

- Mỗi tài sản có mã, trạng thái, đơn vị quản lý, vị trí GIS và lịch sử cập nhật.
- Tài sản có thể liên kết file, ảnh, video và nhật ký bảo trì.

## Phase 6 - Quản lý công việc

### Mục tiêu

Bổ sung module điều hành công việc và theo dõi tiến độ xử lý.

### Chức năng

- Nhiệm vụ.
- Lịch công tác.
- Tiến độ.
- Giao việc.
- Theo dõi xử lý.
- Nhắc việc.
- Dashboard tiến độ.

### Ghi chú kiến trúc

- Nhiệm vụ có người giao, người nhận, hạn xử lý, trạng thái, độ ưu tiên và lịch sử thay đổi.
- Có thể liên kết nhiệm vụ với hộ, nhân khẩu, hồ sơ, GIS hoặc tài sản công.

## Phase 7 - Thông báo đa kênh

### Mục tiêu

Xây dựng kiến trúc thông báo mở, có thể tích hợp nhà cung cấp dịch vụ sau này.

### Kênh hỗ trợ

- Email.
- SMS nếu có dịch vụ.
- Thông báo trong hệ thống.
- Push notification trên ứng dụng di động.

### Ghi chú kiến trúc

- Thiết kế provider interface cho từng kênh.
- Có hàng đợi gửi, retry, log kết quả và cấu hình bật/tắt theo môi trường.
- Không hard-code nhà cung cấp dịch vụ.

## Phase 8 - Dữ liệu mở và tích hợp

### Mục tiêu

Xây dựng khả năng tích hợp có kiểm soát với hệ thống khác, không làm lộ dữ liệu nhạy cảm.

### Chức năng

- REST API công khai có xác thực.
- Webhook.
- Import/Export chuẩn.
- Đồng bộ với hệ thống khác nếu được phép.

### Ghi chú kiến trúc

- API công khai phải dùng scope riêng, rate limit và audit log.
- Webhook có chữ ký xác thực, retry và lịch sử gửi.
- Dữ liệu nhạy cảm phải được lọc, ẩn hoặc tổng hợp trước khi xuất ra ngoài.

## Yêu cầu kiểm thử chung

Mỗi module mới cần có tối thiểu:

- Kiểm thử permission backend cho Guest, Officer, Admin.
- Kiểm thử direct API access cho POST, PUT, PATCH, DELETE.
- Kiểm thử migration không làm mất dữ liệu v1.0.
- Kiểm thử giao diện desktop và mobile theo màn hình liên quan.
- Kiểm thử tiếng Việt UTF-8 trên UI, API JSON và file export nếu có.
- Kiểm thử audit log cho thao tác ghi dữ liệu.

## Tiêu chí hoàn thành v2.0

Sau khi hoàn thành các phase, hệ thống phát triển từ phần mềm quản lý nhân khẩu thành nền tảng Chính quyền số cấp thôn/xã, hỗ trợ quản lý dân cư, GIS, hồ sơ số, điều hành, dịch vụ công, tài sản công, thông báo đa kênh và tích hợp với các hệ thống khác trong tương lai.