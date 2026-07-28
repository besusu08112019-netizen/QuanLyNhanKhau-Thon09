# Architecture Design: Community Control Center Platform

Ngày thiết kế: 2026-07-28

## 0. Mục tiêu và nguyên tắc

Hệ thống được định hướng thành nền tảng quản lý cộng đồng dùng chung một source code, có thể vận hành nhiều thôn hiện tại và mở rộng lên nhiều xã, huyện, tỉnh trong tương lai mà không phải refactor lại kiến trúc nền.

Mục tiêu chính:

- Một source code duy nhất.
- Nhiều domain/subdomain.
- Nhiều đơn vị hành chính.
- Business Modules chỉ tồn tại một lần.
- Community Control Center quản trị toàn hệ thống.
- Tenant Portal giữ nguyên trải nghiệm hiện tại cho từng thôn.

Ràng buộc bắt buộc:

- Không viết lại module hiện có.
- Không sao chép controller, service, model nghiệp vụ.
- Không tạo API nghiệp vụ thứ hai.
- Không tạo Dashboard tenant thứ hai.
- Không hard-code domain, thôn, xã hoặc số cấp tổ chức.
- Không để domain gốc boot tenant.
- Không để domain gốc truy cập API nghiệp vụ tenant.
- Không làm ảnh hưởng production.

## 1. Kiến trúc tổng thể

```text
                         Application Core

     Auth | Tenant | Portal | Config | Event | Audit | Routing
     Permission | ModuleRegistry | DatabaseResolver | Services

                                |

                         Domain Layer

                                |

                 Community Control Center
                                |
                         Admin Portal

                                |

                         Tenant Portal

                                |

                         Business Modules
```

`Admin Portal` không phải trung tâm của kiến trúc. `Admin Portal` chỉ là giao diện vận hành của `Community Control Center`.

`Community Control Center` là tầng điều hành cấp hệ thống, gồm:

- Dashboard tổng.
- Quản lý đơn vị hành chính.
- Quản lý tài khoản.
- Quản lý phân quyền.
- Quản lý module.
- Giám sát hệ thống.
- Audit.
- Backup.
- Cấu hình.
- SSO sang tenant.

## 2. Application Core

Application Core độc lập với Portal. Portal không chứa logic nghiệp vụ, không tự xử lý auth, permission, tenant, audit hay database.

Core chịu trách nhiệm:

- `Authentication`: đăng nhập, token, session, SSO ticket.
- `Authorization`: quyền theo portal, organization scope, tenant, role, module, action.
- `TenantContext`: tenant hiện tại khi request cần tenant.
- `PortalContext`: portal hiện tại của request.
- `Config`: cấu hình domain, hierarchy, feature flags, runtime settings.
- `Event`: event bus và subscriber.
- `Audit`: audit tập trung có đầy đủ context.
- `Routing`: route theo portal và policy.
- `Permission`: permission matrix và policy evaluator.
- `ModuleRegistry`: đăng ký toàn bộ module.
- `DatabaseResolver`: connection/storage/cache resolver.
- `Service Layer`: service dùng chung cho Admin Portal và Tenant Portal.

Luồng request chuẩn:

```text
Request
  -> Config
  -> PortalContext
  -> DatabaseResolver
  -> TenantContext nếu request cần tenant
  -> Authentication
  -> Authorization
  -> Router
  -> Controller
  -> Service Layer
  -> Event Bus
  -> Audit
  -> Response
```

## 3. Domain Layer

Domain Layer là lớp nghiệp vụ cốt lõi, độc lập với giao diện và độc lập với từng portal.

Domain Layer chứa:

- Entity/domain model cốt lõi.
- Business rule dùng chung.
- Domain service.
- Domain event.
- Repository interface hoặc repository contract.

Domain Layer không được phụ thuộc:

- Admin Portal.
- Tenant Portal.
- Web UI.
- Mobile UI.
- Public API.
- AI Assistant.
- Controller cụ thể.

Quy tắc:

- Business rule chỉ viết một lần trong Domain/Service.
- Controller chỉ gọi service.
- Portal chỉ render/điều hướng.
- Repository implementation có thể dùng database hiện tại, nhưng contract phải đủ ổn định để sau này thay database resolver.
- Nếu một logic được dùng bởi cả Admin Portal và Tenant Portal, logic đó phải nằm ở Domain/Service, không đặt trong Portal.

