# Schema Migration

Schema migration must support many tenants while preserving data isolation.

## Principles

- Migrations must be idempotent where practical.
- Migrations must not hard-code tenant names, domains, or database names.
- Each tenant records `schema_version`.
- Migration status must be visible from Community Control Center.
- A failed tenant migration must not silently affect other tenants.

## Migration Modes

### One Tenant

Used when investigating or repairing a specific tenant.

```text
Select tenant
-> Backup tenant
-> Run migration
-> Verify schema version
-> Run smoke test
-> Update Tenant Registry
```

### All Tenants

Used for release upgrades.

```text
Load Tenant Registry
-> For each READY tenant
-> Backup tenant
-> Run migration
-> Verify tenant
-> Continue or stop based on release plan
```

## Rollback

Every schema-changing release must define rollback behavior before deployment.

Rollback options may include:

- Reversible SQL migration.
- Restore tenant database from backup.
- Source rollback if schema remains compatible.

Do not run destructive rollback commands without confirming the affected tenant
and backup availability.

## Acceptance Checks

After migration verify at least:

- Login.
- Dashboard.
- Household CRUD.
- Citizen CRUD.
- Import/export.
- PDF/print.
- Tenant data isolation.
