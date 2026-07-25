const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const speech = fs.readFileSync(path.join(root, 'assets/js/ai-speech.js'), 'utf8');
const appView = fs.readFileSync(path.join(root, 'views/app.php'), 'utf8');
const buildAssets = fs.readFileSync(path.join(root, 'tools/build-assets.js'), 'utf8');

assert.match(speech, /SpeechRecognition/);
assert.match(speech, /webkitSpeechRecognition/);
assert.match(speech, /recognition\.lang = 'vi-VN'/);
assert.match(speech, /tenant:ai-speech-transcript/);
assert.doesNotMatch(speech, /\bfetch\s*\(/);
assert.doesNotMatch(speech, /XMLHttpRequest/);
assert.doesNotMatch(speech, /data-platform-action/);
assert.doesNotMatch(speech, /TenantAppPlatform\.actions/);
assert.doesNotMatch(speech, /location\.(href|assign|replace)/);

assert.match(appView, /id="aiSpeechToggleBtn"/);
assert.match(appView, /id="aiSpeechPanel"/);
assert.match(appView, /src="\/assets\/js\/ai-speech\.min\.js"/);
assert.match(buildAssets, /assets\/js\/ai-speech\.js/);

console.log('AI speech checks passed');
