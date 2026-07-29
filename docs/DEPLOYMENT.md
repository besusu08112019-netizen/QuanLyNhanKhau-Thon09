# Deployment

Production deployment uses one shared source codebase.

## Target Model

```text
GitHub
-> deploy once
-> shared source
-> tenant domains resolve by host
-> each tenant loads its own configuration and database
```

Deploying source for one tenant only is not allowed.

## Current Production Source

Always verify the active DocumentRoot from cPanel or the hosting
configuration before inspecting production source.

Current production DocumentRoot:

- `hongphongnb.com` -> `/home/nhhon5mp/public_html`
- `thon09.hongphongnb.com` -> `/home/nhhon5mp/public_html`
- `thon10.hongphongnb.com` -> `/home/nhhon5mp/public_html`

The active production source is therefore:

- `/home/nhhon5mp/public_html`

All production checks must inspect this source only.

## Legacy Directories

The following directories may still exist on hosting for rollback or historical
reference:

- `/home/nhhon5mp/thon09.hongphongnb.com`
- `/home/nhhon5mp/thon10.hongphongnb.com`

These directories are legacy directories. They are not the active
DocumentRoot and must not be used to determine production source state.

Do not use legacy directories for:

- Production source verification.
- Multi-tenant compatibility checks.
- Feature acceptance.
- Code drift conclusions.

They may only be inspected when an explicit rollback investigation requires it.

## Production Rules

- Do not copy source between tenant folders.
- Do not deploy separately for each tenant.
- Do not hard-code tenant domains or database names in source.
- Do not overwrite tenant `.env.*` files during source deploy.
- Do not delete tenant uploads, backups, logs, or databases during deploy.

## Required Checks Before Deploy

1. `git status` must show only approved changes.
2. Confirm the active DocumentRoot from cPanel or hosting configuration.
3. Inspect only the active production source under that DocumentRoot.
4. Search source for real tenant identifiers before release.
5. Confirm no tenant-specific database or domain is hard-coded.
6. Confirm migration plan, if schema changes are included.
7. Confirm rollback plan.

## Required Checks After Deploy

At minimum verify:

- Tenant 09 login.
- Tenant 10 login.
- Dashboard loads for both tenants.
- CRUD works for a representative tenant-scoped module.
- Upload path is tenant-isolated.
- Export/PDF still works.
- No tenant sees data from another tenant.

## Rollback

Rollback source by redeploying the previous known-good commit or tag.

Do not rollback by copying a tenant-specific source folder over the shared
source. Tenant data, tenant env files, and tenant uploads must be preserved.

If a schema migration was applied, use the migration rollback plan for the
specific release. Do not drop or truncate tenant databases during source
rollback.
