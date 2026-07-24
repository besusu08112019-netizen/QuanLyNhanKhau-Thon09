# Giai đoạn 2 - Module Văn bản

Ngày thực hiện: 2026-07-24

## Đã triển khai

- Module `documents` cho quản lý văn bản điều hành.
- Danh mục văn bản:
  - Thông báo
  - Quyết định
  - Công văn
  - Kế hoạch
  - Báo cáo
  - Biên bản
- Database:
  - `document_categories`
  - `village_documents`
  - `village_document_attachments`
- API:
  - `GET /api/documents`
  - `POST /api/documents`
  - `GET /api/documents/dashboard`
  - `GET /api/documents/catalogs`
  - `GET /api/documents/report`
  - `GET /api/documents/export-excel`
  - `GET /api/documents/export-pdf`
  - `POST /api/documents/{id}/attachments`
  - `GET /api/documents/{id}/attachments/{fileId}/preview`
  - `GET /api/documents/{id}/attachments/{fileId}/download`
  - `DELETE /api/documents/{id}/attachments/{fileId}`
  - `GET /api/documents/{id}`
  - `PUT /api/documents/{id}`
  - `DELETE /api/documents/{id}`
- UI `/documents`:
  - Dashboard KPI.
  - Tìm kiếm theo mã, số văn bản, tiêu đề, người ký.
  - Lọc theo loại, trạng thái, địa bàn, khoảng ngày.
  - Sắp xếp, phân trang server-side.
  - Tạo, sửa, xem, xóa theo quyền.
  - Upload/preview/download file PDF/tài liệu.
  - Export Excel/PDF.
- RBAC module `documents`.
- Audit log cho thêm, sửa, xóa, upload, xóa file và export.

## Migration

- `database/migrations/20260724_150000_create_village_documents.sql`
