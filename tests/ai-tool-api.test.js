const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const index = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
const controller = fs.readFileSync(path.join(root, 'app/Controllers/AiToolController.php'), 'utf8');
const permissionModel = fs.readFileSync(path.join(root, 'app/Models/Permission.php'), 'utf8');
const userModel = fs.readFileSync(path.join(root, 'app/Models/User.php'), 'utf8');
const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));

assert.match(index, /use App\\Controllers\\AiToolController;/);
assert.match(index, /\$router->get\('\/api\/ai\/tools', \[AiToolController::class, 'index'\]\);/);
assert.match(index, /\$router->post\('\/api\/ai\/tools\/execute', \[AiToolController::class, 'execute'\]\);/);
assert.match(index, /\$router->post\('\/api\/ai\/ask', \[AiToolController::class, 'ask'\]\);/);

assert.match(controller, /require_once BASE_PATH \. '\/ai\/bootstrap\.php';/);
assert.match(controller, /AiRuntimeFactory::toolRegistry\(\)/);
assert.match(controller, /new ToolExecutor\(\$registry\)/);
assert.match(controller, /ToolOrchestrator/);
assert.match(controller, /tool_orchestrate_readonly/);
assert.match(controller, /'contributions' => \['read' => \$this->users\(\)->can\(\$user, 'contributions', 'read'\)\]/);
assert.match(controller, /'complaints' => \['read' => \$this->users\(\)->can\(\$user, 'complaints', 'read'\)\]/);
assert.match(controller, /'public_assets' => \['read' => \$this->users\(\)->can\(\$user, 'public_assets', 'read'\)\]/);
assert.match(controller, /PermissionAwareAiToolInterface/);
assert.match(controller, /readOnly\(\)/);
assert.match(controller, /Only read-only AI tools can be executed/);
assert.match(controller, /requirePermission\(\$tool->module\(\), \$tool->action\(\)\)/);
assert.match(controller, /tool_execute_readonly/);

assert.match(permissionModel, /'statistics'/);
assert.match(permissionModel, /\$module === 'statistics' && \$action === 'read'/);
assert.match(userModel, /\$module === 'statistics' && \$action === 'read'/);
assert.match(userModel, /'report','statistics','gis'/);

assert.ok(pkg.scripts['test:ai-tool-api']);
assert.match(pkg.scripts['test:ai-epic6'], /test:ai-tool-api/);

console.log('AI tool API static test passed');
