# Product Backlog: Hong Phong Community Platform

Ngay tao: 2026-07-28

Muc dich: theo doi phat trien san pham theo Release -> Epic -> Feature, uu tien Mission First va Product First.

Nhom uu tien hop le:

- `NOW`: Feature se trien khai ngay. Moi quyet dinh implementation chi dua tren nhom nay neu khong co yeu cau moi.
- `NEXT`: Feature trien khai sau khi nhom `NOW` hoan thanh.
- `LATER`: Feature chua can trien khai.

Trang thai hop le:

- `PLANNED`: da nam trong backlog, chua san sang implementation.
- `READY`: da du dieu kien de bat dau Blueprint/Implementation theo quy trinh Feature.
- `IN PROGRESS`: dang trien khai.
- `REVIEW`: dang Code Review/QA Review/Production Review/Merge Checklist.
- `DONE`: da merge, tag va dong Feature.

## Release 1: Foundation

Epic: Product and Architecture Foundation

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| Product Vision | Xac dinh su menh dai han cua nen tang quan ly cong dong. | NOW | High | None | DONE |
| Master Development Charter | Chuan hoa nguyen tac phat trien, Git workflow va Production First. | NOW | High | Product Vision | DONE |
| Architecture Design | Khoa kien truc Core, Portal, Tenant, Business Modules. | NOW | High | Product Vision | DONE |
| Architecture Review | Xac nhan kien truc khong mau thuan va co the mo rong. | NOW | High | Architecture Design | DONE |
| Architecture Freeze | Dong bang quyet dinh kien truc, thay doi sau nay qua ADR. | NOW | High | Architecture Review | DONE |
| Development Ready | Xac nhan du an san sang vao Product Development. | NOW | High | Architecture Freeze | DONE |
| Feature Template | Tao chuan Blueprint/Review cho Feature. | NOW | Medium | Development Ready | DONE |

## Release 2: Portal Foundation

Epic: Application Core and Portal Boundary

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| PortalContext Foundation | Phan biet Control Center va Tenant Portal theo request context. | NOW | High | Architecture Freeze | DONE |
| Routing theo Portal | Dam bao domain goc vao Control Center, subdomain vao Tenant Portal. | NOW | High | PortalContext Foundation | DONE |
| Tenant API Boundary | Chan domain goc truy cap nham API tenant. | NOW | High | Routing theo Portal | DONE |
| Control Center Shell | Tao shell quan tri doc lap, khong load module tenant. | NOW | High | PortalContext Foundation | DONE |
| Tenant Backward Compatibility | Dam bao Tenant Portal van hoat dong nhu production hien tai. | NOW | High | Routing theo Portal | DONE |

## Release 3: Community Control Center Foundation

Epic: Control Center Read-only Foundation

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| Control Center Layout | Tao layout rieng cho Control Center. | NOW | High | Control Center Shell | DONE |
| Dashboard Tong Read-only | Hien so lieu tong hop, khong hien du lieu ca nhan. | NOW | High | Control Center Layout | DONE |
| Administrative Unit Read-only | Hien danh sach don vi dang quan ly o dang tong quan. | NOW | High | Control Center Layout | DONE |
| System Accounts Read-only | Hien tong quan vai tro/tai khoan he thong. | NOW | Medium | Control Center Layout | DONE |
| Basic Monitoring Read-only | Hien runtime, database, storage va health co ban. | NOW | Medium | Control Center Layout | DONE |

## Release 4: Administrative Unit Management

Epic: Community Control Center

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| Administrative Unit Management | Quan ly danh sach, them, sua, khoa/kich hoat don vi, domain, logo, trang thai va health status. | NOW | Critical | Control Center Foundation | DONE |

## Release 5: User Management

Epic: Community Control Center

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| User Management | Quan ly tai khoan he thong tap trung de can bo van hanh khong phai thao tac truc tiep database. | NOW | Critical | Administrative Unit Management | DONE |

## Release 6: Permission and Scope

Epic: Community Control Center

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| Permission Management | Quan ly quyen theo module/action thay vi hard-code. | NEXT | High | User Management | READY |
| Role Management | Quan ly nhom vai tro he thong theo chuan platform role. | NEXT | High | Permission Management | PLANNED |
| Scope Management | Gioi han quyen theo don vi hanh chinh. | NEXT | High | Administrative Unit Management, Role Management | PLANNED |

## Release 7: SSO

