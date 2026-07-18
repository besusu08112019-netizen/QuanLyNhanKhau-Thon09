# Mobile/Tablet UI RCA and QA

Date: 2026-07-18

## Scope

This report covers the Mobile and Tablet UI redesign/refactor. Desktop business logic, API routes, and database schema were not changed.

## Root Cause

The repeated mobile layout failures were caused by two responsive systems running at the same time:

- Legacy CSS in `assets/css/app.css` still defined mobile card, filter, button, and bottom navigation behavior.
- Legacy runtime code in `assets/js/app.utf8.min.js` still created old filter UI classes such as `mobile-filter-shell` and `mobile-filter-toggle`.
- The new runtime was added separately, so both systems could affect the same DOM.
- Some legacy global rules used broad selectors or `!important`, for example global button minimum widths, which overrode compact mobile/tablet controls.
- Production cache could keep stale CSS/JS because service worker and asset versions did not always change with UI refactors.

## Files Involved

- Legacy responsive CSS: `assets/css/app.css`
- Old runtime design system: `assets/js/mobile-design-system.js`, `assets/js/mobile-design-system.min.js`
- Final shared responsive CSS: `assets/css/mobile-design-system-v2.css`
- Final shared responsive runtime/component library: `assets/js/mobile-component-library.js`
- Asset loading: `views/app.php`, `index.php`
- PWA cache: `service-worker.js`
- Build pipeline: `tools/build-assets.js`

## Fix Summary

- Removed the old `mobile-design-system` assets.
- Added `mobile-design-system-v2.css` and `mobile-component-library.js` as the single Mobile/Tablet UI layer.
- Replaced old table-to-card rendering with V2 module shells and `.app-v2-record-card`.
- Changed title selection to use configured header metadata instead of fixed column indexes.
- Verified UTF-8 source strings in `mobile-component-library.js` with Node so Vietnamese labels render correctly; mojibake seen through PowerShell output is console decoding, not source content.
- Removed old filter runtime creation from `app.utf8.min.js`.
- Removed legacy filter CSS classes from `app.css`.
- Removed the legacy module-specific table-to-card CSS from `app.css`; adaptive table/card rendering now only uses the shared `.mdu-*` contract.
- Removed orphaned legacy CSS for `mobile-filter-active`, `mobile-action-system`, and `mobile-pager-system`; current filter behavior is owned by V2 toolbar/filter components.
- Removed the remaining legacy shared responsive contracts from `app.css` and rebuilt `app.min.css`, including:
  - `Design System Contract v12.1`
  - `Responsive-first UI contract v13`
  - `Responsive QA contract v14`
  - `Shared icon system contract v15`
  - `Shared action icon contract v17`
  - the old `Responsive design system baseline` and `--ds-*` token layer
- Removed the invalid nested legacy media-query pattern `@media(max-width:1023px){@media ...` from the runtime stylesheet.
- Moved the required Mobile/Tablet UI ownership into `mobile-design-system-v2.css`:
  - shared action/filter/toolbar touch target contract,
  - shared KPI/stat icon geometry,
  - shared list-header action/select wrapping,
  - contributions KPI grid behavior at compact widths.
- Split responsive behavior:
  - Table/card adaptive UI: up to `1024px`.
  - Compact filter icon: below `1024px` only.
  - Desktop `>=1024px`: no compact filter icon.
- Fixed shared bottom navigation and action button sizing to avoid overflow at `320px`, `360px`, and `1024px`.
- Updated asset and PWA cache versions to force production reload of the new CSS/JS.
- Bumped `APP_ASSET_VERSION` to `mobile-tablet-ui-redesign-20260718-03` and `PWA_VERSION` to `thon09-pwa-v20260718-mobile-ui-03` after the final legacy CSS cleanup.
- Updated tests to assert the new `mdu-*` component contract and guard against legacy classes returning.

## Why Earlier Refactors Missed It

The previous work verified visible symptoms but did not fully remove the legacy runtime/CSS paths. Because `app.css` and old JS were still loaded, cleanup in one layer could be overridden later by another layer, especially through broad selectors and service worker cache.

## Prevention

- Keep Mobile/Tablet responsive behavior in `mobile-ui.css` and `mobile-ui-system.js`.
- Do not reintroduce `mobile-design-system` or `mobile-filter-*` runtime classes.
- Bump `APP_ASSET_VERSION` and `PWA_VERSION` when changing production CSS/JS.
- Keep tests checking:
  - no legacy design system loaded,
  - no duplicate filter systems,
  - no horizontal overflow,
  - no vertical/card title collapse,
  - no desktop filter icon at `>=1024px`.
