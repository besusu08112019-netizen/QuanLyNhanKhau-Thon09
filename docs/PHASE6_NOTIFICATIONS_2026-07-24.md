# Phase 6 - Notification Center

## Scope

- Added a shared notification center backed by generated operational alerts.
- Stores read/dismissed state per user in `notification_states`.
- Adds topbar bell dropdown, unread badge, mark-read, dismiss, mark-all-read, and optional browser notifications for PWA/desktop contexts.

## Sources

- New complaints.
- Overdue complaints.
- New work tasks.
- Work tasks due within 3 days.
- Overdue work tasks.
- Today's and upcoming calendar events.
- Recent documents.
- Failed backup/restore records when present.

## API

- `GET /api/notifications`
- `POST /api/notifications/read-all`
- `POST /api/notifications/{key}/read`
- `POST /api/notifications/{key}/dismiss`

## QA Checklist

- Bell badge updates from unread count.
- Dropdown opens on desktop and mobile.
- Clicking a notification marks it read and navigates to the related module.
- Dismiss removes one item from the active list.
- Mark all read clears the unread badge.
- Browser notification permission button works where supported.
