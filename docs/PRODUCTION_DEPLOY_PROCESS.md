# Production Release Process

Production uses one deployment pipeline only:

```text
Git
  -> GitHub
  -> GitHub Actions
  -> Build Production
  -> FTPS
  -> public_html
```

cPanel Git Deploy is not used for production. `.cpanel.yml` must not exist in the repository. Do not edit production files directly, upload individual files, or build on production.

Staging uses a separate pipeline:

```text
Git staging branch
  -> GitHub Actions staging workflow
  -> Build Staging
  -> FTPS
  -> staging DocumentRoot
```

Staging must be multi-tenant and isolated from production. See
`docs/STAGING_ENVIRONMENT.md`.

## Development Flow

Use this flow for every production-bound work item:

```text
Analyze requirement
  -> Change source code
  -> Self-review
  -> Run tests
  -> Build production
  -> Local QA
  -> Commit feature branch
  -> Merge to develop
  -> Merge to staging
  -> GitHub Actions Staging
  -> Staging QA
  -> Merge to main
  -> GitHub Actions Production
  -> Build Production
  -> Deploy FTPS
  -> Production QA
  -> Release PASS
```

Do not commit incomplete work. If a change is exploratory, keep it local until the work item is coherent and checked.

## Commit Standard

Commit messages must describe the actual change. Use scoped conventional-style messages:

```text
fix(gis): fix marker rendering after zoom
fix(upload): persist public asset photo path
fix(report): fix citizen Excel export
feat(dashboard): add insurance statistics
refactor(api): standardize auth middleware
security(auth): block guest write access
docs(release): document production release process
```

Do not use vague messages:

```text
update
fix
test
123
```

## When To Deploy

Deploy only after a complete work item is finished and locally checked, for example GIS, Mobile, Reports, Dashboard, Upload, or Security hardening.

Do not deploy after every small edit such as one CSS line, one label, or a partial experiment. Batch small changes into a complete verified item.

## Required Flow

1. Codex changes source code in Git.
2. Run local checks that match the risk of the change.
3. Commit with a clear message.
4. Push to `feature/*`.
5. Merge reviewed work to `develop`.
6. Merge release candidate to `staging`.
7. GitHub Actions runs the staging workflow.
8. Staging workflow runs pre-deploy checks.
9. Staging workflow builds and deploys the staging artifact.
10. Run staging QA and record PASS/FAIL.
11. Merge `staging` to `main` only after staging PASS.
12. GitHub Actions runs the production workflow.
13. The workflow runs pre-deploy checks.
14. The workflow builds `dist/production`.
15. The workflow validates the artifact.
16. The workflow uploads the `production-artifact` for audit.
17. The workflow deploys `dist/production/` to `public_html/` by FTPS.
18. Run production QA.

If any build, test, artifact, staging deploy, staging verification, production
build, production deploy, or production verification step fails, stop.
Production must not be considered updated until the production workflow finishes
successfully.

## Promotion From Staging To Production

Promotion is allowed only when all conditions are met:

```text
Local PASS
Staging deploy PASS
Staging Community Control Center PASS
Staging tenant portals PASS
Staging API PASS
Staging permission checks PASS
Staging audit checks PASS
Staging responsive checks PASS
No Critical or High defects
Rollback commit identified
```

Promotion steps:

```text
git checkout main
git merge --ff-only staging
git push origin main
```

Do not cherry-pick only part of a staging-tested release unless the staging
verification is repeated for the new production candidate.

## Staging Rollback

Rollback Staging by redeploying the previous known-good staging commit:

```text
git checkout staging
git reset --hard <known-good-staging-commit>
git push --force-with-lease origin staging
```

Use force only for Staging rollback and only after confirming the target commit.
Do not use this process for Production.

If a staging database migration needs rollback, use a staging database backup or
an approved staging migration rollback. Never use production data to repair
staging unless it has been sanitized and explicitly approved.

## Production Rollback

Rollback Production by deploying a previous known-good production commit through
the production workflow. Do not copy staging files or staging databases into
production.

## Automated Pre-Deploy Checks

The production workflow must run these before FTPS deploy:

```text
npm run check:js
npm run test:platform
npm run test:navigation-cleanup
node tests/security-regression.test.js
php -l for PHP source files
npm run build:production
npm run validate:artifact
```

The workflow stops before deploy when any check fails.

The workflow must deploy only from:

```text
local-dir: ./dist/production/
server-dir: ./
protocol: ftps
```

The workflow must preserve runtime data and secrets:

```text
.env
.env.*
config/database.php
uploads/**
storage/cache/**
backups/**
```

## Production QA After Deploy

Production is a shared-source multi-tenant system. One commit and one deploy must update the source code used by every tenant. Tenant-specific data must remain isolated.

Source changes that must reach every tenant in the same deploy:

```text
New features
Bug fixes
UI changes
Modules
Dashboard
Policy Engine
Business rules
Executive Dashboard
Data Quality
Work Management
```

Never synchronize these tenant-specific assets or runtime states as part of source deploy:

