<?php

namespace App\Services;

use App\Core\Authorization\ControlCenterAuthorizationInterface;
use App\Core\Database;
use App\Repositories\AdministrativeUnitRepository;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class AdministrativeUnitService
{
    private const STATUSES = ['ACTIVE', 'INACTIVE'];

    public function __construct(
        private AdministrativeUnitRepository $repository,
        private ControlCenterAuthorizationInterface $authorization,
        private ControlCenterAuditService $audit
    ) {
    }

    public function list(array $filters = []): array
    {
        try {
            return $this->repository->paginate($filters);
        } catch (Throwable $e) {
            error_log('[ADMINISTRATIVE_UNIT_LIST_FALLBACK] ' . json_encode([
                'type' => get_class($e),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return ['items' => [], 'page' => 1, 'pageSize' => 20, 'total' => 0, 'totalPages' => 1];
        }
    }

    public function find(int $id): array
    {
        $unit = $this->repository->find($id);
        if (!$unit) {
            throw new RuntimeException('Khong tim thay don vi');
        }
        return $unit;
    }

    public function create(array $input): array
    {
        $actor = $this->authorization->authorize('control_center.units.create');
        $data = $this->validate($input);
        $this->assertUnique($data);
        $unit = $this->repository->create($data);
        $this->audit->write($actor, 'unit.created', (int) ($unit['id'] ?? 0), 'Tao don vi hanh chinh', ['code' => $unit['code'] ?? null]);
        return $unit;
    }

    public function update(int $id, array $input): array
    {
        $actor = $this->authorization->authorize('control_center.units.update');
        $this->find($id);
        $data = $this->validate($input, false);
        $this->assertUnique($data, $id);
        $unit = $this->repository->update($id, $data);
        $this->audit->write($actor, 'unit.updated', $id, 'Cap nhat don vi hanh chinh', ['fields' => array_keys($data)]);
        return $unit;
    }

    public function lock(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.units.lock');
        $unit = $this->find($id);
        if (($unit['status'] ?? '') !== 'ACTIVE') {
            throw new InvalidArgumentException('Don vi khong o trang thai co the khoa');
        }
        $updated = $this->repository->setStatus($id, 'INACTIVE');
        $this->audit->write($actor, 'unit.locked', $id, 'Khoa don vi hanh chinh', ['code' => $unit['code'] ?? null]);
        return $updated;
    }

    public function activate(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.units.activate');
        $unit = $this->find($id);
        if (($unit['status'] ?? '') === 'ACTIVE') {
            throw new InvalidArgumentException('Don vi da duoc kich hoat');
        }
        $updated = $this->repository->setStatus($id, 'ACTIVE');
        $this->audit->write($actor, 'unit.activated', $id, 'Kich hoat don vi hanh chinh', ['code' => $unit['code'] ?? null]);
        return $updated;
    }

    public function checkConnection(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.units.update');
        $unit = $this->find($id);
        if (($unit['status'] ?? '') !== 'ACTIVE') {
            $updated = $this->repository->updateDatabaseHealth($id, 'LOCKED', 'Tenant is inactive');
            $this->audit->write($actor, 'unit.connection_checked', $id, 'Kiem tra ket noi don vi dang khoa', ['status' => 'LOCKED']);
            return $updated;
        }

        $database = trim((string) ($unit['databaseName'] ?? ''));
        if ($database === '') {
            $updated = $this->repository->updateDatabaseHealth($id, 'UNKNOWN', 'Database name is missing');
            $this->audit->write($actor, 'unit.connection_checked', $id, 'Kiem tra ket noi don vi thieu database', ['status' => 'UNKNOWN']);
            return $updated;
        }

        try {
            $this->connectTenantDatabase($unit);
            $updated = $this->repository->updateDatabaseHealth($id, 'CONNECTED');
            $this->audit->write($actor, 'unit.connection_checked', $id, 'Kiem tra ket noi don vi thanh cong', ['status' => 'CONNECTED']);
            return $updated;
        } catch (PDOException $e) {
            $updated = $this->repository->updateDatabaseHealth($id, 'DISCONNECTED', 'Database connection failed');
            $this->audit->write($actor, 'unit.connection_checked', $id, 'Kiem tra ket noi don vi that bai', ['status' => 'DISCONNECTED'], 'WARN');
            return $updated;
        }
    }

    public function checkWebsite(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.units.update');
        $unit = $this->find($id);
        if (($unit['status'] ?? '') !== 'ACTIVE') {
            $updated = $this->repository->updateWebsiteHealth($id, 'LOCKED', 'UNKNOWN', 'Tenant is inactive');
            $this->audit->write($actor, 'unit.website_checked', $id, 'Kiem tra website don vi dang khoa', ['status' => 'LOCKED']);
            return $updated;
        }

        $domain = trim((string) ($unit['domain'] ?? ''));
        if ($domain === '') {
            $updated = $this->repository->updateWebsiteHealth($id, 'UNKNOWN', 'UNKNOWN', 'Domain is missing');
            $this->audit->write($actor, 'unit.website_checked', $id, 'Kiem tra website don vi thieu domain', ['status' => 'UNKNOWN']);
            return $updated;
        }

        $result = $this->probeWebsite($domain);
        $updated = $this->repository->updateWebsiteHealth($id, $result['websiteStatus'], $result['sslStatus'], $result['error']);
        $this->audit->write($actor, 'unit.website_checked', $id, 'Kiem tra website don vi', [
            'website_status' => $result['websiteStatus'],
            'ssl_status' => $result['sslStatus'],
            'http_code' => $result['httpCode'],
        ], $result['websiteStatus'] === 'ONLINE' ? 'INFO' : 'WARN');
        return $updated;
    }

    public function openPortal(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.units.read');
        $unit = $this->find($id);
        $domain = trim((string) ($unit['domain'] ?? ''));
        if ($domain === '') {
            throw new InvalidArgumentException('Don vi chua co domain');
        }
        $url = 'https://' . $domain;
        $this->audit->write($actor, 'unit.portal_opened', $id, 'Mo Tenant Portal tu Community Control Center', ['domain' => $domain]);
        return ['url' => $url];
    }

    private function validate(array $input, bool $creating = true): array
    {
        $data = [];

        if ($creating || array_key_exists('code', $input)) {
            $code = strtolower(trim((string) ($input['code'] ?? '')));
            if ($code === '' || !preg_match('/^[a-z0-9_-]{2,50}$/', $code)) {
                throw new InvalidArgumentException('Ma don vi khong hop le');
            }
            $data['code'] = $code;
        }

        if ($creating || array_key_exists('name', $input)) {
            $name = trim((string) ($input['name'] ?? ''));
            if ($name === '' || mb_strlen($name, 'UTF-8') > 190) {
                throw new InvalidArgumentException('Ten don vi khong hop le');
            }
            $data['name'] = $name;
        }

        if (array_key_exists('type', $input)) {
            $type = strtoupper(trim((string) $input['type']));
            if ($type !== 'VILLAGE') {
                throw new InvalidArgumentException('Loai don vi chua duoc ho tro trong feature nay');
            }
        }

        foreach (['unit_name', 'commune_name'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $this->nullableText($input[$field], 190, $field);
            }
        }

        foreach (['domain', 'subdomain'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $this->nullableHost($input[$field], $field);
            }
        }

        if (array_key_exists('database_name', $input)) {
            $data['database_name'] = $this->nullableDatabaseName($input['database_name']);
        }
        if (array_key_exists('database_host', $input)) {
            $data['database_host'] = $this->nullableDatabaseHost($input['database_host']);
        }
        if (array_key_exists('database_charset', $input)) {
            $data['database_charset'] = $this->nullableCharset($input['database_charset']);
        }
        if (array_key_exists('version', $input)) {
            $data['version'] = $this->nullableText($input['version'], 50, 'version');
        }
        foreach (['app_version', 'schema_version'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $this->nullableText($input[$field], 50, $field);
            }
        }
        if (array_key_exists('build_version', $input)) {
            $data['build_version'] = $this->nullableText($input['build_version'], 100, 'build_version');
        }
        foreach (['manager_name'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $this->nullableText($input[$field], 190, $field);
            }
        }
        if (array_key_exists('notes', $input)) {
            $data['notes'] = $this->nullableText($input['notes'], 2000, 'notes');
        }
        if (array_key_exists('connection_status', $input)) {
            $connectionStatus = strtoupper(trim((string) $input['connection_status']));
            if (!in_array($connectionStatus, ['CONNECTED', 'DISCONNECTED', 'UNKNOWN', 'LOCKED'], true)) {
                throw new InvalidArgumentException('Trang thai ket noi khong hop le');
            }
            $data['connection_status'] = $connectionStatus;
        } elseif ($creating) {
            $data['connection_status'] = 'UNKNOWN';
        }

        if (array_key_exists('logo', $input)) {
            $data['logo'] = $this->nullableLogo($input['logo']);
        }

        if (array_key_exists('status', $input)) {
            $status = strtoupper(trim((string) $input['status']));
            if (!in_array($status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Trang thai don vi khong hop le');
            }
            $data['status'] = $status;
        } elseif ($creating) {
            $data['status'] = 'ACTIVE';
        }

        return $data;
    }

    private function assertUnique(array $data, ?int $ignoreId = null): void
    {
        if (isset($data['code']) && $this->repository->existsByCode($data['code'], $ignoreId)) {
            throw new InvalidArgumentException('Ma don vi da ton tai');
        }
        if (isset($data['domain']) && $data['domain'] !== '' && $this->repository->existsByDomain($data['domain'], $ignoreId)) {
            throw new InvalidArgumentException('Domain da ton tai');
        }
        if (isset($data['subdomain']) && $data['subdomain'] !== '' && $this->repository->existsBySubdomain($data['subdomain'], $ignoreId)) {
            throw new InvalidArgumentException('Subdomain da ton tai');
        }
    }

    private function nullableText(mixed $value, int $max, string $field): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text, 'UTF-8') > $max) {
            throw new InvalidArgumentException($field . ' khong hop le');
        }
        return $text;
    }

    private function nullableHost(mixed $value, string $field): ?string
    {
        $host = strtolower(trim((string) ($value ?? '')));
        if ($host === '') {
            return null;
        }
        if (str_contains($host, '://') || str_contains($host, '/') || str_contains($host, '?')) {
            throw new InvalidArgumentException($field . ' khong hop le');
        }
        if (!preg_match('/^(?=.{1,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/', $host)) {
            throw new InvalidArgumentException($field . ' khong hop le');
        }
        return $host;
    }

    private function nullableLogo(mixed $value): ?string
    {
        $logo = trim((string) ($value ?? ''));
        if ($logo === '') {
            return null;
        }
        if (str_contains($logo, '..') || preg_match('/[\x00-\x1F]/', $logo) || mb_strlen($logo, 'UTF-8') > 500) {
            throw new InvalidArgumentException('Logo khong hop le');
        }
        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://') || str_starts_with($logo, '/')) {
            return $logo;
        }
        throw new InvalidArgumentException('Logo khong hop le');
    }

    private function nullableDatabaseName(mixed $value): ?string
    {
        $name = trim((string) ($value ?? ''));
        if ($name === '') {
            return null;
        }
        if (!preg_match('/^[a-zA-Z0-9_]{1,190}$/', $name)) {
            throw new InvalidArgumentException('Ten database khong hop le');
        }
        return $name;
    }

    private function nullableDatabaseHost(mixed $value): ?string
    {
        $host = trim((string) ($value ?? ''));
        if ($host === '') {
            return null;
        }
        if (mb_strlen($host, 'UTF-8') > 190 || str_contains($host, '/') || str_contains($host, '?')) {
            throw new InvalidArgumentException('Database host khong hop le');
        }
        return $host;
    }

    private function nullableCharset(mixed $value): ?string
    {
        $charset = strtolower(trim((string) ($value ?? '')));
        if ($charset === '') {
            return null;
        }
        if (!preg_match('/^[a-z0-9_]{1,50}$/', $charset)) {
            throw new InvalidArgumentException('Database charset khong hop le');
        }
        return $charset;
    }

    private function connectTenantDatabase(array $unit): void
    {
        $current = Database::diagnostics()['config'] ?? [];
        $host = trim((string) ($unit['databaseHost'] ?? '')) ?: (string) ($current['host'] ?? 'localhost');
        $database = trim((string) ($unit['databaseName'] ?? ''));
        $username = (string) env(['TENANT_REGISTRY_DB_USERNAME', 'DB_USERNAME', 'DB_USER']);
        $password = (string) env(['TENANT_REGISTRY_DB_PASSWORD', 'DB_PASSWORD', 'DB_PASS'], '');
        $charset = trim((string) ($unit['databaseCharset'] ?? '')) ?: (string) env(['TENANT_REGISTRY_DB_CHARSET', 'DB_CHARSET'], 'utf8mb4');
        $port = (int) env(['TENANT_REGISTRY_DB_PORT', 'DB_PORT'], '3306');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);
        $pdo->query('SELECT 1');
    }

    private function probeWebsite(string $domain): array
    {
        $url = 'https://' . $domain;
        $result = [
            'websiteStatus' => 'OFFLINE',
            'sslStatus' => 'UNKNOWN',
            'httpCode' => null,
            'error' => null,
        ];

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            curl_exec($curl);
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            $result['httpCode'] = $code ?: null;
            if ($errno === 0 && $code >= 200 && $code < 500) {
                $result['websiteStatus'] = 'ONLINE';
                $result['sslStatus'] = 'VALID';
                return $result;
            }
            $result['sslStatus'] = str_contains(strtolower($error), 'ssl') ? 'INVALID' : 'UNKNOWN';
            $result['error'] = $error ?: ('HTTP ' . ($code ?: 0));
            return $result;
        }

        $headers = @get_headers($url, true);
        if (is_array($headers) && isset($headers[0]) && preg_match('/\s([0-9]{3})\s/', (string) $headers[0], $match)) {
            $code = (int) $match[1];
            $result['httpCode'] = $code;
            if ($code >= 200 && $code < 500) {
                $result['websiteStatus'] = 'ONLINE';
                $result['sslStatus'] = 'UNKNOWN';
                return $result;
            }
        }
        $result['error'] = 'Website check failed';
        return $result;
    }
}
