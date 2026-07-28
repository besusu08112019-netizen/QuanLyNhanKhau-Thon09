# QA Review: User Management

Ngay review: 2026-07-28

Release: Release 5

Epic: Community Control Center

Feature: User Management

Ket qua: PASS

## Mission First

PASS.

Feature giup quan tri he thong tao/sua/vo hieu hoa/reset mat khau tai khoan ma khong thao tac database truc tiep. Viec nay giam loi van hanh, giam thoi gian xu ly va giu audit.

## Scope

PASS.

Da bao gom:

- Danh sach tai khoan.
- Them tai khoan.
- Sua tai khoan.
- Vo hieu hoa/kich hoat.
- Reset mat khau.
- Don vi.
- Vai tro theo mapping hien co.
- Trang thai `ACTIVE/INACTIVE`.
- Last login, IP cuoi, thiet bi cuoi, thoi gian tao, nguoi tao.
- Tim kiem nhieu tieu chi.

Khong bao gom:

- Delete account.
- Permission System.
- Role System day du.
- Scope Management.
- SSO.
- Migration database.

## UI / UX

PASS.

- Cot dau tien la Ten, khong phai Email.
- Email hien phu ben duoi ten.
- Vai tro dung nhan de hieu.
- Don vi chon tu Administrative Unit.
- Tai khoan chua dang nhap hien `Chua dang nhap`.
- Reset mat khau dung modal rieng.
- Khong co nut xoa tai khoan.
- Username duoc goi y tu email/ho ten de giam nhap lieu.

## Validation

PASS.

- Username format 3-60 ky tu.
- Email dung format.
- Ho ten bat buoc.
- Password toi thieu 8 ky tu khi tao/reset.
- Status chi `ACTIVE/INACTIVE`.
- `COMMUNE_ADMIN` bi disabled tren UI va bi tu choi backend.
- Don vi bat buoc.

## Business Flow

PASS.

- Create: validate -> create -> audit.
- Update: validate -> update -> audit.
- Deactivate: guard -> status `INACTIVE` -> revoke sessions -> audit.
- Activate: guard -> status `ACTIVE` -> audit.
- Reset password: guard -> update password hash -> revoke sessions -> audit.

## Error / Empty / Loading State

PASS.

- List co loading row.
- Empty state khi khong co data.
- Alert section khi API loi.
- Form error hien trong modal.
- Submit button disabled khi dang xu ly.

## Regression

PASS.

- Administrative Unit Management smoke test PASS.
- Control Center Phase 1/2 smoke tests PASS.
- PortalContext test PASS.
- Domain goc van chan API tenant.
- Tenant domain khong truy cap API Control Center users.

## Recommendation

PASS.

Co the tiep tuc Production Review.
