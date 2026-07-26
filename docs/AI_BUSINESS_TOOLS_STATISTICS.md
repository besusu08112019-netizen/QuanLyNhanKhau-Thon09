# AI Business Tools - StatisticsTool

## Pham vi

Day la buoc tiep theo cua Epic 6, chi trien khai **StatisticsTool** read-only cho thong ke.

Tool nay cho phep:

- Lay tong so ho va nhan khau qua contract `counts`.
- Lay chi so thong ke tong hop qua contract `metrics`.
- Lay thong ke BHYT qua contract `healthInsuranceStats`.
- Lay dashboard summary qua contract `summary`.

Khong co create, update, delete, import, export hay thao tac ghi du lieu.

## Thiet ke

- `Ai\Business\StatisticsTool` implements `PermissionAwareAiToolInterface`.
- Tool nhan repository/model qua constructor de tai su dung `App\Models\PopulationStatistics` hoac `App\Models\Dashboard` khi tich hop runtime.
- Runtime registry mac dinh dang ky tool qua `Ai\Core\AiRuntimeFactory`.
- Tool khong tu khoi tao database.
- Tool yeu cau permission `statistics:read`.
- Input chi gom cac filter thong ke an toan.
- Filter ngay thang chi nhan dinh dang `YYYY-MM-DD`.

## Action

```php
['action' => 'counts']
['action' => 'metrics', 'gender' => 'Nam']
['action' => 'health_insurance']
['action' => 'summary', 'dateFrom' => '2026-01-01', 'dateTo' => '2026-07-25']
```

## Kiem thu

```bash
npm.cmd run test:ai-statistics-tool
```

Test dung fake repository, khong ket noi database.

## Rui ro

- Runtime registry va endpoint execute tool da co; orchestration hoi dap ngon ngu tu nhien se lam o buoc sau.
- Ket qua phu thuoc contract cua `App\Models\PopulationStatistics` hoac `App\Models\Dashboard`.
- `summary` co the ton tai chi tren Dashboard model, nen runtime can inject dung model cho action nay.

## Rollback

Revert commit StatisticsTool de go bo:

- `ai/src/Business/StatisticsTool.php`.
- `tests/ai-statistics-tool-smoke.php`.
- `docs/AI_BUSINESS_TOOLS_STATISTICS.md`.
- Script npm tuong ung.

Khong can rollback database vi khong co migration va tool read-only.
