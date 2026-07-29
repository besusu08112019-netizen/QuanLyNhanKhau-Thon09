# Policy Engine Sprint 2 Report - Insurance Policy

## Scope

Sprint 2 only refactors Health Insurance Policy.

Included:

- Default BHYT eligibility through occupation.
- Default BHYT eligibility through student status.
- Default BHYT eligibility through age 70+.
- Health insurance SQL predicates for dashboard and reports.
- Compatibility facade for existing `HealthInsuranceDefaultService` callers.
- Policy Test Suite coverage for Insurance Policy.

Not included:

- Social Support Policy.
- Household Relation Policy.
- Employment Policy.
- Import Policy refactor.
- Public API changes.
- UI changes.
- Database changes.

## Changed Files

Runtime:

- `app/Policies/InsurancePolicy.php`
- `app/Services/HealthInsuranceDefaultService.php`
- `app/Models/Citizen.php`
- `app/Models/PopulationStatistics.php`
- `app/Models/Report.php`
- `app/Repositories/ControlCenterRepository.php`
- `config/policy_alerts.php`
- `index.php`

Tests:

- `tests/policies/insurance/InsurancePolicyTest.php`

Docs:

- `docs/POLICY_ENGINE_SPRINT2_REPORT.md`

## What Changed

- Added `InsurancePolicy` as the shared source for BHYT default rules and BHYT SQL predicates.
- Kept `HealthInsuranceDefaultService` as a compatibility facade so existing callers continue to work.
- Citizen default occupation and BHYT flag calculation now call `InsurancePolicy`.
- Dashboard and report health-insurance statistics now use `InsurancePolicy` SQL helpers instead of duplicating predicate strings.
- Policy alert age 70 BHYT configuration now reads the BHYT threshold from `InsurancePolicy::DEFAULT_AGE`.
- Frontend policy payload reads the BHYT default age and occupation list through `InsurancePolicy`.

## Behavior Compatibility

Preserved behavior:

- Student academic age still defaults to student occupation and BHYT eligibility.
- Age 70+ still defaults to elderly occupation and BHYT eligibility.
- Missing BHYT still means `COALESCE(has_health_insurance,0)=0`.
- Enrolled BHYT still means `COALESCE(has_health_insurance,0)=1`.
- Effective BHYT still requires enrolled BHYT and no expired end date.
- Expiring BHYT still uses the existing 30-day window.
- Expired BHYT still requires an end date before the current date.

No database migration was added.

## Production Acceptance

Production/Staging acceptance was executed on:

- `https://thon09.hongphongnb.com`
- `https://thon10.hongphongnb.com`

Acceptance checks:

| Area | Thon 09 | Thon 10 |
| --- | --- | --- |
| Login | PASS | PASS |
| Dashboard summary | PASS | PASS |
| Dashboard BHYT metrics | PASS | PASS |
| Policy alert summary | PASS | PASS |
| Health insurance report | PASS | PASS |
| Missing BHYT report | PASS | PASS |
| Expired BHYT report | PASS | PASS |
| Expiring BHYT report | PASS | PASS |
| Health insurance by household report | PASS | PASS |
| Multi-tenant isolation | PASS | PASS |

Observed metrics during acceptance:

| Tenant | Total Citizens | Insured | Uninsured |
| --- | ---: | ---: | ---: |
| Thon 09 | 530 | Production value returned by dashboard | Production value returned by dashboard |
| Thon 10 | 0 | 0 | 0 |

Temporary acceptance users were created only for the acceptance run and then removed.

Cleanup:

- Temporary users removed from Thon 09 and Thon 10.
- Temporary acceptance scripts removed from `public_html`.
- Temporary token removed from `public_html`.

## Test Results

Automated checks:

- `composer run test:policies`: PASS.
- `composer run check:php`: PASS.
- `php -l` on changed PHP files: PASS.
- `npm.cmd run test:regression`: PASS.
- `php tests/tenant-resolver.test.php`: PASS.
- `php tests/portal-context.test.php`: PASS.
- `php tests/control-center-authorization.test.php`: PASS.

Notes:

- `portal-context` and `control-center-authorization` tests print env diagnostics because the local `.env` has no tenant DB credentials. The tests still passed.

## Risk Assessment

| Risk | Level | Mitigation |
| --- | --- | --- |
| BHYT default mismatch for students | Low | Covered by `InsurancePolicyTest`. |
| BHYT default mismatch for age 70+ | Low | Covered by `InsurancePolicyTest` and production acceptance. |
| Dashboard/report count changes | Low/Medium | SQL predicate output is tested and production reports were checked on two tenants. |
| Existing service callers break | Low | `HealthInsuranceDefaultService` remains as a facade. |
| Tenant regression | Low | Production acceptance passed on both Thon 09 and Thon 10. |

## Rollback Plan

Rollback is isolated:

1. Revert the Sprint 2 commit.
2. Restore production runtime files from `.tmp/sprint2-insurance-backup-20260729-135124` if immediate host rollback is required.
3. No database rollback required.
4. No storage rollback required.
5. No tenant data rollback required.

## PASS/FAIL

Implementation gate: PASS.

Policy Test Suite: PASS.

Automated regression: PASS.

Multi-tenant test: PASS.

Production acceptance on Thon 09 and Thon 10: PASS.

Database change: PASS, no database change.

UI change: PASS, no intentional UI change.

Commit recommendation: PASS.
