# Production Stabilization Audit

## Scope
- No new features.
- Cleanup focused on encoding stability, duplicate assets, stale asset references, and runtime verification.

## Changes
- Added API response UTF-8 normalizer in `app/Core/Response.php` for mojibake strings already stored or returned as corrupted text.
- Kept database unchanged and did not perform migrations or production data writes.
- Removed duplicate JS assets where `.utf8.js` is the runtime source:
  - `assets/js/app.js`
  - `assets/js/admin.js`
- Removed unreferenced legacy assets:
  - `assets/css/admin-design-system.css`
  - `assets/css/gis-household-location.css`
  - `assets/css/mobile-household.css`
  - `assets/css/mobile-household-dienho.css`
  - `assets/css/person-card-redesign.css`
  - `assets/js/admin-design-system.js`
  - `assets/js/mobile-design-system.js`
  - `assets/js/person-card-redesign.js`
- Cleaned stale/nonexistent asset entries from `index.php` versioning list.
- Fixed the remaining unaccented UI fallback string in `assets/js/app.utf8.js`.

## Verification
- `npm.cmd run check:js`: PASS
- `npm.cmd run test:browser`: PASS, 3/3 projects
- PHP lint all `.php`: PASS
- Local cross-module UI check: PASS
  - Desktop 1366px
  - Tablet 768px
  - Mobile 390px
  - Console errors: 0
  - Failed requests: 0
  - Horizontal overflow: 0
- Production read-only check before deploying this stabilization:
  - API status/shape: PASS
  - Console errors: 0
  - Horizontal overflow: PASS
  - Existing production API data still contains mojibake before this fix is deployed.
  - Mobile GIS tile aborts were third-party OpenStreetMap tile cancellations, not application API errors.

## Artifacts
- `local-ui-check.json`
- `production/production-check.json`
- `desktop-stabilization.png`
- `tablet-stabilization.png`
- `mobile-stabilization.png`