- CI now verifies the rendered app shell loads `mobile-ui.min.css` and `mobile-ui-system.min.js`, and does not load legacy `mobile-design-system` assets.
- Runtime source audit found no remaining references to legacy `mobile-design-system`, `mobile-filter-*`, `mobile-list-card`, `person-card-layout`, or `gis-search.min.js` in `assets`, `views`, `tools`, `index.php`, or `service-worker.js`.
- CSS source audit found no remaining legacy responsive contract blocks in `assets/css/app.css` or `assets/css/app.min.css`.
- Shared card contract audit confirms `.mdu-card` uses `grid-template-columns: minmax(0, 1fr) auto`, `.mdu-card-content` owns the flexible text column, and `.mdu-card-actions` only takes action width.
- `tests/navigation-cleanup.test.js` now guards against reintroducing orphaned mobile CSS states and module-specific table-to-card CSS in `app.css`.

## QA Evidence

Latest executed checks:

- `npm run check:js`: PASS
- `npm run build:assets`: PASS
- `npm run test:navigation-cleanup`: PASS
- `git diff --check`: PASS
- `npx.cmd playwright test tests/browser/mobile-ui-redesign.spec.js tests/browser/responsive-ui.spec.js --project=mobile-390`: PASS, 30 passed, 2 skipped
- `npx.cmd playwright test tests/browser/mobile-ui-redesign.spec.js tests/browser/responsive-ui.spec.js --project=tablet-768`: PASS, 30 passed, 2 skipped
- `npx.cmd playwright test tests/browser/responsive-ui.spec.js --project=desktop-chromium -g "narrow desktop sidebar keeps module clicks inside the viewport"`: PASS, 1 passed
- `npx.cmd playwright test tests/browser/responsive-ui.spec.js --project=desktop-chromium -g "full responsive QA contract across requested breakpoints"`: PASS, 1 passed
- `npx.cmd playwright test tests/browser/production-ui-audit.spec.js tests/browser/public-assets.spec.js tests/browser/mobile-render-dedup.spec.js --project=desktop-chromium`: PASS, 21 passed
- `npx.cmd playwright test tests/browser/leaflet-assets.spec.js --project=desktop-chromium`: PASS, 3 passed
- `npm run test:browser`: PASS, 199 passed, 5 skipped after legacy CSS cleanup, asset/PWA version bump, and guard additions
- `Select-String` audit against `assets/css/app.css` and `assets/css/app.min.css`: PASS
  - no `--ds-*`
  - no `Responsive design system baseline`
  - no `Design System Contract v12.1`
  - no `Responsive-first UI contract v13`
  - no `Responsive QA contract v14`
  - no `Shared icon system contract v15`
  - no `Shared action icon contract v17`
  - no invalid nested `@media(max-width:1023px){@media` pattern
- App shell runtime asset check: PASS
  - Uses `APP_ASSET_VERSION` `mobile-tablet-ui-redesign-20260718-03`
  - Loads `mobile-ui.min.css`
  - Loads `mobile-ui-system.min.js`
  - Does not load `mobile-design-system.js`
  - Does not load `mobile-design-system.min.js`
  - Does not load legacy `gis-search.min.js`
- PHP built-in server app shell check on `127.0.0.1:8099`: PASS
  - Rendered HTML contains `mobile-tablet-ui-redesign-20260718-03`
  - Rendered HTML loads `assets/css/mobile-ui.min.css`
  - Rendered HTML loads `assets/js/mobile-ui-system.min.js`
  - Rendered HTML does not load legacy mobile design system, person card layout, or GIS search assets
- Runtime source legacy reference audit: PASS
- Shared mobile card/title/action contract audit: PASS
- UTF-8 source string verification for `mobile-ui-system.js`: PASS
- Legacy module-specific table-to-card CSS audit: PASS
- Orphaned legacy mobile CSS audit: PASS
- CSS legacy guard in `npm run test:navigation-cleanup`: PASS
- `composer validate --strict`: PASS
- `php -l index.php`: PASS
- PHP lint for all project `.php` files excluding `vendor`, `uploads`, and `backups`: PASS

## Limits

QA was executed with Playwright/Chromium viewport coverage in this workspace. Physical Android device verification was not available in this environment.
