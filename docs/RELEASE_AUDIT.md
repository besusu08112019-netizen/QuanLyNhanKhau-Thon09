# Release Audit

Ngay audit: 2026-07-26

Ket luan: PASS

## 1. Git status

Working tree hien chi co cac file du kien cho thay doi AI UI va bao cao:

- `views/app.php`
- `index.php`
- `.htaccess`
- `assets/css/app.css`
- `assets/css/app.min.css`
- `assets/js/ai-speech.js`
- `assets/js/ai-speech.min.js`
- `assets/js/ai-conversation.js`
- `assets/js/ai-conversation.min.js`
- `tests/ai-speech.test.js`
- `tests/ai-conversation.test.js`
- `tests/navigation-cleanup.test.js`
- `docs/AI_UI_ORCHESTRATION.md`
- `docs/PRODUCTION_READINESS.md`
- `docs/RELEASE_AUDIT.md`

Khong co file tam/debug moi du kien commit.

## 2. Source code

Da kiem tra pham vi thay doi:

- Khong co `TODO`, `FIXME`, `HACK`.
- Khong co `debugger`.
- Khong co `console.log` runtime.
- Khong them dead code hoac duplicate code nghiem trong.
- Click handler moi cua AI UI da duoc dua vao whitelist `navigation-cleanup` co chu dich.

## 3. Security

Da kiem tra:

- Khong them debug endpoint.
- Khong them test account.
- Khong them development configuration.
- Khong thay doi `.env` hoac secret.
- `Permissions-Policy` chi mo microphone cho `self`, khong mo third-party origin.
- AI UI chi goi AI qua `/api/ai/ask`.
- Speech layer khong goi API va khong gui transcript ra ngoai tru khi conversation layer dung endpoint noi bo.
- Conversation fallback fetch giu `Authorization` va `X-CSRF-Token` hien co.
- Backend AI permission model khong doi; AI khong truy cap database truc tiep tu UI.

## 4. Performance

Da kiem tra:

- Asset AI duoc minify lai bang `tools/build-assets.js`.
- Production artifact duoc build va validate thanh cong.
- UI khong them polling, interval nen khong tao tai nen khi AI khong duoc mo.
- Floating button/panel dung CSS fixed responsive, khong chen vao renderer cua cac module.

## 5. Documentation

Da cap nhat:

- `docs/AI_UI_ORCHESTRATION.md`
- `docs/PRODUCTION_READINESS.md`
- `docs/RELEASE_AUDIT.md`

## 6. Deployment

Da kiem tra:

- `npm.cmd run test:ai-epic12` da build production artifact va validate artifact thanh cong.
- Khong thay doi co che deploy cPanel/GitHub Actions.
- Rollback la revert cac file AI UI va docs/test lien quan.

## 7. Kiem thu va bang chung

Da chay va dat:

- `npm.cmd run build:assets` - PASS.
- `npm.cmd run test:ai-speech` - PASS.
- `npm.cmd run test:ai-conversation` - PASS.
- `npm.cmd run test:navigation-cleanup` - PASS.
- `npm.cmd run check:js` - PASS.
- `npm.cmd run test:ai-epic12` - PASS.
- `npm.cmd run test:browser` - PASS, 265 passed, 5 skipped.

## 8. Rui ro con lai

- Micro phu thuoc Web Speech Recognition cua trinh duyet va quyen microphone.
- PWA/mobile can HTTPS hop le de trinh duyet cho phep microphone; server header da cho phep same-origin.
- 5 Playwright tests skip theo cau hinh hien co, khong phai regression moi.

## 9. De xuat commit

PASS. Co the de xuat commit sau khi nguoi quan tri xac nhan.

Commit message de xuat:

```text
Add AI assistant shell UI
```
