# Policy Engine Sprint 1 Report - Age Policy

## Scope

Sprint 1 chi refactor Age Policy.

Included:

- Nhom tuoi.
- Tre em.
- Nguoi cao tuoi.
- Hoc sinh.
- Tuoi lao dong thong ke.
- Quy tac BHYT 70 tuoi.
- Quy tac bao tro xa hoi 75 tuoi.
- Lookahead canh bao chinh sach.
- Helper tinh tuoi dung chung.

Not included:

- Insurance Policy refactor.
- Social Support Policy refactor.
- Household Relation Policy refactor.
- Import Policy refactor.
- Contribution/Employment retirement-age policy.
- UI redesign.
- Database change.

## Changed Files

Runtime:

- `app/Policies/AgePolicy.php`
- `app/Config/CitizenPolicyDefaults.php`
- `app/Services/StudentStatusService.php`
- `app/Services/HealthInsuranceDefaultService.php`
- `app/Services/ContributionRuleEngine.php`
- `app/Models/Citizen.php`
- `app/Models/DigitalProfile.php`
- `app/Models/PolicyAlert.php`
- `app/Models/PopulationStatistics.php`
- `app/Models/Dashboard.php`
- `app/Models/Report.php`
- `app/Models/GisHouseholdLocation.php`
- `app/Models/OperationCenter.php`
- `app/Models/PartyMember.php`
- `app/Repositories/ControlCenterRepository.php`
- `app/Controllers/ImportController.php`
- `config/policy_alerts.php`
- `index.php`
- `assets/js/app.utf8.min.js`

Local ignored deployment artifact checked but not part of commit:

- `dist/production/index.php`

Tests:

- `tests/age-policy.test.php`

Docs:

- `docs/POLICY_ENGINE_SPRINT1_REPORT.md`

## What Changed

- Added `AgePolicy` as the single source for shared age thresholds, age helpers, and age SQL expressions.
- Kept existing thresholds and behavior:
  - Children: `< 16`.
  - Statistical elderly: `>= 60`.
  - Working age statistics: `16-59`.
  - Student academic age: `<= 17`.
  - Academic year starts in August.
  - BHYT review age: `70`.
  - Social allowance review age: `75`.
  - Upcoming policy lookahead: `90` days.
- Existing services, models, dashboard metrics, reports, GIS metrics, party-member age calculations, Control Center metrics, and frontend age grouping now delegate shared age thresholds to `AgePolicy` or the backend policy payload derived from `AgePolicy`.
- Import acceptance found a transaction blocker when importing citizens after the optional health-insurance schema check. The fix prepares the optional citizen schema before starting the import transaction so MySQL DDL cannot implicitly commit the active import transaction.
- No database migration.
- No routing change.
- No tenant resolver change.
- No intentional UI/UX change.

## Final Source Scan

Scanned production PHP and frontend source under:

- `app/`
- `config/`
- `assets/`
- `index.php`
- `dist/production/index.php`

Search classes:

- Age keywords: `age`, `tuoi`, `birth_year`, `year_of_birth`, `date_of_birth`, `current_year`, `dob`.
- Age SQL: `TIMESTAMPDIFF(YEAR`.
- Direct threshold comparisons: `17`, `18`, `60`, `70`, `75`.

Result:

- Direct age SQL outside `AgePolicy`: `0`.
- Direct business age threshold comparisons outside `AgePolicy`: `0`.
- Age helper duplication moved to `AgePolicy`: `Citizen`, `DigitalProfile`, `PartyMember`, shared dashboards/reports/alerts.
- Frontend dashboard age grouping now reads policy values from `window.AppSettings.citizenPolicyDefaults`, which is populated by backend constants from `AgePolicy`.

Remaining non-age matches:

- PDF layout positioning, UTF-8 bitmask logic, string length validation, GIS retry counters, year-based report filters, identity/document expiry checks.
- These are not Age Policy business rules and were not changed.

## Rule Count

Age rule groups moved to `AgePolicy`: `17`.

Moved rule groups:

1. Exact age calculation from `date_of_birth`.
2. Shared SQL age expression.
3. Child threshold.
4. Statistical elderly threshold.
5. Working-age statistics threshold.
6. Academic year start month.
7. Academic age calculation.
8. Student maximum academic age.
9. BHYT default age.
10. Social support age.
11. Policy alert lookahead.
12. Upcoming policy target date SQL.
13. Dashboard age bands.
14. Report age bands.
15. GIS household age metrics.
16. Party-member age chart/filter/badge thresholds.
17. Control Center aggregate age metrics.

