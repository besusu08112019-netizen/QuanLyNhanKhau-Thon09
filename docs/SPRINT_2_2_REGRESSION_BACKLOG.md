# Sprint 2.2 Regression Backlog

## Status

Sprint 2.2 is closed as:

COMPLETED (Local Accepted)

Current release state:

- Local ACCEPTED
- Waiting for Staging Infrastructure

## Scope

This backlog tracks items outside the accepted local scope of Sprint 2.2 or items that require Staging infrastructure before verification can continue.

No item below is a Sprint 2.2 local software blocker.

## Backlog Items

| ID | Item | Category | Priority | Production Impact | Status | Recommended Action |
| --- | --- | --- | --- | --- | --- | --- |
| REG-2.2-001 | Provision Staging infrastructure for multi-tenant verification | Infrastructure | High | No current production impact | Pending | Create Staging domains, databases, upload paths, cache paths, sessions, and environment files according to the Staging Provision Guide. |
| REG-2.2-002 | Run Sprint 2.2 Staging Verification | Staging Verification | High | No current production impact | Pending Infrastructure | After Staging is available, deploy Sprint 2.2 to Staging and run full Tenant Installer verification. |
| REG-2.2-003 | Perform real Tenant Installer installation on Staging | Integration | High | No current production impact | Pending Infrastructure | Use a staging tenant with isolated Database, upload, cache, session, and `.env`. |
| REG-2.2-004 | Verify rollback on a real Staging tenant | Integration | Medium | No current production impact | Pending Infrastructure | Trigger controlled installer failure on Staging and verify application-level rollback only. |
| REG-2.2-005 | Verify retry after manual infrastructure correction on Staging | Integration | Medium | No current production impact | Pending Infrastructure | Simulate missing privilege or missing `.env` write permission, fix the prerequisite, then retry from the job state. |

## Explicitly Out Of Scope For Sprint 2.2 Closure

- Production deployment.
- Production tenant installation.
- VPS provisioner.
- Cloud provisioner.
- Automated cPanel API provisioning.
- Phase 2.3 or later feature development.
- Regression fixes unrelated to Tenant Installer.

## Acceptance Note

Sprint 2.2 does not require fixing or closing the items above before Local Acceptance because they depend on infrastructure that is not currently provisioned.

