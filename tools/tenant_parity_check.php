<?php

declare(strict_types=1);

$urls = parseList(getenv('TENANT_PARITY_URLS') ?: 'https://thon09.hongphongnb.com,https://thon10.hongphongnb.com,https://ccc01.hongphongnb.com');
$requiredModules = parseList(getenv('TENANT_PARITY_REQUIRED_MODULES') ?: '');
$requireLogin = envBool('TENANT_PARITY_REQUIRE_LOGIN', true);
$credentials = parseCredentials(getenv('TENANT_PARITY_LOGIN_JSON') ?: '{}');

$failures = [];
$reports = [];
$baselineVersion = null;

foreach ($urls as $url) {
    $baseUrl = normalizeBaseUrl($url);
    $host = parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl;
    $report = [
        'url' => $baseUrl,
        'host' => $host,
        'home_status' => null,
        'source_version' => null,
        'login_config' => 'NOT_RUN',
        'login' => 'NOT_RUN',
        'api_me' => 'NOT_RUN',
        'modules' => [],
    ];

    try {
        $home = httpRequest('GET', $baseUrl . '/');
        $report['home_status'] = $home['status'];
        if ($home['status'] < 200 || $home['status'] >= 300) {
            $failures[] = "{$host}: home returned HTTP {$home['status']}";
        }

        $version = extractAssetVersion($home['body']);
        $report['source_version'] = $version;
        if ($version === null) {
            $failures[] = "{$host}: APP_ASSET_VERSION token was not found in home HTML";
        } elseif ($baselineVersion === null) {
            $baselineVersion = $version;
        } elseif ($version !== $baselineVersion) {
            $failures[] = "{$host}: source version {$version} differs from baseline {$baselineVersion}";
        }

        foreach ($requiredModules as $module) {
            $present = stripos($home['body'], $module) !== false;
            $report['modules'][$module] = $present ? 'PASS' : 'FAIL';
            if (!$present) {
                $failures[] = "{$host}: required module marker '{$module}' was not found";
            }
        }

        $loginConfig = httpRequest('GET', $baseUrl . '/api/public/login-config');
        $loginConfigJson = json_decode($loginConfig['body'], true);
        if ($loginConfig['status'] >= 200 && $loginConfig['status'] < 300 && is_array($loginConfigJson)) {
            $report['login_config'] = 'PASS';
        } else {
            $report['login_config'] = 'FAIL';
            $failures[] = "{$host}: /api/public/login-config did not return valid JSON";
        }

        $credential = credentialForHost($credentials, $host, $baseUrl);
        if ($credential === null) {
            if ($requireLogin) {
                $report['login'] = 'MISSING_CREDENTIALS';
                $failures[] = "{$host}: login credentials missing in TENANT_PARITY_LOGIN_JSON";
            }
        } else {
            $login = httpRequest(
                'POST',
                $baseUrl . '/api/auth/login',
                json_encode([
                    'email' => $credential['email'],
                    'password' => $credential['password'],
                ], JSON_UNESCAPED_SLASHES),
                ['Content-Type: application/json']
            );
            $loginJson = json_decode($login['body'], true);
            $token = is_array($loginJson) ? (string) ($loginJson['data']['token'] ?? $loginJson['token'] ?? '') : '';

            if ($login['status'] >= 200 && $login['status'] < 300 && $token !== '') {
                $report['login'] = 'PASS';
                $me = httpRequest('GET', $baseUrl . '/api/me', null, ['Authorization: Bearer ' . $token]);
                $meJson = json_decode($me['body'], true);
                if ($me['status'] >= 200 && $me['status'] < 300 && is_array($meJson)) {
                    $report['api_me'] = 'PASS';
                } else {
                    $report['api_me'] = 'FAIL';
                    $failures[] = "{$host}: /api/me did not return valid authenticated JSON";
                }
            } else {
                $report['login'] = 'FAIL';
                $failures[] = "{$host}: login failed with HTTP {$login['status']}";
            }
        }
    } catch (Throwable $exception) {
        $failures[] = "{$host}: " . $exception->getMessage();
    }

    $reports[] = $report;
}

echo json_encode([
    'baseline_source_version' => $baselineVersion,
    'tenant_count' => count($reports),
    'reports' => $reports,
    'failures' => $failures,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($failures !== []) {
    fwrite(STDERR, "Tenant parity acceptance FAILED\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

echo "Tenant parity acceptance PASS\n";

function parseList(string $value): array
{
    $items = array_map('trim', explode(',', $value));
    return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
}

function envBool(string $name, bool $default): bool
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    return !in_array(strtolower((string) $value), ['0', 'false', 'no', 'off'], true);
}

function parseCredentials(string $json): array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('TENANT_PARITY_LOGIN_JSON must be valid JSON');
    }

    return $decoded;
}

function credentialForHost(array $credentials, string $host, string $baseUrl): ?array
{
    $candidates = [$host, $baseUrl, 'default'];
    foreach ($candidates as $candidate) {
        if (isset($credentials[$candidate]) && is_array($credentials[$candidate])) {
            $credential = $credentials[$candidate];
            if (!empty($credential['email']) && array_key_exists('password', $credential)) {
                return [
                    'email' => (string) $credential['email'],
                    'password' => (string) $credential['password'],
                ];
            }
        }
    }

    return null;
}

function normalizeBaseUrl(string $url): string
{
    $url = trim($url);
    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }

    return rtrim($url, '/');
}

function httpRequest(string $method, string $url, ?string $body = null, array $headers = []): array
{
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException("Unable to initialize HTTP client for {$url}");
    }

    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }

    $raw = curl_exec($curl);
    if ($raw === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException("HTTP request failed for {$url}: {$error}");
    }

    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    return [
        'status' => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
    ];
}

function extractAssetVersion(string $html): ?string
{
    if (preg_match("/assets\/js\/app\.utf8\.min\.js\?v=([^\"'&<>]+)/", $html, $matches) === 1) {
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    if (preg_match("/\?v=([^\"'&<>]+)/", $html, $matches) === 1) {
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return null;
}
