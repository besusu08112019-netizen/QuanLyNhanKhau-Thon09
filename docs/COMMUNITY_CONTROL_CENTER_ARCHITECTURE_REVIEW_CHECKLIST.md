# Architecture Review Checklist: Community Control Center Platform

Ngày review: 2026-07-28

Phạm vi review:

- `docs/COMMUNITY_CONTROL_CENTER_ARCHITECTURE.md`
- Nguyên tắc triển khai trước Phase 1

Kết luận tổng thể:

- Kiến trúc đạt yêu cầu để chuẩn bị Phase 1 ở mức thiết kế.
- Không còn quyết định kiến trúc mở sau Architecture Freeze.
- Phase 1 chỉ được phép triển khai `PortalContext` và route guard; không sửa Business Modules.
- Chỉ được bắt đầu Phase 1 sau khi người phụ trách phê duyệt Architecture Freeze.

## Checklist nguyên tắc bắt buộc

| # | Nguyên tắc | Đánh giá | Bằng chứng trong thiết kế | Rủi ro còn lại | Điều chỉnh đề xuất |
|---|---|---|---|---|---|
| 1 | Application Core là lớp thấp nhất và không phụ thuộc Portal | Đạt | Tài liệu xác định Core độc lập với Portal, chịu trách nhiệm Auth, Tenant, Portal, Config, Event, Audit, Routing, Permission, ModuleRegistry, DatabaseResolver, Services. Portal không tự xử lý các trách nhiệm này. | Khi code Phase 1 có thể vô tình thêm logic portal vào Core theo dạng điều kiện hard-code domain. | `PortalContext` phải đọc config, không dùng literal domain trong code. Core chỉ expose API context/policy, không import class UI portal. |
| 2 | Domain Layer độc lập với giao diện, chứa nghiệp vụ cốt lõi | Đạt sau điều chỉnh | Đã bổ sung mục `Domain Layer`, nêu rõ Domain không phụ thuộc Admin Portal, Tenant Portal, Web UI, Mobile UI, Public API, AI Assistant hoặc controller cụ thể. | Code hiện tại lịch sử đang trộn một phần nghiệp vụ trong model/controller; đây là debt hiện hữu, không nên refactor hàng loạt ở Phase 1. | Phase 1 không động nghiệp vụ. Các logic mới phải đặt trong Domain/Service. Refactor module cũ chỉ thực hiện dần khi có test bao phủ. |
| 3 | Business Modules chỉ tổ chức chức năng, dùng Domain và Service, không chứa logic trùng lặp | Đạt về định hướng | Tài liệu quy định Business Modules không phụ thuộc Portal, chỉ phụ thuộc Core, Domain Layer và Service Layer dùng chung; không tạo logic nghiệp vụ trùng lặp. | Một số module hiện tại có logic nằm trực tiếp trong controller/model. Nếu sửa mạnh sẽ rủi ro production. | Không viết lại module. Khi thêm chức năng mới, tạo service chung trước. Khi cần chỉnh module cũ, chỉ bọc dần bằng service với regression test. |
| 4 | Community Control Center chỉ là Portal quản trị, không truy cập trực tiếp database nghiệp vụ | Đạt | Tài liệu nêu Control Center không gọi controller nghiệp vụ, không trả dữ liệu chi tiết, Admin Portal chỉ render dữ liệu service cung cấp; Admin Dashboard không query trực tiếp DB. | Service tổng hợp nếu viết vội có thể query thẳng bảng nghiệp vụ không qua repository/service chuẩn. | Tạo `StatisticsService`, `VillageService`, `SystemHealthService`; controller admin chỉ gọi service. Review code chặn query DB trực tiếp trong controller admin. |
| 5 | Tenant Portal chỉ là Portal vận hành, không chứa logic riêng ngoài giao diện và điều hướng | Đạt | Tài liệu giữ Tenant Portal là hệ thống hiện tại, chỉ bổ sung portal guard ở Core; Portal chỉ render/điều hướng. | Frontend hiện có nhiều JS module; một số logic UI có thể đang chứa quy tắc nghiệp vụ nhẹ. Không xử lý trong Phase 1. | Không thay Tenant Portal trong Phase 1. Các logic mặc định mới sau này phải đặt service/policy, không đặt frontend. |
| 6 | Mọi truy cập dữ liệu phải đi qua Service/Repository dùng chung | Đạt về mục tiêu, cần kiểm soát khi triển khai | Tài liệu Service Layer quy định truy cập dữ liệu qua Service/Repository dùng chung, query tổng hợp nằm trong service. | Code hiện tại có model truy vấn trực tiếp. Đây là kiến trúc hiện hữu; áp dụng tuyệt đối ngay sẽ thành refactor lớn. | Áp dụng bắt buộc cho phần mới. Với module cũ, giữ nguyên và bọc dần. Không tạo query trực tiếp trong portal/controller mới. |
| 7 | Không tạo Controller hoặc Service trùng chức năng giữa Admin Portal và Tenant Portal | Đạt | Tài liệu quy định không tạo API nghiệp vụ thứ hai, không sao chép controller/service/model; Admin API chỉ cho Control Center, Tenant API giữ nguyên. | Có nguy cơ tạo `AdminCitizenController` hoặc `AdminDashboardController` sao chép tenant dashboard. | Cấm tạo controller nghiệp vụ admin. Controller admin chỉ cho aggregate/control-plane. Cùng nghiệp vụ phải đi qua service chung. |
| 8 | Kiến trúc hỗ trợ Web, Mobile, Public API, AI Assistant mà không sửa Domain Layer | Đạt | `PortalContext` dự phòng `PUBLIC`, `API`, `MOBILE`; Domain Layer không phụ thuộc UI; Event Bus hỗ trợ Notification, Audit, AI subscribe. | Nếu Domain Layer nhận payload phụ thuộc HTTP/UI thì sẽ phá nguyên tắc. | Domain service chỉ nhận DTO/domain value, không nhận `Request`, `$_POST`, DOM state hoặc token UI. |

