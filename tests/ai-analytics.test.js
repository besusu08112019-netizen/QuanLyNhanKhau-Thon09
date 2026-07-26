const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const model = fs.readFileSync(path.join(root, 'app/Models/SystemInsight.php'), 'utf8');
const controller = fs.readFileSync(path.join(root, 'app/Controllers/InsightController.php'), 'utf8');
const index = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
const orchestrator = fs.readFileSync(path.join(root, 'ai/src/Orchestration/ToolOrchestrator.php'), 'utf8');
const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));

assert.match(model, /public function analytics\(\): array/);
assert.match(model, /analyticsRules/);
assert.match(model, /analyticsSummary/);
assert.match(model, /'analytics_alerts'/);
assert.match(model, /'suggestions'/);
assert.match(model, /'mode' => 'READ_ONLY'/);
assert.match(model, /invalid_identity/);
assert.match(model, /duplicate_identity/);
assert.match(model, /households_without_members/);
assert.match(model, /missing_identity/);
assert.match(model, /missing_area_code/);
assert.doesNotMatch(model, /\bINSERT\s+INTO\b|\bUPDATE\s+\w+\s+SET\b|\bDELETE\s+FROM\b/i);

assert.match(controller, /public function analytics\(\): void/);
assert.match(controller, /requirePermission\('dashboard', 'read'\)/);
assert.match(controller, /requirePermission\('household', 'read'\)/);
assert.match(controller, /requirePermission\('citizen', 'read'\)/);
assert.match(controller, /analytics_readonly/);
assert.match(index, /\$router->get\('\/api\/insights\/analytics', \[InsightController::class, 'analytics'\]\);/);
assert.match(orchestrator, /bat thuong/);
assert.match(orchestrator, /canh bao/);
assert.ok(pkg.scripts['test:ai-analytics']);
assert.ok(pkg.scripts['test:ai-epic11']);

console.log('AI analytics checks passed');
