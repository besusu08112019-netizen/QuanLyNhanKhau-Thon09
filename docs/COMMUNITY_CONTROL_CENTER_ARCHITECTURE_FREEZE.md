# Architecture Freeze: Community Control Center Platform

Ngày freeze: 2026-07-28

Trạng thái: Đã phê duyệt

Phạm vi:

- Khóa các quyết định kiến trúc cốt lõi trước Phase 1.
- Không tạo code runtime.
- Không sửa Controller.
- Không sửa Service.
- Không sửa Database.
- Không sửa Router.
- Không sửa Module.
- Không commit trong bước freeze.

Tài liệu liên quan:

- `docs/COMMUNITY_CONTROL_CENTER_ARCHITECTURE.md`
- `docs/COMMUNITY_CONTROL_CENTER_ARCHITECTURE_REVIEW_CHECKLIST.md`

## 1. Mô hình tổ chức đã khóa

Hệ thống không xoay quanh `Village` như root.

Mô hình đã khóa:

```text
System
  -> Administrative Unit
      -> Province (future)
          -> District (future)
              -> Commune
                  -> Village
                      -> Household
                          -> Person
```

Quyết định:

- `Village` chỉ là một loại `Administrative Unit`.
- Hiện tại sử dụng `Commune` và `Village`.
- Kiến trúc phải hỗ trợ `Province` và `District` mà không refactor Business Modules.
- Business Modules hiện tại tiếp tục dùng `village_id`.

## 2. Community Control Center đã khóa

Domain gốc:

```text
hongphongnb.com
```

là `Community Control Center`.

Quyết định:

- Community Control Center không phải Tenant Portal.
- Community Control Center là trung tâm điều hành toàn hệ thống.
- Admin Portal chỉ là UI của Community Control Center.
- Community Control Center không chứa logic nghiệp vụ tenant.
- Community Control Center không truy cập trực tiếp database nghiệp vụ.

Chức năng thuộc Community Control Center:

- Dashboard tổng.
- Quản lý đơn vị.
- Quản lý tài khoản.
- Quản lý phân quyền.
- Quản lý module.
- Audit.
- Backup.
- Monitoring.
- Cấu hình.
- Thông báo.
- SSO.

## 3. Portal Model đã khóa

Portal là lớp giao diện.

Portal không chứa nghiệp vụ.

Portal chỉ gọi Application Service.

PortalContext phải hỗ trợ:

- `CONTROL_CENTER`
- `TENANT`
- `PUBLIC` future
- `API` future
- `MOBILE` future

Quyết định:

- PortalContext không phụ thuộc domain cố định.
- Domain được resolve bằng config.
- Domain không xác định phải fail closed hoặc fallback explicit qua config.
- Domain gốc không boot Tenant.

## 4. Application Core đã khóa

Application Core là lớp thấp nhất của nền tảng.

Core không phụ thuộc Portal.

Core gồm:

- Authentication.
- Authorization.
- PortalContext.
- TenantContext.
- Config.
- Routing.
- Audit.
- Permission.
- DatabaseResolver.
- ModuleRegistry.
- EventBus.
- FeatureFlags.

Quyết định:

- Core không chứa giao diện.
- Core không import Portal UI.
- Mọi Portal dùng chung Core.

## 5. Domain Layer đã khóa

Toàn bộ nghiệp vụ cốt lõi phải nằm trong Domain.

Domain ví dụ:

- Citizen.
- Household.
- Village.
- Contribution.
- Agriculture.
- Vehicle.
- Housing.
- Party.
- Notification.

Quyết định:

- Domain không phụ thuộc Portal.
- Domain không phụ thuộc UI.
- Domain không truy cập View.
- Domain không nhận HTTP request hoặc DOM state.
- Web, Mobile, Public API, AI Assistant phải có thể dùng Domain mà không sửa Domain.

## 6. Business Module đã khóa

Business Module chỉ là lớp tổ chức chức năng.

Business Module sử dụng:

```text
Core
  -> Domain
      -> Service
```

Quyết định:

- Business Module không chứa logic trùng lặp.
- Business Module không được copy.
- Business Module không phụ thuộc Control Center.
- Business Module không phụ thuộc Tenant Portal.
- Business Modules hiện tại giữ nguyên trong Phase 1.

## 7. Service Layer đã khóa

Mọi portal phải đi qua:

```text
Portal
  -> Application Service
      -> Repository
          -> Database
```

Quyết định:

- Portal tuyệt đối không query trực tiếp database.
- Controller mới không query trực tiếp database.
- Không duplicate query.
- Không duplicate service.
- Logic dùng chung phải nằm trong Application Service hoặc Domain Service.

## 8. ModuleRegistry đã khóa

ModuleRegistry là nơi duy nhất đăng ký module.

Metadata bắt buộc:

- `id`
- `code`
- `name`
- `icon`
- `permission`
- `portal_scope`
- `menu_group`
- `sort_order`
- `enabled`
- `version`
- `feature_flag`

Quyết định:

