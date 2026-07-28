# Feature Blueprint: User Management

Ngay tao: 2026-07-28

Release: Release 5

Epic: Community Control Center

Feature: User Management

Trang thai: Cho review

Phan loai thay doi: Implementation Change. Khong phai Architecture Change. Khong can ADR neu trien khai dung Architecture Freeze va Master Development Charter v2.0.

## 1. Feature Goal

User Management cho phep `SYSTEM_ADMIN` quan ly tai khoan he thong trong Community Control Center.

Feature nay giai quyet:

- Quan ly danh sach tai khoan tap trung.
- Tao tai khoan quan tri/nhan su van hanh cho cac don vi.
- Cap nhat thong tin co ban va trang thai tai khoan.
- Khoa/kich hoat tai khoan khi can.
- Reset mat khau theo quy trinh quan tri.
- Khong xoa tai khoan de giu lich su va audit.

Doi tuong su dung:

- `SYSTEM_ADMIN`: duoc xem, tao, sua, khoa/kich hoat va reset mat khau tai khoan trong Control Center.

Gia tri mang lai:

- Tao nen cho Permission, Scope va SSO o cac Feature sau.
- Giam thao tac truc tiep database khi quan ly tai khoan.
- Dam bao moi thao tac ghi tai khoan duoc guard va audit.

## 2. Business Flow

### List Accounts

- Actor vao Control Center.
- He thong kiem tra authorization `control_center.users.read`.
- He thong lay danh sach tai khoan theo filter.
- UI hien metadata tai khoan, khong hien password hash, token, session secret.
- Cot danh sach uu tien theo thu tu: Ten -> Vai tro -> Don vi -> Trang thai -> Dang nhap cuoi.
- Neu tai khoan chua tung dang nhap, hien `Chua dang nhap`.

### Create Account

Dieu kien hop le:

- Actor co `control_center.users.create`.
- `username`, `email`, `display_name`, `password`, `role`, `status` hop le.
- `username` va `email` khong trung voi tai khoan chua xoa.
- Role nam trong danh sach duoc Feature cho phep.
- Status khi tao chi nam trong `ACTIVE`, `INACTIVE`.
- Mac dinh status la `ACTIVE` neu nguoi dung khong chon.
- Username co the duoc goi y tu email hoac ho ten de giam nhap lieu.
- Don vi duoc chon tu danh sach Administrative Unit hien co.

Ket qua:

- Tai khoan moi duoc tao.
- Password duoc hash bang co che hien co.
- Audit ghi `user.created`.

### Update Account

Dieu kien hop le:

- Actor co `control_center.users.update`.
- Tai khoan ton tai.
- Khong sua truc tiep password qua update metadata; reset password dung endpoint rieng.
- Khong ha cap/khoa tai khoan `SYSTEM_ADMIN` cuoi cung neu logic hien co chua dam bao an toan.

Ket qua:

- Metadata tai khoan duoc cap nhat.
- Audit ghi `user.updated`.

### Deactivate Account

Dieu kien hop le:

- Actor co `control_center.users.update`.
- Tai khoan ton tai.
- Khong vo hieu hoa tai khoan dang thuc hien thao tac.
- Khong vo hieu hoa tai khoan `SYSTEM_ADMIN` cuoi cung.

Ket qua:

- Status chuyen sang `INACTIVE`.
- Session dang hoat dong cua tai khoan bi revoke neu co co che hien co phu hop.
- Audit ghi `user.deactivated`.

### Activate Account

Dieu kien hop le:

- Actor co `control_center.users.activate`.
- Tai khoan ton tai.
- Tai khoan dang `INACTIVE`.

Ket qua:

- Status chuyen sang `ACTIVE`.
- Audit ghi `user.activated`.

### Reset Password

Dieu kien hop le:

- Actor co `control_center.users.reset_password`.
- Tai khoan ton tai.
- Password moi dat policy.
- Khong reset password cua `SYSTEM_ADMIN` khac neu chua co quy tac an toan duoc phe duyet.

Ket qua:

- Password hash duoc cap nhat.
- Session cu cua tai khoan bi revoke neu co the thuc hien bang co che hien co.
- Audit ghi `user.password_reset`.

### No Delete

- Feature nay khong ho tro xoa tai khoan.
- Khong co nut xoa tren UI.
- Khong co endpoint delete trong Control Center User Management.
- Khong set `DELETED` trong Feature nay.
- Lich su audit va thong tin tai khoan duoc giu lai.

## 3. UI Flow

