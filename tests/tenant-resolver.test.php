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

assert_same('tenant-a.hongphongnb.com', TenantResolver::host('Tenant-A.HongPhongNB.com:443'), 'Host must normalize domain and strip port.');
assert_same('tenant-a', TenantResolver::tenantCodeFromHost('tenant-a.hongphongnb.com'), 'Tenant code must come from subdomain.');
assert_same('tenant-b', TenantResolver::tenantCodeFromHost('tenant-b.hongphongnb.com'), 'Tenant code must support future tenants without code changes.');
assert_same('control-center', TenantResolver::tenantCodeFromHost('hongphongnb.com'), 'Root domain must resolve to control center code.');
assert_same(['tenant-a.hongphongnb.com', 'tenant-a'], TenantResolver::candidateKeys('tenant-a.hongphongnb.com'), 'Candidate keys must include host and tenant code.');

$_SERVER['HTTP_HOST'] = 'tenant-c.hongphongnb.com';
$hosts = env_host_candidates(BASE_PATH);
if (!in_array('tenant-c', $hosts, true)) {
    fwrite(STDERR, 'env_host_candidates must include tenant code for host-specific config.' . PHP_EOL);
    exit(1);
}

echo "TenantResolver tests passed.\n";
