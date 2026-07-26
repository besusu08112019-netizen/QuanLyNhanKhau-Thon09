# AI Tool Orchestration - Epic 7

## Pham vi

Epic 7 them orchestration read-only cho cac business tool da co o Epic 6.

Thanh phan moi:

- `Ai\Orchestration\ToolOrchestrator`: nhan cau hoi tu nhien, lap ke hoach tool/input, execute qua `ToolExecutor`.
- `POST /api/ai/ask`: endpoint hoi dap read-only dua tren tool registry.

Khong goi dich vu AI ben ngoai, khong sinh SQL tu input nguoi dung, khong goi create/update/delete.

## Mapping hien tai

- Ma ho `H09-0001` -> `household.find_by_code`.
- CCCD/so dinh danh kem ngu canh nhan khau -> `resident.find_by_identity`.
- BHYT/bao hiem y te -> `statistics.health_insurance`.
- Thong ke/bao cao/tong hop/dashboard -> `statistics.summary`.
- Tong so/so luong/bao nhieu -> `statistics.counts`.
- Tren 80/cao tuoi -> `resident.list` voi `ageFrom=80`.
- Nhan khau/cong dan/cu dan -> `resident.list`.
- Ho dan/ho gia dinh -> `household.list`.
- Ho chua dong quy, phan anh chua xu ly, bao tri, vat nuoi, bien dong thang nay -> `insight.ask`.

Neu khong xac dinh duoc intent, orchestrator tra `needs_clarification`.

## API

```http
POST /api/ai/ask
```

Request:

```json
{
  "question": "Tim ho dan H09-0001"
}
```

Response:

```json
{
  "status": "answered",
  "mode": "READ_ONLY",
  "plan": {
    "tool": "household",
    "input": {
      "action": "find_by_code",
      "code": "H09-0001"
    }
  },
  "result": {
    "ok": true
  }
}
```

## Bao mat

- Endpoint yeu cau `dashboard:read` truoc khi orchestration.
- Tool executor tiep tuc enforce permission theo module/action.
- Context quyen chi cap read theo RBAC cho cac module lookup/thong ke/van hanh: `household`, `citizen`, `statistics`, `dashboard`, `contributions`, `complaints`, `public_assets`, `livestock`, `movement`.
- Moi cau hoi qua endpoint duoc audit voi action `tool_orchestrate_readonly`.

## Kiem thu

```bash
npm.cmd run test:ai-epic7
```

Lenh tren chay:

- `test:ai-tool-orchestrator`
- `test:ai-tool-api`

## Rui ro

- Planner hien tai rule-based, chi bao phu cac cau hoi pho bien cua Epic 7.
- Ket qua phu thuoc cac business tool read-only cua Epic 6.
- UI speech/conversation va Operation Center da goi `/api/ai/ask`; cac buoc sau co the mo rong chat UX va render ket qua chi tiet hon.

## Rollback

Revert cac file:

- `ai/src/Orchestration/ToolOrchestrator.php`
- `tests/ai-tool-orchestrator-smoke.php`
- `docs/AI_TOOL_ORCHESTRATION.md`
- Route `POST /api/ai/ask`.
- Phan `ask()` trong `App\Controllers\AiToolController`.
- Script `test:ai-tool-orchestrator` va `test:ai-epic7`.
