# Release v1.0.0 Production Runbook

This runbook is for the Production Ready release after Sprint 18. Sprint 18 must not add new business behavior; it only verifies, hardens, documents, and prepares the system for release.

## 1. Preconditions

- Confirm the deployment branch is clean except intentional release changes.
- Confirm `.env` exists on the server and is not committed to Git.
- Confirm `.env.example` contains placeholders only.
- Confirm `config/database.php` or local database overrides are not committed with production credentials.
- Confirm upload, cache, log, and temporary folders are writable by the web server user.
- Confirm a recent database and upload backup exists before deployment.

## 2. Install

```powershell
composer install --no-dev --optimize-autoloader
npm install
npm run build:assets
```

If the server cannot build assets, build them in CI or on a trusted build machine and deploy the generated assets together with the PHP application.

## 3. Configuration

Create or update `.env` from `.env.example`:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_KEY=<long-random-secret>
DB_HOST=<database-host>
DB_NAME=<database-name>
DB_USER=<database-user>
DB_PASS=<database-password>
```

Never reuse the placeholder `APP_KEY` in production.

## 4. Database

- Run migrations through the project migration command or deployment process used by the environment.
- Do not edit the production database schema manually.
- Verify required tables for households, citizens, GIS data, digital profiles, reporting, operation center, and administration are present.

## 5. Backup Before Release

Back up these items before deploying v1.0.0:

- Database.
- `uploads/`.
- Runtime configuration.
- Existing release package or current server directory.

Store the backup outside the web root.

## 6. Deploy

- Put the application in maintenance mode if the environment supports it.
- Deploy code and built assets.
- Apply migrations.
- Clear application cache if supported.
- Restore write permissions for upload, cache, log, session, and temporary folders.
- Disable maintenance mode.

## 7. Verification

Run or manually verify:

- Login, logout, password flows.
- Household create, edit, delete, search, filter, detail, upload, and digital profile.
- Citizen create, edit, delete, household transfer, move in/out, and digital profile.
- GIS polygon, marker, popup, GPS, search, cluster, route, and direction button.
- Dashboard KPI, widgets, charts, operation center, and progress widgets.
- Reports preview, PDF, Excel, Word, and print.
- Administration dashboard, health check, backup, restore in a test environment, system logs, sessions, cache, and configuration.
- Desktop, tablet, and mobile responsive layouts.
- Browser console has no source-code JavaScript errors.
- PHP logs have no new fatal errors.
- API endpoints used by widgets return HTTP 200 with valid JSON or controlled error JSON.
- No internal asset 404 errors.

## 8. Rollback

If a critical issue appears:

- Put the application in maintenance mode if available.
- Restore the previous release package.
- Restore database backup only if a migration or data change requires it.
- Restore upload backup only if file data was changed or lost.
- Clear cache.
- Re-run the verification checklist.

Do not overwrite production data unless the rollback plan has been confirmed.

## 9. Tagging

After final verification succeeds on the release branch:

```powershell
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

## 10. Operational Notes

- Keep backup archives outside the web root.
- Rotate credentials if a secret was ever exposed.
- Keep `APP_DEBUG=false` in production.
- Review system health, storage usage, upload growth, and slow API indicators after release.
