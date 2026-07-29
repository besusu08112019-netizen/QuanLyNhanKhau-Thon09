<?php

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/config/env.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Core\BaseModel;
use App\Services\DataQualityService;

$reflection = new ReflectionClass(DataQualityService::class);
$source = file_get_contents(BASE_PATH . '/app/Services/DataQualityService.php') ?: '';
$controller = file_get_contents(BASE_PATH . '/app/Controllers/DataQualityController.php') ?: '';
$routes = file_get_contents(BASE_PATH . '/index.php') ?: '';
$view = file_get_contents(BASE_PATH . '/views/app.php') ?: '';
$js = file_get_contents(BASE_PATH . '/assets/js/data-quality.js') ?: '';
$platform = file_get_contents(BASE_PATH . '/assets/js/app-platform.js') ?: '';

assert_true($reflection->isFinal(), 'DataQualityService must be final.');
assert_true($reflection->isSubclassOf(BaseModel::class), 'DataQualityService must use tenant-aware BaseModel reads.');
assert_true($reflection->hasMethod('summary'), 'DataQualityService must expose summary().');
assert_true($reflection->hasMethod('issueList'), 'DataQualityService must expose issueList().');
assert_true($reflection->hasMethod('issueDetail'), 'DataQualityService must expose issueDetail().');
assert_true(str_contains($source, 'PopulationStatistics'), 'DataQualityService must reuse PopulationStatistics.');
assert_true(str_contains($source, 'RiskWarningEngine'), 'DataQualityService must reuse RiskWarningEngine.');
assert_true(str_contains($source, 'HouseholdRelationPolicy'), 'DataQualityService must reuse HouseholdRelationPolicy.');
assert_true(str_contains($source, 'InsurancePolicy'), 'DataQualityService must reuse InsurancePolicy.');
assert_true(str_contains($source, "'mode' => 'read_only'"), 'DataQualityService must declare read-only mode.');
assert_true(str_contains($source, 'citizen.missing_identity'), 'DataQualityService must include citizen quality checks.');
assert_true(str_contains($source, 'household.no_head'), 'DataQualityService must include household quality checks.');
assert_true(str_contains($source, 'identity.duplicate_identity'), 'DataQualityService must include duplicate identity checks.');
assert_true(str_contains($source, 'policy.eligible_health_insurance_missing'), 'DataQualityService must include policy quality checks.');
assert_false((bool) preg_match('/thon0[0-9]|hongphongnb\\.com|nhhon5mp_thon/i', $source . $controller . $js), 'Data Quality Center must not hard-code tenant names, domains, or databases.');
assert_false((bool) preg_match('/\\b(INSERT\\s+INTO|UPDATE\\s+|DELETE\\s+FROM|CREATE\\s+TABLE|ALTER\\s+TABLE|DROP\\s+TABLE)\\b/i', $source), 'DataQualityService must not write data or change schema.');

assert_true(str_contains($controller, "requirePermission('report', 'read')"), 'DataQualityController must be protected by read permission.');
assert_true(str_contains($routes, '/api/data-quality/summary'), 'Data Quality summary route must be registered.');
assert_true(str_contains($routes, '/api/data-quality/issues'), 'Data Quality issue list route must be registered.');
assert_true(str_contains($routes, '/api/data-quality/issue'), 'Data Quality issue detail route must be registered.');
assert_true(str_contains($view, 'dataQualityScreen'), 'Data Quality screen must be present.');
assert_true(str_contains($view, 'data-quality.min.js'), 'Data Quality script must be loaded.');
assert_true(str_contains($js, "const API = '/api/data-quality'") && str_contains($js, "API + '/summary'"), 'Data Quality UI must call the read-only summary API.');
assert_true(str_contains($js, "API + '/issue'"), 'Data Quality UI must call the read-only issue detail API.');
assert_true(str_contains($platform, "moduleKey: 'dataQuality'"), 'Data Quality module must be registered.');
assert_true(str_contains($platform, "path: '/data-quality'"), 'Data Quality route must be registered.');

echo "DataQualityCenter tests: PASS\n";

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_false(bool $condition, string $message): void
{
    if ($condition) {
        throw new RuntimeException($message);
    }
}
