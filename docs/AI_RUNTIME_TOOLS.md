# AI Runtime Tools - Epic 6

## Pham vi

Buoc nay noi cac tool nghiep vu read-only cua Epic 6 vao runtime registry mac dinh.

Registry mac dinh gom:

- `household`: tra cuu ho dan qua `App\Models\Household`.
- `resident`: tra cuu nhan khau qua `App\Models\Citizen`.
- `statistics`: tra cuu thong ke qua `App\Models\Dashboard`, fallback `App\Models\PopulationStatistics` neu can.
- `insight`: hoi cac insight van hanh read-only qua `App\Models\SystemInsight`.

Khong co tool ghi du lieu, khong tu goi AI ben ngoai, khong tu thuc thi khi khoi tao registry.

## API

```http
GET /api/ai/tools
POST /api/ai/tools/execute
```

Payload execute:

```json
{
  "tool": "household",
  "input": {
    "action": "find_by_code",
    "code": "H09-0001"
  }
}
```

Endpoint execute:

- Chi cho phep tool read-only.
- Lay module/action tu `PermissionAwareAiToolInterface`.
- Goi `requirePermission(module, action)` truoc khi execute.
- Chuyen exception cua tool thanh loi API an toan qua `ToolExecutor`.
- Ghi audit action `tool_execute_readonly`.

## Thiet ke

- `Ai\Core\AiRuntimeFactory::toolRegistry()` tao `ToolRegistry` mac dinh.
- Factory cho phep inject repository khi test hoac khi runtime muon dung adapter rieng.
- Neu model App chua co trong autoloader, factory bo qua model do thay vi lam crash bootstrap doc lap cua `/ai`.
- Cac tool van duoc `ToolExecutor` va `ToolPermissionChecker` kiem tra quyen theo module/action.
- RBAC them module `statistics` de `StatisticsTool` dung dung permission `statistics:read`.
- `InsightTool` tu kiem tra source module permissions do `SystemInsight::requiredModulesForQuestion()` tra ve.

## Kiem thu

```bash
npm.cmd run test:ai-epic6
```

Lenh tren chay:

- `test:ai-household-tool`
- `test:ai-resident-tool`
- `test:ai-statistics-tool`
- `test:ai-runtime-tools`
- `test:ai-tool-api`

## Rui ro

- Runtime registry va endpoint execute tool da co; orchestration hoi dap ngon ngu tu nhien se thuoc buoc sau.
- `statistics` uu tien `Dashboard` vi contract `summary` nam o model nay.
- Cac tool tiep tuc read-only; thao tac ghi phai co epic rieng va approval rieng.

## Rollback

Revert cac file:

- `ai/src/Core/AiRuntimeFactory.php`
- `tests/ai-runtime-tools-smoke.php`
- `tests/ai-tool-api.test.js`
- `docs/AI_RUNTIME_TOOLS.md`
- Script `test:ai-runtime-tools`, `test:ai-tool-api` va `test:ai-epic6` trong `package.json`.
- Route `/api/ai/tools*` va controller `App\Controllers\AiToolController`.
