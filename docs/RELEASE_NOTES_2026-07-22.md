# Release Notes - 2026-07-22

## Production Ready

Target release: `v1.0.1`

Status: Production Ready after final release gate revalidation.

## Scope

- Production hardening release for the population management system.
- No new business functionality.
- No UI redesign or workflow refactor.
- Focused on safe production packaging, security headers, upload protection, logging hygiene, and final release validation.

## Changes

- Added a minimal production artifact at `dist/production`.
- Updated production build command to generate assets and assemble the deployment package.
- Updated deployment workflows to deploy the artifact instead of the full repository.
- Added upload directory `.htaccess` protection against executable uploads.
- Hardened production logging to avoid SQL, SQL parameters, stack traces, absolute paths, and detailed driver messages.
- Preserved production uploads and runtime cache directories during deployment.

## Release Gate Results

- Build artifact: PASS.
- Deployment package: PASS.
- Security checklist: PASS.
- Permission checks: PASS.
- API authorization checks: PASS.
- HTTP redirect: PASS, `301` to HTTPS.
- HTTPS root response: PASS, `200`.
- HSTS: PASS.
- CSP: PASS.
- X-Content-Type-Options: PASS.
- X-Frame-Options: PASS.
- Referrer-Policy: PASS.
- Permissions-Policy: PASS.
- Logging hardening: PASS.
- Backup authorization: PASS.
- Restore authorization: PASS.
- Dependency audit: PASS.

## Verification Commands

```powershell
npm.cmd run build:production
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
npm.cmd run check:js
node tests/security-regression.test.js
npm.cmd run test:platform
npm.cmd run test:navigation-cleanup
npm.cmd audit --audit-level=moderate
curl.exe --ssl-no-revoke -I http://nhankhauthon09.com/
```

HTTPS headers were revalidated with a Node TLS client because Windows Schannel in the sandbox could not complete the HTTPS `curl` handshake.

## Production Requirements

- Keep `APP_DEBUG=false`.
- Keep `.env` outside Git and inaccessible over HTTP.
- Keep backup archives outside the web root.
- Keep plain FTP disabled; use FTPS or SFTP only.
- Rotate production secrets according to the administrator checklist.
- Confirm restore only in a controlled test or maintenance window because restore changes data.

## Known Non-Blocking Notes

- Authenticated restore was not executed against production during the release gate because it is a data-changing operation.
- Restore authorization and SQL signature validation were verified by source review and anonymous endpoint checks.
