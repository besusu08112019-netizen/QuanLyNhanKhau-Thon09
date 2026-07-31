# Release Baseline v2.0.2

Status: Official Production Release Baseline.
Date: 2026-07-31.
Commit: 92d0a50c25a4833cdd5ac5d839a129df90321315.

## Purpose

This release marks the accepted stable baseline for future development of Hong
Phong Community Platform after Production verification across Tenant 09, Tenant
10, and Root Control Center.

## Baseline Architecture

- 1 Source Code shared by all tenants.
- 1 Database per Tenant.
- 1 `.env` per Tenant.
- 1 Domain/Subdomain per Tenant.

Tenant-specific differences must come from database, environment, domain,
branding, uploaded assets, and data. Adding a tenant must not require PHP,
JavaScript, or CSS source changes.

## Included Scope

- Mobile/Tablet UI standardization.
- Shared Design System alignment.
- Multi-Tenant architecture validation.
- Community Control Center baseline.
- Tenant Management baseline.
- Tenant Installer local accepted baseline.
- Production fixes for Party Member dashboard and GIS household summary.
- Regression fixes.
- Security regression verification.
- Responsive verification.

## Production Acceptance

- Tenant 09: PASS.
- Tenant 10: PASS.
- Root Control Center: PASS.
- Regression: PASS.
- Security: PASS.
- Production Verification: PASS.
- Multi-Tenant Verification: PASS.

## Development Rules After Baseline

- Do not modify Production directly except for approved Critical, security, or service-interruption fixes.
- Every new feature must be compatible with the multi-tenant architecture.
- Do not hard-code tenant names, domains, database names, tenant ids, paths, logos, or environment values.
- Do not affect active tenants when adding new functionality.
- Every release must pass Regression, Security, and Production Verification before publication.

## Release Decision

Version `v2.0.2` is the official stable Release Baseline. Future features must
branch from and remain compatible with this baseline unless an approved
architecture change supersedes it.
