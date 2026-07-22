# Production Release Report Template

```text
Release version:
Commit SHA:
Deploy time:
Work item:
Files changed:
GitHub Actions run:
Production artifact:

Build: PASS/FAIL
Deploy: PASS/FAIL
Production artifact sync: PASS/FAIL

Security:
- .env not public: PASS/FAIL
- logs not public: PASS/FAIL
- backups not public: PASS/FAIL
- internal uploads not executable: PASS/FAIL
- debug not exposed: PASS/FAIL
- stack traces not exposed: PASS/FAIL
- raw SQL errors not exposed: PASS/FAIL
- security headers present: PASS/FAIL

Desktop:
- Login: PASS/FAIL/BLOCKED
- Logout: PASS/FAIL/BLOCKED
- Session: PASS/FAIL/BLOCKED
- Dashboard: PASS/FAIL/BLOCKED
- Statistics: PASS/FAIL/BLOCKED
- GIS marker: PASS/FAIL/BLOCKED
- GIS popup: PASS/FAIL/BLOCKED
- GIS polygon: PASS/FAIL/BLOCKED
- GIS layer: PASS/FAIL/BLOCKED
- Upload photo: PASS/FAIL/BLOCKED
- Upload preview: PASS/FAIL/BLOCKED
- Upload delete: PASS/FAIL/BLOCKED
- Reports preview: PASS/FAIL/BLOCKED
- Reports Excel: PASS/FAIL/BLOCKED
- Reports PDF: PASS/FAIL/BLOCKED
- Reports print: PASS/FAIL/BLOCKED

API:
- HTTP status: PASS/FAIL/BLOCKED
- Authentication: PASS/FAIL/BLOCKED
- Permission: PASS/FAIL/BLOCKED

Mobile:
- Responsive: PASS/FAIL/BLOCKED
- Bottom navigation: PASS/FAIL/BLOCKED
- FAB: PASS/FAIL/BLOCKED

PWA:
- Manifest: PASS/FAIL/BLOCKED
- Service worker: PASS/FAIL/BLOCKED
- Offline: PASS/FAIL/BLOCKED

Overall: PASS/FAIL
Notes:
```
