<?php

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/env.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Core\PortalContext;

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ', actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function set_env_value(string $key, string $value): void
{
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

set_env_value('PLATFORM_ADMIN_ENABLED', 'false');
set_env_value('PLATFORM_ADMIN_DOMAINS', 'hongphongnb.com,www.hongphongnb.com');
set_env_value('PLATFORM_TENANT_DOMAIN_PATTERN', '{code}.hongphongnb.com');
set_env_value('PLATFORM_DEFAULT_PORTAL', PortalContext::TENANT);

$_SERVER['HTTP_HOST'] = 'hongphongnb.com';
PortalContext::reset();
assert_same(PortalContext::PUBLIC, PortalContext::type(), 'Admin domain must fail closed while platform admin is disabled.');
assert_same(true, PortalContext::isPublic(), 'Public helper must be true for disabled admin domain.');

set_env_value('PLATFORM_ADMIN_ENABLED', 'true');
$_SERVER['HTTP_HOST'] = 'hongphongnb.com';
PortalContext::reset();
assert_same(PortalContext::CONTROL_CENTER, PortalContext::type(), 'Root domain must resolve to Control Center when enabled.');
assert_same(true, PortalContext::isControlCenter(), 'Control Center helper must be true for root domain.');

$_SERVER['HTTP_HOST'] = 'tenant-a.hongphongnb.com';
PortalContext::reset();
assert_same(PortalContext::TENANT, PortalContext::type(), 'Tenant subdomain must resolve to Tenant portal.');
assert_same(true, PortalContext::isTenant(), 'Tenant helper must be true for tenant subdomain.');

set_env_value('PLATFORM_ADMIN_ENABLED', 'false');
$_SERVER['HTTP_HOST'] = 'tenant-b.hongphongnb.com';
PortalContext::reset();
assert_same(PortalContext::TENANT, PortalContext::type(), 'Tenant subdomain must stay Tenant while platform admin is disabled.');

$_SERVER['HTTP_HOST'] = 'unknown.example';
set_env_value('PLATFORM_DEFAULT_PORTAL', PortalContext::TENANT);
PortalContext::reset();
assert_same(PortalContext::TENANT, PortalContext::type(), 'Unknown host must use explicit default portal.');

echo "PortalContext tests passed.\n";
