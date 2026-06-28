# Production Checklist - PHP/MySQL Linux Hosting

## Triển khai

- Hosting chạy PHP 8.2 trở lên.
- Database MySQL/MariaDB đã được tạo với charset `utf8mb4`.
- Đã import `database/database.sql` thành công.
- `config/database.php` đã đúng host, database, username, password và charset.
- Domain hoặc subdomain đã trỏ tới thư mục chứa `index.php`.
- Apache đã nhận `.htaccess`, hoặc Nginx đã cấu hình rewrite về `index.php`.
- Truy cập `/api/health` trả về trạng thái `ok`.
- Tạo tài khoản quản trị đầu tiên qua `/api/auth/setup` nếu database chưa có Admin.

## Bảo mật

- Không dùng mật khẩu quản trị yếu hoặc mật khẩu mẫu.
- Thư mục `app`, `config`, `database`, `docs`, `uploads`, `vendor`, `controllers`, `models` không truy cập trực tiếp từ trình duyệt.
- Người dùng không phải Admin không thấy menu Người dùng, Nhật ký và Sao lưu.
- API quản trị vẫn chặn truy cập trái phép kể cả khi gọi trực tiếp.
- Tài khoản Cán bộ chỉ thao tác nghiệp vụ được phân quyền.
- Tài khoản Chỉ xem không thể thêm, sửa, xóa hoặc xuất dữ liệu nếu không có quyền.

## Kiểm thử chức năng

- Đăng nhập/đăng xuất hoạt động và có ghi nhật ký.
- Dashboard hiển thị tổng hộ, tổng nhân khẩu, Nam, Nữ, còn sống, tạm trú, tạm vắng.
- Bộ lọc Dashboard theo thời gian, trạng thái hộ, thường trú/tạm trú và ở nhà/đi vắng hoạt động.
- Hộ dân: danh sách, tìm kiếm, phân trang, thêm, sửa, xóa mềm, xóa nhiều.
- Hộ dân: Mã hộ là định danh thống nhất, không dùng lẫn ID hộ trong giao diện.
- Hộ dân: hiển thị chủ hộ, diện hộ, số người ở nhà và số người đi vắng đúng theo nhân khẩu cùng mã hộ.
- Hộ dân: bấm vào một hộ hiển thị thành viên cùng mã hộ.
- Nhân khẩu: danh sách, tìm kiếm, phân trang, thêm, sửa, xóa mềm, khôi phục, xóa nhiều.
- Nhân khẩu: không trùng CCCD khi có dữ liệu CCCD.
- Nhân khẩu: Mã hộ phải tồn tại trước khi thêm.
- Nhân khẩu: ngày sinh hiển thị dạng ngày/tháng/năm.
- Nhân khẩu: nếu quan hệ là Chủ hộ thì thông tin chủ hộ bên Hộ dân được cập nhật.
- Import: tài khoản Admin/Cán bộ thấy menu Import dữ liệu.
- Import: đọc được file CSV/XLSX theo tên cột, không phụ thuộc thứ tự cột.
- Import: kiểm tra trước hiển thị tổng dòng, dòng hợp lệ và dòng lỗi.
- Import: import Hộ dân hỗ trợ bỏ qua hoặc cập nhật khi trùng Mã hộ.
- Import: import Nhân khẩu kiểm tra Mã hộ, Họ tên, Ngày sinh và CCCD qua model hiện có.
- Báo cáo: tổng hợp, hộ dân, nhân khẩu, giới tính, độ tuổi, cư trú, biến động và nhóm chính sách.
- Xuất Excel tải được file và giữ dữ liệu tiếng Việt.
- Xuất PDF tải được file đúng định dạng cơ bản.
- In phiếu mở cửa sổ in khổ A4.
- Người dùng: thêm, sửa, xóa, khóa, mở khóa, đổi vai trò.
- Nhật ký: tìm kiếm, phân trang và ghi thao tác dữ liệu chính.
- Sao lưu: tạo và tải file SQL.
- Phục hồi: chỉ Admin thực hiện sau khi đã có backup an toàn.

## Dữ liệu

- Không sửa tay khóa chính trong database production.
- Không xóa cứng dữ liệu nếu chưa có backup.
- Kiểm tra index của `household_code`, `citizen_code`, `identity_number`, `status`, `created_at` đã có sau khi import SQL.
- Kiểm tra dữ liệu tiếng Việt không bị lỗi font sau khi import.
- Với dữ liệu thực, tạo backup trước và sau khi import.

## Hiệu năng

- Danh sách Hộ dân và Nhân khẩu dùng phân trang, không tải toàn bộ dữ liệu ra giao diện.
- Tìm kiếm dùng API server-side.
- Dashboard đọc dữ liệu tổng hợp qua truy vấn SQL, không lặp xử lý trên giao diện.
- Import đọc file một lần, map theo tiêu đề cột và ghi qua model nghiệp vụ hiện có.
- Báo cáo lớn nên lọc theo khoảng thời gian hoặc nhóm dữ liệu trước khi xuất.

## Bàn giao

- Ghi lại domain truy cập.
- Ghi lại thông tin database ở nơi an toàn của đơn vị.
- Bàn giao tài khoản Admin đầu tiên và yêu cầu đổi mật khẩu sau khi nhận.
- Tạo ít nhất một tài khoản Cán bộ và một tài khoản Chỉ xem để kiểm thử phân quyền.
- Tạo backup SQL cuối cùng trước khi bàn giao.
