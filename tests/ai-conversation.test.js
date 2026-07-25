const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const conversation = fs.readFileSync(path.join(root, 'assets/js/ai-conversation.js'), 'utf8');
const appView = fs.readFileSync(path.join(root, 'views/app.php'), 'utf8');
const buildAssets = fs.readFileSync(path.join(root, 'tools/build-assets.js'), 'utf8');

assert.match(conversation, /TenantAiConversation/);
assert.match(conversation, /tenant:ai-intent-recognized/);
assert.match(conversation, /tenant:ai-conversation-clarification/);
assert.match(conversation, /localStorage/);
assert.match(conversation, /maxMessages = 20/);
assert.doesNotMatch(conversation, /\bfetch\s*\(/);
assert.doesNotMatch(conversation, /XMLHttpRequest/);
assert.doesNotMatch(conversation, /TenantAppPlatform\.actions/);
assert.doesNotMatch(conversation, /data-platform-action/);
assert.doesNotMatch(conversation, /location\.(href|assign|replace)/);

assert.match(appView, /id="aiConversationLog"/);
assert.match(appView, /id="aiConversationClearBtn"/);
assert.match(appView, /src="\/assets\/js\/ai-conversation\.min\.js"/);
assert.match(buildAssets, /assets\/js\/ai-conversation\.js/);

console.log('AI conversation checks passed');

