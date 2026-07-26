# AI Analytics - Epic 11

## Pham vi

Epic 11 them phan tich bat thuong va goi y xu ly dang read-only dua tren `SystemInsight`.

Thanh phan moi/thay doi:

- `App\Models\SystemInsight::analytics()`: tong hop bat thuong du lieu tu `smartAlerts()`.
- `GET /api/insights/analytics`: endpoint read-only cho analytics.
- `SystemInsight::ask()` va `ToolOrchestrator`: nhan cau hoi ve `bat thuong`, `canh bao`, `goi y xu ly`, `ho so thieu`.
- `tests/ai-analytics.test.js`: kiem tra route, permission, audit, analytics contract va khong co write SQL.

Khong them bang moi, khong ghi DB, khong tu dong sua ho so.

## Dau vao

Analytics hien tai tai su dung cac chi so `smartAlerts()`:

- CCCD/SĐD khong hop le.
- Trung CCCD/SĐD.
- Ho chua co thanh vien.
- Nhan khau thieu CCCD/SĐD.
- Nhan khau thieu so dien thoai.
- Ho thieu khu vuc/xom.

## API

```http
GET /api/insights/analytics
```

Response:

```json
{
  "mode": "READ_ONLY",
  "summary": "Phat hien ...",
  "metrics": {
    "total_alerts": 0,
    "high": 0,
    "medium": 0,
    "low": 0
  },
  "items": [],
  "suggestions": [],
  "generatedAt": "2026-07-26T00:00:00+07:00"
}
```

## Bao mat

- Endpoint yeu cau `dashboard:read`, `household:read`, `citizen:read`.
- Moi lan goi endpoint duoc audit voi action `analytics_readonly`.
- AI orchestration van di qua `InsightTool` va permission checker hien co.
- Khong co create/update/delete va khong sinh SQL tu input nguoi dung.

## Kiem thu

```bash
npm.cmd run test:ai-epic11
npm.cmd run test:ai-epic7
php -l app/Models/SystemInsight.php
php -l app/Controllers/InsightController.php
```

## Rui ro

- Day la rule-based analytics, chua phai mo hinh du doan.
- Chi bao phu cac bat thuong co san trong `smartAlerts()`.
- Ket qua phu thuoc chat luong va do day du cua du lieu hien co.

## Rollback

Revert cac file:

- `app/Models/SystemInsight.php`
- `app/Controllers/InsightController.php`
- route `/api/insights/analytics` trong `index.php`
- mapping analytics trong `ai/src/Orchestration/ToolOrchestrator.php`
- `tests/ai-analytics.test.js`
- `docs/AI_ANALYTICS.md`
- scripts `test:ai-analytics` va `test:ai-epic11` trong `package.json`
