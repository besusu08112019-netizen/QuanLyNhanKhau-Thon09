const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const report = fs.readFileSync(path.join(root, 'app/Models/Report.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/app.css'), 'utf8');

assert.match(
  report,
  /COUNT\(\*\) = 1 THEN MAX\(NULLIF\(TRIM\(report_head\.full_name\), ''\)\)/,
  'household report must only resolve one unambiguous current household head',
);
assert.match(
  report,
  /report_head\.relationship = '\$head'/,
  'household report must only use citizens explicitly related as household head',
);
assert.match(
  report,
  /Chưa xác định chủ hộ/,
  'household report must label missing or ambiguous household heads',
);
assert.doesNotMatch(
  report.match(/public function householdReport[\s\S]*?\n    }/)?.[0] || '',
  /\$r\['head_citizen_name'\]/,
  'household report must not render the stale denormalized household head name',
);
assert.match(
  css,
  /\.report-table th:first-child, \.report-table td:first-child \{ white-space: nowrap; \}/,
  'the household code column must stay on one line',
);

console.log('Household report head regression checks passed.');
