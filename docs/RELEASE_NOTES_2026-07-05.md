# Release Notes - 2026-07-05

## Release Candidate

Target tag: `v2026.07.05`

## Highlights

- Hardened application security and validation.
- Added production minified CSS/JS asset build output and build script.
- Improved production caching and lazy loading behavior.
- Added PHP-level security headers and stricter upload/SVG handling.
- Restricted backup restore to application-generated SQL dumps.
- Hardened import upload validation and XLSX parsing limits.
- Updated production checklist and README in UTF-8.

## Verification Completed Locally

- PHP syntax lint across all PHP files.
- JavaScript syntax check across all JS files.
- Production asset build.
- Browser smoke tests for desktop, tablet and mobile.
- Static scan for debug artifacts and temporary files.
- Verified `.cpanel.yml` exists and is unchanged.
- Verified git history is linear and local branch matches `origin/main` before release documentation changes.

## Verification Pending Outside This Local Session

- Production hosting deploy verification.
- Production database migration state verification.
- Production backup creation/download verification.
- Authenticated production CRUD verification with real/staging data.
- Release tag creation and push after final approval.

## Upgrade Notes

1. Backup production database before deploy.
2. Deploy `origin/main` at the release commit.
3. Run any unapplied SQL files in `database/migrations/` in filename order.
4. Confirm `APP_KEY` or persisted `uploads/.app_key` is stable.
5. Clear browser/application cache if stale assets remain.
6. Run `docs/PRODUCTION_CHECKLIST.md` completely before declaring Production Ready.

## Production Readiness

Do not declare Production Ready until every checklist item in `docs/PRODUCTION_CHECKLIST.md` has passed on production or a staging environment equivalent to production.