- Them section `Tai khoan he thong` trong Control Center tu trang hien co.
- Hien danh sach tai khoan voi pagination.
- Toolbar gom tim kiem, filter role, filter status, nut them tai khoan.
- Modal them/sua tai khoan.
- Modal reset mat khau rieng, khong tron vao form sua thong tin.
- Action tung dong:
  - Sua.
  - Vo hieu hoa hoac kich hoat.
  - Reset mat khau.
- Khong co action xoa.
- Error hien tai form/section.
- Empty state khi chua co tai khoan.
- Loading state khi tai danh sach va submit form.

Cot danh sach tai khoan:

1. Ten.
2. Vai tro.
3. Don vi.
4. Trang thai.
5. Dang nhap cuoi.
6. IP cuoi.
7. Thiet bi cuoi.
8. Thoi gian tao.
9. Nguoi tao.
10. Thao tac.

Email khong la cot dau tien. Email co the hien phu ben duoi ten hoac trong tooltip/detail.

## 4. Navigation

- Su dung navigation Control Center hien co.
- Khong tao Tenant route.
- Khong thay doi Tenant Portal menu.
- Khong hard-code menu moi ngoai scope Control Center hien tai neu ModuleRegistry chua duoc trien khai day du.

## 5. API Design

Tat ca endpoint nam trong Control Center:

- `GET /api/control-center/users`
- `GET /api/control-center/users/{id}`
- `POST /api/control-center/users`
- `PUT /api/control-center/users/{id}`
- `PATCH /api/control-center/users/{id}/activate`
- `PATCH /api/control-center/users/{id}/deactivate`
- `PATCH /api/control-center/users/{id}/reset-password`

Khong thay doi API tenant hien co:

- `/api/users`
- `/api/permissions`
- `/api/auth/*`

## 6. Request

### Create

```json
{
  "username": "commune_admin",
  "email": "commune.admin@example.com",
  "display_name": "Commune Admin",
  "password": "minimum-8-chars",
  "role": "VILLAGE_ADMIN",
  "status": "ACTIVE",
  "unit_id": 1
}
```

### Update

```json
{
  "username": "commune_admin",
  "email": "commune.admin@example.com",
  "display_name": "Commune Admin",
  "role": "VILLAGE_ADMIN",
  "status": "ACTIVE",
  "unit_id": 1
}
```

### Reset Password

```json
{
  "password": "minimum-8-chars"
}
```

## 7. Response

Danh sach:

```json
{
  "items": [],
  "page": 1,
  "pageSize": 20,
  "total": 0,
  "totalPages": 1
}
```

Item:

```json
{
  "id": 1,
  "username": "commune_admin",
  "email": "commune.admin@example.com",
  "displayName": "Commune Admin",
  "role": "VILLAGE_ADMIN",
  "sourceRole": "ADMIN",
  "status": "ACTIVE",
  "unitId": 1,
  "unitName": "Thon 09",
  "lastLoginAt": null,
  "lastLoginLabel": "Chua dang nhap",
  "lastIp": null,
  "lastDevice": null,
  "createdAt": "2026-07-28T00:00:00+07:00",
  "createdBy": "System Admin"
}
```

## 8. Validation

Bat buoc:

- `username`
- `email`
- `display_name`
- `role`
- `status`
- `password` khi tao/reset

Unique:

- `username`
- `email`

Quy tac:

- `username`: 3-60 ky tu, chi gom chu thuong/so/dau cham/gach ngang/gach duoi.
- `email`: dung email format.
- `display_name`: khong rong.
- `password`: toi thieu 8 ky tu, toi da theo policy hien co.
- `status`: `ACTIVE` hoac `INACTIVE`.
- `role`: chi cho cac platform role duoc Feature phe duyet.

Role mapping tam thoi de tranh migration:

- `SYSTEM_ADMIN` -> `SUPER_ADMIN`
- `VILLAGE_ADMIN` -> `ADMIN`
- `STAFF` -> `OFFICER`
- `VIEWER` -> `VIEWER`

`COMMUNE_ADMIN` chua co source role rieng trong schema hien tai. Implementation phai chon mot trong hai huong truoc khi code:

- Khong cho tao `COMMUNE_ADMIN` trong Feature nay, chi hien disabled/future.
- Khong map tam `COMMUNE_ADMIN` vao `ADMIN` neu chua co Scope/Permission chinh thuc.

Neu can schema/role enum moi thi dung lai va lap Migration Plan, khong tu y thay doi database.

Tim kiem ho tro:

- Username.
- Ho ten.
- Email.
- Vai tro.
- Don vi.
- Trang thai.

## 9. Permission

Trong Feature nay chua trien khai Permission System day du.

Guard toi thieu:

- Write API chi cho `SYSTEM_ADMIN`.
- Read API Control Center chi cho `SYSTEM_ADMIN` neu truy cap du lieu tai khoan that.
- Feature phu thuoc Authorization Interface, khong phu thuoc truc tiep `SUPER_ADMIN`.

