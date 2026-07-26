const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

function exists(rel) {
  return fs.existsSync(path.join(root, rel));
}

const pkg = JSON.parse(read('package.json'));
const aiConfig = read('ai/config/ai.php');
const serviceWorker = read('service-worker.js');
const artifactBuilder = read('tools/build-production-artifact.js');
const artifactValidator = read('tools/validate-production-artifact.js');
const manifest = JSON.parse(read('manifest.webmanifest'));

[
  'docs/AI_FOUNDATION.md',
  'docs/AI_SPEECH.md',
  'docs/AI_INTENT_RECOGNITION.md',
  'docs/AI_CONVERSATION_MANAGER.md',
  'docs/AI_TOOL_FRAMEWORK.md',
  'docs/AI_BUSINESS_TOOLS_HOUSEHOLD.md',
  'docs/AI_BUSINESS_TOOLS_RESIDENT.md',
  'docs/AI_BUSINESS_TOOLS_STATISTICS.md',
  'docs/AI_BUSINESS_TOOLS_INSIGHT.md',
  'docs/AI_TOOL_ORCHESTRATION.md',
  'docs/AI_UI_ORCHESTRATION.md',
  'docs/AI_OCR_CAMERA.md',
  'docs/AI_TEXT_TO_SPEECH.md',
  'docs/AI_ANALYTICS.md',
  'docs/AI_PRODUCTION_READINESS.md',
].forEach((rel) => assert.ok(exists(rel), `Missing AI release document: ${rel}`));

[
  'assets/js/ai-speech.js',
  'assets/js/ai-intent.js',
  'assets/js/ai-conversation.js',
  'assets/js/ai-ocr.js',
  'assets/js/ai-tts.js',
].forEach((rel) => {
  const minified = rel.replace(/\.js$/, '.min.js');
  assert.ok(exists(rel), `Missing AI source asset: ${rel}`);
  assert.ok(exists(minified), `Missing AI minified asset: ${minified}`);
  assert.match(serviceWorker, new RegExp(minified.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
});

assert.match(serviceWorker, /tenant-pwa-v20260726-ai-release-1/);
assert.match(aiConfig, /'enabled'\s*=>\s*false/);
assert.match(aiConfig, /'external_api'\s*=>\s*false/);
assert.match(aiConfig, /'log_sensitive_keys'/);
assert.ok(Array.isArray(manifest.icons) && manifest.icons.length >= 4, 'PWA manifest must keep install icons.');
assert.strictEqual(manifest.display, 'standalone');
assert.match(artifactBuilder, /'ai'/);
assert.match(artifactBuilder, /'assets'/);
assert.match(artifactValidator, /'ai\/bootstrap\.php'/);
assert.match(artifactValidator, /'service-worker\.js'/);
assert.ok(pkg.scripts['test:ai-all'], 'Missing test:ai-all script.');
assert.ok(pkg.scripts['test:ai-epic12'], 'Missing test:ai-epic12 script.');
assert.match(pkg.scripts['test:ai-epic12'], /validate:artifact/);

console.log('AI release readiness checks passed');
