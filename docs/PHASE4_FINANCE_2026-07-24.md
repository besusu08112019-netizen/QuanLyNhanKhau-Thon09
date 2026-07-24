# Phase 4 - Finance Module

## Scope

- Added a general finance module for village fund management, income vouchers, expense vouchers, attachments, dashboard metrics, and PDF/Excel reports.
- Kept the existing household contribution module unchanged. Contributions remain for household collection campaigns; finance records the general ledger.

## Database

- `finance_funds`
- `finance_categories`
- `finance_transactions`
- `finance_transaction_attachments`

The schema uses indexed search/filter columns and stores attachments separately for lazy loading.

## API

- `GET /api/finance`
- `POST /api/finance`
- `GET /api/finance/dashboard`
- `GET /api/finance/catalogs`
- `GET /api/finance/report`
- `GET /api/finance/export-excel`
- `GET /api/finance/export-pdf`
- `GET /api/finance/{id}`
- `PUT /api/finance/{id}`
- `DELETE /api/finance/{id}`
- Attachment upload, preview, download, and delete endpoints under `/api/finance/{id}/attachments`.

## Security

- Backend permission scope: `finance`.
- Frontend permission checks mirror backend actions.
- Uploads use `FileStorageService` validation, MIME allow-listing, safe path streaming, and audit logs.

## QA Checklist

- Create income voucher.
- Create expense voucher.
- Upload PDF/image/document proof.
- Open detail and preview/download proof.
- Filter by type, fund, category, status, and date range.
- Export Excel and PDF.
- Verify no regression in existing contribution workflows.
