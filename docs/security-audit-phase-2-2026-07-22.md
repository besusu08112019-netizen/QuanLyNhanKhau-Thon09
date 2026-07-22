# Security Audit Phase 2 - 2026-07-22

Security Preflight không chạy trong sandbox do giới hạn thực thi executable ngoài workspace, không phải do máy thiếu Python.

## Scope

- Authentication, authorization, API, database access, upload, session/cookie, GIS, reports, import/export, backup/restore, deploy pipeline, mobile/desktop API usage.
- Review method: repository-wide code review, targeted attack-path review, static pattern scan, dependency scan, browser QA, and regression checks.

## Critical

No Critical finding remains open in this phase.

## High

### H1. Source-module authorization bypass in Operation Center and Insight APIs

- Root cause: aggregate endpoints authorized only with broad `dashboard:read`, then queried and returned household, citizen, GIS, movement, file, and audit-derived data.
- Affected files:
  - `app/Controllers/OperationCenterController.php`
  - `app/Controllers/InsightController.php`
  - `app/Models/OperationCenter.php`
  - `app/Models/SystemInsight.php`
- Exploit path: a role with `dashboard:read` but without `citizen:read` or `household:read` could call `/api/operation-center/search`, `/api/operation-center/quick-profile`, or `/api/insights/search` and receive citizen identity, phone, address, member, GPS, file metadata, or timeline data.
- Impact: broken access control / IDOR-style data exposure across personal-data modules.
- Fix applied: every affected endpoint now requires the relevant source module permission before querying. Quick profile additionally requires `file:read` when returning file metadata, and timeline requires `logs:read` plus `movement:read`.
- Result after fix: security regression asserts the source permission checks; PHP lint and Node regression pass.

### H2. Non-super users could become or create `ADMIN` if granted user mutation

- Root cause: role assignment protection only handled `SUPER_ADMIN`, while `ADMIN` still grants broad backend access through `User::can()`.
- Affected files:
  - `app/Models/User.php`
  - `app/Controllers/UserController.php`
- Exploit path: a lower role that obtains `user:update` could submit `role=ADMIN` for itself or another account.
- Impact: privilege escalation to broad administrative access.
- Fix applied: role assignment guard now treats both `SUPER_ADMIN` and `ADMIN` as protected roles. Only `SUPER_ADMIN` can assign, promote, demote, or modify those roles.
- Result after fix: security regression checks the protected-role list and role assignment guard.

## Medium

### M1. Permission matrix management was not Super Admin-only

- Root cause: permission APIs trusted `permission:read` and `permission:update`, so a misconfigured role could manage the permission matrix.
- Affected files:
  - `app/Controllers/PermissionController.php`
  - `app/Models/Permission.php`
- Exploit path: a role with `permission:update` could grant its own role sensitive module actions, then chain into user or export APIs.
- Impact: self-service privilege escalation after one permission misconfiguration.
- Fix applied: permission read/update endpoints now require `requireSuperAdmin()`. Permission writes are constrained to server allowlists for role, module, and action.
- Result after fix: security regression verifies Super Admin enforcement and allowlist presence.

### M2. Report BI and operation export used report/dashboard permission without source mapping

- Root cause: typed reports used `requireReportSourcePermissions()`, but BI/report-center and operation executive export did not apply the same source-module authorization model.
- Affected files:
  - `app/Controllers/ReportController.php`
  - `app/Controllers/OperationCenterController.php`
- Exploit path: a role with `report:read` or `report:export` but missing one of the source permissions could access cross-module metrics from BI or operation-center export.
- Impact: unauthorized aggregate disclosure across household, citizen, movement, GIS, business, agriculture, livestock, vehicles, contributions, houses, and public assets.
- Fix applied: BI/report-center and operation export now require the union of contributing source modules before building data.
- Result after fix: security regression asserts `bi-dashboard` source mapping and operation source checks.

### M3. Production deploy used plaintext FTP

