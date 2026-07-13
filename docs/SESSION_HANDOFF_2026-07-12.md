# Session handoff 2026-07-13

## Workspace

- Repo: `D:\Projects\QuanLyNhanKhau-Thon09`
- Branch: `main`
- Current phase: frontend architecture refactor, platform delegation cleanup, modal/permission/navigation migration.
- This workspace is intentionally dirty. Do not restart from scratch and do not revert existing changes.

## Completed in the current refactor run

- Action/navigation cleanup:
  - Moved broad UI actions in `digital-profile.js`, `household-photo-capture.js`, `admin-panel-bridge.js`, `gis-household-location.js`, `view-inline-patches.js` and related modules toward `Thon09Platform` action/navigation delegation.
  - Kept only intentional physical handlers such as Leaflet/GIS map interactions and compatibility delegation.
- Modal service migration:
  - Migrated stable Bootstrap modals to `Thon09Platform.modals` with fallbacks in modules including public assets, livestock, agriculture, houses, household business, admin, sprint8/sprint10, operation center and GIS/photo modules.
  - `digital-profile.js` still uses direct Bootstrap only for the runtime-created media lightbox because it has no stable modal id.
- Permission migration:
  - Added helper pattern that prefers `Thon09Platform.permissions` when explicit grants exist, with legacy fallback.
  - Applied in public assets, import, admin, admin bridge, digital profile, GIS and household business.
- Navigation state cleanup:
  - `admin-panel.js` and `module-dashboards.js` now prefer platform navigation state instead of direct `App.screen`.
  - `view-inline-patches.js` legacy controller now reads `Thon09Platform.navigation.current()` first, then falls back to `App.screen` and `localStorage('thon09_screen')`.
  - `sprint10.js` no longer contains the dead `App.screen` dashboard patch; `patchSprint10Dashboard()` is now a clear no-op.

## Latest verification

All of these passed after the latest changes:

- `npm.cmd run build:assets`
- `npm.cmd run check:js`
- `npm.cmd run test:navigation-cleanup`
- `npm.cmd run test:platform`
- `npx.cmd playwright test tests/browser/navigation-controller.spec.js tests/browser/production-ui-audit.spec.js tests/browser/responsive-ui.spec.js`
  - Result: 72 passed, 3 skipped.
- `npm.cmd run test:browser`
  - Result: 111 passed, 3 skipped.

## Current known remaining state

- Remaining non-minified `App.screen` / `thon09_screen` references are expected:
  - `assets/js/app-platform.js`: `window.App.screen = state.screenId` compatibility mirror.
  - `assets/js/view-inline-patches.js`: legacy fallback and sync around `Thon09NavigationController`.
- `assets/js/app.utf8.js` is deleted in the working tree from earlier work. Do not restore unless explicitly requested.
- `tests/browser/smoke.spec.js` is deleted in the working tree from earlier work. Do not restore unless explicitly requested.
- `tests/browser/global-setup.js` and `tests/browser/global-teardown.js` are untracked and are part of the current browser test setup.
- Broad line-ending/encoding/minified diffs already exist. Avoid broad cleanup unless directly required.

## Dirty workspace snapshot

Important dirty groups:

- Platform/core: `assets/js/app-platform.js`, `assets/js/app-platform.min.js`, `views/app.php`.
- Refactored modules: `admin-panel*`, `admin.utf8*`, `agriculture*`, `digital-profile*`, `gis-household-location*`, `household-business*`, `household-photo-*`, `houses*`, `import*`, `livestock*`, `module-dashboards*`, `operation-center*`, `public-assets*`, `report*`, `sprint8*`, `sprint10*`, `system-admin*`, `view-inline-patches*`.
- Tests/config: `tests/app-platform.test.js`, browser specs, `tests/navigation-cleanup.test.js`, `playwright.config.js`.
- Untracked: this handoff file and browser global setup/teardown.

## Next recommended step

Continue with a small, verifiable refactor slice. Good candidates:

1. Inspect remaining direct DOM click listeners in one module at a time and move only clear command buttons to platform actions.
2. Re-scan direct Bootstrap modal usage and leave only dynamic/no-stable-id cases.
3. Re-scan permission helpers and normalize any remaining legacy-only helper to the explicit-platform-then-fallback pattern.

After each slice, run:

```powershell
npm.cmd run build:assets
npm.cmd run check:js
npm.cmd run test:navigation-cleanup
npm.cmd run test:platform
npx.cmd playwright test tests/browser/navigation-controller.spec.js tests/browser/production-ui-audit.spec.js tests/browser/responsive-ui.spec.js
npm.cmd run test:browser
```

## Useful scans

```powershell
Select-String -Path assets\js\*.js -SimpleMatch -Pattern "App.screen","window.App.screen","thon09_screen" | Where-Object { $_.Path -notlike '*.min.js' } | Select-Object Path,LineNumber,Line
Select-String -Path assets\js\*.js -SimpleMatch -Pattern "new bootstrap.Modal","getOrCreateInstance" | Where-Object { $_.Path -notlike '*.min.js' } | Select-Object Path,LineNumber,Line
Select-String -Path assets\js\*.js -SimpleMatch -Pattern "addEventListener('click'","addEventListener(\"click\"" | Where-Object { $_.Path -notlike '*.min.js' } | Select-Object Path,LineNumber,Line
git status --short
```
