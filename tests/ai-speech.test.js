const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const speech = fs.readFileSync(path.join(root, 'assets/js/ai-speech.js'), 'utf8');
const appView = fs.readFileSync(path.join(root, 'views/app.php'), 'utf8');
const indexPhp = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
const htaccess = fs.readFileSync(path.join(root, '.htaccess'), 'utf8');
const serviceWorker = fs.readFileSync(path.join(root, 'service-worker.js'), 'utf8');
const buildAssets = fs.readFileSync(path.join(root, 'tools/build-assets.js'), 'utf8');

assert.match(speech, /SpeechRecognition/);
assert.match(speech, /webkitSpeechRecognition/);
assert.match(speech, /recognition\.lang = 'vi-VN'/);
assert.match(speech, /tenant:ai-speech-transcript/);
assert.match(speech, /#aiFloatingMicBtn/);
assert.match(speech, /#aiSpeechMinimizeBtn/);
assert.match(speech, /openAndListen/);
assert.match(speech, /toggleMinimized/);
assert.match(speech, /Đang nghe/);
assert.doesNotMatch(speech, /\bfetch\s*\(/);
assert.doesNotMatch(speech, /XMLHttpRequest/);
assert.doesNotMatch(speech, /data-platform-action/);
assert.doesNotMatch(speech, /TenantAppPlatform\.actions/);
assert.doesNotMatch(speech, /location\.(href|assign|replace)/);

assert.match(appView, /id="aiSpeechToggleBtn"/);
assert.match(appView, /id="aiSpeechPanel"/);
assert.match(appView, /id="aiFloatingMicBtn"/);
assert.match(appView, /id="aiSpeechMinimizeBtn"/);
assert.match(appView, /Tr&#7907; l&yacute; AI/);
assert.match(appView, /href="\/assets\/css\/app\.min\.css\?v=20260726-ai-ui-2"/);
assert.match(appView, /src="\/assets\/js\/ai-speech\.min\.js\?v=20260726-ai-ui-2"/);
assert.match(appView, /src="\/assets\/js\/ai-speech\.min\.js\?v=20260726-ai-ui-2"[\s\S]+src="\/assets\/js\/ai-intent\.min\.js\?v=20260726-ai-ui-2"/);
assert.match(indexPhp, /Permissions-Policy: geolocation=\(self\), camera=\(self\), microphone=\(self\)/);
assert.match(htaccess, /Permissions-Policy "geolocation=\(self\), camera=\(self\), microphone=\(self\)"/);
assert.match(serviceWorker, /tenant-pwa-v20260726-ai-ui-2/);
assert.match(buildAssets, /assets\/js\/ai-speech\.js/);

console.log('AI speech checks passed');
