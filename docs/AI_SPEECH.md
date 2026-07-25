# AI Speech - Epic 2

## Pham vi

Epic 2 chi them Speech To Text tren trinh duyet:

- Nut micro trong topbar.
- Panel nhap giong noi doc lap.
- Trang thai dang nghe / san sang / khong ho tro.
- Chuyen giong noi tieng Viet thanh van ban trong textarea rieng.

Khong goi AI, khong goi backend, khong thuc hien lenh va khong thao tac du lieu he thong.

## Thiet ke ky thuat

- `assets/js/ai-speech.js`: dung Web Speech API (`SpeechRecognition` hoac `webkitSpeechRecognition`).
- `views/app.php`: them nut micro va panel speech.
- `assets/css/app.css`: style cho nut, panel va trang thai dang nghe.
- `tools/build-assets.js`: sinh `assets/js/ai-speech.min.js`.
- `tests/ai-speech.test.js`: static regression de dam bao Speech khong goi network/AI/action.

Transcript duoc phat ra event noi bo:

```js
document.addEventListener('tenant:ai-speech-transcript', event => {
  console.log(event.detail.text);
});
```

Event nay chi de cac Epic sau su dung. Epic 2 khong xu ly intent va khong chay lenh.

## Rui ro

- Web Speech API phu thuoc trinh duyet; mot so trinh duyet desktop/mobile co the khong ho tro.
- Quyen micro do trinh duyet quan ly, nguoi dung phai chap nhan khi duoc hoi.
- Ket qua nhan dang tieng Viet co the sai theo moi truong am thanh.

## Rollback

Revert commit Epic 2 se go bo:

- `assets/js/ai-speech.js` va file minified.
- Markup nut/panel Speech trong `views/app.php`.
- CSS Speech trong `assets/css/app.css`.
- Test va tai lieu Epic 2.

Khong can rollback database vi Epic 2 khong co migration va khong luu du lieu.