## Review theo thành phần kiến trúc

| Thành phần | Trạng thái | Nhận xét |
|---|---|---|
| Application Core | Đạt | Đã định nghĩa đủ Auth, Tenant, Portal, Config, Event, Audit, Routing, Permission, ModuleRegistry, DatabaseResolver, Services. |
| Domain Layer | Đạt sau điều chỉnh | Đã được bổ sung thành lớp độc lập. Cần triển khai dần vì code hiện hữu chưa tách domain hoàn toàn. |
| Community Control Center | Đạt | Được định nghĩa là tầng điều hành, Admin Portal chỉ là UI. |
| Admin Portal | Đạt | Chỉ render và gọi Control Center API; không chứa logic nghiệp vụ. |
| Tenant Portal | Đạt | Giữ nguyên hệ thống hiện tại, chỉ thêm guard ở Core. |
| ModuleRegistry | Đạt | Metadata đủ: id, name, icon, route, permission, portal_scope, menu_group, sort_order, enabled, version. |
| Service Layer | Đạt có điều kiện | Phần mới phải dùng service/repository. Phần cũ không refactor hàng loạt ở Phase 1. |
| Event System | Đạt | Có EventBus, DomainEvent, Subscriber, EventStore; triển khai synchronous trước để giảm rủi ro. |
| Feature Flags | Đạt | Hỗ trợ system/commune/village, kết hợp permission. |
| DatabaseResolver | Đạt | Giữ single DB + `village_id`, chuẩn bị multi-database/storage/cache sau này. |
| Routing | Đạt | Portal resolved trước route registration; domain gốc không boot tenant. |
| SSO | Đạt | Ticket một lần, TTL ngắn, không chia sẻ session/token. |
| Audit | Đạt | Có portal, tenant, organization, user, impersonation, IP, action. |

