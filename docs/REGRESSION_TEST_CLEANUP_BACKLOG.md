# Regression Test Cleanup

Date opened: 2026-07-30
Source commit: `d5421127f664292a2edc716f16d74310f021b379`
Source workflow: GitHub Actions `CI`
Scope: Project-wide browser regression cleanup, separate from Community Control Center Phase 1.

## Summary

Community Control Center Phase 1 is accepted independently. The remaining GitHub CI failures are isolated to the full Playwright browser regression suite and are managed in this backlog.

Do not treat these items as Community Control Center Phase 1 blockers. Do not change Community Control Center code for these items unless a later investigation proves a direct regression from the Phase 1 release.

CI browser result:

- 300 passed.
- 5 skipped.
- 16 failed.

## Backlog Items

| ID | Test | Failure Cause | Classification | Priority | Production Impact | Proposed Handling |
| --- | --- | --- | --- | --- | --- | --- |
| RTC-001 | `tests/browser/auto-logout.spec.js:85` - warning appears on schedule, continue resets once, and logout now clears auth | Playwright did not find `#idleTimeoutWarningModal` within the expected timeout. Failed on desktop and repeated on mobile/tablet variants. | Population system session behavior; possible Playwright/CI timing sensitivity. Not related to Community Control Center. | P2 | No confirmed impact to Community Control Center production. Potential impact is limited to general app idle-session UX and must be verified separately. | Reproduce locally with the full browser suite, inspect session timer setup, then decide whether to fix app behavior or stabilize test timing. |
| RTC-002 | `tests/browser/auto-logout.spec.js:124` - auto timeout logs out once and survives back, refresh, and new tab | Playwright did not find `#idleTimeoutWarningModal` before expected logout state. Failed on desktop and repeated on mobile/tablet variants. | Population system session behavior; possible Playwright/CI timing sensitivity. Not related to Community Control Center. | P2 | No confirmed impact to Community Control Center production. Potential impact is limited to general app idle-session behavior. | Re-run with trace review, verify timer mocks and session storage behavior, then fix only if the product behavior is broken. |
| RTC-003 | `tests/browser/auto-logout.spec.js:197` - mobile and PWA-sized viewports show usable warning modal | Playwright did not find `#idleTimeoutWarningModal` in mobile/PWA viewport checks. | Responsive session modal behavior; possible Playwright/CI timing sensitivity. Not related to Community Control Center. | P2 | No confirmed impact to Community Control Center production. Potential impact is limited to general app idle-warning UI. | Review trace screenshots for modal creation and viewport constraints; separate real responsive defect from flaky timing. |
| RTC-004 | `tests/browser/public-assets.spec.js:280` - public assets reports preview, print and exports use shared report API | Test timed out while selecting `#reportTypeSelect` for `public-assets`. Failed across browser project variants. | Public assets / report module. Not related to Community Control Center. | P2 | No confirmed impact to Community Control Center production. Possible impact to public assets reporting must be validated separately. | Run the public-assets test in isolation, inspect whether the select is disabled/covered/busy, then fix module or test wait condition as appropriate. |
| RTC-005 | `tests/browser/public-assets.spec.js:339` - required management modules expose report buttons and report types | Test timed out while selecting report types through `#reportTypeSelect`. Failed across browser project variants. | Public assets and shared report workflow. Not related to Community Control Center. | P2 | No confirmed impact to Community Control Center production. Possible impact to general report navigation must be validated separately. | Run the report workflow tests in isolation, compare expected report types with current UI state, and update app or tests only after confirming the source of failure. |
| RTC-006 | `tests/browser/responsive-ui.spec.js:639` - responsive system navigation audit full responsive QA contract | Responsive audit reported `320/partyMembers icon failures`. | General responsive UI; population system module. Not related to Community Control Center. | P3 | No confirmed impact to Community Control Center production. Possible narrow mobile visual issue in Party Members module. | Inspect 320px viewport screenshots for Party Members navigation/icons, then fix responsive styling or update stale test expectations in the regression sprint. |

## Release Separation

These failures are intentionally excluded from Community Control Center Phase 1 acceptance because they belong to the project-wide browser regression suite. They should be planned, prioritized, and fixed in a separate Sprint Regression workstream.

## Acceptance Rule For Cleanup

Each backlog item should be closed only when:

- The failing test is reproduced or proven flaky.
- The production impact is explicitly confirmed or ruled out.
- The fix is scoped to the affected module or test.
- The full related browser spec passes after the change.
