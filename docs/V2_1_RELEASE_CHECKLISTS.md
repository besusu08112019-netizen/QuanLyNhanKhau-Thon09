# Version 2.1 Release Checklists

These checklists keep Version 2.1 feature and optimization releases traceable without changing the production deploy architecture.

## QA Checklist

- [ ] Login and logout work with a production account.
- [ ] Dashboard cards, charts, and filters load real data.
- [ ] Household, citizen, temporary residence, temporary absence, population change, house, public asset, business, agriculture, livestock, vehicle, and contribution modules can list and open details.
- [ ] Create, edit, delete, search, filter, sort, paginate, import, export Excel, export PDF, and print are checked for every module touched by the sprint.
- [ ] GIS marker, cluster, popup, polygon, layer, GPS, route, search, and Esri imagery flows are checked on desktop and mobile.
- [ ] Reports preview, print, Excel, and PDF output are checked for changed modules.
- [ ] Mobile widths 320, 360, 375, 390, 414, and tablet 768 are checked for overflow, dialogs, bottom navigation, FAB, and touch workflows.

## Deploy Checklist

- [ ] Work is complete for the sprint scope.
- [ ] No direct production edits were made.
- [ ] `npm run check:js` passes.
- [ ] `npm run test:platform` passes.
- [ ] `npm run test:navigation-cleanup` passes.
- [ ] `node tests/security-regression.test.js` passes.
- [ ] PHP syntax check passes for all PHP files.
- [ ] `npm run build:production` passes.
- [ ] `npm run validate:artifact` passes.
- [ ] Commit message follows the release commit standard.
- [ ] Push goes to `main` and triggers the GitHub Actions FTPS workflow.

## Backup Checklist

- [ ] Database backup exists before a production release.
- [ ] Production file backup exists before a production release.
- [ ] Backup files are outside webroot or blocked from HTTP access.
- [ ] Restore procedure is documented for the current release.
- [ ] Backup logs do not expose secrets or personal data.

## Security Checklist

- [ ] `.env`, logs, backups, and internal upload metadata are not included in the artifact.
- [ ] Security headers are still present: HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
- [ ] API endpoints touched by the sprint require authentication and server-side permissions.
- [ ] Upload endpoints touched by the sprint keep extension, MIME, magic-byte, size, and path validation.
- [ ] Errors shown to users do not expose stack traces, SQL, server paths, tokens, or credentials.
- [ ] Audit logs record sensitive actions without storing passwords, tokens, cookies, session IDs, full CCCD, or unnecessary contact data.

## Release Checklist

- [ ] GitHub Actions build passes.
- [ ] Production deploy via FTPS passes.
- [ ] Production smoke QA passes for Login, Dashboard, GIS, Upload, Reports, API, Mobile, Desktop, and PWA.
- [ ] Release report records version, commit SHA, deploy time, files changed, build result, deploy result, QA result, security result, PWA result, mobile result, desktop result, and overall PASS/FAIL.
- [ ] Rollback target is known before marking the release PASS.
