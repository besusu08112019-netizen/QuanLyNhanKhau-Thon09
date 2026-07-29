# BI Sprint 1 - Citizen Insight Engine

## Status

PASS.

Local implementation, regression tests, and production acceptance PASS.

## Scope

- Added `CitizenInsightEngine` as a shared read-only service.
- Standardized output for population, age structure, labor, policy, household, and movement insights.
- Reused `PopulationStatistics`, `AgePolicy`, `InsurancePolicy`, `HouseholdRelationPolicy`, and `BusinessRuleCenter`.
- Added a contract test for service shape, policy dependencies, and tenant hard-code prevention.

## Out of Scope

- No database changes.
- No UI changes.
- No public API changes.
- No dashboard integration in BI-1.
- No changes to Policy Engine behavior.

## Changed Files

- `app/Services/CitizenInsightEngine.php`
- `tests/bi/CitizenInsightEngineTest.php`
- `docs/BI_SPRINT1_CITIZEN_INSIGHT_REPORT.md`

## Risk

- Low production risk because the service is read-only and not wired into existing screens or APIs.
- Query risk is isolated to BI consumers and can be rolled back by removing the new service.

## Rollback

Remove the BI-1 files:

- `app/Services/CitizenInsightEngine.php`
- `tests/bi/CitizenInsightEngineTest.php`
- `docs/BI_SPRINT1_CITIZEN_INSIGHT_REPORT.md`

No data rollback is required.

## Test Plan

- PHP syntax check: PASS.
- CitizenInsightEngine contract test: PASS.
- Policy Test Suite: PASS.
- Regression tests: PASS.
- Production acceptance on Thon 09 and Thon 10 with read-only BI summary execution: PASS.

## Production Acceptance

PASS:

- Active production DocumentRoot was confirmed from cPanel before acceptance:
  - `thon09.hongphongnb.com` -> `/home/nhhon5mp/public_html`
  - `thon10.hongphongnb.com` -> `/home/nhhon5mp/public_html`
- Production acceptance inspected only `/home/nhhon5mp/public_html`.
- Legacy tenant directories were not used as source evidence.
- Thon 09 returned BI summary through `CitizenInsightEngine`.
- Thon 10 returned BI summary through `CitizenInsightEngine`.
- The same acceptance script was called through both tenant domains and resolved tenant data by host.

Summary sample:

- Thon 09: 530 citizens, 149 households.
- Thon 10: 0 citizens, 0 households.

No data writes were performed during acceptance.
