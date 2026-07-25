# AI Tool Framework - Epic 5

## Pham vi

Epic 5 them framework de dang ky va thuc thi AI Tool mot cach co kiem soat:

- Tool Interface.
- Tool Registry.
- Tool Executor.
- Permission Checker.

Chua tao tool nghiep vu nhu HouseholdTool, ResidentTool, GIS Tool hay ReportTool. Chua thao tac database va chua thuc hien lenh nguoi dung tren du lieu he thong.

## Thiet ke

- `Ai\Contracts\AiToolInterface`: interface co san tu Epic 1, giu tuong thich.
- `Ai\Contracts\PermissionAwareAiToolInterface`: optional metadata cho module/action/read-only.
- `Ai\Core\ToolRegistry`: dang ky, tra cuu, liet ke va mo ta tool.
- `Ai\Tools\ToolPermissionChecker`: kiem tra role ADMIN/SUPER_ADMIN hoac permission theo context.
- `Ai\Tools\ToolExecutor`: chuan hoa ket qua, chan tool khong ton tai, chan permission denied, boc loi exception.
- `Ai\Tools\ToolExecutionResult`: result object co `ok`, `tool`, `data`, `error`, `meta`.
- `Ai\Tools\NullTool`: tool framework-only dung cho test wiring, khong phai tool nghiep vu.

## Mau context permission

```php
[
    'role' => 'ADMIN',
    'permissions' => [
        'ai_framework' => ['read' => true],
    ],
]
```

## Nguyen tac an toan

- Executor khong tu dong tim hay khoi tao tool.
- Tool phai duoc register ro rang trong registry.
- Permission denied tra ve result loi, khong throw ra UI.
- Exception tu tool duoc boc thanh `tool_execution_failed` va khong lo stack trace.

## Kiem thu

```bash
npm.cmd run test:ai-tools
```

## Rui ro

- Permission context hien la array truyen vao; Epic sau can map tu user/session thuc te.
- Chua co business tool nen framework moi duoc test bang `NullTool`.
- Chua co audit log thuc thi tool; se bo sung khi bat dau tool nghiep vu.

## Rollback

Revert commit Epic 5 de go bo:

- `Ai\Contracts\PermissionAwareAiToolInterface`.
- `Ai\Tools/*`.
- Phan mo rong `Ai\Core\ToolRegistry`.
- Test va tai lieu Epic 5.

Khong can rollback database vi Epic 5 khong co migration.