Mục tiêu dài hạn:

- Có thể thêm Web, Mobile, Public API hoặc AI Assistant mà không sửa Domain Layer.
- Các kênh mới chỉ thêm adapter/controller/presenter.

## 4. Community Control Center

Community Control Center là khái niệm điều hành cấp nền tảng. Nó không chứa logic nghiệp vụ chi tiết của thôn.

Nhiệm vụ:

- Điều phối các đơn vị hành chính.
- Tổng hợp số liệu từ các tenant.
- Quản lý module và feature flags.
- Quản lý người dùng hệ thống và phân quyền.
- Theo dõi health, backup, audit.
- Cấp SSO ticket sang Tenant Portal.

Không được làm:

- Không gọi trực tiếp controller nghiệp vụ như `PersonController`, `HouseholdController`, `DashboardController`.
- Không trả dữ liệu nhân khẩu/hộ dân chi tiết.
- Không bypass `TenantContext`.
- Không cập nhật dữ liệu nghiệp vụ của tenant trừ các thao tác quản trị được định nghĩa rõ.

Admin Portal chỉ render dữ liệu do Control Center service cung cấp.

## 5. PortalContext

`PortalContext` xác định request thuộc portal nào. Nó độc lập với `TenantContext`.

Portal ban đầu:

- `CONTROL_CENTER`: giao diện điều hành toàn hệ thống.
- `TENANT`: hệ thống quản lý hiện tại của từng thôn.

Portal mở rộng tương lai:

- `PUBLIC`
- `API`
- `MOBILE`

Cấu hình đề xuất:

```env
PLATFORM_ROOT_DOMAIN=hongphongnb.com
PLATFORM_ADMIN_DOMAINS=hongphongnb.com,www.hongphongnb.com
PLATFORM_TENANT_DOMAIN_PATTERN={code}.hongphongnb.com
PLATFORM_DEFAULT_PORTAL=TENANT
PLATFORM_ADMIN_ENABLED=false
```

Quy tắc:

- Domain gốc trong `PLATFORM_ADMIN_DOMAINS` resolve thành `CONTROL_CENTER`.
- Subdomain match pattern resolve thành `TENANT`.
- Host không xác định phải fail closed hoặc dùng fallback theo config explicit.
- Domain gốc không được gọi `TenantContext::fallback()`.

## 6. Organization Hierarchy

Kiến trúc tổ chức không thiết kế cứng theo thôn.

Cây tổ chức mục tiêu:

```text
System
  -> Administrative Unit
      -> Province
          -> District
              -> Commune
                  -> Village
                      -> Household
                          -> Person
```

Hiện tại sử dụng:

```text
System
  -> Commune
      -> Village
          -> Household
              -> Person
```

Thiết kế dữ liệu đề xuất:

- `administrative_units`
  - `id`
  - `parent_id`
  - `type`: `SYSTEM`, `PROVINCE`, `DISTRICT`, `COMMUNE`, `VILLAGE`
  - `code`
  - `name`
  - `domain`
  - `subdomain`
  - `status`
  - `metadata`
  - `created_at`
  - `updated_at`

- `villages`
  - giữ tương thích bảng hiện tại
  - bổ sung `administrative_unit_id`
  - bổ sung metadata vận hành tenant

Nguyên tắc:

- Business Modules hiện tại tiếp tục dùng `village_id`.
- Hierarchy service ánh xạ `village_id` với node `VILLAGE`.
- Khi mở rộng cấp xã/huyện/tỉnh, không cần sửa module nghiệp vụ.
- `Village` chỉ là một loại `Administrative Unit`, không phải root của hệ thống.

## 7. ModuleRegistry

`ModuleRegistry` là nơi đăng ký toàn bộ module của hệ thống, không chỉ dùng để render menu.

Metadata bắt buộc:

- `id`
- `name`
- `icon`
- `route`
- `permission`
- `portal_scope`
- `menu_group`
- `sort_order`
- `enabled`
- `version`

Metadata mở rộng:

- `feature_flag`
- `dependencies`
- `description`
- `api_prefix`
- `screen_id`
- `category`
- `introduced_at`