## Rủi ro production còn lại

1. Route guard sai có thể làm domain gốc truy cập nhầm Business API.

Giảm thiểu:

- Fail closed khi host không xác định.
- Test bắt buộc `hongphongnb.com/api/citizens` phải bị chặn.
- Test bắt buộc `thon09.hongphongnb.com/api/admin/overview` phải bị chặn.

2. Session/token hiện tại đang tenant-scoped bằng `village_id`.

Giảm thiểu:

- Không chia sẻ token admin sang tenant.
- SSO dùng ticket ngắn hạn và tạo session mới.
- Phase 1 chưa triển khai SSO, chỉ chuẩn bị context.

3. Service Layer chưa tồn tại đầy đủ trong code hiện tại.

Giảm thiểu:

- Phase 1 không yêu cầu refactor service.
- Mọi code mới từ Phase 1 trở đi không được query DB trực tiếp từ portal/controller.
- Service/repository được thêm dần theo từng chức năng Control Center.

4. Module menu hiện tại còn hard-code ở tenant UI.

Giảm thiểu:

- Phase 1 chưa chuyển menu tenant.
- ModuleRegistry có thể chạy song song trước.
- Chỉ thay menu sau khi có snapshot/regression test.

5. Organization hierarchy mới phải không phá `village_id`.

Giảm thiểu:

- Migration chỉ additive.
- `villages.administrative_unit_id` là liên kết mở rộng.
- Business Modules tiếp tục dùng `village_id`.

6. Event Bus synchronous có thể ảnh hưởng latency nếu subscriber lỗi.

Giảm thiểu:

- Subscriber phải bắt lỗi riêng và log.
- Event dispatch không được làm hỏng transaction chính nếu event không bắt buộc.
- Queue/asynchronous để phase sau.

## Điều chỉnh bắt buộc trước Phase 1

Đã thực hiện trong tài liệu:

- Bổ sung `Domain Layer`.
- Làm rõ Business Modules chỉ phụ thuộc Core/Domain/Service.
- Làm rõ mọi truy cập dữ liệu mới phải qua Service/Repository.
- Làm rõ không tạo service/controller trùng chức năng giữa Admin và Tenant.
- Khóa portal model: `CONTROL_CENTER`, `TENANT`, `PUBLIC`, `API`, `MOBILE`.
- Khóa organization hierarchy: `System -> Administrative Unit -> Province -> District -> Commune -> Village -> Household -> Person`.
- Khóa roadmap Phase 1-8 theo thứ tự.

Quyết định đã khóa:

1. `hongphongnb.com` là `CONTROL_CENTER`.
2. `www.hongphongnb.com` thuộc `CONTROL_CENTER` nếu có trong config `PLATFORM_ADMIN_DOMAINS`.
3. User model mục tiêu: `SYSTEM_ADMIN`, `COMMUNE_ADMIN`, `VILLAGE_ADMIN`, `STAFF`, `VIEWER`.
4. Feature flags lưu DB ở phase triển khai chính, có config fallback.
5. EventBus được thiết kế từ đầu; Notification subscribe event ở Phase 8.

## Gate quyết định Phase 1

Phase 1 chỉ được bắt đầu khi các điều kiện sau đạt:

- Checklist 8 nguyên tắc bắt buộc không còn mục `Chưa đạt`.
- Tài liệu kiến trúc đã được chấp thuận.
- Architecture Freeze được phê duyệt.
- Có test plan tối thiểu cho domain routing và route guard.
- Có rollback flag cho Admin Portal.

Trạng thái hiện tại:

- Checklist kiến trúc: Đạt ở mức thiết kế.
- Architecture Freeze: chờ phê duyệt.
- Sẵn sàng chuẩn bị Phase 1 sau khi Architecture Freeze được phê duyệt.
- Chưa được triển khai code runtime cho đến khi người phụ trách xác nhận bắt đầu Phase 1.
