# Release Process

Release process is production-first and multi-tenant-first.

## Release Baseline

`v2.0.0` is the Multi-Tenant Stable baseline.

## Pre-Release Checklist

- Feature scope approved.
- No tenant-specific hard-code.
- No domain-specific hard-code.
- No database-specific hard-code.
- TenantResolver remains compatible.
- Tenant 09 tested.
- Tenant 10 tested.
- Data isolation tested.
- Deploy-once behavior confirmed.
- Rollback plan prepared.

## Release Steps

```text
Review changes
-> Run tests
-> Smoke test Tenant 09
-> Smoke test Tenant 10
-> Deploy once to shared source
-> Verify both tenants
-> Tag release
-> Record release notes
```

## Pull Request Checklist

Every pull request must confirm:

- Multi-tenant compatibility.
- No tenant-specific source code.
- No cross-tenant data access.
- Tenant storage remains isolated.
- Source deploy updates all tenants.
- Rollback is possible.

## Tagging

Use semantic version tags for stable releases.

Examples:

- `v2.0.0` - Multi-Tenant Stable
- `v2.0.1` - Patch release
- `v2.1.0` - Feature release

Do not move an existing release tag without explicit approval.
