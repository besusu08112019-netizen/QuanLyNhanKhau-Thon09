# Security Review 2026-07-22

Scope: repository-wide source review of authentication, authorization, API, upload, backup/restore, reporting, GIS, logging, headers, and public endpoints.

Limitations: the Codex Security preflight helper could not run because neither `python` nor `py` is available in this environment. The review used static code inspection, targeted sub-agent review, and local regression checks; it did not run against a production database or live hosting stack.

## Fixed Findings

### High: Backup restore allowed ADMIN-level SQL mutation

Evidence: `BackupController::restore()` only required `backup.restore`; `User::can()` grants ADMIN all permissions; `Backup::restoreSql()` executes validated SQL statements with `PDO::exec()`.

Risk: an ADMIN could upload a backup-like SQL file and mutate protected application tables, bypassing model guards such as Super Admin account protections.

Fix: `BackupController::restore()` now requires `requireSuperAdmin('backup', 'restore')` in addition to the existing auth/CSRF/permission path.

### Medium: Public login config exposed demographic metrics

Evidence: unauthenticated `/api/public/login-config` called `Dashboard::metrics()` and returned household/citizen/social aggregate counts.

Risk: anonymous users could read operational demographic statistics that are otherwise protected by dashboard permissions.

Fix: public login config now returns only non-sensitive UI settings and an empty `metrics` payload.

### Medium: Login lacked backend brute-force throttling

Evidence: login routes called `User::login()` directly and only returned `401` on failure.

Risk: online password guessing depended entirely on external infrastructure.

Fix: `AuthController` now applies IP+login keyed failure throttling for the login window and audits failed attempts without logging the raw login identifier.

### Medium: Sensitive exception/audit metadata could include raw SQL params

Evidence: API exception logging included `sql_params`; audit metadata wrote arbitrary arrays directly.

Risk: passwords, tokens, cookies, CCCD, phone, email, or session data could be persisted in logs.

Fix: exception logging and audit metadata now recursively redact sensitive keys and bearer-like tokens.

### Medium: No global API request-size guard before reading JSON body

Evidence: `Request::capture()` read `php://input` before a central size check.

Risk: oversized API payloads could waste memory before controller-level validation.

Fix: `index.php` rejects non-GET `/api/*` requests larger than 25 MB with HTTP 413 before request body parsing.

### Low: SVG attachment preview lacked a sandbox CSP

Evidence: setting media SVG responses set CSP, but generic file preview only set MIME and `nosniff`.

Risk: if a permitted SVG attachment is previewed inline, browser SVG behaviors are not constrained by a response-specific policy.

Fix: `FileController` now sends a restrictive sandbox CSP for SVG previews.

### Low: HSTS and upload execution hardening were incomplete

Evidence: PHP headers did not set HSTS, and `.htaccess` blocked uploads broadly but did not explicitly deny executable extensions under `uploads`.

Risk: weaker transport hardening on HTTPS deployments and less defense in depth if rewrite rules change.

Fix: added HSTS on HTTPS responses and explicit denial of executable extensions in `uploads`.

### Low: Cookie token fallback weakened bearer-token auth model

Evidence: `Request::bearerToken()` accepted `thon09_token` from `$_COOKIE`.

Risk: cookie-carried tokens become ambient credentials and increase CSRF/session-hijack blast radius.

Fix: removed cookie fallback and require bearer or `X-Auth-Token` values to match a 64-hex token format.

## Validation

- `php -l index.php`
- `php -l app/Core/Request.php`
- `php -l app/Core/BaseController.php`
- `php -l app/Controllers/AuthController.php`
- `php -l app/Controllers/BackupController.php`
- `php -l app/Controllers/SettingController.php`
- `php -l app/Controllers/FileController.php`
- `php -l app/Models/AuditLog.php`
- `node tests/security-regression.test.js`
- `npm run check:js`

## Remaining Recommendations

- Rotate the Google Maps API key currently present in local `.env`; the file is gitignored and not tracked, but exposed local keys should still be treated as compromised.
- Add production-level rate limiting at the webserver/WAF layer in addition to the application login throttle.
- Move backup restore to an offline/admin-only operational process if possible; even Super Admin restore remains a high-impact operation.
- Consider replacing bearer tokens in browser storage with HttpOnly, Secure, SameSite cookies plus double-submit CSRF if the frontend can be changed deliberately.
- Add DB-backed or distributed login throttling for multi-instance deployments.
- Add automated integration tests for anonymous/VIEWER/OFFICER/ADMIN/SUPER_ADMIN permission matrices against a seeded test database.
- Ensure production `APP_DEBUG=false`, HTTPS is enforced, and the webserver applies the same deny rules as `.htaccess`.