Ví dụ:

```php
[
    'id' => 'citizen',
    'name' => 'Nhân khẩu',
    'icon' => 'users',
    'route' => '/citizens',
    'permission' => 'citizen',
    'portal_scope' => ['TENANT'],
    'menu_group' => 'population',
    'sort_order' => 20,
    'enabled' => true,
    'version' => '1.0.0',
]
```

Quy tắc:

- Portal chỉ đọc registry để render menu.
- Permission evaluator đọc registry để biết module/action hợp lệ.
- Feature flag evaluator đọc registry để bật/tắt module.
- Router có thể đọc registry metadata để kiểm soát route group.
- Không hard-code menu trong Admin Portal hoặc Tenant Portal.

## 8. Business Modules

Business Modules là toàn bộ module nghiệp vụ hiện tại:

- Dashboard.
- Hộ gia đình.
- Nhân khẩu.
- GIS.
- Đảng viên.
- Công trình.
- Nhà ở.
- Xe cộ.
- Nông nghiệp.
- Vật nuôi.
- Đóng góp hộ.
- Báo cáo.
- AI.
- Import/Export.
- PDF/Excel.

Nguyên tắc:

- Business Modules không phụ thuộc Admin Portal.
- Business Modules không phụ thuộc Tenant Portal.
- Business Modules chỉ phụ thuộc Application Core, Domain Layer và Service Layer dùng chung.
- API hiện tại giữ nguyên.
- Controller/service/model hiện tại giữ nguyên.
- Logic tenant scoping tiếp tục qua `TenantContext` và `BaseModel`.
- Không tạo logic nghiệp vụ trùng lặp giữa các module hoặc portal.

Điều này đảm bảo có thể thêm portal mới mà không phải sửa nghiệp vụ.

## 9. Service Layer

Không để Admin Dashboard query trực tiếp database.

Service Layer là nơi chứa logic dùng chung:

- `StatisticsService`
- `VillageService`
- `SystemHealthService`
- `DashboardService`
- `OrganizationService`
- `FeatureFlagService`
- `ModuleRegistryService`
- `SsoTicketService`
- `AuditService`

Quy tắc:

- Controller chỉ điều phối request/response.
- Portal chỉ render.
- Query tổng hợp nằm trong service.
- Truy cập dữ liệu phải đi qua Service/Repository dùng chung.
- Admin Portal và Tenant Portal dùng lại service khi có cùng nghiệp vụ.
- Không duplicate query giữa admin và tenant.
- Không tạo service trùng chức năng giữa Admin Portal và Tenant Portal.

Ví dụ:

```text
AdminDashboardController
  -> StatisticsService::platformOverview()

DashboardController hiện tại
  -> DashboardService::tenantOverview(TenantContext)
```

Giai đoạn đầu có thể bọc các model hiện tại bằng service mới, không sửa sâu module đang ổn định.

## 10. DatabaseResolver

Hiện tại giữ một database và `village_id`.

`DatabaseResolver` được thiết kế để sau này hỗ trợ:

- database riêng theo tenant
- storage riêng theo tenant
- cache riêng theo tenant
- read replica
- central reporting database

Giai đoạn hiện tại:

```text
CONTROL_CENTER -> primary database
TENANT -> primary database + village_id scope
```

Tương lai:

```text
CONTROL_CENTER -> central database/reporting adapter
TENANT thon09 -> tenant database profile thon09
TENANT thon10 -> tenant database profile thon10
```

Business Modules không gọi database config trực tiếp. Chúng dùng Core database abstraction hiện tại và sau này được resolver cấp connection phù hợp.

## 11. Routing

Routing phải quyết định portal trước khi đăng ký route.

Quy tắc:

- `CONTROL_CENTER` chỉ đăng ký route Control Center.
- `TENANT` đăng ký route hiện tại.
- Domain gốc không đăng ký route nghiệp vụ tenant.
- Subdomain tenant không đăng ký route Control Center.

Thiết kế:

```text
bootstrap
  -> PortalContext::boot()
  -> DatabaseResolver::boot()
  -> if CONTROL_CENTER:
       registerControlCenterRoutes()
       render Admin Portal
     if TENANT:
       TenantContext::boot()
       registerTenantBusinessRoutes()
       render Tenant Portal
```

