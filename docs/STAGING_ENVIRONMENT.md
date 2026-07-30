# Multi-Tenant Staging Environment

## Status

Current status: `Infrastructure Pending`.

Sprint 2.1 status remains:

```text
Local PASS - Waiting for Staging Infrastructure
```

This is an infrastructure readiness state, not a Sprint 2.1 source-code
failure.

## Purpose

Staging is a permanent environment for the whole system, not a temporary
environment for one Sprint.

It is used to verify:

- Community Control Center.
- Tenant Management.
- Tenant Installer.
- Deploy Center.
- Backup & Restore.
- Monitoring.
- Dashboard.
- Future Community Control Center Sprints.
- Full multi-tenant regression before production promotion.

## Environment Flow

```text
Developer
  -> Local
  -> GitHub
  -> Staging
  -> Production
```

Local:

- Used for implementation and fast feedback.
- May use local databases and local mock data.
- Must not be considered release-ready by itself.

Staging:

- Used for full-system verification.
- Must mirror production architecture.
- Must use isolated staging data.
- Must pass before a change is promoted to production.

Production:

- Used for real operation.
- Receives only changes that have passed staging.

## Branch Model

Long-term branch flow:

```text
feature/*
  -> develop
  -> staging
  -> main
```

Branch responsibilities:

- `feature/*`: isolated feature or Sprint work.
- `develop`: integration branch for reviewed work before staging.
- `staging`: deployable branch for full staging verification.
- `main`: production release branch.

Rules:

- `feature/*` must not deploy production.
- `develop` must not deploy production.
- `staging` deploys only staging.
- `main` deploys only production.
- Production promotion happens only after staging PASS.

## Multi-Tenant Staging Topology

Preferred URLs:

```text
Community Control Center staging:
https://ccc-staging.hongphongnb.com

Tenant staging:
https://thon09-staging.hongphongnb.com
https://thon10-staging.hongphongnb.com
```

Acceptable alternative:

```text
Community Control Center staging:
https://staging.hongphongnb.com

Tenant staging:
https://thon09.staging.hongphongnb.com
https://thon10.staging.hongphongnb.com
```

Each staging tenant must have:

- Dedicated database.
- Dedicated upload directory.
- Dedicated cache directory.
- Dedicated session directory.
- Dedicated log directory.
- Dedicated tenant registry record.

Staging must not share with production:

- Database.
- `.env`.
- Uploads.
- Cache.
- Sessions.
- Logs.
- Backups.
- Tenant runtime configuration.

## Recommended Hosting Layout

Production:

```text
/home/nhhon5mp/public_html
/home/nhhon5mp/production_uploads
/home/nhhon5mp/production_storage
```

Staging:

```text
/home/nhhon5mp/staging_public_html
/home/nhhon5mp/staging_uploads
/home/nhhon5mp/staging_storage/cache
/home/nhhon5mp/staging_storage/logs
/home/nhhon5mp/staging_storage/sessions
/home/nhhon5mp/staging_storage/backups
```

Staging databases:

```text
ccc_staging
thon09_staging
thon10_staging
```

The exact names may differ, but they must be clearly separated from production
databases.

## GitHub Environments

Required GitHub environments:

```text
staging
production
```

The staging environment must have its own secrets and variables.

Required staging secrets:

```text
STAGING_FTP_SERVER
STAGING_FTP_USERNAME
STAGING_FTP_PASSWORD
STAGING_DB_PASSWORD
STAGING_APP_KEY
```

Required staging variables:

```text
STAGING_FTP_SERVER_DIR
STAGING_APP_URL
STAGING_DB_HOST
STAGING_DB_DATABASE
STAGING_DB_USERNAME
```

Recommended staging variables:

```text
STAGING_FTP_PORT=21
STAGING_DB_PORT=3306
STAGING_DB_CHARSET=utf8mb4
STAGING_PLATFORM_ADMIN_DOMAINS=ccc-staging.hongphongnb.com,staging.hongphongnb.com
STAGING_PLATFORM_TENANT_DOMAIN_PATTERN={code}-staging.hongphongnb.com
STAGING_UPLOAD_PATH=/home/nhhon5mp/staging_uploads
STAGING_STORAGE_PATH=/home/nhhon5mp/staging_storage
STAGING_CACHE_PATH=/home/nhhon5mp/staging_storage/cache
STAGING_LOGS_PATH=/home/nhhon5mp/staging_storage/logs
STAGING_TENANT_PARITY_URLS=https://thon09-staging.hongphongnb.com,https://thon10-staging.hongphongnb.com
STAGING_TENANT_PARITY_REQUIRED_MODULES=control-center,tenant-management
```

Optional staging secret:

```text
STAGING_TENANT_PARITY_LOGIN_JSON
```

Use `STAGING_TENANT_PARITY_LOGIN_JSON` when authenticated tenant parity checks
are available.

## Deploy Workflow

Local:

```text
developer machine
  -> unit/smoke/browser tests
  -> feature branch commit
```

Develop:

```text
feature/*
  -> pull request or reviewed merge
  -> develop
  -> CI
```

Staging:

```text
develop
  -> merge or fast-forward to staging
  -> GitHub Actions staging workflow
  -> deploy staging artifact to staging document root
  -> staging verification
```

Production:

```text
staging PASS
  -> merge staging to main
  -> GitHub Actions production workflow
  -> deploy production artifact to production document root
  -> production verification
```

## Release Gates

A change can move from Local to Staging only when:

- Local tests PASS.
- Scope is reviewed.
- No unrelated work is mixed into the release branch.
- Any database migration is additive or has an approved migration plan.

A change can move from Staging to Production only when:

- Staging deploy PASS.
- Staging Community Control Center PASS.
- Staging tenant portals PASS.
- Staging API checks PASS.
- Staging permission checks PASS.
- Staging audit checks PASS.
- Staging responsive checks PASS.
- No Critical or High defects remain.
- Rollback path is documented.

## Staging Verification

Every Sprint should verify at minimum:

- Authentication.
- Session.
- Cookie.
- CSRF.
- Authorization.
- API.
- Console.
- Network.
- Responsive desktop/tablet/mobile.
- Audit logging for mutations.
- Permission-controlled buttons and endpoints.
- No sensitive data exposure.

Multi-tenant checks:

- Community Control Center staging loads.
- Each staging tenant loads.
- Each staging tenant uses its own database.
- Each staging tenant uses its own uploads.
- Session isolation works between staging tenants.
- No staging tenant reads production data.
- Tenant registry status is enforced.

Security checks:

- No password in UI.
- No token in UI.
- No secret in UI.
- No connection string in UI.
- No production database name or credential in staging output.

## Rollback Workflow

Staging rollback:

```text
redeploy previous known-good staging commit
restore staging database from staging backup if needed
do not touch production
```

Production rollback:

```text
redeploy previous known-good production commit
apply approved production migration rollback plan if needed
do not copy staging data into production
do not copy tenant-specific folders between environments
```

Rollback rules:

- Source rollback and database rollback are separate decisions.
- Never rollback production by copying staging files manually.
- Never use production backup files for staging restore unless explicitly
  sanitized and approved.

## Promotion Record

Each promotion from staging to production must record:

```text
Sprint or release name:
Source commit:
Staging URL:
Staging deployment time:
Staging verification result:
Known limitations:
Production approval:
Production deployment time:
Production verification result:
Rollback commit:
```

## Current Next Step

Provision the staging hosting resources and GitHub `staging` environment.

Until then, Sprint work that is otherwise complete should use:

```text
Local PASS - Waiting for Staging Infrastructure
```
