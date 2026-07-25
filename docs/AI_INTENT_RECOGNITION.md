# AI Intent Recognition - Epic 3

## Pham vi

Epic 3 chi nhan dien y dinh tu van ban:

- Phan tich cau lenh.
- Nhan dien intent.
- Phan loai lenh.
- Chuan hoa input va entity co ban.

Khong thao tac du lieu he thong, khong goi AI, khong goi backend tu UI, khong chay tool nghiep vu.

## Thiet ke ky thuat

Backend doc lap trong `/ai/src/Intent`:

- `CommandNormalizer`: chuan hoa text, bo ky tu thua, tach token.
- `IntentRecognizer`: rule-based classifier deterministic.
- `IntentResult`: ket qua intent, category, confidence, entity, confirmation flag.
- `NormalizedCommand`: raw text, normalized text va tokens.

Frontend preview:

- `assets/js/ai-intent.js`: nhan event `tenant:ai-speech-transcript`, nhan dien intent cuc bo va hien preview.
- Phat event `tenant:ai-intent-recognized` de Epic sau co the su dung.
- Khong goi `TenantAppPlatform.actions`, khong navigation, khong fetch.

## Intent hien co

- `navigation.open_module`
- `search.query`
- `report.view`
- `data.create_draft`
- `unknown`

## Entity hien co

- `module`
- `household_code`
- `phone`
- `identity_number`

## Kiem thu

```bash
npm.cmd run test:ai-intent
php tests/ai-intent-smoke.php
```

## Rui ro

- Rule-based classifier co the nhan dien sai cau noi phuc tap.
- Chua co hoi lai khi thieu thong tin; phan nay thuoc Epic 4.
- Chua co permission checker va tool executor; phan nay thuoc Epic 5.

## Rollback

Revert commit Epic 3 de go bo:

- `/ai/src/Intent`.
- `assets/js/ai-intent.js` va file minified.
- Preview intent trong panel Speech.
- Test va tai lieu Epic 3.

Khong can rollback database vi Epic 3 khong co migration.

