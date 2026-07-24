# Phase 5 - Command Dashboard

## Scope

- Extended Operation Center into a broader command dashboard.
- Added a `command-center` widget API that aggregates core village operations without replacing existing module dashboards.
- Added responsive dashboard widgets on desktop, tablet, and mobile.

## Metrics

- Households and citizens.
- Temporary residence and temporary absence.
- Poor households and party members.
- Public assets and maintenance due in the next 30 days.
- Work tasks and overdue work tasks.
- Complaints and overdue complaints.
- Village documents.
- Today's calendar events.
- Current month income, expense, balance, and finance voucher count.

## API

- `GET /api/operation-center/command-center`

The endpoint uses table/column guards so environments can load even if optional phase migrations have not been applied yet.

## UI

- Added `Dashboard dieu hanh` panel at the top of Operation Center.
- Widgets include drill-down buttons to related modules.
- Uses the existing Operation Center responsive layout and shared navigation controller.

## QA Checklist

- Open Operation Center on desktop, tablet, and mobile.
- Verify command dashboard cards render.
- Verify refresh button reloads only command widgets.
- Verify drill-down opens the related module.
- Verify existing notifications, tasks, area dashboard, progress, timeline, and logs still render.
