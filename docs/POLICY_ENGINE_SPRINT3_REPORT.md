# Policy Engine Sprint 3 Report - Household Relation Policy

## Scope

Sprint 3 only refactors household relationship policy within one household.

Included:

- Standard household relationship normalization.
- Household-head detection.
- Child relationship inference from father/mother name and gender.
- Grandchild relationship inference within the same household.
- Legacy great-grandchild inference compatibility.
- Import relationship normalization and missing-relationship inference.
- Citizen create/update relationship normalization.
- Policy Test Suite coverage for Household Relation Policy.

Not included:

- Relationship inference across households.
- Social Support Policy.
- Education Policy.
- Employment Policy.
- Dashboard refactor.
- Public API changes.
- UI changes.
- Database changes.
- Import format changes.

## Changed Files

Runtime:

- `app/Policies/HouseholdRelationPolicy.php`
- `app/Services/HouseholdRelationshipInferenceService.php`
- `app/Models/Citizen.php`
- `app/Controllers/ImportController.php`

Tests:

- `tests/policies/household/HouseholdRelationPolicyTest.php`

Docs:

- `docs/POLICY_ENGINE_SPRINT3_REPORT.md`

## What Changed

- Added `HouseholdRelationPolicy` as the shared source for household relationship normalization and inference.
- Kept `HouseholdRelationshipInferenceService` as the DB integration layer for existing import workflow.
- Moved name normalization and relation inference out of the service into the policy.
- Citizen create/update now normalizes relationship labels through `HouseholdRelationPolicy`.
- Import now normalizes provided relationship labels through `HouseholdRelationPolicy`.
- Missing import relationship still uses the existing inference flow, now delegated to `HouseholdRelationPolicy`.

## Behavior Compatibility

Preserved behavior:

- Existing import flow still infers missing relationships after successful person import.
- Existing locked relationships are not overwritten.
- Ambiguous household-head names are not inferred.
- Existing one-household boundary is preserved.
- Existing great-grandchild inference is retained as compatibility behavior.

Intentional standardization:

- Generic `con` can now normalize to `Con trai` or `Con gái` using gender.
- Empty fallback relationship now maps to `Người thân khác`, matching the approved standard relationship list.

No database migration was added.

## Source Review

Relationship inference rule moved to `HouseholdRelationPolicy`:

- Household head from household head name.
- Son/daughter from father/mother name matching household head.
- Grandchild from parent relationship within the same household.
- Son-in-law/daughter-in-law from child-parent relation context within the same household.
- Legacy great-grandchild compatibility.

Remaining relationship references outside policy:

- Display labels.
- Report headers.
- SQL ordering with `Chủ hộ` first.
- Single household-head validation.
- Household head synchronization.
- Audit/movement labels.

These are not independent relationship inference rules and were not expanded in Sprint 3 to avoid scope creep.

TODO:

- None blocking for Sprint 3.
- Future cleanup may replace repeated `Chủ hộ` ordering literals with a shared SQL helper if Product Owner approves a technical-debt sprint.

## Production Acceptance

Production/Staging acceptance was executed on:

- `https://thon09.hongphongnb.com`
- `https://thon10.hongphongnb.com`

Acceptance checks:

| Area | Thon 09 | Thon 10 |
| --- | --- | --- |
| Tenant resolution by domain | PASS | PASS |
| Temporary household creation | PASS | PASS |
| Temporary citizen creation | PASS | PASS |
| Household relationship inference | PASS | PASS |
| Head inference | PASS | PASS |
| Son inference | PASS | PASS |
| Daughter inference | PASS | PASS |
| Grandchild inference | PASS | PASS |
| Tenant cleanup | PASS | PASS |
| Multi-tenant isolation | PASS | PASS |

Observed acceptance result:

| Tenant | Inferred Rows | Result |
| --- | ---: | --- |
| Thon 09 | 4 | `Chủ hộ`, `Con trai`, `Con gái`, `Cháu` |
| Thon 10 | 4 | `Chủ hộ`, `Con trai`, `Con gái`, `Cháu` |

Cleanup:

- Temporary households removed from Thon 09 and Thon 10.
- Temporary citizens removed from Thon 09 and Thon 10.
- Temporary acceptance script removed from `public_html/.tmp`.

## Test Results

Automated checks:

- `php -l app/Policies/HouseholdRelationPolicy.php`: PASS.
- `php -l app/Services/HouseholdRelationshipInferenceService.php`: PASS.
- `php -l app/Models/Citizen.php`: PASS.
- `php -l app/Controllers/ImportController.php`: PASS.
- `php -l tests/policies/household/HouseholdRelationPolicyTest.php`: PASS.
- `composer run test:policies`: PASS.
- `composer run test:policy-regression`: PASS.
- `composer run check:php`: PASS.
- `npm.cmd run test:regression`: PASS.
- `php tests/tenant-resolver.test.php`: PASS.
- `php tests/portal-context.test.php`: PASS.
- `php tests/control-center-authorization.test.php`: PASS.

Notes:

- `portal-context` and `control-center-authorization` tests print env diagnostics because the local `.env` has no tenant DB credentials. The tests still passed.

## Risk Assessment

| Risk | Level | Mitigation |
| --- | --- | --- |
| Import relationship inference changes unexpectedly | Low/Medium | Covered by policy tests and production acceptance on two tenants. |
| Gender-based child relation changes old generic `Con` output | Medium | This matches the approved Sprint 3 standard relation list and is covered by tests. |
| Existing reports/order by `Chủ hộ` break | Low | No report/order SQL was changed except service sync using policy constant. |
| Tenant data mix-up | Low | Production acceptance ran independently on Thon 09 and Thon 10 with cleanup. |
| DB/UI/API regression | Low | No database, UI, or public API change was made. |

## Rollback Plan

Rollback is isolated:

1. Revert the Sprint 3 commit.
2. Restore production runtime files from `.tmp/sprint3-household-relation-backup` if immediate host rollback is required.
3. Delete `app/Policies/HouseholdRelationPolicy.php` from production if rolling back before Git revert.
4. No database rollback required.
5. No storage rollback required.
6. No tenant data rollback required.

## PASS/FAIL

Implementation gate: PASS.

Policy Test Suite: PASS.

Automated regression: PASS.

Multi-tenant test: PASS.

Production acceptance on Thon 09 and Thon 10: PASS.

Database change: PASS, no database change.

UI change: PASS, no intentional UI change.

Public API change: PASS, no public API change.

Commit recommendation: PASS.
