const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const tts = fs.readFileSync(path.join(root, 'assets/js/ai-tts.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/app.css'), 'utf8');
const view = fs.readFileSync(path.join(root, 'views/app.php'), 'utf8');
const buildAssets = fs.readFileSync(path.join(root, 'tools/build-assets.js'), 'utf8');
const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));

assert.match(tts, /speechSynthesis/);
assert.match(tts, /SpeechSynthesisUtterance/);
assert.match(tts, /utterance\.lang = 'vi-VN'/);
assert.match(tts, /utterance\.rate/);
assert.match(tts, /utterance\.volume/);
assert.match(tts, /tenant:ai-answer/);
assert.match(tts, /tenant:ai-conversation-cleared/);
assert.match(tts, /TenantAiTts/);
assert.match(tts, /storageKey\(key\)/);
assert.match(tts, /loadSetting\('rate'/);
assert.match(tts, /loadSetting\('volume'/);
assert.match(tts, /loadSetting\('enabled'/);
assert.match(tts, /saveSetting\('rate'/);
assert.match(tts, /saveSetting\('volume'/);
assert.match(tts, /saveSetting\('enabled'/);
assert.match(tts, /vietnameseVoice/);
assert.doesNotMatch(tts, /\bfetch\s*\(/);
assert.doesNotMatch(tts, /XMLHttpRequest/);
assert.doesNotMatch(tts, /window\.api/);
assert.doesNotMatch(tts, /location\.(href|assign|replace)/);

assert.match(view, /id="aiTtsToggleBtn"/);
assert.match(view, /id="aiTtsStopBtn"/);
assert.match(view, /id="aiTtsRate"/);
assert.match(view, /id="aiTtsVolume"/);
assert.match(view, /src="\/assets\/js\/ai-tts\.min\.js"/);
assert.match(view, /src="\/assets\/js\/ai-ocr\.min\.js"[\s\S]+src="\/assets\/js\/ai-tts\.min\.js"/);
assert.match(css, /\.ai-tts-controls/);
assert.match(buildAssets, /assets\/js\/ai-tts\.js/);
assert.ok(pkg.scripts['test:ai-tts']);
assert.ok(pkg.scripts['test:ai-epic10']);

console.log('AI TTS checks passed');
