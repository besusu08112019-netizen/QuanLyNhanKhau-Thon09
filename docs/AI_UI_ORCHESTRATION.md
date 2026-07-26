# AI UI Orchestration - Epic 8

## Pham vi

Epic 8 noi panel nhap giong noi/hoi thoai vao endpoint orchestration read-only cua Epic 7.

Thanh phan thay doi:

- `views/app.php`: hien nut `Tro ly AI` tren topbar, nut micro noi, cua so hoi thoai AI, nut gui cau hoi `aiAskBtn`, nut thu nho/dong/xoa lich su.
- `assets/js/ai-speech.js`: dieu khien panel AI, micro noi, trang thai nghe, thu nho/dong panel va phat transcript qua event noi bo.
- `assets/js/ai-conversation.js`: goi `POST /api/ai/ask`, hien cau tra loi trong conversation log, phat event `tenant:ai-answer`, cap nhat trang thai dang xu ly/dang tra loi.
- `assets/js/ai-conversation.min.js`: build tu source.
- `assets/js/operation-center.js`: chuyen hoi dap AI cua trung tam dieu hanh sang `POST /api/ai/ask` va normalize ket qua tool.
- `assets/css/app.css`: style topbar AI button, floating mic, panel hoi thoai responsive va preview ket qua co cau truc.

## Luong hoat dong

- Nguoi dung nhap cau hoi va bam nut gui.
- Nguoi dung bam `Tro ly AI` de mo cua so hoi thoai hoac bam micro noi de mo va bat dau nghe.
- Panel ho tro thu nho, dong, xoa lich su hoi thoai va xoa noi dung nhap.
- Trang thai UI gom san sang, dang nghe, dang xu ly va dang tra loi.
- Khi speech transcript final duoc nhan dien intent du thong tin, UI tu goi backend mot lan.
- Neu intent can bo sung thong tin, UI van hien cau hoi clarification cuc bo.
- Ket qua backend duoc chuyen thanh cau tra loi ngan gon trong log; rieng insight dung truc tiep truong `answer`.
- Cau tra loi assistant hien dong nguon `tool.action`/intent de can bo biet du lieu den tu planner nao.
- Neu backend tra ve item/list/statistics, UI hien them preview dang dong label/value ben duoi cau tra loi.
- Neu backend tra ve `metrics` va `items`, UI uu tien hien metrics truoc roi toi mot so dong mau.
- Trong luc dang truy van, nut gui bi disable va doi sang spinner.
- Operation Center hien cau tra loi, metrics va items tu cung endpoint orchestration de khong con lech API voi panel hoi thoai.

## Bao mat

- Request uu tien `window.api` de dung token/CSRF san co cua app.
- Fallback `fetch` tu them `Authorization` va `X-CSRF-Token` neu co.
- UI chi goi `/api/ai/ask`, endpoint nay van enforce read-only orchestration va RBAC.
- UI hien nguon tool/action tu response orchestration, khong hien SQL hay chi tiet noi bo.
- Khong dung `XMLHttpRequest`, khong dung navigation action, khong doi `location`.
- Floating mic chi kich hoat Web Speech Recognition phia browser; moi truy van du lieu van di qua AI Foundation endpoint va permission hien co.
- `Permissions-Policy` cho phep `microphone=(self)` de nut micro hoat dong tren chinh ung dung, khong mo microphone cho third-party origin.

## Kiem thu

```bash
npm.cmd run test:ai-epic8
npm.cmd run check:js
```

## Rui ro

- Cau tra loi frontend hien la summary/answer ngan, preview chi phuc vu doc nhanh.
- Preview chi hien toi da mot so field/dong dau tien de giu panel gon.
- Speech recognition co the gui cau da normalize khong dau; backend planner da ho tro khong dau cho cac intent chinh.
- Spinner chi nam o nut gui; panel chua co skeleton/table loading rieng.

## Rollback

Revert cac file:

- `assets/js/ai-conversation.js`
- `assets/js/ai-conversation.min.js`
- `assets/js/operation-center.js`
- `assets/js/operation-center.min.js`
- `assets/css/app.css`
- `assets/css/app.min.css`
- `views/app.php`
- `tests/ai-conversation.test.js`
- `docs/AI_UI_ORCHESTRATION.md`
- Script `test:ai-epic8` trong `package.json`.