Route namespace:

```text
/api/admin/*       Control Center API
/api/*             Tenant business API hiện tại
/sso/consume       SSO consume endpoint trên tenant domain
```

## 12. Authentication

Không dùng chung session giữa Admin Portal và Tenant Portal.

Không chia sẻ bearer token giữa domain gốc và subdomain.

Nguyên tắc:

- Admin login tạo admin session/token.
- Tenant login tạo tenant session/token.
- SSO tạo tenant session/token mới từ ticket.
- Token lookup phải biết portal và tenant context.

Mở rộng dữ liệu đề xuất:

- `user_sessions.portal`
- `user_sessions.village_id`
- `user_sessions.organization_unit_id`
- `user_sessions.impersonated_by_user_id`
- `user_sessions.sso_ticket_id`

Nếu cần giảm rủi ro migration, có thể thêm bảng phụ:

- `session_contexts`

## 13. Authorization

Authorization phải đánh giá:

- Portal.
- Organization scope.
- Tenant.
- User.
- Role.
- Module.
- Action.
- Feature flag.

Role hiện tại đang có trong code:

- `SUPER_ADMIN`
- `ADMIN`
- `OFFICER`
- `VIEWER`

Role model kiến trúc đã khóa:

- `SYSTEM_ADMIN`
- `COMMUNE_ADMIN`
- `VILLAGE_ADMIN`
- `STAFF`
- `VIEWER`

Nguyên tắc:

- Role và permission tách riêng.
- Không hard-code role trong portal hoặc module.
- Role hiện tại được migration/map dần sang role model mới, không đổi đột ngột trong Phase 1.

Scope đề xuất:

- `SYSTEM`
- `PROVINCE`
- `DISTRICT`
- `COMMUNE`
- `VILLAGE`

Ví dụ:

- `SYSTEM_ADMIN + SYSTEM`: toàn quyền Control Center, SSO mọi tenant.
- `ADMIN + COMMUNE`: quản lý các village trong một xã.
- `ADMIN + VILLAGE`: quản trị một thôn.
- `OFFICER + VILLAGE`: thao tác nghiệp vụ trong thôn.
- `VIEWER + VILLAGE`: chỉ xem trong thôn.

Policy:

- Tenant admin không thấy Control Center.
- Tenant admin không biết tenant khác tồn tại.
- Super Admin khi SSO vào tenant không bị ghi nhận thành admin thôn.
- Business API chỉ chạy khi `PortalContext = TENANT`.

## 14. Audit

Audit là service Core, không thuộc portal.

Audit record phải có:

- `portal`
- `organization_unit_id`
- `tenant_id`
- `tenant_code`
- `user_id`
- `actor_email`
- `actor_role`
- `impersonated_by_user_id`
- `ip_address`
- `user_agent`
- `module`
- `action`
- `entity_id`
- `message`
- `metadata`
- `created_at`

Ví dụ SYSTEM_ADMIN chuyển sang Thôn 09:

```text
portal = CONTROL_CENTER
tenant_id = thon09
user_id = system_admin_id
action = sso_issue
message = Super Admin tạo SSO ticket vào Thôn 09
```

Khi tenant consume ticket:

```text
portal = TENANT
tenant_id = thon09
user_id = system_admin_id
impersonated_by_user_id = system_admin_id
action = sso_consume
message = Tạo tenant session từ SSO ticket
```

## 15. SSO

Không dùng chung session.

Không chia sẻ bearer token.

Luồng:

1. SYSTEM_ADMIN ở Control Center chọn Thôn 09.
2. Control Center gọi `SsoTicketService::issue()`.
3. Service tạo ticket:
   - random token mạnh
   - chỉ lưu hash
   - one-time use
   - TTL ngắn, ví dụ 60 giây
   - bound với tenant đích
   - bound với actor
4. Browser redirect sang tenant:
   - `https://thon09.hongphongnb.com/sso/consume?ticket=...`
5. Tenant consume ticket:
   - kiểm tra hash
   - kiểm tra chưa dùng
   - kiểm tra chưa hết hạn
   - kiểm tra target tenant match domain hiện tại
   - tạo tenant session mới
   - đánh dấu ticket đã dùng
   - ghi audit
6. Redirect vào Tenant Portal.

