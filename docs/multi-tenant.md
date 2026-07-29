# Multi-Tenant Runtime

This document defines the stable multi-tenant baseline for Hong Phong Community
Platform.

## Principle

One source codebase serves all tenants. Tenant-specific behavior comes from the
request domain and the resolved tenant configuration.

## Tenant Resolution

```text
Request host
-> TenantResolver::host()
-> TenantResolver::tenantCodeFromHost()
-> TenantResolver::candidateKeys()
-> Env Loader
-> TenantContext
```

Adding a new tenant must not require source code changes.

## Tenant Configuration

Tenant configuration is loaded from environment files or an equivalent secure
configuration mechanism. Secrets are not committed to Git.

Configuration may differ by tenant for:

- Database host
- Database name
- Database credentials
- Upload path
- Storage path
- Cache path
- Session path
- Log path
- Backup path
- Tenant name/logo/theme/contact information

Configuration must not differ by application source code.

## Database Isolation

Each tenant uses its own database. Runtime code must use the active tenant
database resolved by configuration.

The application must never:

- Build database names from hard-coded tenant strings.
- Read another tenant database in a tenant request.
- Write to another tenant database in a tenant request.
- Accept tenant switching from arbitrary request parameters.

## Storage Isolation

Tenant uploads and runtime storage must be separated. At minimum, uploads,
cache, sessions, logs, temporary files, and backups must be tenant-specific.

## Tenant Registry

The Tenant Registry is the central source of tenant metadata for Community
Control Center. It stores operational metadata such as:

- Tenant code
- Unit name
- Domain
- Database host
- Database name
- Database charset
- Schema version
- App version
- Website status
- Database status
- SSL status
- Storage usage
- Last checked time
- Last backup time
- Last error
- Manager
- Notes

The registry must not store database passwords.

## Compatibility Checklist

Every feature must pass:

- Works on Tenant 09.
- Works on Tenant 10.
- Data remains isolated.
- Deploy once updates all tenants.
- No tenant-specific hard-code exists in source.
