# AI Conversation Manager - Epic 4

## Pham vi

Epic 4 them quan ly hoi thoai doc lap:

- Luu lich su hoi thoai.
- Ghi nho ngu canh ngan han.
- Tao cau hoi bo sung khi thieu thong tin.
- Quan ly pending clarification.

Khong goi AI, khong goi backend tu frontend, khong chay tool nghiep vu, khong thao tac du lieu he thong.

## Thiet ke backend

- `Ai\Core\ConversationManager`: luu history, memory, pending clarification va reset theo conversation id.
- `Ai\Conversation\ClarificationManager`: xac dinh field thieu va sinh cau hoi bo sung.
- `Ai\Conversation\ConversationOrchestrator`: ket hop Intent Recognition voi Conversation Manager.

Tat ca dang chay trong bo nho process. Chua co persistence database.

## Thiet ke frontend

- `assets/js/ai-conversation.js`: lang nghe `tenant:ai-intent-recognized`.
- Luu history ngan han vao `localStorage` theo tenant key.
- Hien thi 6 tin gan nhat trong panel Speech.
- Phat `tenant:ai-conversation-clarification` khi can hoi lai.
- Khong dispatch action va khong navigation.

## Kiem thu

```bash
npm.cmd run test:ai-conversation
```

## Rui ro

- History frontend luu trong localStorage nen chi phu hop ngu canh ngan han.
- Backend memory la in-memory, chua chia se giua request/process.
- Cau hoi bo sung con rule-based; hoi thoai phuc tap se duoc xu ly tiep o cac Epic sau.

## Rollback

Revert commit Epic 4 de go bo:

- `ai/src/Conversation`.
- Phan mo rong `Ai\Core\ConversationManager`.
- `assets/js/ai-conversation.js` va file minified.
- UI/CSS conversation log trong panel Speech.
- Test va tai lieu Epic 4.

Khong can rollback database vi Epic 4 khong co migration.

