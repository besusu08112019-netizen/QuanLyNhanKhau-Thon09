# Community Control Center Staging Environment

## Status

Current status: `Pending Staging Verification`.

Sprint 2.1 source is Local PASS and pushed on a feature branch, but staging
cannot be verified until hosting infrastructure and GitHub `staging`
environment secrets are provisioned.

This is an infrastructure blocker, not a Sprint 2.1 source-code failure.

## Environment Model

Production:

```text
Branch: main
URL: https://hongphongnb.com
DocumentRoot: /home/nhhon5mp/public_html
Database: production database
Runtime data: production uploads, cache, sessions, logs
Workflow: .github/workflows/deploy-ftp.yml
GitHub environment: production
```

Staging:

```text
Branch: staging
Preferred URL: https://staging.hongphongnb.com
Alternative URL: https://ccc-staging.hongphongnb.com
DocumentRoot: separate staging directory, for example /home/nhhon5mp/staging_public_html
Database: separate staging database
Runtime data: separate staging uploads, cache, sessions, logs
Workflow: .github/workflows/deploy-staging-ftp.yml
GitHub environment: staging
```

## Hard Constraints

- Staging must not share the production database.
- Staging must not share production `.env`.
- Staging must not share production uploads.
- Staging must not share production cache, sessions, or logs.
- Staging deployment must not push to `main`.
- Sprint verification must follow `Local -> Staging -> Production`.
- Production deployment remains available only from `main`.

## Required Hosting Setup

Create one of these subdomains in cPanel:

```text
staging.hongphongnb.com
ccc-staging.hongphongnb.com
```

Set the subdomain DocumentRoot to a directory that is not
`/home/nhhon5mp/public_html`, for example:

```text
/home/nhhon5mp/staging_public_html
```

Create separate runtime directories:

```text
/home/nhhon5mp/staging_uploads
/home/nhhon5mp/staging_storage/cache
/home/nhhon5mp/staging_storage/logs
/home/nhhon5mp/staging_storage/sessions
```

Create a separate staging database and user. Do not reuse production database
credentials.

## GitHub Environment Configuration

Create GitHub environment:

```text
staging
```

Required secrets:

```text
STAGING_FTP_SERVER
STAGING_FTP_USERNAME
STAGING_FTP_PASSWORD
STAGING_DB_PASSWORD
STAGING_APP_KEY
```

Required variables:

```text
STAGING_FTP_SERVER_DIR
STAGING_APP_URL
STAGING_DB_HOST
STAGING_DB_DATABASE
STAGING_DB_USERNAME
```

Recommended variables:

```text
STAGING_FTP_PORT=21
STAGING_DB_PORT=3306
STAGING_DB_CHARSET=utf8mb4
STAGING_PLATFORM_ADMIN_DOMAINS=staging.hongphongnb.com,ccc-staging.hongphongnb.com
STAGING_PLATFORM_TENANT_DOMAIN_PATTERN={code}.staging.hongphongnb.com
STAGING_UPLOAD_PATH=/home/nhhon5mp/staging_uploads
STAGING_STORAGE_PATH=/home/nhhon5mp/staging_storage
STAGING_CACHE_PATH=/home/nhhon5mp/staging_storage/cache
STAGING_LOGS_PATH=/home/nhhon5mp/staging_storage/logs
```

## Workflow

Development:

```text
feature branch
  -> local test
  -> merge or fast-forward to staging
  -> deploy staging
  -> staging verification
```

Release:

```text
staging PASS
  -> merge to main
  -> production deploy
  -> production verification
```

## Sprint 2.1 Staging Verification Checklist

Tenant Management:

- List Tenant.
- View Tenant details.
- Create Tenant metadata.
- Update Tenant metadata.
- Lock Tenant.
- Unlock Tenant.
- Soft-delete Tenant.
- View Activity.
- Search.
- Filter.
- Sort.
- Paginate.

Security:

- No password is shown.
- No connection string is shown.
- No token is shown.
- No secret is shown.
- API permission checks remain active.
- Audit records are written for mutations.

Responsive:

- Desktop.
- Tablet.
- Mobile.
- No horizontal overflow.
- Modals are not clipped.
- Buttons remain reachable.

Regression:

- Community Control Center Phase 1 still loads.
- Overview still loads.
- Administrative Unit module still loads.
- Users module still loads.
- Permission module still loads.
- Monitoring and Audit still load.

## Acceptance State Names

Use this status while staging infrastructure is not ready:

```text
Pending Staging Verification
```

Use this status only after all staging checks pass:

```text
Sprint 2.1 - Tenant Management: ACCEPTED
```
