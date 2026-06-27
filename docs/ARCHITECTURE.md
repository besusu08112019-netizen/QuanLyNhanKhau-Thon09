# Architecture

He thong ap dung Clean Architecture trong gioi han runtime Google Apps Script.

## Domain

`src/server/domain` dinh nghia schema, enum, validation va chuan hoa entity. Lop nay khong phu thuoc Google Sheets hay giao dien.

## Application

`src/server/application` chua use case nghiep vu:

- Quan ly ho
- Quan ly nhan khau
- Quan ly bien dong
- Bao cao
- Xuat PDF
- Sao luu
- Phan quyen
- Nguoi dung
- Logs

## Infrastructure

`src/server/infrastructure` trien khai Google Sheets repository, LockService va cac dich vu nen tang Google Workspace.

## Interface

`src/server/interface` gom WebApp entrypoint va API controller. Client chi goi `api(action, payload)`, server chiu trach nhiem kiem tra quyen, goi use case va ghi log.

## Data Flow

1. Nguoi dung thao tac tren HtmlService WebApp.
2. Client goi `google.script.run.api(action, payload)`.
3. API controller xac thuc quyen.
4. Use case xu ly nghiep vu.
5. Repository doc/ghi Google Sheets.
6. Logger ghi audit trail vao bang `logs`.
