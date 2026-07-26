# AI Business Tools - ResidentTool

## Pham vi

Day la buoc tiep theo cua Epic 6, chi trien khai **ResidentTool** read-only cho nhan khau.

Tool nay cho phep:

- Liet ke nhan khau qua contract `paginate`.
- Tim nhan khau theo `id` qua contract `find`.
- Tim nhan khau theo CCCD/so dinh danh qua contract `findByIdentity`.

Khong co create, update, delete, restore, import, export hay thao tac ghi du lieu.

## Thiet ke

- `Ai\Business\ResidentTool` implements `PermissionAwareAiToolInterface`.
- Tool nhan repository/model qua constructor de tai su dung `App\Models\Citizen` khi tich hop runtime.
- Runtime registry mac dinh dang ky tool qua `Ai\Core\AiRuntimeFactory`.
- Tool khong tu khoi tao database.
- Tool yeu cau permission `citizen:read`.
- `pageSize` duoc gioi han toi da 50.

## Action

```php
['action' => 'list', 'search' => 'Nguyen', 'householdCode' => 'H09-0001']
['action' => 'find', 'id' => 7]
['action' => 'find_by_identity', 'identity' => '036155013781']
```

## Kiem thu

```bash
npm.cmd run test:ai-resident-tool
```

Test dung fake repository, khong ket noi database.

## Rui ro

- Runtime registry va endpoint execute tool da co; orchestration hoi dap ngon ngu tu nhien se lam o buoc sau.
- Ket qua phu thuoc contract cua `App\Models\Citizen`.
- Chua co tool ghi du lieu; cac thao tac them/sua/xoa phai doi Epic rieng neu duoc phe duyet.

## Rollback

Revert commit ResidentTool de go bo:

- `ai/src/Business/ResidentTool.php`.
- `tests/ai-resident-tool-smoke.php`.
- `docs/AI_BUSINESS_TOOLS_RESIDENT.md`.
- Script npm tuong ung.

Khong can rollback database vi khong co migration va tool read-only.