```text
Database
Data
Logo
Background
Unit name
Domain
Uploads
Storage
Cache
Session
Tenant .env/config
```

After FTPS finishes, the workflow must run `tools/tenant_parity_check.php` for:

```text
https://thon09.hongphongnb.com
https://thon10.hongphongnb.com
https://ccc01.hongphongnb.com
```

The deployment is PASS only when every tenant returns the same source asset version, login works, authenticated `/api/me` works, and public JSON endpoints return valid JSON. If one tenant has an older source version or is missing a module introduced by the release, treat it as a deployment failure, not a business-rule issue.

Configure `TENANT_PARITY_LOGIN_JSON` as a GitHub Actions secret with per-tenant smoke-test credentials. Optional repository/environment variables:

```text
TENANT_PARITY_URLS
TENANT_PARITY_REQUIRED_MODULES
```

Run QA after every successful deploy:

```text
Authentication
Login
Logout
Session
Dashboard
Statistics
GIS
Marker
Popup
Polygon
Layer
Upload
Upload photo
Preview
Delete
Reports
Excel
PDF
Print
API
HTTP status
Authentication
Permission
Tenant source parity
Thon 09 version
Thon 10 version
tenant-test version
Dashboard on all tenants
Health Check on all tenants
New module visible on all tenants
Mobile
Responsive
Bottom navigation
FAB
PWA
Manifest
Service worker
Offline
```

If application credentials are not available, report authenticated QA as blocked and still verify public/PWA/API unauthenticated behavior.

## Deployment Record Template

Record the result after every production deploy:

```text
Release version:
Commit SHA:
Deploy time:
Work item:
Files changed:
GitHub Actions run:
Build: PASS/FAIL
Deploy: PASS/FAIL
Production artifact sync: PASS/FAIL
Security: PASS/FAIL
Authentication: PASS/FAIL/BLOCKED
Login: PASS/FAIL/BLOCKED
Logout: PASS/FAIL/BLOCKED
Session: PASS/FAIL/BLOCKED
Dashboard: PASS/FAIL/BLOCKED
Statistics: PASS/FAIL/BLOCKED
GIS: PASS/FAIL/BLOCKED
Marker: PASS/FAIL/BLOCKED
Popup: PASS/FAIL/BLOCKED
Polygon: PASS/FAIL/BLOCKED
Layer: PASS/FAIL/BLOCKED
Upload: PASS/FAIL/BLOCKED
Upload photo: PASS/FAIL/BLOCKED
Preview: PASS/FAIL/BLOCKED
Delete: PASS/FAIL/BLOCKED
Reports: PASS/FAIL/BLOCKED
Excel: PASS/FAIL/BLOCKED
PDF: PASS/FAIL/BLOCKED
Print: PASS/FAIL/BLOCKED
API: PASS/FAIL/BLOCKED
Tenant source parity: PASS/FAIL
Thon 09: PASS/FAIL
Thon 10: PASS/FAIL
tenant-test: PASS/FAIL
Module parity: PASS/FAIL
Mobile: PASS/FAIL/BLOCKED
Desktop: PASS/FAIL/BLOCKED
PWA: PASS/FAIL/BLOCKED
Overall: PASS/FAIL
Notes:
```

Use `docs/RELEASE_REPORT_TEMPLATE.md` for the full report format.

## Rollback

Rollback must also use GitHub/GitHub Actions/FTPS, not direct production edits.

1. Identify the last known good commit or tag.
2. Create or select a Git tag for that version.
3. Use GitHub Release notes to document the rollback target.
4. Deploy the previous version through GitHub Actions.
5. Run production QA.
6. Mark rollback PASS only when QA passes.

## Logging And Traceability

Keep these logs for each release:

```text
GitHub Actions build log
GitHub Actions deploy log
production-artifact
QA notes
Release report
```

The release record must make it possible to trace a production state back to commit SHA, deploy time, workflow run, artifact, and QA result.

## Security Release Gate

Every release must verify:

```text
.env is not public
logs are not public
backups are not public
internal uploads are not executable
debug mode is not exposed
stack traces are not exposed
raw SQL errors are not exposed
security headers are present
```

## Failure Policy

If build or deploy fails:

1. Do not edit production directly.
2. Do not use cPanel Git Deploy.
3. Diagnose the failed workflow step.
4. Fix in source code or GitHub/cPanel FTP configuration.
5. Commit and push again.
6. Re-run production QA only after GitHub Actions deploys successfully.

If production QA fails after a successful deploy, mark the release as FAIL and either fix forward through the same pipeline or rollback.

## Codex Working Rules

For each completed work item, Codex must:

1. Review the changed scope.
2. Check likely regression impact on adjacent modules.
3. Run relevant tests and `npm run build:production`.
4. Commit with a clear scoped message.
5. Push to GitHub when deployment is requested.
6. Monitor GitHub Actions where possible.
7. Report deploy and QA as PASS only after the workflow and production checks pass.

If QA or deploy fails, Codex must stop, report the exact failing step, and not claim the release is complete.
