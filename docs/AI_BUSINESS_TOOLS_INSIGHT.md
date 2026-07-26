# AI Business Tools - InsightTool

## Pham vi

Buoc nay them **InsightTool** read-only de tai su dung `App\Models\SystemInsight` trong AI tool registry.

Tool nay cho phep:

- Hoi cac cau hoi van hanh da duoc map san trong `SystemInsight`.
- Tra ve answer, intent, metrics va items theo contract hien co.
- Enforce read permission cho cac module nguon do `requiredModulesForQuestion()` tra ve.

Khong co create, update, delete, import, export hay sinh SQL tu input.

## Thiet ke

- `Ai\Business\InsightTool` implements `PermissionAwareAiToolInterface`.
- Permission metadata chinh la `dashboard:read`.
- Truoc khi goi `ask`, tool kiem tra source modules trong context permissions neu repository co `requiredModulesForQuestion`.
- Runtime registry mac dinh dang ky tool qua `Ai\Core\AiRuntimeFactory`.

## Action

```php
['action' => 'ask', 'question' => 'Co bao nhieu phan anh chua xu ly?']
['action' => 'ask', 'question' => 'Ho nao chua dong quy?']
['action' => 'ask', 'question' => 'Cong trinh nao sap bao tri?']
```

## Kiem thu

```bash
npm.cmd run test:ai-runtime-tools
npm.cmd run test:ai-epic7
```

Test dung fake repository, khong ket noi database.

## Rui ro

- Ket qua phu thuoc contract cua `App\Models\SystemInsight`.
- Loi thieu source permission duoc ToolExecutor boc thanh `tool_execution_failed`.
- Cac intent moi can them trong `SystemInsight` hoac planner orchestration.

## Rollback

Revert:

- `ai/src/Business/InsightTool.php`.
- Phan dang ky `insight` trong `AiRuntimeFactory`.
- Mapping `insight` trong `ToolOrchestrator`.
- Test/doc lien quan.
