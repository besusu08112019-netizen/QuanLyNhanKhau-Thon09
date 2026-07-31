# Changelog

All notable production release changes are tracked here.

## Unreleased

### Added
- Added independent Agricultural Land Fund module for village-level land-zone statistics by year, configurable land usage types, dashboard charts, CRUD, reports, and exports.

## v2.0.2 - 2026-07-31

### Release scope
- Established the official Production Release Baseline for the shared-source multi-tenant platform.
- Confirmed Tenant 09, Tenant 10, and Root Control Center as Production PASS.
- Marked this release as the stable baseline for future feature development.

### Mobile and tablet UI
- Standardized Mobile/Tablet layout behavior across production modules.
- Confirmed unified Design System usage for cards, buttons, forms, popups, filters, navigation, empty states, loading states, and responsive module surfaces.
- Confirmed Desktop layout remains unaffected by the Mobile/Tablet standardization.

### Multi-Tenant architecture
- Confirmed the target architecture:
  - 1 Source Code.
  - 1 Database per Tenant.
  - 1 `.env` per Tenant.
  - 1 Domain/Subdomain per Tenant.
- Confirmed Tenant 09 and Tenant 10 run through the same shared source with isolated database, environment, branding, and runtime context.
- Confirmed no tenant-specific hard-code is required to deploy the same release to additional tenants.

### Community Control Center
- Confirmed Root Control Center Production readiness.
- Confirmed Tenant Management API and navigation are available on Production.
- Confirmed Platform Actions are active in Control Center UI.

### Production fixes
- Fixed Party Member dashboard SQL generation so `/api/party-members/dashboard` no longer returns HTTP 500.
- Fixed GIS household summary SQL generation so `/api/gis/households?light=1&located=1` no longer returns HTTP 500.
- Confirmed `/api/control-center/tenants` returns HTTP 200 on Root Control Center.

### Regression and security
- Regression checks PASS.
- Security regression checks PASS.
- Production UI audit PASS.
- JavaScript syntax checks PASS.
- PHP lint checks PASS for changed production files.

### Release gate
- Production: PASS.
- Multi-Tenant: PASS.
- Responsive: PASS.
- Security: PASS.
- Regression: PASS.
- No remaining Critical or High blocker at release baseline time.

## v1.1.1 - 2026-07-26

### Release scope
- Removed the AI assistant, AI-only backend, AI UI, AI frontend assets, AI tests, and AI documentation.
- Kept existing administrative management modules unchanged.

### Production hardening
- Removed AI API routes and AI production artifact entries.
- Removed AI assets from service worker precache and centralized asset versioning.
- Kept database schema unchanged; no migration rollback is included in this release.

### Verification
- Pending final build, regression, security review, and release audit in `docs/AI_REMOVAL_REPORT.md`.

## v1.1.0 - 2026-07-26

### Release scope
- Completed AI Agent implementation across Epic 1-12 with final independent review, production readiness review, and release audit.
- AI remains permission-bound, read-only by default, and does not call an external AI provider unless explicitly enabled in future configuration.

### AI capabilities
- Added AI foundation, router, context manager, conversation flow, intent recognition, tool registry, tool executor, and permission checker.
- Added read-only business tools for household, resident, statistics, and insight queries.
- Added voice command orchestration, natural language AI query flow, OCR/camera form autofill, text-to-speech controls, and rule-based analytics alerts.

### Production hardening
- Hardened AI permission failure handling with explicit `permission_denied` metadata for source-module access.
- Removed production `console.log` output and debug request logging.
- Extended production artifact validation to block debug markers, test files, environment files, logs, backups, and SQL dumps.
- Revalidated PWA/cache behavior, CSP/security headers, runtime directory protection, and production artifact contents.

### Verification
- `npm.cmd run test:browser`
- `npm.cmd run validate:artifact`

## v1.0.1 - 2026-07-22

### Release scope
- Production hardening and final release gate validation for the population management system.
- No new business features were added.
- Focus areas: minimal deployment artifact, security headers, upload execution protection, production-safe logging, and production transport validation.

### Production hardening
- Added a production artifact build that deploys only runtime files from `dist/production`.
- Excluded repository metadata, tests, docs, tools, sample data, package manifests, database files, logs, backups, `.env`, and deployment state files from the production package.
- Added upload directory execution protection through `uploads/.htaccess`.
- Hardened production logging to avoid SQL text, SQL parameters, stack traces, absolute paths, and detailed driver messages in normal production logs.
- Updated FTP deployment workflow to use the production artifact and FTPS.

### Release gate
- Revalidated HTTP redirect: `http://your-domain.example/` returns `301` to `https://your-domain.example/`.
- Revalidated HTTPS root: returns `200` with HSTS, CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, and Permissions-Policy.
- Revalidated anonymous API authorization for `/api/me`, backup, and restore endpoints.
- Restore remains restricted to Super Admin and application-generated signed SQL dumps.

### Verification
- `npm run build:production`
- PHP syntax lint across source and production artifact.
- `npm run check:js`
- `node tests/security-regression.test.js`
- `npm run test:platform`
- `npm run test:navigation-cleanup`
- `npm audit --audit-level=moderate`

### Production readiness
- Status: Production Ready after final release gate revalidation on 2026-07-22.
- Required production setting: `APP_DEBUG=false`.

## v1.0.0 - 2026-07-06

### Release scope
- Production readiness pass for the population management system after Sprints 1-17.
- No new business features were added in Sprint 18.
- Focus areas: verification, production hardening, release documentation, cleanup, and asset consistency.

### Stabilized modules
- Authentication and account flows.
- Household management and digital household profiles.
- Citizen management and digital citizen profiles.
- GIS Smart, map markers, popup actions, GPS, routing, search, clustering, and responsive map panels.
- Realtime dashboard and operation center widgets.
- Smart reporting, preview, print, and export entry points.
- System administration, health checks, backup, restore, logs, sessions, cache, and configuration screens.

### Production hardening
- Removed GIS popup debug console output from production JavaScript.
- Ignored local browser test output and runtime upload key files from Git tracking.
- Added a production release runbook for install, deploy, backup, restore, update, rollback, and verification.

### Verification targets
- JavaScript syntax check.
- Browser smoke test suite.
- PHP syntax lint for application files.
- Git whitespace check.
- Scan for debug console output, debugger statements, TODO/FIXME markers, and merge conflict markers in production source paths.

### Release notes
- Package version is `1.0.0`.
- Production tag target: `v1.0.0` after final verification on the deployment branch.
- Sensitive values must remain outside Git and be provided through `.env` or server-side configuration.