- Không hard-code menu.
- Không hard-code route.
- Không hard-code quyền.
- Portal chỉ đọc registry để hiển thị.
- Permission và FeatureFlags sử dụng registry làm nguồn metadata.

## 9. Database Strategy đã khóa

Phase hiện tại:

- Một database.
- Tách dữ liệu bằng `village_id`.
- `DatabaseResolver` chỉ chuẩn bị cho tương lai.
- Không triển khai multi-database trong Phase 1.

Quyết định:

- Mọi module nghiệp vụ vẫn hoạt động như hiện tại.
- Multi-database là khả năng tương lai, không phải phạm vi Phase 1.
- Resolver mặc định trỏ database hiện tại.

## 10. User Model đã khóa

Không sử dụng trực tiếp `ADMIN` hiện tại làm mô hình kiến trúc dài hạn.

User model mục tiêu:

- `SYSTEM_ADMIN`
- `COMMUNE_ADMIN`
- `VILLAGE_ADMIN`
- `STAFF`
- `VIEWER`

Quyết định:

- Role và Permission tách riêng.
- Không hard-code role trong Portal.
- Không hard-code role trong Business Module.
- Role hiện tại được map/migrate dần, không đổi đột ngột trong Phase 1.

## 11. Event Bus đã khóa

Event Bus tối thiểu được thiết kế từ đầu.

Event ví dụ:

- `CitizenCreated`
- `CitizenUpdated`
- `CitizenDeleted`
- `HouseholdMerged`
- `VillageLocked`
- `ModuleInstalled`

Quyết định:

- Notification chỉ subscribe Event.
- Audit có thể subscribe Event.
- AI có thể subscribe Event ở phase sau.
- Không sửa module nghiệp vụ khi thêm Notification.
- Phase đầu có thể synchronous; queue là mở rộng sau.

## 12. Feature Flags đã khóa

Feature Flags phải hỗ trợ bật/tắt:

- Toàn hệ thống.
- Theo Commune.
- Theo Village.

Quyết định:

- Không hard-code module.
- Feature flags lưu DB ở phase triển khai chính.
- Có config fallback để rollback.
- Module chỉ hoạt động khi registry, permission và feature flag đều cho phép.

## 13. Audit đã khóa

Audit phải ghi:

- Portal.
- Administrative Unit.
- Tenant.
- User.
- Action.
- Resource.
- IP.
- User Agent.
- Timestamp.
- Correlation ID.

Quyết định:

- Nếu `SYSTEM_ADMIN` đang quản lý Village thì audit vẫn ghi đúng user hệ thống.
- Không ghi nhầm thành admin thôn.
- SSO phải audit cả issue và consume.

## 14. Roadmap đã khóa

Sau Architecture Freeze, roadmap bắt buộc:

```text
Phase 1: PortalContext
Phase 2: Control Center Shell
Phase 3: Quản lý đơn vị
Phase 4: Dashboard tổng
Phase 5: SSO
Phase 6: Monitoring
Phase 7: Feature Flags
Phase 8: Notification
```

Quyết định:

- Không bỏ qua thứ tự phase.
- Phase 1 không sửa module nghiệp vụ.
- Mỗi phase phải có rollback, migration, smoke test, regression test và production checklist.

## 15. Production Safety đã khóa

Mọi phase phải có:

- Rollback Plan.
- Migration Plan.
- Smoke Test.
- Regression Test.
- Production Checklist.

Không deploy nếu chưa pass toàn bộ.

## 16. Điều kiện được phép code

Chỉ được bắt đầu Phase 1 khi:

- Architecture Freeze hoàn tất.
- Tất cả ADR đã được khóa.
- Không còn quyết định kiến trúc mở.
- Đã đánh giá rủi ro production.
- Có kế hoạch rollback.
- Có checklist kiểm thử.
- Người phụ trách xác nhận bắt đầu Phase 1.

Trạng thái hiện tại:

- Architecture Design: hoàn tất.
- Architecture Review Checklist: hoàn tất.
- Architecture Freeze: đã phê duyệt.
- Phase 1: chưa bắt đầu.

## 17. Quy trình thay đổi sau Freeze

Sau khi Architecture Freeze được phê duyệt:

- Mọi thay đổi kiến trúc phải có ADR.
- ADR phải nêu quyết định, lý do, trade-off, migration, rollback, rủi ro production và test plan.
- Không được thay đổi nền tảng bằng sửa code trực tiếp khi chưa có ADR.

## 18. Kết luận

Community Control Center Platform được khóa theo mô hình:

- Một source code.
- Nhiều đơn vị.
- Core độc lập Portal.
- Domain độc lập UI.
- Business Modules không bị copy hoặc viết lại.
- Portal chỉ là lớp hiển thị và điều hướng.
- Service/Repository là đường truy cập dữ liệu bắt buộc.
- EventBus và FeatureFlags chuẩn bị mở rộng dài hạn.

Mục tiêu cuối cùng là mở rộng nền tảng nhiều năm mà không phải thay đổi kiến trúc cốt lõi, đồng thời giữ nguyên các module nghiệp vụ đang vận hành ổn định.
