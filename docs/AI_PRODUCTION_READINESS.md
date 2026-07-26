# AI Production Readiness - Epic 12

## Pham vi

Epic 12 hoan thien lop san sang production cho cac Epic AI da trien khai.

Thay doi trong Epic nay:

- Them `test:ai-all` de chay lai toan bo test AI tu Epic 1 den Epic 11.
- Them `test:ai-release` de kiem tra tai lieu, asset, cau hinh bao mat, PWA va production artifact.
- Them `test:ai-epic12` gom test AI, JS syntax check, build production va validate artifact.
- Dua cac asset AI minified vao `service-worker.js` precache de PWA/Desktop/Mobile/Tablet co day du runtime AI.
- Tang version service worker len `tenant-pwa-v20260726-ai-release-1`.

Khong them tinh nang AI moi trong Epic 12.

## Thiet ke ky thuat

Epic 12 dung co che hien co cua du an:

- `tools/build-assets.js` minify asset hien co.
- `tools/build-production-artifact.js` tao `dist/production`.
- `tools/validate-production-artifact.js` chan file khong duoc dua len production.
- `service-worker.js` cache static asset can thiet.
- `tests/ai-release-readiness.test.js` la static release gate cho AI.

Luon giu AI provider o trang thai disabled mac dinh trong `ai/config/ai.php`.

## Kiem tra bao mat

- `ai/config/ai.php` van co `'enabled' => false`.
- `external_api` van mac dinh `false`.
- Log sensitive keys van bao gom token, api key, authorization va so dinh danh.
- Production artifact validator tiep tuc loai bo `.env`, `.git`, docs, tests, tools, node_modules, vendor va file backup/log/sql.
- Epic 12 khong them endpoint ghi du lieu.

## Kiem tra hieu nang

- AI asset duoc cache qua service worker de giam tai lai tren thiet bi.
- Khong them listener hoac query moi vao luong khoi dong server.
- `test:ai-epic12` chay build production de phat hien asset thieu truoc khi deploy.

## Kiem thu

Lenh chinh:

```bash
npm.cmd run test:ai-epic12
```

Lenh nay bao gom:

- Unit/smoke test AI tu Epic 1 den Epic 11.
- Static release readiness test.
- JavaScript syntax check.
- Production build.
- Production artifact validation.

## Kiem thu thu cong

Checklist truoc production:

- Desktop: mo dashboard, AI panel, speech/tts controls, OCR fallback text paste.
- Mobile: mo AI panel, nut micro, nut doc ket qua, camera input OCR.
- Tablet: kiem tra panel khong che noi dung va cac nut dieu khien vua kich thuoc.
- PWA: cai app, reload offline, dam bao AI panel van co asset UI; cac thao tac ghi van theo co che queue hien co.
- Backend: goi `/api/ai/tools`, `/api/ai/tools/ask`, `/api/insights/analytics` voi user co va khong co quyen.

## Danh sach file thay doi

- `service-worker.js`
- `package.json`
- `tests/ai-release-readiness.test.js`
- `docs/AI_PRODUCTION_READINESS.md`

## Rui ro

- Test release readiness la static test, khong thay the kiem thu trinh duyet that tren moi thiet bi.
- PWA cache phu thuoc trinh duyet va secure context.
- AI van la rule-based/read-only, chua co provider AI that.

## Rollback

Revert commit Epic 12 de:

- Bo `test:ai-release`, `test:ai-all`, `test:ai-epic12`.
- Bo `tests/ai-release-readiness.test.js`.
- Bo `docs/AI_PRODUCTION_READINESS.md`.
- Dua `service-worker.js` ve version va danh sach precache truoc Epic 12.
