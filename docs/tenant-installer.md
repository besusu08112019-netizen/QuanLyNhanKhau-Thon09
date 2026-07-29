# Tenant Installer

The existing `/install` flow remains the first-time website installer. It must
not be removed.

Tenant Installer is a separate operational capability used after the platform is
already running. Its purpose is to add a new tenant without copying source code
or changing application code.

## Service-Oriented Design

Community Control Center must only call services. Tenant creation logic belongs
in reusable services that can also be called from CLI, API, cron, or automation.

Recommended services:

- `TenantInstallerService`
- `TenantRegistryService`
- `TenantConfigWriter`
- `TenantDatabaseInitializer`
- `TenantStorageInitializer`
- `TenantHealthChecker`
- `TenantMigrationService`
- `TenantBackupService`
- `TenantInstallRollbackService`

## Tenant Creation Flow

```text
Start
-> Validate input
-> Create registry entry: CREATING
-> Generate tenant configuration
-> Initialize tenant database
-> Import schema
-> Import seed
-> Create first tenant admin
-> Initialize tenant storage
-> Run health check
-> Update registry: READY
-> Finish
```

## Business Transaction

Tenant creation is a business transaction. If any step fails, all completed
steps must be rolled back where safe.

Rollback may include:

- Mark registry entry as `FAILED`.
- Remove generated tenant config if newly created.
- Remove newly created empty storage folders.
- Drop a newly created empty database only if the installer created it and the
  rollback plan explicitly allows it.
- Preserve any existing database, uploads, or tenant data.

## Tenant Status

Supported lifecycle states:

- `CREATING`
- `READY`
- `FAILED`
- `DISABLED`
- `MAINTENANCE`

Community Control Center must display these states clearly.

## Version Tracking

Each tenant must track:

- `schema_version`
- `app_version`
- `build_version`
- `last_migrated_at`
- `last_checked_at`
- `last_error`

This allows operators to identify tenants that need migration or investigation.

## Control Center Operations

Tenant management should support:

- Add tenant
- Edit tenant metadata
- Disable/enable tenant
- Health check
- Database connection check
- Run migration for one tenant
- Run migration for all tenants
- Backup tenant
- View tenant log

## Reuse From Existing Installer

Reusable pieces from `install/index.php`:

- Environment checks
- Database connection test
- SQL splitting and execution
- `schema.sql` import
- `seed.sql` import
- Tenant `.env.*` generation
- First admin creation
- Storage/upload/backup directory creation

The upgrade path is extraction, not rewrite.
