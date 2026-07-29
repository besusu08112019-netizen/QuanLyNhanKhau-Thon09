# Backup And Restore

Backup and restore must preserve tenant isolation.

## Backup Scope

Each tenant backup should include:

- Tenant database dump
- Tenant upload directory
- Tenant configuration metadata, excluding secrets where possible
- Schema version
- App version/build version
- Backup timestamp

Platform-level backup should include:

- Shared source release/tag reference
- Tenant Registry database
- Control Center configuration
- Deployment configuration

## Rules

- Do not mix tenant backups.
- Do not restore one tenant over another tenant.
- Do not store database passwords in Tenant Registry backups.
- Keep backup metadata sufficient to identify source tag and schema version.

## Tenant Restore Flow

```text
Select tenant
-> Verify backup belongs to tenant
-> Put tenant into MAINTENANCE if needed
-> Restore database
-> Restore uploads
-> Verify schema version
-> Run health check
-> Re-enable tenant
-> Record audit
```

## Source Rollback Versus Data Restore

Source rollback and data restore are different operations.

Source rollback redeploys a previous commit or tag. Data restore restores tenant
database and tenant uploads. Do not use data restore to compensate for an
ordinary source deploy issue unless data was actually damaged.
