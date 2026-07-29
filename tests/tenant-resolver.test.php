<?php

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/env.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Core\TenantResolver;

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ', actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

putenv('PLATFORM_ROOT_DOMAIN=hongphongnb.com');
$_ENV['PLATFORM_ROOT_DOMAIN'] = 'hongphongnb.com';
$_SERVER['PLATFORM_ROOT_DOMAIN'] = 'hongphongnb.com';

assert_same('thon09.hongphongnb.com', TenantResolver::host('Thon09.HongPhongNB.com:443'), 'Host must normalize domain and strip port.');
assert_same('thon09', TenantResolver::tenantCodeFromHost('thon09.hongphongnb.com'), 'Tenant code must come from subdomain.');
assert_same('thon10', TenantResolver::tenantCodeFromHost('thon10.hongphongnb.com'), 'Tenant code must support future tenants without code changes.');
assert_same('control-center', TenantResolver::tenantCodeFromHost('hongphongnb.com'), 'Root domain must resolve to control center code.');
assert_same(['thon09.hongphongnb.com', 'thon09'], TenantResolver::candidateKeys('thon09.hongphongnb.com'), 'Candidate keys must include host and tenant code.');

$_SERVER['HTTP_HOST'] = 'thon11.hongphongnb.com';
$hosts = env_host_candidates(BASE_PATH);
if (!in_array('thon11', $hosts, true)) {
    fwrite(STDERR, 'env_host_candidates must include tenant code for host-specific config.' . PHP_EOL);
    exit(1);
}

echo "TenantResolver tests passed.\n";
