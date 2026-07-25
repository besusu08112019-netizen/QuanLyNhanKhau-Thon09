# AI Foundation - Epic 1

## Pham vi

Epic 1 chi tao nen tang AI doc lap trong thu muc `/ai`. Nen tang nay chua ket noi vao UI, API nghiep vu, database, Speech, OCR hay dich vu AI ben ngoai.

## Kien truc

- `ai/bootstrap.php`: autoloader rieng cho namespace `Ai\\`.
- `ai/config/ai.php`: cau hinh mac dinh. AI bi tat mac dinh va tat tat ca feature flag.
- `Ai\Core\AiRouter`: diem vao noi bo cho cac phase sau.
- `Ai\Core\ContextManager`: tao context tu request va metadata.
- `Ai\Core\ConversationManager`: khung quan ly lich su hoi thoai trong bo nho.
- `Ai\Core\ToolRegistry`: dang ky va mo ta AI Tool.
- `Ai\Contracts\AiToolInterface`: interface bat buoc cho tool AI.
- `Ai\Logging\AiLogger`: logger JSONL co redact truong nhay cam.

## Nguyen tac an toan

- Khong goi API AI.
- Khong truy cap database.
- Khong thuc thi tool nghiep vu.
- Khong doc secret tu `.env`.
- Khong anh huong cac module dang chay neu `/ai/bootstrap.php` chua duoc require.

## Kiem thu

Chay smoke test:

```bash
php tests/ai-foundation-smoke.php
```

Chay build production:

```bash
npm.cmd run build:production
npm.cmd run validate:artifact
```

## Rui ro

- `/ai` chua duoc tich hop vao runtime nen cac Epic sau phai them API/UI rieng.
- `ConversationManager` hien dung bo nho tam, chua phu hop luu hoi thoai dai han.
- `ToolRegistry` moi dang ky tool trong process, chua co permission checker thuc thi.

## Rollback

Rollback Epic 1 bang cach revert commit chua:

- Thu muc `/ai`.
- `docs/AI_FOUNDATION.md`.
- `tests/ai-foundation-smoke.php`.
- Thay doi artifact build neu co.

Vi Epic 1 chua duoc require trong runtime chinh, rollback khong can migration database.
