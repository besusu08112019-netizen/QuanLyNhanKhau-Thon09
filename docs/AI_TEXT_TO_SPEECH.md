# AI Text To Speech - Epic 10

## Pham vi

Epic 10 them kha nang doc ket qua AI bang Web Speech API tren trinh duyet.

Thanh phan moi:

- `assets/js/ai-tts.js`: dieu khien TTS doc cau tra loi AI.
- `assets/js/ai-tts.min.js`: build tu source.
- `tests/ai-tts.test.js`: kiem tra contract TTS, UI controls va bao mat.

Khong them backend endpoint, khong goi dich vu ngoai, khong thay doi AI query/orchestration.

## Luong hoat dong

- Nguoi dung mo panel AI.
- Bat nut doc ket qua.
- Khi panel nhan event `tenant:ai-answer`, TTS doc cau tra loi assistant gan nhat.
- Nut dung doc goi `speechSynthesis.cancel()`.
- Slider toc do dieu chinh `SpeechSynthesisUtterance.rate`.
- Slider am luong dieu chinh `SpeechSynthesisUtterance.volume`.
- Uu tien voice tieng Viet `vi-VN`, fallback sang voice mac dinh cua trinh duyet.

## Bao mat

- Chi doc text da hien thi tren UI.
- Khong gui noi dung ra server.
- Khong dung `fetch`, `XMLHttpRequest` hay `window.api`.
- Chi luu tuy chon bat/tat, toc do va am luong theo tenant; khong luu noi dung cau tra loi.

## Kiem thu

```bash
npm.cmd run test:ai-epic10
npm.cmd run check:js
npm.cmd run build:assets
```

## Rui ro

- Mot so trinh duyet khong ho tro `speechSynthesis` hoac khong co voice `vi-VN`.
- Chat luong giong doc phu thuoc he dieu hanh/trinh duyet.
- Browser co the chan autoplay neu nguoi dung chua tuong tac; nut bat doc la hanh dong nguoi dung de giam rui ro nay.

## Rollback

Revert cac file:

- `assets/js/ai-tts.js`
- `assets/js/ai-tts.min.js`
- dong script `ai-tts.min.js` trong `views/app.php`
- block `.ai-tts-controls` trong `views/app.php`
- CSS `.ai-tts-controls` trong `assets/css/app.css`
- dong build asset trong `tools/build-assets.js`
- `tests/ai-tts.test.js`
- `docs/AI_TEXT_TO_SPEECH.md`
- script `test:ai-tts` va `test:ai-epic10` trong `package.json`
