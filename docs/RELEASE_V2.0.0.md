# Release v2.0.0 - Multi-Tenant Stable

Status: Stable baseline.

## Purpose

This release marks the accepted multi-tenant production baseline for Hong Phong
Community Platform.

## Stable Foundation

The following components are considered stable:

- Shared source deployment
- Domain-based TenantResolver
- Tenant-specific environment loading
- Tenant Registry
- Tenant database isolation
- Tenant storage isolation
- Multi-tenant routing

## Freeze Rule

Do not change the foundation components unless required by a production issue or
an approved technical change. Any such change must include:

- Impact analysis
- Rollback plan
- Tenant 09 verification
- Tenant 10 verification
- Data isolation verification

## Development Direction

After this release, development focus moves from architecture stabilization to
business value:

- Tenant Installer in Community Control Center
- Advanced dashboard
- Reports and statistics
- Notifications
- GIS improvements
- AI support
- Performance
- Security
- User experience

## Multi-Tenant Acceptance Criteria

The platform is considered compatible with this release only when:

- One deploy updates all tenants using the shared source.
- Tenant 09 and Tenant 10 both run the same source.
- Each tenant uses its own database.
- Tenant data does not leak across tenants.
- No production code hard-codes real tenant names, domains, or database names.
- New tenants can be added through configuration/registry without source changes.
