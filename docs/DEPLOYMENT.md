# Deployment

Deployment uses one shared source codebase per environment. Production and
Staging must be isolated from each other.

## Environment Flow

```text
Developer
-> Local
-> GitHub
-> Staging
-> Production
```

Long-term branch flow:

```text
feature/*
-> develop
-> staging
-> main
```

Only `staging` deploys Staging. Only `main` deploys Production.

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

## Current Staging Source

Staging is an official multi-tenant environment, but the hosting resources are
currently `Infrastructure Pending`.

Preferred staging hosts:

- `ccc-staging.hongphongnb.com`
- `thon09-staging.hongphongnb.com`
- `thon10-staging.hongphongnb.com`

Preferred staging source:

- `/home/nhhon5mp/staging_public_html`

Staging must use separate databases, uploads, cache, sessions, logs, and
backups. See `docs/STAGING_ENVIRONMENT.md`.

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

## Staging Rules

- Do not point Staging at `/home/nhhon5mp/public_html`.
- Do not use the production database.
- Do not use production uploads, cache, sessions, logs, or backups.
- Do not deploy Staging from `main`.
- Do not promote to Production until Staging PASS is recorded.

## Required Checks Before Deploy

1. `git status` must show only approved changes.
2. Confirm the active DocumentRoot from cPanel or hosting configuration.
3. Inspect only the active production source under that DocumentRoot.
4. Search source for real tenant identifiers before release.
5. Confirm no tenant-specific database or domain is hard-coded.
6. Confirm migration plan, if schema changes are included.
7. Confirm rollback plan.

For Staging, also confirm:

1. `staging` branch contains the intended release candidate.
2. Staging GitHub environment uses only `STAGING_*` secrets and variables.
3. Staging tenant databases are isolated from production databases.
4. Staging tenant URLs resolve to the staging DocumentRoot.

## Required Checks After Deploy

At minimum verify:

- Tenant 09 login.
- Tenant 10 login.
- Dashboard loads for both tenants.
- CRUD works for a representative tenant-scoped module.
- Upload path is tenant-isolated.
- Export/PDF still works.
- No tenant sees data from another tenant.

For Staging, verify the same behavior against staging tenants only. Staging
verification must not mutate production data.

## Rollback

Rollback source by redeploying the previous known-good commit or tag.

Do not rollback by copying a tenant-specific source folder over the shared
source. Tenant data, tenant env files, and tenant uploads must be preserved.

If a schema migration was applied, use the migration rollback plan for the
specific release. Do not drop or truncate tenant databases during source
rollback.