- Root cause: GitHub Actions deployment used `protocol: ftp` for credentials and file upload.
- Affected file:
  - `.github/workflows/deploy-ftp.yml`
- Exploit path: a network observer between GitHub Actions and hosting could capture FTP credentials or tamper with uploaded files.
- Impact: production file-write compromise outside application controls.
- Fix applied: deployment action now uses `protocol: ftps`.
- Result after fix: security regression rejects `protocol: ftp` and requires `protocol: ftps`.

### M4. cPanel deploy deny-list was weaker than GitHub deploy

- Root cause: cPanel rsync excluded only a subset of sensitive/runtime paths.
- Affected file:
  - `.cpanel.yml`
- Exploit path: if cPanel deploy runs from a working tree containing runtime logs, backups, storage artifacts, or deploy state, those files could be copied to docroot.
- Impact: accidental publication of backup/runtime artifacts.
- Fix applied: cPanel rsync now excludes `.github/`, `.deploy.env`, FTP state, logs, `backups/`, `storage/`, caches, outputs, and test artifacts.
- Result after fix: security regression verifies key excludes.

## Low

### L1. FTP deploy state file was tracked in source

- Root cause: `.gitignore` ignored `.ftp-deploy-sync-state.json` but not `.ftp-deploy-sync-state-utf8.json`.
- Affected files:
  - `.ftp-deploy-sync-state-utf8.json`
  - `.gitignore`
- Exploit path: deploy metadata including local file inventory and hashes remains in repository history and can reveal operational structure.
- Impact: information disclosure and accidental `.env` deployment history metadata.
- Fix applied: removed the tracked state file and added `.ftp-deploy-sync-state-utf8.json` to `.gitignore`.
- Result after fix: security regression asserts the file is absent and ignored.

## Reviewed With No New Finding

- Authentication/session: token lookup, CSRF header validation for state-changing authenticated requests, session revocation on logout, token hashing, password verification/hash policy.
- Upload/import: MIME and extension allowlists, random server filenames, double-extension resistance, SVG restrictions, XLSX zip entry size/ratio checks.
- Backup/restore: Super Admin restore requirement and HMAC-signed SQL backups.
- GIS: controller-level GIS and sibling module permission checks from Round 2 remain in place.
- Database: reviewed dynamic SQL usage was constrained to internal allowlists, integer limits, or prepared parameters in the checked flows.
- Logging: sensitive key redaction and exception sanitization from prior hardening remain in place.

## Dependency Scan

- npm: `npm audit --audit-level=moderate` completed with 0 vulnerabilities.
- Composer: `composer audit` could not run against installed packages because this workspace has `composer.json` but no `composer.lock` or installed Composer vendor set. No conclusion was made from missing runtime packages.

## Verification

- `php -l` on all PHP files: PASS.
- `node tests/security-regression.test.js`: PASS.
- `npm run check:js`: PASS.
- `npm run test:platform`: PASS.
- `npm run test:navigation-cleanup`: PASS.
- `npm audit --audit-level=moderate`: PASS, 0 vulnerabilities.
- `composer audit`: NOT APPLICABLE in this workspace because no installed Composer packages or `composer.lock` are present.
- `git diff --check`: PASS; only Windows CRLF conversion warnings were emitted.
- `npm run test:browser`: PASS, 256 passed and 5 skipped.
- Browser QA blockers fixed before commit: desktop create actions remain visible above 1024px, mobile bottom navigation is constrained to five core destinations, service-worker cache naming matches the regression contract, GIS map toolbar follows the horizontal toolbar contract, and compact module summaries use `Tóm tắt` instead of duplicating the mobile `Tổng quan` contract.

## Remaining Risk

- Historical Git history may still contain the removed FTP state file. Rotate deploy credentials if the previous tracked metadata is considered sensitive.
- `ADMIN` remains broad by design except for endpoints protected with `requireSuperAdmin()`. A future hardening phase should consider making `ADMIN` fully permission-matrix controlled if business rules allow.
- Composer security scan should be rerun after a lock file or installed dependency set exists.
