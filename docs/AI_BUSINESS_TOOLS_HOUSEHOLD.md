# AI Business Tools - HouseholdTool

## Pham vi

Day la buoc dau cua Epic 6, chi trien khai **HouseholdTool** read-only.

Tool nay cho phep:

- Liet ke ho dan qua contract `paginate`.
- Tim ho dan theo `id` qua contract `find`.
- Tim ho dan theo ma ho qua contract `findByCode`.

Khong co create, update, delete, import, export hay thao tac ghi du lieu.

## Thiet ke

- `Ai\Business\HouseholdTool` implements `PermissionAwareAiToolInterface`.
- Tool nhan repository/model qua constructor de tai su dung `App\Models\Household` khi tich hop runtime.
- Runtime registry mac dinh dang ky tool qua `Ai\Core\AiRuntimeFactory`.
- Tool khong tu khoi tao database.
- Tool yeu cau permission `household:read`.
- `pageSize` duoc gioi han toi da 50 de tranh truy van qua lon.

## Action

```php
['action' => 'list', 'search' => 'H09', 'page' => 1, 'pageSize' => 20]
['action' => 'find', 'id' => 1]
['action' => 'find_by_code', 'code' => 'H09-0001']
```

## Kiem thu

```bash
npm.cmd run test:ai-household-tool
```

Test dung fake repository, khong ket noi database.

## Rui ro

- Runtime registry va endpoint execute tool da co; orchestration hoi dap ngon ngu tu nhien se lam o buoc sau.
- Ket qua phu thuoc contract cua `App\Models\Household`.
- ResidentTool da co rieng cho cau hoi ve nhan khau/thanh vien.

## Rollback

Revert commit HouseholdTool de go bo:

- `ai/src/Business/HouseholdTool.php`.
- `tests/ai-household-tool-smoke.php`.
- `docs/AI_BUSINESS_TOOLS_HOUSEHOLD.md`.
- Script npm tuong ung.

Khong can rollback database vi khong co migration va tool read-only.