Epic: Cross Portal Access

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| Control Center to Tenant SSO | Cho SYSTEM_ADMIN chuyen sang Tenant khong dang nhap lai, dung ticket mot lan. | NEXT | High | User Management, Scope Management | PLANNED |
| SSO Audit Trail | Ghi nhan nguoi thuc hien goc khi chuyen portal. | NEXT | High | Control Center to Tenant SSO | PLANNED |

## Release 8: Aggregate Dashboard and Reports

Epic: Community Control Center

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| Advanced Aggregate Dashboard | Theo doi tong ho, nhan khau, lao dong, BHYT, Dang vien theo don vi. | NEXT | High | Administrative Unit Management | PLANNED |
| System-wide Reports | Xuat bao cao tong hop theo don vi, khong lo du lieu ca nhan ngoai pham vi. | NEXT | High | Advanced Aggregate Dashboard, Permission Management | PLANNED |
| Administrative Unit Comparison | So sanh chi so tong hop giua cac don vi. | NEXT | Medium | Advanced Aggregate Dashboard | PLANNED |

## Release 9: Monitoring and Operations

Epic: System Operations

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| Advanced Monitoring | Giam sat runtime, database, storage, queue va loi he thong. | LATER | Medium | Basic Monitoring Read-only | PLANNED |
| Audit Management | Tra cuu audit theo portal, user, don vi, action va resource. | NEXT | High | User Management, Permission Management | PLANNED |
| Backup Management | Quan ly backup, download, lich su va trang thai sao luu. | LATER | Medium | Permission Management | PLANNED |

## Release 10: Notification

Epic: Communication and Automation

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| Notification Center | Gui/thong bao noi bo cho can bo theo don vi va vai tro. | LATER | Medium | User Management, Permission Management | PLANNED |
| Event Consumer | Subscribe EventBus de tao thong bao tu su kien he thong. | LATER | Medium | Notification Center | PLANNED |
| Feature Flags Management | Bat/tat module theo system/don vi ma khong hard-code. | LATER | Medium | Administrative Unit Management, Permission Management | PLANNED |

## Release 11: Field Work and Public Services

Epic: Community Services

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| Citizen Portal | Cho nguoi dan gui yeu cau cap nhat thong tin, khong ghi truc tiep vao du lieu goc. | LATER | Medium | Permission Management, Audit Management | PLANNED |
| Mobile Field App | Ho tro can bo tra cuu ho/nhan khau/GIS khi di hien truong. | LATER | Medium | Public API, Permission Management | PLANNED |
| Request Workflow | Tiep nhan, phan cong, xu ly va dong yeu cau tu nguoi dan. | LATER | Medium | Citizen Portal, User Management | PLANNED |

## Release 12: AI and Analytics

Epic: Intelligence and Insights

| Feature | Muc tieu | Nhom | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- | --- |
| Analytics | Phan tich xu huong dan cu, lao dong, an sinh va bao cao dieu hanh. | LATER | Medium | System-wide Reports | PLANNED |
| AI Assistant | Ho tro can bo tim kiem, tong hop va goi y thao tac tren du lieu duoc phep. | LATER | Low | Permission Management, Audit Management, Analytics | PLANNED |
| Data Quality Assistant | Phat hien du lieu thieu, sai dinh dang, trung lap va goi y lam sach. | LATER | Medium | Analytics, Audit Management | PLANNED |

## Current Focus

- Feature vua dong: `User Management` = `DONE`.
- Feature tiep theo: `Permission Management` = `NEXT` / `READY`.
- Chua bat dau implementation `Permission Management`.
- Feature `Permission Management` bat dau bang Product Review va Blueprint, khong code khi chua duoc phe duyet.

## Product Priority Rule

Moi Feature moi phai tra loi Mission First, Product First va Community First truoc khi code:

- Giup ai?
- Giam thao tac nao?
- Giam sai sot nao?
- Co tan dung du lieu hien co khong?
- Neu bo Feature nay thi cong viec cua nguoi dung co kho hon khong?

Neu cau tra loi chua ro, Feature phai quay lai Blueprint/Review truoc khi implementation.

## Delivery Priority Rule

- Chi trien khai Feature trong nhom `NOW`.
- Khong trien khai Feature trong nhom `NEXT` hoac `LATER` neu chua co yeu cau moi.
- Khi Feature `NOW` hoan thanh, chi cap nhat trang thai cua Feature ke tiep thay vi viet lai toan bo backlog.
