# Deployment

## Thong tin Apps Script

- Script ID: `10g6OZEUePpkorwHPtJ9BS2Jc9UcyKjhVJqqAsVyPLCHZIbwU8o5_T_3B`
- Runtime: V8
- Timezone: Asia/Ho_Chi_Minh

## Cau hinh clasp

`.clasp.json` tro toi Script ID production. `.claspignore` chi day manifest va ma nguon Apps Script len Google.

## Khoi tao database

Sau khi `clasp push`, vao Apps Script Editor va chay `setup()` mot lan. Ham nay:

- Tao Google Sheets database neu chua co.
- Tao day du cac bang theo schema.
- Seed quyen mac dinh cho ADMIN, OFFICER, VIEWER.
- Tao tai khoan SUPER_ADMIN dau tien tu email nguoi chay.

## Deploy WebApp

Deploy dang WebApp. Tai khoan deploy can co quyen truy cap Spreadsheet va Drive folder do ung dung tao ra.

## Backup

Module Backup tao ban sao Spreadsheet tren Google Drive va ghi metadata vao bang `backups`.

## PDF

Module PDF tao file tu template HtmlService va luu vao Drive folder production.
