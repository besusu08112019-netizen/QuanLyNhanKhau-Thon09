# AI OCR and Camera - Epic 9

## Pham vi

Epic 9 them lop OCR/camera client-side cho form nhan khau.

Thanh phan moi:

- `assets/js/ai-ocr.js`: panel OCR CCCD gan vao `#personForm`.
- `assets/js/ai-ocr.min.js`: build tu source.
- `tests/ai-ocr.test.js`: kiem tra parser CCCD, autofill form, camera input va cac rang buoc bao mat.

Khong them backend endpoint, khong upload anh OCR, khong goi dich vu AI/OCR ben ngoai, khong tu dong luu form.

## Luong hoat dong

- Can bo mo form nhan khau.
- Panel OCR CCCD duoc chen vao dau form.
- Chon `Chup giay to` de mo camera thiet bi qua `capture="environment"` hoac `Chon anh` tu thu vien.
- Neu trinh duyet ho tro `TextDetector`, nut `Doc OCR` doc text truc tiep tren thiet bi.
- Neu khong ho tro OCR native, can bo dan text OCR vao o fallback.
- Nut `Dien form` parse CCCD va dien cac truong: `identityNumber`, `fullName`, `dateOfBirth`, `gender`, `currentAddress`.

## Thiet ke

- Toan bo xu ly anh/text dien ra trong trinh duyet.
- Parser tach rieng qua `TenantAiOcr.parseCccdText(text)`.
- Autofill tach rieng qua `TenantAiOcr.applyCccdToPersonForm(form, data)`.
- Ket qua OCR chi dien vao form hien tai; nguoi dung van phai kiem tra va bam `Luu`.

## Bao mat

- Khong goi `fetch`, `window.api` hay API moi.
- Khong ghi `localStorage`.
- Khong upload anh/doc OCR len server.
- Text OCR khi hien thi lai duoc escape HTML.
- Khong tu dong tao/sua/xoa ban ghi.

## Kiem thu

```bash
npm.cmd run test:ai-epic9
npm.cmd run check:js
npm.cmd run build:assets
```

## Rui ro

- `TextDetector` khong co san tren moi trinh duyet, nen fallback dan text OCR la bat buoc.
- Chat luong OCR phu thuoc anh chup va browser.
- Parser CCCD chi nhan cac mau text pho bien; can bo phai kiem tra truoc khi luu.

## Rollback

Revert cac file:

- `assets/js/ai-ocr.js`
- `assets/js/ai-ocr.min.js`
- dong script `ai-ocr.min.js` trong `views/app.php`
- dong build asset trong `tools/build-assets.js`
- `tests/ai-ocr.test.js`
- `docs/AI_OCR_CAMERA.md`
- script `test:ai-ocr` va `test:ai-epic9` trong `package.json`
