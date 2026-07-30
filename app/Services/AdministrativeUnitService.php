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
    private const STATUSES = ['READY', 'DISABLED', 'MAINTENANCE', 'FAILED', 'CREATING', 'ACTIVE', 'INACTIVE'];

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
            throw new RuntimeException('Không tìm thấy đơn vị');
        }
        return $unit;
    }

    public function create(array $input): array
    {
        $actor = $this->authorization->authorize('control_center.units.create');
        $data = $this->validate($input);
        $this->assertUnique($data);
        $unit = $this->repository->create($data);
        $this->audit->write($actor, 'unit.created', (int) ($unit['id'] ?? 0), 'Tạo đơn vị hành chính', ['code' => $unit['code'] ?? null]);
        return $unit;
    }

    public function update(int $id, array $input): array
    {
        $actor = $this->authorization->authorize('control_center.units.update');
        $this->find($id);
        $data = $this->validate($input, false);
        $this->assertUnique($data, $id);
        $unit = $this->repository->update($id, $data);
        $this->audit->write($actor, 'unit.updated', $id, 'Cập nhật đơn vị hành chính', ['fields' => array_keys($data)]);
        return $unit;
    }

    public function lock(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.units.lock');
        $unit = $this->find($id);
        if (!in_array((string) ($unit['status'] ?? ''), ['READY', 'ACTIVE'], true)) {
            throw new InvalidArgumentException('Đơn vị không ở trạng thái có thể khóa');
        }
        $updated = $this->repository->setStatus($id, 'DISABLED');
        $this->audit->write($actor, 'unit.locked', $id, 'Khóa đơn vị hành chính', ['code' => $unit['code'] ?? null]);
        return $updated;
    }

    public function activate(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.units.activate');
        $unit = $this->find($id);
        if (in_array((string) ($unit['status'] ?? ''), ['READY', 'ACTIVE'], true)) {
            throw new InvalidArgumentException('Đơn vị đã được kích hoạt');
        }
        $updated = $this->repository->setStatus($id, 'ACTIVE');
        $this->audit->write($actor, 'unit.activated', $id, 'Kích hoạt đơn vị hành chính', ['code' => $unit['code'] ?? null]);
        return $updated;
    }

    public function checkConnection(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.units.update');
        $unit = $this->find($id);
        if (!in_array((string) ($unit['status'] ?? ''), ['READY', 'ACTIVE'], true)) {
            $updated = $this->repository->updateDatabaseHealth($id, 'LOCKED', 'Đơn vị đang bị khóa');
            $this->audit->write($actor, 'unit.connection_checked', $id, 'Kiểm tra kết nối đơn vị đang khóa', ['status' => 'LOCKED']);
            return $updated;
        }

        $database = trim((string) ($unit['databaseName'] ?? ''));
        if ($database === '') {
            $updated = $this->repository->updateDatabaseHealth($id, 'UNKNOWN', 'Thiếu tên cơ sở dữ liệu');
            $this->audit->write($actor, 'unit.connection_checked', $id, 'Kiểm tra kết nối đơn vị thiếu cơ sở dữ liệu', ['status' => 'UNKNOWN']);
            return $updated;
        }

        try {
            $this->connectTenantDatabase($unit);
            $updated = $this->repository->updateDatabaseHealth($id, 'CONNECTED');
            $this->audit->write($actor, 'unit.connection_checked', $id, 'Kiểm tra kết nối đơn vị thành công', ['status' => 'CONNECTED']);
            return $updated;
        } catch (PDOException $e) {
            $updated = $this->repository->updateDatabaseHealth($id, 'DISCONNECTED', 'Không kết nối được cơ sở dữ liệu');
            $this->audit->write($actor, 'unit.connection_checked', $id, 'Kiểm tra kết nối đơn vị thất bại', ['status' => 'DISCONNECTED'], 'WARN');
            return $updated;
        }
    }

    public function checkWebsite(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.units.update');
        $unit = $this->find($id);
        if (!in_array((string) ($unit['status'] ?? ''), ['READY', 'ACTIVE'], true)) {
            $updated = $this->repository->updateWebsiteHealth($id, 'LOCKED', 'UNKNOWN', 'Đơn vị đang bị khóa');
            $this->audit->write($actor, 'unit.website_checked', $id, 'Kiểm tra trang web đơn vị đang khóa', ['status' => 'LOCKED']);
            return $updated;
        }

        $domain = trim((string) ($unit['domain'] ?? ''));
        if ($domain === '') {
            $updated = $this->repository->updateWebsiteHealth($id, 'UNKNOWN', 'UNKNOWN', 'Thiếu tên miền');
            $this->audit->write($actor, 'unit.website_checked', $id, 'Kiểm tra trang web đơn vị thiếu tên miền', ['status' => 'UNKNOWN']);
            return $updated;
        }

        $result = $this->probeWebsite($domain);
        $updated = $this->repository->updateWebsiteHealth($id, $result['websiteStatus'], $result['sslStatus'], $result['error']);
        $this->audit->write($actor, 'unit.website_checked', $id, 'Kiểm tra trang web đơn vị', [
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
            throw new InvalidArgumentException('Đơn vị chưa có tên miền');
        }
        $url = 'https://' . $domain;
        $this->audit->write($actor, 'unit.portal_opened', $id, 'Mở cổng đơn vị từ Community Control Center', ['domain' => $domain]);
        return ['url' => $url];
    }

    private function validate(array $input, bool $creating = true): array
    {
        $data = [];

        if ($creating || array_key_exists('code', $input)) {
            $code = strtolower(trim((string) ($input['code'] ?? '')));
            if ($code === '' || !preg_match('/^[a-z0-9_-]{2,50}$/', $code)) {
                throw new InvalidArgumentException('Mã đơn vị không hợp lệ');
            }
            $data['code'] = $code;
        }

        if ($creating || array_key_exists('name', $input)) {
            $name = trim((string) ($input['name'] ?? ''));
            if ($name === '' || mb_strlen($name, 'UTF-8') > 190) {
                throw new InvalidArgumentException('Tên đơn vị không hợp lệ');
            }
            $data['name'] = $name;
        }

        if (array_key_exists('type', $input)) {
            $type = strtoupper(trim((string) $input['type']));
            if ($type !== 'VILLAGE') {
                throw new InvalidArgumentException('Loại đơn vị chưa được hỗ trợ trong tính năng này');
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
                throw new InvalidArgumentException('Trạng thái kết nối không hợp lệ');
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
                throw new InvalidArgumentException('Trạng thái đơn vị không hợp lệ');
            }
            $data['status'] = $status;
        } elseif ($creating) {
            $data['status'] = 'READY';
        }

        return $data;
    }

    private function assertUnique(array $data, ?int $ignoreId = null): void
    {
        if (isset($data['code']) && $this->repository->existsByCode($data['code'], $ignoreId)) {
            throw new InvalidArgumentException('Mã đơn vị đã tồn tại');
        }
        if (isset($data['domain']) && $data['domain'] !== '' && $this->repository->existsByDomain($data['domain'], $ignoreId)) {
            throw new InvalidArgumentException('Tên miền đã tồn tại');
        }
        if (isset($data['subdomain']) && $data['subdomain'] !== '' && $this->repository->existsBySubdomain($data['subdomain'], $ignoreId)) {
            throw new InvalidArgumentException('Tên miền phụ đã tồn tại');
        }
    }

    private function nullableText(mixed $value, int $max, string $field): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text, 'UTF-8') > $max) {
            throw new InvalidArgumentException($field . ' không hợp lệ');
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
            throw new InvalidArgumentException($field . ' không hợp lệ');
        }
        if (!preg_match('/^(?=.{1,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/', $host)) {
            throw new InvalidArgumentException($field . ' không hợp lệ');
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
            throw new InvalidArgumentException('Logo không hợp lệ');
        }
        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://') || str_starts_with($logo, '/')) {
            return $logo;
        }
        throw new InvalidArgumentException('Logo không hợp lệ');
    }

    private function nullableDatabaseName(mixed $value): ?string
    {
        $name = trim((string) ($value ?? ''));
        if ($name === '') {
            return null;
        }
        if (!preg_match('/^[a-zA-Z0-9_]{1,190}$/', $name)) {
            throw new InvalidArgumentException('Tên cơ sở dữ liệu không hợp lệ');
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
            throw new InvalidArgumentException('Máy chủ cơ sở dữ liệu không hợp lệ');
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
            throw new InvalidArgumentException('Bảng mã cơ sở dữ liệu không hợp lệ');
        }
        return $charset;
    }

    private function connectTenantDatabase(array $unit): void
    {
        $current = Database::diagnostics()['config'] ?? [];
        $tenantConfig = $this->tenantDatabaseConfig((string) ($unit['domain'] ?? ''));
        $host = trim((string) ($unit['databaseHost'] ?? '')) ?: (string) ($tenantConfig['host'] ?? $current['host'] ?? 'localhost');
        $database = trim((string) ($unit['databaseName'] ?? ''));
        $username = (string) ($tenantConfig['username'] ?? env(['TENANT_REGISTRY_DB_USERNAME', 'DB_USERNAME', 'DB_USER']));
        $password = (string) ($tenantConfig['password'] ?? env(['TENANT_REGISTRY_DB_PASSWORD', 'DB_PASSWORD', 'DB_PASS'], ''));
        $charset = trim((string) ($unit['databaseCharset'] ?? '')) ?: (string) ($tenantConfig['charset'] ?? env(['TENANT_REGISTRY_DB_CHARSET', 'DB_CHARSET'], 'utf8mb4'));
        $port = (int) ($tenantConfig['port'] ?? env(['TENANT_REGISTRY_DB_PORT', 'DB_PORT'], '3306'));
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);
        $pdo->query('SELECT 1');
    }

    private function tenantDatabaseConfig(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/:\d+$/', '', $domain) ?? $domain;
        $domain = preg_replace('/[^a-z0-9.-]/', '', $domain) ?? '';
        if ($domain === '') {
            return [];
        }

        $path = BASE_PATH . '/.env.' . $domain;
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
            $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }

        $database = $values['DB_DATABASE'] ?? $values['DB_NAME'] ?? '';
        $username = $values['DB_USERNAME'] ?? $values['DB_USER'] ?? '';
        if ($database === '' || $username === '') {
            return [];
        }

        return [
            'host' => $values['DB_HOST'] ?? 'localhost',
            'port' => (int) ($values['DB_PORT'] ?? 3306),
            'database' => $database,
            'username' => $username,
            'password' => $values['DB_PASSWORD'] ?? $values['DB_PASS'] ?? '',
            'charset' => $values['DB_CHARSET'] ?? 'utf8mb4',
        ];
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
        $result['error'] = 'Kiểm tra trang web thất bại';
        return $result;
    }
}
