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

## Development Flow

Use this flow for every production-bound work item:

```text
Analyze requirement
  -> Change source code
  -> Self-review
  -> Run tests
  -> Build production
  -> Local QA
  -> Commit
  -> Push GitHub main
  -> GitHub Actions
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
4. Push to `main`.
5. GitHub Actions runs the production workflow.
6. The workflow runs pre-deploy checks.
7. The workflow builds `dist/production`.
8. The workflow validates the artifact.
9. The workflow uploads the `production-artifact` for audit.
10. The workflow deploys `dist/production/` to `public_html/` by FTPS.
11. Run production QA.

If any build, test, artifact, or deploy step fails, stop. Production must not be considered updated until the workflow finishes successfully.

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
