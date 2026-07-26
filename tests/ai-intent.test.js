const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const intent = fs.readFileSync(path.join(root, 'assets/js/ai-intent.js'), 'utf8');
const appView = fs.readFileSync(path.join(root, 'views/app.php'), 'utf8');
const buildAssets = fs.readFileSync(path.join(root, 'tools/build-assets.js'), 'utf8');

assert.match(intent, /TenantAiIntent/);
assert.match(intent, /tenant:ai-speech-transcript/);
assert.match(intent, /tenant:ai-intent-recognized/);
assert.match(intent, /navigation\.open_module/);
assert.match(intent, /search\.query/);
assert.match(intent, /report\.view/);
assert.match(intent, /data\.create_draft/);
assert.doesNotMatch(intent, /\bfetch\s*\(/);
assert.doesNotMatch(intent, /XMLHttpRequest/);
assert.doesNotMatch(intent, /TenantAppPlatform\.actions/);
assert.doesNotMatch(intent, /data-platform-action/);
assert.doesNotMatch(intent, /location\.(href|assign|replace)/);

assert.match(appView, /id="aiIntentPreview"/);
assert.match(appView, /src="\/assets\/js\/ai-intent\.min\.js\?v=20260726-ai-ui-2"/);
assert.match(buildAssets, /assets\/js\/ai-intent\.js/);

console.log('AI intent checks passed');