Age rule groups intentionally left for later: `1`.

- `app/Services/ContributionRuleEngine.php`
  - Retirement-age calculation uses month-precise contribution/employment policy.
  - Marked with `TODO: Move to Policy Engine`.
  - Not moved in Sprint 1 because it belongs to Contribution/Employment Policy, not shared Age Policy.

## TODO Files

- `app/Services/ContributionRuleEngine.php`
  - `LABOR_AGE`
  - `isLaborAge`
  - `ageMonths`

## Risk Assessment

| Risk | Level | Mitigation |
| --- | --- | --- |
| Age SQL expression mismatch | Low/Medium | Unit tests assert generated SQL strings preserve existing expressions. |
| Dashboard/report counts change | Low/Medium | Shared SQL fragments preserve current thresholds exactly. |
| Student classification changes | Low/Medium | Unit tests cover academic year boundary and age 17/18 behavior. |
| Policy alert 70/75 changes | Low/Medium | Unit tests cover reached-age and upcoming-age conditions. |
| Frontend age chart drift | Low | Frontend now reads backend policy payload from `AgePolicy` constants. |
| Contribution labor-age policy remains duplicated | Medium | Explicit TODO; defer to Contribution/Employment Policy sprint. |
| Tenant production regression | Low | Production/Staging acceptance passed on both Thon 09 and Thon 10. |
| Import transaction regression | Low/Medium | Acceptance exposed and fixed the DDL-before-transaction issue; import was re-tested on both tenants. |

## Rollback Plan

Rollback is isolated:

1. Revert the Sprint 1 Age Policy commit.
2. Restore direct age expressions in the changed files.
3. Remove `app/Policies/AgePolicy.php`.
4. Remove `tests/age-policy.test.php`.
5. No database rollback required.
6. No storage rollback required.
7. No tenant data rollback required.

## Test Results

Automated checks:

- `php -l` on changed PHP files: PASS.
- `php -l index.php`: PASS.
- `php -l dist/production/index.php`: PASS.
- `node --check assets/js/app.utf8.min.js`: PASS.
- `php tests/age-policy.test.php`: PASS.
- `php tests/tenant-resolver.test.php`: PASS.
- `php tests/portal-context.test.php`: PASS.
- `php tests/control-center-authorization.test.php`: PASS.
- `composer run check:php`: PASS.
- `npm.cmd run test:regression`: PASS.

Notes:

- `portal-context` and `control-center-authorization` tests print env diagnostics because the local `.env` has no tenant DB credentials. The tests still passed.
- Production/Staging acceptance was executed directly on Thon 09 and Thon 10 after deploying the Sprint 1 runtime files.

Production/Staging acceptance:

| Area | Thon 09 | Thon 10 |
| --- | --- | --- |
| Login | PASS | PASS |
| Dashboard | PASS | PASS |
| Dashboard elderly metric | PASS | PASS |
| Age chart / age groups | PASS | PASS |
| Citizen list and age-related fields | PASS | PASS |
| Policy summary | PASS | PASS |
| BHYT default age 70 | PASS | PASS |
| Social support age 75 | PASS | PASS |
| Policy lookahead 90 days | PASS | PASS |
| Age report | PASS | PASS |
| Student report | PASS | PASS |
| Elderly report | PASS | PASS |
| BHYT report | PASS | PASS |
| Import household | PASS | PASS |
| Import citizen/person | PASS | PASS |
| Multi-tenant isolation | PASS | PASS |

Import acceptance details:

- Imported one acceptance household and one acceptance citizen on each tenant.
- Verified age-policy defaults for the imported citizen through persisted citizen fields:
  - BHYT default was applied.
  - Social support was not applied before the 75-year threshold.
  - Elderly occupation default was applied for age 70+.
- Cleaned all acceptance households and citizens after verification.
- Verified no acceptance household/citizen records remained on either tenant.

Production cleanup:

- Temporary acceptance scripts and tokens were removed from `public_html`.
- The temporary Thon 10 acceptance user was removed and no longer authenticates.

## PASS/FAIL

Implementation gate: PASS.

Automated regression: PASS.

AgePolicy single-source scan: PASS.

Database change: PASS, no database change.

UI change: PASS, no intentional UI/UX change.

Production tenant acceptance on Thon 09 and Thon 10: PASS.

Production import acceptance: PASS.

Multi-tenant regression: PASS.

Commit recommendation: PASS.
