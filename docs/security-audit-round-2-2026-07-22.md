# Security Audit Round 2 - 2026-07-22

Scope: full repository review focused on authentication, authorization, API security, upload/import, GIS, reports, backup/restore, logging, deployment secrets, and dependency audit.

Note: Security Preflight không chạy trong sandbox do giới hạn thực thi executable ngoài workspace, không phải do máy thiếu Python. Windows resolves `python`/`py` to Python 3.13.14 outside the sandbox; the preflight was rerun with the absolute Python executable using escalated execution and returned `status: ready`.

## Fixed Findings

### Critical - ADMIN could create or promote SUPER_ADMIN
- Files: `app/Controllers/UserController.php`, `app/Models/User.php`
- Root cause: user role transitions trusted the submitted `role` because account create/update only checked generic `user:create/update`; `ADMIN` is broadly allowed by RBAC.
- Fix: account create/update now passes the actor into `User`; assigning or modifying `SUPER_ADMIN` is allowed only when the actor is `SUPER_ADMIN`.
- Verification: `tests/security-regression.test.js`, PHP lint.

### High - ADMIN could export raw SQL backups
- Files: `app/Controllers/BackupController.php`
- Root cause: database backup export used generic `backup:export`, while raw dumps contain all tables and should be a root operation.
- Fix: raw SQL backup export now requires `requireSuperAdmin('backup', 'export')`.
- Verification: security regression test.

### High - Production secrets were materialized into deploy root
- Files: `.github/workflows/deploy-ftp.yml`
- Root cause: deployment wrote `.env` under the deployed tree and FTP sync did not exclude `.env` or deployment state/log files.
- Fix: FTP excludes now cover `.env`, `.deploy.env`, FTP state, logs, and local DB config.
- Required ops action: configure production DB values as hosting environment variables or move runtime config outside docroot, then rotate any secret that was deployed previously.

### Medium - ADMIN could access system-admin control plane
- Files: `app/Controllers/SystemAdminController.php`
- Root cause: local `requireAdmin()` accepted both `ADMIN` and `SUPER_ADMIN` for session and system actions.
- Fix: system-admin endpoints now use `requireSuperAdmin('system_admin', read/update)`.
- Verification: security regression test.

### Medium - Report export/read bypassed source module permissions
- Files: `app/Controllers/ReportController.php`
- Root cause: report endpoints checked only `report` permission while report `type` selected sensitive module data.
- Fix: report preview/print/export now requires read permission for the source module(s) mapped from report type.
- Verification: security regression test, PHP lint.

### Medium - GIS read exposed sibling module data
- Files: `app/Controllers/GisController.php`
- Root cause: GIS endpoints authorized only `gis:read` while returning household, citizen, business, and livestock details.
- Fix: GIS household/search/detail endpoints now require the relevant sibling module read permissions before loading those data.
- Verification: security regression test, PHP lint.

### Medium - Public asset photo path mass assignment
- Files: `app/Models/PublicAsset.php`, `app/Controllers/PublicAssetController.php`
- Root cause: create/update payloads could set DB-stored photo paths, and photo streaming trusted those stored paths.
- Fix: public asset and inventory photo paths are only retained/set by upload APIs; streaming now restricts paths to the expected public asset upload directories.
- Verification: security regression test, PHP lint.

### Medium - XLSX import zip bomb risk
- Files: `app/Controllers/ImportController.php`
- Root cause: XLSX entries were decompressed into memory before checking expanded size.
- Fix: ZIP entry metadata is checked before extraction, with uncompressed size and compression ratio limits.
- Verification: PHP lint, security regression test.

### Medium - SQL restore trusted forgeable banner
- Files: `app/Models/Backup.php`
- Root cause: restore accepted a comment banner plus keyword blocklist before executing SQL.
- Fix: new backups include an HMAC signature using `APP_KEY`; restore verifies the signature before executing.
- Note: old unsigned backups are intentionally rejected.
- Verification: PHP lint, security regression test.

### Low - Exception logs could retain sensitive query strings
- Files: `index.php`
- Root cause: exception logging redacted payloads but logged raw request URI.
- Fix: URI query parameters now pass through the same recursive redaction.
- Verification: security regression test.

## Dependency Audit

- `npm audit --audit-level=moderate`: 0 vulnerabilities.
- `composer audit`: not runnable because the repository has no `composer.lock`/installed package set to audit. Composer dependencies are PHP extensions only in `composer.json`.

## Regression Results

- Codex Security config preflight: passed with `status: ready` when run outside the sandbox using the absolute Python 3.13.14 executable.
- `php -l` on full PHP source set: passed.
- `node tests/security-regression.test.js`: passed.
- `npm.cmd run check:js`: passed.
- `node tests/app-platform.test.js`: passed.
- `node tests/navigation-cleanup.test.js`: passed.
- `npm.cmd audit --audit-level=moderate`: passed, 0 vulnerabilities.
- `git diff --check`: passed with line-ending warnings only.

## Residual Risks And Recommendations

- Rotate production DB/API secrets that may have been deployed in `.env`.
- Add a real `composer.lock` if PHP packages are introduced, then enforce `composer audit --locked` in CI.
- Add runtime integration tests for role-bound report/GIS access using seeded users for `VIEWER`, `OFFICER`, `ADMIN`, and `SUPER_ADMIN`.
- Consider moving backup restore away from raw SQL execution to a structured importer for stronger invariant enforcement.
