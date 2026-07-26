const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const operationCenter = fs.readFileSync(path.join(root, 'assets/js/operation-center.js'), 'utf8');
const minified = fs.readFileSync(path.join(root, 'assets/js/operation-center.min.js'), 'utf8');

assert.match(operationCenter, /apiPost\('\/api\/ai\/ask'/);
assert.doesNotMatch(operationCenter, /\/api\/insights\/ask/);
assert.match(operationCenter, /normalizeAiAnswer/);
assert.match(operationCenter, /sourceLabel/);
assert.match(operationCenter, /Nguon: /);
assert.match(operationCenter, /renderAiMetrics/);
assert.match(operationCenter, /plan\.tool === 'insight'/);
assert.match(operationCenter, /plan\.tool === 'statistics'/);
assert.match(operationCenter, /plan\.tool === 'household'/);
assert.match(operationCenter, /plan\.tool === 'resident'/);

assert.match(minified, /\/api\/ai\/ask/);
assert.doesNotMatch(minified, /\/api\/insights\/ask/);

console.log('AI operation center checks passed');