Bảng đề xuất:

- `sso_tickets`
  - `id`
  - `ticket_hash`
  - `source_portal`
  - `target_portal`
  - `target_tenant_id`
  - `actor_user_id`
  - `mapped_user_id`
  - `expires_at`
  - `used_at`
  - `created_ip`
  - `used_ip`
  - `created_at`

## 16. Event System

Thiết kế Event Bus ngay từ đầu để mở rộng Notification, Audit, AI, reporting mà không sửa sâu module nghiệp vụ.

Event ví dụ:

- `CitizenCreated`
- `CitizenUpdated`
- `HouseholdCreated`
- `HouseholdMerged`
- `VillageLocked`
- `VillageActivated`
- `ModuleInstalled`
- `ModuleDisabled`
- `SsoTicketIssued`
- `SsoTicketConsumed`

Thành phần:

- `EventBus`
- `DomainEvent`
- `EventSubscriber`
- `EventStore` hoặc `event_logs`

Nguyên tắc:

- Business Module phát event qua Core.
- Subscriber xử lý audit/notification/reporting/AI.
- Event payload không chứa dữ liệu nhạy cảm nếu không cần.
- Event Bus ban đầu có thể synchronous để giảm rủi ro.
- Sau này có thể chuyển sang queue mà không đổi controller.

Ví dụ:

```text
CitizenService::create()
  -> save citizen
  -> EventBus::dispatch(CitizenCreated)

AuditSubscriber
  -> ghi audit

NotificationSubscriber
  -> tạo thông báo nếu policy yêu cầu
```

## 17. Feature Flags

Feature flags cho phép bật/tắt module theo:

- toàn hệ thống
- từng xã
- từng thôn

Scope:

- `SYSTEM`
- `COMMUNE`
- `VILLAGE`

Bảng đề xuất:

- `feature_flags`
  - `id`
  - `module_id`
  - `scope_type`
  - `scope_id`
  - `enabled`
  - `config`
  - `created_at`
  - `updated_at`

Quy tắc:

- `ModuleRegistry.enabled` là mặc định hệ thống.
- `FeatureFlagService` quyết định trạng thái cuối cùng theo scope.
- Portal không hard-code module ẩn/hiện.
- Permission và feature flag đều phải pass thì module mới hiển thị và route mới chạy.

## 18. Admin Portal

Admin Portal là UI của Community Control Center.

Chức năng:

- Dashboard tổng.
- Quản lý organization hierarchy.
- Quản lý thôn.
- Quản lý tài khoản.
- Quản lý phân quyền.
- Quản lý module.
- Feature flags.
- Audit.
- System health.
- Backup.
- Cấu hình.
- SSO.

Admin Portal không chứa logic. Nó chỉ:

- gọi Control Center API
- render dữ liệu
- điều hướng

## 19. Tenant Portal

Tenant Portal là hệ thống hiện tại.

Giữ nguyên:

- `views/app.php`
- API nghiệp vụ hiện tại
- Dashboard tenant hiện tại
- Import/Export/PDF/Excel hiện tại
- Trải nghiệm người dùng hiện tại

Chỉ bổ sung ở Core:

- Portal guard.
- Tenant route registration chỉ chạy khi `PortalContext = TENANT`.
- ModuleRegistry có thể thay thế dần menu hard-code theo phase riêng.

## 20. Kế hoạch migration

### Phase 0: Chốt kiến trúc

- Hoàn thiện tài liệu này.
- Thống nhất ADR.
- Không code runtime trước khi thống nhất.

### Phase 1: PortalContext

- Thêm `PortalContext`.
- Thêm config domain.
- Chặn Business API trên domain gốc.
- Không sửa module nghiệp vụ.
- Không triển khai Control Center Shell đầy đủ.

Rollback:

- Tắt `PLATFORM_ADMIN_ENABLED`.
- Domain gốc quay về behavior cũ nếu cần.

### Phase 2: Control Center Shell

- Render Control Center shell.
- Thêm route loader riêng cho Control Center.
- Tenant Portal giữ nguyên.

### Phase 3: Quản lý đơn vị

- Migration additive cho organization hierarchy.
- Quản lý Administrative Unit, Commune, Village.
- Không thay `village_id` trong Business Modules.

