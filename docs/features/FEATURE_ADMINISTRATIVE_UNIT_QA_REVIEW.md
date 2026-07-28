# QA Review: Administrative Unit Management

Ngay review: 2026-07-28

Release: Release 4

Epic: Community Control Center

Feature: Administrative Unit Management

Ket qua: PASS

## Scope

PASS.

Da bao gom:

- Danh sach don vi.
- Them don vi.
- Sua don vi.
- Khoa don vi.
- Kich hoat don vi.
- Domain.
- Logo.
- Trang thai.
- Health Status.

Khong bao gom va khong bi trien khai lan:

- User Management.
- Permission System day du.
- Role System.
- SSO.
- Dashboard tong nang cao.
- Monitoring nang cao.
- Notification.
- AI.
- Business Modules.

## UI

PASS.

- Control Center co toolbar cho danh sach don vi.
- Co tim kiem theo ma/ten/domain.
- Co loc trang thai.
- Co nut them don vi.
- Co modal form them/sua.
- Co nut sua, khoa, kich hoat theo tung dong.
- Khong dung Tenant layout.

## UX

PASS.

- Form focus vao truong phu hop khi mo modal.
- Khoa/kich hoat co confirm truoc khi goi API.
- Sau khi luu hoac doi trang thai, danh sach duoc tai lai.
- Loi API hien trong form hoac alert cua section.

## Validation

PASS.

- UI validate toi thieu `code` va `name`.
- Backend validate bat buoc:
  - `code`
  - `name`
  - `type`
  - `status`
  - `domain`
  - `subdomain`
  - `logo`
- Backend kiem tra unique cho code/domain/subdomain.

## Business Flow

PASS.

- List: hien metadata quan tri, khong hien du lieu ca nhan.
- Create: yeu cau authorization, validate, insert, audit.
- Update: yeu cau authorization, validate, update, audit.
- Lock: chi cho ACTIVE -> INACTIVE, audit.
- Activate: chi cho non-ACTIVE -> ACTIVE, audit.
- Khong xoa vat ly.

## Error Handling

PASS.

- 401/403/419 tra ve JSON error tu guard.
- 422 tra ve validation error.
- 404 tra ve khi khong tim thay don vi.
- UI hien loi khi save/status action that bai.

## Empty State

PASS.

- Bang don vi hien `Chua co du lieu hien thi` khi khong co item.
- Local DB unavailable fallback ve empty page de Control Center shell khong crash.

## Loading State

PASS.

- Bang don vi hien `Dang tai du lieu...` khi load.
- Nut save bi disable trong luc submit.

## Permission QA

PASS.

- Write API khong token bi tu choi.
- Write API yeu cau CSRF.
- Guard tam thoi chi chap nhan `SUPER_ADMIN` map thanh `SYSTEM_ADMIN`.
- Feature khong tu tao User/Role/Permission UI.

## Regression QA

PASS.

- Tenant Portal van load login UI tren tenant domain.
- Domain goc khong truy cap duoc API tenant.
- Tenant domain khong truy cap duoc API Control Center.
- Control Center Phase 1/Phase 2 smoke tests van PASS.

## Test Evidence

PASS.

- `node tests\administrative-unit-management.test.js`
- `php tests\control-center-authorization.test.php`
- `php tests\portal-context.test.php`
- `node tests\control-center-phase2.test.js`
- `node tests\control-center-phase1.test.js`

## Recommendation

PASS.

Co the tiep tuc Production Review va Merge Checklist.
