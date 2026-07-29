# System Architecture

Hong Phong Community Platform uses one shared PHP source codebase for all tenants.
Each tenant is resolved at runtime by domain and receives isolated configuration,
database, storage, cache, session, and logs.

## Stable Baseline

Release baseline: `v2.0.0` - Multi-Tenant Stable.

The following foundation components are frozen unless a production issue or an
approved change requires modification:

- `TenantResolver`
- Tenant Registry
- Env Loader
- Multi-tenant routing
- Shared-source deployment
- Database isolation
- Storage isolation

Any change to these components must include impact analysis, rollback plan, and
multi-tenant regression testing.

## Runtime Flow

```text
HTTP request
-> TenantResolver
-> Env Loader
-> TenantContext
-> Database connection for resolved tenant
-> Controller
-> Service
-> Repository
-> Tenant database
```

## Core Components

### TenantResolver

`app/Core/TenantResolver.php` normalizes the request host and derives the tenant
candidate key. It must not hard-code tenant names, domains, or database names.

### Env Loader

`config/env.php` loads shared configuration and tenant-specific configuration
from host candidates. Tenant secrets stay outside Git.

### TenantContext

`app/Core/TenantContext.php` resolves tenant metadata inside the active tenant
database and provides the current tenant id/code to runtime code.

### Database Isolation

Each tenant uses its own database. Runtime code must never select a database by
hard-coded tenant name. Database configuration is loaded through the resolved
tenant environment.

### Storage Isolation

Uploads, cache, sessions, logs, temporary files, and backups must be separated
per tenant. Shared source code must not imply shared tenant data.

## Application Layers

```text
Portal/UI
-> Controller
-> Service
-> Repository
-> Database
```

Controllers handle HTTP concerns only. Business logic belongs in services.
Repositories perform data access. Views and UI components must not query the
database directly.

## Development Rules

- Do not hard-code tenant code, domain, database, upload path, or document root.
- Do not create tenant-specific source branches or folders.
- Do not write functionality for only one tenant.
- Use TenantResolver and tenant configuration for all tenant-specific behavior.
- A single deploy must update all tenants using the shared source.