### Phase 4: Dashboard tổng

- `StatisticsService`.
- Aggregate theo service/repository.
- Không truy cập dữ liệu chi tiết nhân khẩu/hộ dân.

### Phase 5: SSO

- Tạo `sso_tickets`.
- Issue/consume flow.
- Tạo tenant session mới.
- Audit đầy đủ.

### Phase 6: Monitoring

- System health.
- Session monitoring.
- Backup monitoring.
- Audit monitoring.

### Phase 7: Feature Flags

- ModuleRegistry.
- FeatureFlagService.
- Scope theo system/commune/village.

### Phase 8: Notification

- Notification subscribe EventBus.
- Không sửa module nghiệp vụ khi thêm notification.

Mọi phase phải có:

- Rollback Plan.
- Migration Plan.
- Smoke Test.
- Regression Test.
- Production Checklist.

## 21. Architecture Decision Record

### ADR-001: Chọn PortalContext

Quyết định:

- Thêm `PortalContext` độc lập với `TenantContext`.

Lý do:

- Domain gốc là Control Center, không phải tenant.
- Tương lai có thể thêm `PUBLIC`, `API`, `MOBILE`.
- Router không phải refactor khi thêm portal.

Chi phí:

- Cần thêm config domain và route guard.

Rollback:

- Tắt `PLATFORM_ADMIN_ENABLED`.

Rủi ro:

- Resolve sai portal có thể expose nhầm route. Cần fail closed.

### ADR-002: Không sửa module hiện tại

Quyết định:

- Business Modules giữ nguyên và chỉ phụ thuộc Core.

Lý do:

- Các module đang vận hành ổn định.
- Rủi ro production thấp nhất.
- Tránh duplicate logic và dữ liệu.

Chi phí:

- Một số service wrapper sẽ được thêm dần thay vì refactor ngay.

Rollback:

- Vì không sửa module nghiệp vụ, rollback chủ yếu là tắt portal/admin route mới.

Rủi ro:

- Menu hiện tại còn hard-code, cần chuyển dần sang ModuleRegistry theo phase.

### ADR-003: Chọn ModuleRegistry

Quyết định:

- Tạo registry khai báo module bằng metadata chuẩn.

Lý do:

- Không hard-code menu/module.
- Feature flags và permission có nguồn dữ liệu chung.
- Thêm module mới không cần sửa portal.

Chi phí:

- Cần map module hiện tại vào registry.

Rollback:

- Tenant menu có thể giữ fallback hiện tại trong giai đoạn chuyển đổi.

Rủi ro:

- Registry sai metadata có thể ẩn nhầm module. Cần test snapshot menu.

### ADR-004: Dùng Community Control Center

Quyết định:

- Đặt tầng điều hành là `Community Control Center`; Admin Portal chỉ là UI của tầng này.

Lý do:

- Phản ánh đúng mục tiêu nền tảng quản lý cộng đồng.
- Tránh biến Admin Portal thành nơi chứa logic.
- Cho phép sau này có Mobile/API/Public Portal dùng chung Control Center services.

Chi phí:

- Cần service layer rõ ràng.

Rollback:

- Admin Portal có thể bị tắt mà Tenant Portal vẫn chạy.

Rủi ro:

- Nếu service layer query quá rộng có thể ảnh hưởng performance. Cần phân trang/cache aggregate.

### ADR-005: Giữ `village_id`, thêm DatabaseResolver

Quyết định:

- Không chuyển multi-database ngay.
- Thêm `DatabaseResolver` để chuẩn bị tương lai.

Lý do:

- Giảm rủi ro migration.
- `BaseModel` hiện đã hỗ trợ tenant scoping bằng `village_id`.
- Sau này có thể đổi resolver mà không sửa module.

Chi phí:

- Cần định nghĩa database/storage/cache profile metadata.

Rollback:

- Resolver mặc định luôn trỏ primary database hiện tại.

Rủi ro:

- Query tổng hợp toàn hệ thống có thể nặng khi dữ liệu lớn. Cần service aggregate/cache.

### ADR-006: Thêm Event Bus

Quyết định:

- Thiết kế Event Bus từ đầu, triển khai synchronous trước.

Lý do:

- Audit, Notification, AI, reporting có thể subscribe mà không sửa module nhiều lần.
- Dễ nâng cấp sang queue.

Chi phí:

- Cần chuẩn hóa event payload.

Rollback:

- Có thể tắt subscriber, không ảnh hưởng transaction chính nếu thiết kế an toàn.

Rủi ro:

- Subscriber lỗi có thể ảnh hưởng request nếu synchronous. Cần bắt lỗi và log rõ.

## 22. Kế hoạch kiểm thử

Kiểm thử routing:

- `hongphongnb.com` render Admin Portal.
- `thon09.hongphongnb.com` render Tenant Portal.
- `hongphongnb.com/api/citizens` bị chặn.
- `thon09.hongphongnb.com/api/admin/overview` bị chặn.

Kiểm thử auth:

- Admin token không dùng trực tiếp được ở tenant.
- Tenant token không dùng được ở Control Center.
- CSRF vẫn hoạt động theo session/token hiện tại.

Kiểm thử authorization:

- SYSTEM_ADMIN thấy Control Center.
- Admin thôn không thấy Control Center.
- Viewer không ghi được dữ liệu.
- Feature flag tắt module thì menu ẩn và API bị chặn.

Kiểm thử SSO:

- Ticket dùng một lần.
- Ticket hết hạn bị từ chối.
- Ticket tenant A không consume được ở tenant B.
- Audit ghi đúng actor hệ thống.

Kiểm thử regression tenant:

- Dashboard tenant không đổi.
- Nhân khẩu không đổi.
- Hộ dân không đổi.
- GIS không đổi.
- Import/Export/PDF/Excel không đổi.

## 23. Thành phần giữ nguyên

- `DashboardController`
- `PersonController`
- `HouseholdController`
- `GisController`
- `ReportController`
- `ImportController`
- Export/PDF/Excel hiện tại
- Các model/service nghiệp vụ hiện tại
- Các API tenant hiện tại
- UI tenant hiện tại

## 24. Thành phần thêm mới

- `PortalContext`
- `Domain Layer` contracts
- `DatabaseResolver`
- `ModuleRegistry`
- `EventBus`
- `FeatureFlagService`
- `OrganizationService`
- `StatisticsService`
- `VillageService`
- `SystemHealthService`
- `SsoTicketService`
- `AuditService`
- `ControlCenterController`
- `AdminPortal` view/assets
- Migrations additive cho organization, feature flags, SSO, audit/session context.

## 25. Architecture Freeze Decisions

Các quyết định kiến trúc cốt lõi đã khóa trước Phase 1:

1. Domain gốc `hongphongnb.com` là `CONTROL_CENTER`.
2. `www.hongphongnb.com` thuộc `CONTROL_CENTER` khi được cấu hình trong `PLATFORM_ADMIN_DOMAINS`.
3. `PortalContext` dùng enum/mã portal: `CONTROL_CENTER`, `TENANT`, `PUBLIC`, `API`, `MOBILE`.
4. Phase 1 chỉ triển khai `PortalContext` và route guard, không sửa module nghiệp vụ.
5. User model mục tiêu là `SYSTEM_ADMIN`, `COMMUNE_ADMIN`, `VILLAGE_ADMIN`, `STAFF`, `VIEWER`.
6. Role và Permission tách riêng, không hard-code role trong portal/module.
7. Phase hiện tại giữ một database và `village_id`; không triển khai multi-database trong Phase 1.
8. Feature flags lưu bằng database ở phase triển khai chính, có config fallback cho rollback.
9. EventBus được thiết kế từ đầu; Notification subscribe event ở Phase 8.
10. Mọi thay đổi kiến trúc sau freeze phải đi qua ADR.

## 26. Điều kiện được phép bắt đầu Phase 1

Chỉ được bắt đầu Phase 1 khi:

- Architecture Freeze hoàn tất.
- Tất cả ADR trong tài liệu này được khóa.
- Không còn quyết định kiến trúc mở.
- Rủi ro production đã được đánh giá.
- Có rollback plan.
- Có checklist kiểm thử.

Trạng thái sau tài liệu freeze:

- Được phép chuẩn bị Phase 1 sau khi người phụ trách phê duyệt Architecture Freeze.
- Chưa được code runtime khi chưa có phê duyệt rõ ràng.