Permission keys du kien:

- `control_center.users.read`
- `control_center.users.create`
- `control_center.users.update`
- `control_center.users.activate`
- `control_center.users.deactivate`
- `control_center.users.reset_password`

## 10. Audit

Bat buoc audit:

- `user.created`
- `user.updated`
- `user.activated`
- `user.password_reset`
- `user.deactivated`

Audit khong ghi:

- Password plaintext.
- Password hash.
- Token.
- CSRF.
- Session secret.

## 11. Repository

Tao hoac bo sung repository rieng cho Control Center User Management.

Repository chi lam:

- Doc users.
- Tao users.
- Cap nhat users.
- Cap nhat status.
- Cap nhat password hash.
- Doc last login IP/device tu `user_sessions` neu du lieu hien co co san.
- Doc nguoi tao tu `users.created_by` neu du lieu hien co co san.
- Revoke session neu can.

Repository khong chua business rule.

Su dung bang hien co:

- `users`
- `user_sessions`
- `villages` de hien unit metadata neu can.

Khong thay doi database schema trong Blueprint nay neu khong co Migration Plan duoc phe duyet.

## 12. Service

Tao `ControlCenterUserService` hoac service tuong duong.

Service chiu trach nhiem:

- Authorization orchestration.
- Validation.
- Role mapping platform/source role.
- Goi repository.
- Goi audit.
- Khong duplicate Business Module logic.

Can xem xet tai su dung `App\Models\User` neu khong gay phu thuoc TenantContext sai cho Control Center.

## 13. Controller

Tao `ControlCenterUserController` hoac controller ten tuong duong trong namespace hien co.

Controller chi lam:

- Parse input/query.
- Goi service.
- Map exception thanh HTTP response.
- Khong query database.
- Khong chua business logic.

## 14. Error Handling

- 401: chua dang nhap.
- 403: khong co quyen.
- 419: CSRF invalid.
- 404: tai khoan khong ton tai.
- 422: validation error.
- 500: loi khong mong doi, khong expose secret.

Loi nghiep vu can hien ro:

- Khong duoc vo hieu hoa tai khoan dang dang nhap.
- Khong duoc vo hieu hoa `SYSTEM_ADMIN` cuoi cung.
- `COMMUNE_ADMIN` chua san sang trong Feature nay.

## 15. Empty State

- Danh sach trong: hien `Chua co tai khoan hien thi`.
- Filter khong co ket qua: hien `Khong tim thay tai khoan phu hop`.
- Tai khoan chua tung dang nhap: hien `Chua dang nhap`.

## 16. Loading State

- Hien loading row khi load list.
- Disable submit button khi dang luu.
- Disable action button dang xu ly.

## 17. Rollback

Rollback bang revert cac commit cua Feature User Management.

Neu khong co migration:

- Khong can rollback database.

Neu co migration duoc phe duyet rieng:

- Migration Plan phai co rollback SQL rieng truoc khi code.

## 18. Test Case

Unit/Smoke:

- List users tren Control Center.
- Create khong token bi tu choi.
- Create co token nhung thieu CSRF bi tu choi.
- Create validation fail voi email sai/password ngan/role sai.
- Create `COMMUNE_ADMIN` bi tu choi hoac disabled theo UI.
- Update validation fail voi user khong ton tai.
- Search theo username/ho ten/email/vai tro/don vi/trang thai.
- Last login null hien `Chua dang nhap`.
- Deactivate/activate khong token bi tu choi.
- Khong co DELETE endpoint/action.
- Tenant domain khong truy cap duoc `/api/control-center/users`.
- Domain goc khong truy cap duoc API tenant.

Regression:

- Tenant login/logout/session khong doi.
- Tenant User API hien co khong doi.
- Control Center Administrative Unit Management van PASS.
- Control Center Phase 1/2 tests van PASS.

## 19. Production Impact

Du kien LOW neu khong migration.

- Tenant Portal khong thay doi.
- Business Modules khong thay doi.
- API tenant khong thay doi.
- Database schema khong thay doi.
- Write actions duoc guard va audit.

Rui ro:

- Role mapping giua platform role va source role hien co can chot truoc implementation.
- `App\Models\User` hien phu thuoc TenantContext, can tranh dung sai trong Control Center.
- Khong duoc vo tinh expose password hash/token/session data.

## 20. Future Extension

Feature nay tao nen cho:

- Permission System.
- Role Management.
- Scope Management.
- SSO.
- Audit nang cao.
- `LOCKED` va `PENDING` sau khi Permission, Role, Scope va SSO hoan thanh.

Khong trien khai cac muc tren trong Feature nay.
