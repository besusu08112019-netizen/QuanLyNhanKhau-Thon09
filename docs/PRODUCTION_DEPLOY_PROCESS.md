# Production Deploy Process

Production uses one deployment pipeline only:

```text
Git
  -> GitHub
  -> GitHub Actions
  -> Build Production
  -> FTPS
  -> public_html
```

cPanel Git Deploy is not used for production. `.cpanel.yml` must not exist in the repository. Do not edit production files directly and do not copy individual files to the host.

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
8. The workflow uploads the `production-artifact` for audit.
9. The workflow deploys `dist/production/` to `public_html/` by FTPS.
10. Run production QA.

If any build, test, artifact, or deploy step fails, stop. Production must not be considered updated until the workflow finishes successfully.

## Automated Pre-Deploy Checks

The production workflow must run these before FTPS deploy:

```text
npm run check:js
npm run test:platform
npm run test:navigation-cleanup
node tests/security-regression.test.js
npm run build:production
```

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
Login
Dashboard
GIS
Upload
Reports
API
Mobile
PWA
```

If application credentials are not available, report authenticated QA as blocked and still verify public/PWA/API unauthenticated behavior.

## Deployment Record Template

Record the result after every production deploy:

```text
Commit SHA:
Deploy time:
Work item:
GitHub Actions run:
Build: PASS/FAIL
Deploy: PASS/FAIL
Production artifact sync: PASS/FAIL
Login: PASS/FAIL/BLOCKED
Dashboard: PASS/FAIL/BLOCKED
GIS: PASS/FAIL/BLOCKED
Upload: PASS/FAIL/BLOCKED
Reports: PASS/FAIL/BLOCKED
API: PASS/FAIL/BLOCKED
Mobile: PASS/FAIL/BLOCKED
PWA: PASS/FAIL/BLOCKED
Notes:
```

## Failure Policy

If build or deploy fails:

1. Do not edit production directly.
2. Do not use cPanel Git Deploy.
3. Diagnose the failed workflow step.
4. Fix in source code or GitHub/cPanel FTP configuration.
5. Commit and push again.
6. Re-run production QA only after GitHub Actions deploys successfully.
