# Phase: Environment Configuration & Multi-Tenant Foundation

Date: 2026-07-25

## Scope Completed

- Added a central `.env` loader at `config/env.php`.
- Routed app, database, upload, storage, cache, logs, and mail configuration through environment variables.
- Removed tracked `config/database.local.php`; local database configuration now belongs in `.env`.
- Added host-based tenant resolution through `App\Core\TenantContext`.
- Added `villages` schema and tenant metadata fields.
- Added `village_id` support to the new schema and an idempotent migration for existing databases.
- Added BaseModel tenant helpers:
  - `tenantId()`
  - `tenantWhere()`
  - `withTenant()`
  - `addTenantInsert()`
- Applied tenant scoping to:
  - login/session lookup and user management
  - system settings
  - audit logs
  - backup export/list records
  - household list/detail/create/update/delete
  - citizen list/detail/create/update/delete/code generation
- Converted upload/media/system health paths to `.env`-driven runtime paths.

## Tenant Resolution

The application resolves the active tenant from:

1. `$_SERVER['HTTP_HOST']`
2. `villages.domain`
3. `villages.subdomain`
4. fallback village code from `TENANT_DEFAULT_VILLAGE_CODE`
5. fallback environment values when the `villages` table is not available

## New Environment Variables

See `.env.example` for the full list. The main additions are:

- `APP_HOST`
- `STORAGE_PATH`
- `CACHE_PATH`
- `LOGS_PATH`
- `TENANT_DEFAULT_VILLAGE_ID`
- `TENANT_DEFAULT_VILLAGE_CODE`
- `MAIL_*`

## Database Upgrade

For an existing database, run:

```sql
database/migrations/20260725_120000_environment_multi_tenant_foundation.sql
```

For a new database, import:

```sql
database/schema.sql
database/seed.sql
```

## Important Boundary

This phase establishes the tenant foundation and applies tenant scoping to authentication, settings, audit, backup, households, and citizens. Other feature modules now have schema support through `village_id`, but their individual model queries should be hardened module by module before using multiple villages in the same database.

The system remains safe for the existing deployment model where each host points to its own configured database.
