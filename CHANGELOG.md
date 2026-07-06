# Changelog

All notable production release changes are tracked here.

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
