# Phase 7 - Read-only Data Assistant

## Scope

- Added a read-only data assistant endpoint under the existing insight layer.
- The assistant does not call write APIs and does not generate SQL from user input.
- Questions are mapped to fixed, permission-checked intents and predefined aggregate SELECT queries.

## Supported Questions

- Households that have not completed active contribution payments.
- Number of unresolved complaints and overdue complaints.
- Citizens aged 80 or older.
- Public assets/works with maintenance due in the next 30 days.
- Households with livestock records.
- Population movements in the current month.

## API

- `POST /api/insights/ask`

Request:

```json
{ "question": "Co bao nhieu phan anh chua xu ly?" }
```

Response includes `mode: READ_ONLY`, `intent`, a textual answer, metrics, and up to 20 sample rows.

## Security

- Requires dashboard read access.
- Requires read access to the source module inferred from the question.
- Audits every assistant query with the inferred intent.
- No create/update/delete endpoint is exposed for AI.

## UI

- Added a `Tro ly du lieu chi doc` panel inside Operation Center.
- Includes example questions and a compact result table.
