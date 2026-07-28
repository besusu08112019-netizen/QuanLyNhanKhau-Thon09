# Product Backlog: Hong Phong Community Platform

Ngay tao: 2026-07-28

Muc dich: theo doi phat trien san pham theo Release -> Epic -> Feature, uu tien Mission First va Product First.

Trang thai hop le:

- `Planned`: da nam trong backlog, chua san sang implementation.
- `Ready`: da du dieu kien de bat dau Blueprint/Implementation theo quy trinh Feature.
- `In Progress`: dang trien khai.
- `Review`: dang Code Review/QA Review/Production Review/Merge Checklist.
- `Done`: da merge, tag va dong Feature.

## Release 1: Foundation

Epic: Product and Architecture Foundation

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| Product Vision | Xac dinh su menh dai han cua nen tang quan ly cong dong. | High | None | Done |
| Master Development Charter | Chuan hoa nguyen tac phat trien, Git workflow va Production First. | High | Product Vision | Done |
| Architecture Design | Khoa kien truc Core, Portal, Tenant, Business Modules. | High | Product Vision | Done |
| Architecture Review | Xac nhan kien truc khong mau thuan va co the mo rong. | High | Architecture Design | Done |
| Architecture Freeze | Dong bang quyet dinh kien truc, thay doi sau nay qua ADR. | High | Architecture Review | Done |
| Development Ready | Xac nhan du an san sang vao Product Development. | High | Architecture Freeze | Done |
| Feature Template | Tao chuan Blueprint/Review cho Feature. | Medium | Development Ready | Done |

## Release 2: Portal Foundation

Epic: Application Core and Portal Boundary

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| PortalContext Foundation | Phan biet Control Center va Tenant Portal theo request context. | High | Architecture Freeze | Done |
| Routing theo Portal | Dam bao domain goc vao Control Center, subdomain vao Tenant Portal. | High | PortalContext Foundation | Done |
| Tenant API Boundary | Chan domain goc truy cap nham API tenant. | High | Routing theo Portal | Done |
| Control Center Shell | Tao shell quan tri doc lap, khong load module tenant. | High | PortalContext Foundation | Done |
| Tenant Backward Compatibility | Dam bao Tenant Portal van hoat dong nhu production hien tai. | High | Routing theo Portal | Done |

## Release 3: Community Control Center Foundation

Epic: Control Center Read-only Foundation

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| Control Center Layout | Tao layout rieng cho Control Center. | High | Control Center Shell | Done |
| Dashboard Tong Read-only | Hien so lieu tong hop, khong hien du lieu ca nhan. | High | Control Center Layout | Done |
| Administrative Unit Read-only | Hien danh sach don vi dang quan ly o dang tong quan. | High | Control Center Layout | Done |
| System Accounts Read-only | Hien tong quan vai tro/tai khoan he thong. | Medium | Control Center Layout | Done |
| Basic Monitoring Read-only | Hien runtime, database, storage va health co ban. | Medium | Control Center Layout | Done |

## Release 4: Administrative Unit Management

Epic: Community Control Center

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| Administrative Unit Management | Quan ly danh sach, them, sua, khoa/kich hoat don vi, domain, logo, trang thai va health status. | Critical | Control Center Foundation | Done |

## Release 5: User Management

Epic: Community Control Center

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| User Management | Quan ly tai khoan he thong tap trung de can bo van hanh khong phai thao tac truc tiep database. | Critical | Administrative Unit Management | Ready |

## Release 6: Permission and Scope

Epic: Community Control Center

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| Permission Management | Quan ly quyen theo module/action thay vi hard-code. | High | User Management | Planned |
| Role Management | Quan ly nhom vai tro he thong theo chuan platform role. | High | Permission Management | Planned |
| Scope Management | Gioi han quyen theo don vi hanh chinh. | High | Administrative Unit Management, Role Management | Planned |

## Release 7: SSO

Epic: Cross Portal Access

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| Control Center to Tenant SSO | Cho SYSTEM_ADMIN chuyen sang Tenant khong dang nhap lai, dung ticket mot lan. | High | User Management, Scope Management | Planned |
| SSO Audit Trail | Ghi nhan nguoi thuc hien goc khi chuyen portal. | High | Control Center to Tenant SSO | Planned |

## Release 8: Aggregate Dashboard and Reports

Epic: Community Control Center

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| Advanced Aggregate Dashboard | Theo doi tong ho, nhan khau, lao dong, BHYT, Dang vien theo don vi. | High | Administrative Unit Management | Planned |
| System-wide Reports | Xuat bao cao tong hop theo don vi, khong lo du lieu ca nhan ngoai pham vi. | High | Advanced Aggregate Dashboard, Permission Management | Planned |
| Administrative Unit Comparison | So sanh chi so tong hop giua cac don vi. | Medium | Advanced Aggregate Dashboard | Planned |

## Release 9: Monitoring and Operations

Epic: System Operations

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| Advanced Monitoring | Giam sat runtime, database, storage, queue va loi he thong. | Medium | Basic Monitoring Read-only | Planned |
| Audit Management | Tra cuu audit theo portal, user, don vi, action va resource. | High | User Management, Permission Management | Planned |
| Backup Management | Quan ly backup, download, lich su va trang thai sao luu. | Medium | Permission Management | Planned |

## Release 10: Notification

Epic: Communication and Automation

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| Notification Center | Gui/thong bao noi bo cho can bo theo don vi va vai tro. | Medium | User Management, Permission Management | Planned |
| Event Consumer | Subscribe EventBus de tao thong bao tu su kien he thong. | Medium | Notification Center | Planned |
| Feature Flags Management | Bat/tat module theo system/don vi ma khong hard-code. | Medium | Administrative Unit Management, Permission Management | Planned |

## Release 11: Field Work and Public Services

Epic: Community Services

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| Citizen Portal | Cho nguoi dan gui yeu cau cap nhat thong tin, khong ghi truc tiep vao du lieu goc. | Medium | Permission Management, Audit Management | Planned |
| Mobile Field App | Ho tro can bo tra cuu ho/nhan khau/GIS khi di hien truong. | Medium | Public API, Permission Management | Planned |
| Request Workflow | Tiep nhan, phan cong, xu ly va dong yeu cau tu nguoi dan. | Medium | Citizen Portal, User Management | Planned |

## Release 12: AI and Analytics

Epic: Intelligence and Insights

| Feature | Muc tieu | Uu tien | Phu thuoc | Trang thai |
| --- | --- | --- | --- | --- |
| Analytics | Phan tich xu huong dan cu, lao dong, an sinh va bao cao dieu hanh. | Medium | System-wide Reports | Planned |
| AI Assistant | Ho tro can bo tim kiem, tong hop va goi y thao tac tren du lieu duoc phep. | Low | Permission Management, Audit Management, Analytics | Planned |
| Data Quality Assistant | Phat hien du lieu thieu, sai dinh dang, trung lap va goi y lam sach. | Medium | Analytics, Audit Management | Planned |

## Current Focus

- Feature vua dong: `Administrative Unit Management` = Done.
- Feature tiep theo: `User Management` = Ready.
- Chua bat dau implementation `User Management`.

## Product Priority Rule

Moi Feature moi phai tra loi Mission First, Product First va Community First truoc khi code:

- Giup ai?
- Giam thao tac nao?
- Giam sai sot nao?
- Co tan dung du lieu hien co khong?
- Neu bo Feature nay thi cong viec cua nguoi dung co kho hon khong?

Neu cau tra loi chua ro, Feature phai quay lai Blueprint/Review truoc khi implementation.
