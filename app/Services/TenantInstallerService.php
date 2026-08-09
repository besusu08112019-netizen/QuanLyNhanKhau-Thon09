<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\PlatformSettingsRepository;
use PDO;
use RuntimeException;
use Throwable;

final class TenantInstallerService
{
    private const STEPS = [
        'validate_input',
        'check_domain',
        'check_database_connection',
        'verify_database_ready',
        'initialize_database',
        'import_schema',
        'import_seed',
        'create_tenant_record',
        'create_admin',
        'write_config',
        'create_storage',
        'health_check',
        'mark_ready',
    ];

    private const DRY_RUN_STEPS = [
        'validate_input',
        'check_domain',
        'check_database_connection',
        'check_database_empty',
        'check_database_privileges',
        'check_registry',
        'check_storage_paths',
    ];

    private ?PDO $db = null;

    public function __construct(private ControlCenterAuditService $audit)
    {
    }

    public function start(array $input, array $actor): array
    {
        $data = $this->validateInput($input);
        $preflight = $this->preflight($input, $actor);
        if (!$preflight['ready']) {
            throw new RuntimeException('Tiền kiểm chưa đạt. Không thể tạo đơn vị.');
        }
        $existing = $this->findActiveJobByCode($data['code']);
        if ($existing && in_array((string) $existing['status'], ['CREATING', 'WAITING_MANUAL', 'FAILED'], true)) {
            return $this->run((int) $existing['id'], $actor);
        }

        $registryId = $this->upsertRegistryVillage($data, 'CREATING');
        $stmt = $this->db()->prepare(
            'INSERT INTO tenant_install_jobs (village_id, code, status, current_step, input_json, created_by)
             VALUES (:village_id, :code, "CREATING", "validate_input", :input_json, :created_by)'
        );
        $stmt->execute([
            'village_id' => $registryId,
            'code' => $data['code'],
            'input_json' => json_encode($this->storedInput($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by' => $actor['id'] ?? null,
        ]);
        $jobId = (int) $this->db()->lastInsertId();
        $this->audit->write($actor, 'tenant_install.started', null, 'Bắt đầu khởi tạo đơn vị', ['code' => $data['code']]);
        return $this->run($jobId, $actor);
    }

    public function dryRun(array $input, array $actor): array
    {
        $data = $this->validateInput($input);
        $preflight = $this->preflight($input, $actor);
        if (!$preflight['ready']) {
            throw new RuntimeException('Tiền kiểm chưa đạt. Không thể chạy thử.');
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO tenant_install_jobs (village_id, code, status, current_step, input_json, created_by)
             VALUES (NULL, :code, "CREATING", "dry_run", :input_json, :created_by)'
        );
        $stmt->execute([
            'code' => $data['code'],
            'input_json' => json_encode($this->storedInput($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by' => $actor['id'] ?? null,
        ]);
        $jobId = (int) $this->db()->lastInsertId();
        $this->writeInstallerAudit($jobId, $actor, null, 'dry_run.started', 'INFO', 'Bắt đầu chạy thử khởi tạo đơn vị', ['code' => $data['code']]);

        foreach (self::DRY_RUN_STEPS as $index => $step) {
            $this->startStep($jobId, $step, $index, count(self::DRY_RUN_STEPS), $actor);
            try {
                $details = $this->executeDryRunStep($step, $data);
                $this->finishStep($jobId, $step, 'DONE', $details['message'] ?? 'OK', $details, $actor);
            } catch (Throwable $e) {
                $this->finishStep($jobId, $step, 'FAILED', $e->getMessage(), ['type' => get_class($e)], $actor);
                $this->failJob($jobId, $step, 'DRY_RUN_FAILED', $e->getMessage());
                $this->writeInstallerAudit($jobId, $actor, $step, 'dry_run.failed', 'ERROR', $e->getMessage());
                return $this->status($jobId);
            }
        }

        $this->db()->prepare('UPDATE tenant_install_jobs SET status="DRY_RUN_PASSED", current_step="dry_run_complete", progress_percent=100, finished_at=NOW() WHERE id=:id')->execute(['id' => $jobId]);
        $this->writeInstallerAudit($jobId, $actor, null, 'dry_run.passed', 'INFO', 'Chạy thử khởi tạo đơn vị đạt', ['code' => $data['code']]);
        return $this->status($jobId);
    }

    public function preflight(array $input, array $actor): array
    {
        $items = [];
        $data = null;

        $this->addPreflightItem($items, 'input_valid', 'Tên miền hợp lệ', function () use ($input, &$data): array {
            $data = $this->validateInput($input);
            return ['message' => 'Dữ liệu đơn vị hợp lệ'];
        });

        if ($data !== null) {
            $this->addPreflightItem($items, 'database_connection', 'Cơ sở dữ liệu kết nối được', fn () => $this->preflightDatabaseConnection($data));
            $this->addPreflightItem($items, 'database_empty', 'Cơ sở dữ liệu rỗng', fn () => $this->preflightDatabaseEmpty($data));
            $this->addPreflightItem($items, 'database_privileges', 'Người dùng có quyền', fn () => $this->preflightDatabasePrivileges($data));
            $this->addPreflightItem($items, 'tenant_code_available', 'Mã đơn vị chưa tồn tại', fn () => $this->preflightTenantCodeAvailable($data));
            $this->addPreflightItem($items, 'registry_valid', 'Registry hợp lệ', fn () => $this->preflightRegistry());
            $this->addPreflightItem($items, 'source_writable', 'Mã nguồn có quyền ghi', fn () => $this->preflightWritable(BASE_PATH, 'Thư mục mã nguồn không có quyền ghi'));
            $this->addPreflightItem($items, 'storage_writable', 'Lưu trữ có quyền ghi', fn () => $this->preflightWritableTarget((string) $data['storage_path']));
            $this->addPreflightItem($items, 'upload_writable', 'Tải lên có quyền ghi', fn () => $this->preflightWritableTarget((string) $data['upload_path']));
            $this->addPreflightItem($items, 'backup_writable', 'Sao lưu có quyền ghi', fn () => $this->preflightWritableTarget((string) $data['backup_path']));
        }

        $ready = $items !== [] && count(array_filter($items, fn (array $item): bool => $item['status'] !== 'PASS')) === 0;
        $result = [
            'ready' => $ready,
            'status' => $ready ? 'READY_TO_CREATE_TENANT' : 'FAILED',
            'message' => $ready ? 'Sẵn sàng tạo đơn vị' : 'Tiền kiểm còn mục không đạt',
            'items' => $items,
        ];
        $this->audit->write($actor, 'tenant_install.preflight', null, 'Tiền kiểm khởi tạo đơn vị', ['status' => $result['status'], 'items' => $items], $ready ? 'INFO' : 'WARN');
        return $result;
    }

    public function databaseCheck(array $input, array $actor): array
    {
        $items = [];
        $data = null;
        $this->addPreflightItem($items, 'database_input', 'Thông tin cơ sở dữ liệu', function () use ($input, &$data): array {
            $data = $this->validateInput($input);
            if ((string) $data['database_password'] === '') {
                throw new RuntimeException('Mật khẩu cơ sở dữ liệu là bắt buộc');
            }
            return ['message' => 'Đã nhập máy chủ, cơ sở dữ liệu, người dùng và mật khẩu'];
        });
        if ($data !== null) {
            $this->addPreflightItem($items, 'database_host', 'Host', fn () => $this->preflightDatabaseConnection($data));
            $this->addPreflightItem($items, 'database_name', 'Cơ sở dữ liệu', fn () => $this->preflightDatabaseEmpty($data));
            $this->addPreflightItem($items, 'database_user', 'Người dùng', fn () => $this->preflightDatabaseConnection($data));
            $this->addPreflightItem($items, 'database_password', 'Mật khẩu', fn () => $this->preflightDatabaseConnection($data));
            $this->addPreflightItem($items, 'database_privileges', 'Quyền', fn () => $this->preflightDatabasePrivileges($data));
        }
        $ready = $items !== [] && count(array_filter($items, fn (array $item): bool => $item['status'] !== 'PASS')) === 0;
        $result = [
            'ready' => $ready,
            'status' => $ready ? 'DATABASE_READY' : 'FAILED',
            'message' => $ready ? 'Cơ sở dữ liệu kết nối thành công và đủ quyền' : 'Kiểm tra cơ sở dữ liệu còn mục không đạt',
            'items' => $items,
        ];
        $this->audit->write($actor, 'tenant_install.database_check', null, 'Kiểm tra kết nối cơ sở dữ liệu khi khởi tạo đơn vị', ['status' => $result['status'], 'items' => $items], $ready ? 'INFO' : 'WARN');
        return $result;
    }

    public function retry(int $jobId, array $actor): array
    {
        $job = $this->job($jobId);
        if (!$job) {
            throw new RuntimeException('Không tìm thấy tiến trình cài đặt đơn vị');
        }
        if (!in_array((string) $job['status'], ['FAILED', 'WAITING_MANUAL', 'CREATING'], true)) {
            throw new RuntimeException('Trạng thái tiến trình không thể thử lại');
        }
        $this->db()->prepare('UPDATE tenant_install_jobs SET status="CREATING", error_code=NULL, error_message=NULL, manual_action_json=NULL WHERE id=:id')->execute(['id' => $jobId]);
        $this->audit->write($actor, 'tenant_install.retry', (int) ($job['village_id'] ?? 0) ?: null, 'Thử lại khởi tạo đơn vị', ['job_id' => $jobId, 'code' => $job['code'] ?? null]);
        return $this->run($jobId, $actor);
    }

    public function rollback(int $jobId, array $actor): array
    {
        $job = $this->job($jobId);
        if (!$job) {
            throw new RuntimeException('Không tìm thấy tiến trình cài đặt đơn vị');
        }
        $input = $this->input($job);
        $villageId = (int) ($job['village_id'] ?? 0);

        $rollbackDetails = [];
        if ($this->stepWasStarted($jobId, 'import_schema')) {
            $this->clearTenantDatabase($input);
            $rollbackDetails[] = 'tenant_schema_cleared';
        }

        if ($villageId > 0) {
            $this->db()->prepare('DELETE FROM villages WHERE id=:id')->execute(['id' => $villageId, 'status' => $this->defaultTenantStatus()]);
            $rollbackDetails[] = 'registry_deleted';
        }

        $envPath = $this->envFilePath((string) ($input['domain'] ?? ''));
        if (is_file($envPath)) {
            @unlink($envPath);
            $rollbackDetails[] = 'config_deleted';
        }

        foreach (['storage_path', 'upload_path', 'backup_path'] as $key) {
            $path = (string) ($input[$key] ?? '');
            if ($path !== '' && is_dir($path)) {
                $this->removeDirectory($path);
                $rollbackDetails[] = $key . '_deleted';
            }
        }

        $this->db()->prepare('UPDATE tenant_install_jobs SET status="ROLLED_BACK", progress_percent=0, finished_at=NOW(), error_message=NULL WHERE id=:id')->execute(['id' => $jobId]);
        $this->markAllPendingRolledBack($jobId);
        $this->writeInstallerAudit($jobId, $actor, null, 'rollback.done', 'WARN', 'Hoàn tác khởi tạo đơn vị hoàn tất', ['actions' => $rollbackDetails]);
        $this->audit->write($actor, 'tenant_install.rollback', $villageId ?: null, 'Hoàn tác khởi tạo đơn vị', ['job_id' => $jobId, 'code' => $job['code'] ?? null, 'actions' => $rollbackDetails]);
        return $this->status($jobId);
    }

    public function status(int $jobId): array
    {
        $job = $this->job($jobId);
        if (!$job) {
            throw new RuntimeException('Không tìm thấy tiến trình cài đặt đơn vị');
        }
        return $this->normalizeJob($job);
    }

    private function run(int $jobId, array $actor): array
    {
        $job = $this->job($jobId);
        if (!$job) {
            throw new RuntimeException('Không tìm thấy tiến trình cài đặt đơn vị');
        }
        $input = $this->input($job);

        foreach (self::STEPS as $index => $step) {
            if ($this->stepStatus($jobId, $step) === 'DONE') {
                continue;
            }

            $this->startStep($jobId, $step, $index, count(self::STEPS), $actor);
            try {
                $details = $this->executeStep($step, $jobId, $input, $actor);
                $this->finishStep($jobId, $step, 'DONE', $details['message'] ?? 'OK', $details, $actor);
            } catch (TenantInstallerManualActionException $e) {
                $this->finishStep($jobId, $step, 'WAITING_MANUAL', $e->getMessage(), $e->details(), $actor);
                $this->waitingManual($jobId, $step, $e);
                return $this->status($jobId);
            } catch (Throwable $e) {
                $this->finishStep($jobId, $step, 'FAILED', $e->getMessage(), ['type' => get_class($e)], $actor);
                $this->failJob($jobId, $step, 'STEP_FAILED', $e->getMessage());
                $this->audit->write($actor, 'tenant_install.failed', $this->jobVillageId($jobId), 'Khởi tạo đơn vị lỗi', ['job_id' => $jobId, 'step' => $step, 'error' => $e->getMessage()], 'ERROR');
                return $this->status($jobId);
            }
        }

        $this->db()->prepare('UPDATE tenant_install_jobs SET status="READY", current_step="complete", progress_percent=100, finished_at=NOW() WHERE id=:id')->execute(['id' => $jobId]);
        $this->audit->write($actor, 'tenant_install.ready', $this->jobVillageId($jobId), 'Khởi tạo đơn vị hoàn tất', ['job_id' => $jobId]);
        return $this->status($jobId);
    }

    private function executeStep(string $step, int $jobId, array $input, array $actor): array
    {
        return match ($step) {
            'validate_input' => ['message' => 'Dữ liệu hợp lệ'],
            'check_domain' => $this->checkDomain($input),
            'check_database_connection' => $this->checkDatabaseConnection($input),
            'verify_database_ready' => $this->verifyDatabaseReady($input),
            'initialize_database' => $this->initializeDatabase($input),
            'import_schema' => $this->executeSqlFile($this->tenantPdo($input), BASE_PATH . '/database/schema.sql', 'schema.sql'),
            'import_seed' => $this->executeSqlFile($this->tenantPdo($input), BASE_PATH . '/database/seed.sql', 'seed.sql'),
            'create_tenant_record' => $this->createTenantRecord($jobId, $input),
            'create_admin' => $this->createAdmin($jobId, $input, $actor),
            'write_config' => $this->writeConfig($input),
            'create_storage' => $this->createStorage($input),
            'health_check' => $this->healthCheck($input),
            'mark_ready' => $this->markReady($jobId),
            default => throw new RuntimeException('Step không hợp lệ: ' . $step),
        };
    }

    private function validateInput(array $input): array
    {
        $code = strtolower(trim((string) ($input['code'] ?? '')));
        if ($code === '' || !preg_match('/^[a-z0-9_-]{2,50}$/', $code)) {
            throw new RuntimeException('Mã thôn không hợp lệ');
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 190) {
            throw new RuntimeException('Tên thôn không hợp lệ');
        }
        $domain = strtolower(trim((string) (($input['domain'] ?? '') ?: ($input['subdomain'] ?? ''))));
        if ($domain === '' || str_contains($domain, '://') || str_contains($domain, '/') || !preg_match('/^(?=.{1,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/', $domain)) {
            throw new RuntimeException('Tên miền hoặc tên miền phụ không hợp lệ');
        }
        $database = trim((string) ($input['database_name'] ?? ''));
        if ($database === '' || !preg_match('/^[a-zA-Z0-9_]{1,190}$/', $database)) {
            throw new RuntimeException('Tên cơ sở dữ liệu không hợp lệ');
        }
        $username = trim((string) ($input['database_username'] ?? $input['db_username'] ?? ''));
        if ($username === '') {
            throw new RuntimeException('Người dùng cơ sở dữ liệu là bắt buộc');
        }
        $host = trim((string) ($input['database_host'] ?? 'localhost')) ?: 'localhost';
        $charset = strtolower(trim((string) ($input['database_charset'] ?? 'utf8mb4'))) ?: 'utf8mb4';
        if (!preg_match('/^[a-z0-9_]{1,50}$/', $charset)) {
            throw new RuntimeException('Bảng mã cơ sở dữ liệu không hợp lệ');
        }

        $storageRoot = BASE_PATH . '/storage/' . $domain;
        return [
            'code' => $code,
            'name' => $name,
            'unit_name' => trim((string) ($input['unit_name'] ?? $name)),
            'commune_name' => trim((string) ($input['commune_name'] ?? '')),
            'domain' => $domain,
            'subdomain' => strtolower(trim((string) ($input['subdomain'] ?? $domain))),
            'database_host' => $host,
            'database_port' => (int) ($input['database_port'] ?? 3306) ?: 3306,
            'database_name' => $database,
            'database_username' => $username,
            'database_password' => (string) ($input['database_password'] ?? $input['db_password'] ?? ''),
            'database_charset' => $charset,
            'admin_email' => strtolower(trim((string) ($input['admin_email'] ?? ('admin@' . $domain)))),
            'admin_username' => $this->adminUsername((string) ($input['admin_username'] ?? $input['admin_email'] ?? ('admin@' . $domain))),
            'admin_name' => trim((string) ($input['admin_name'] ?? 'Quản trị ' . $name)),
            'admin_password' => (string) ($input['admin_password'] ?? ''),
            'app_url' => 'https://' . $domain,
            'storage_path' => (string) ($input['storage_path'] ?? $storageRoot),
            'upload_path' => (string) ($input['upload_path'] ?? BASE_PATH . '/uploads/' . $domain),
            'backup_path' => (string) ($input['backup_path'] ?? BASE_PATH . '/backups/' . $domain),
        ];
    }

    private function adminUsername(string $value): string
    {
        $base = strtolower(trim($value));
        if (str_contains($base, '@')) {
            $base = strstr($base, '@', true) ?: 'admin';
        }
        $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?: 'admin';
        if (strlen($base) < 3) {
            $base = str_pad($base, 3, '0');
        }
        return substr($base, 0, 60);
    }
    private function checkDomain(array $input): array
    {
        $host = (string) $input['domain'];
        $records = function_exists('dns_get_record') ? @dns_get_record($host, DNS_A + DNS_CNAME) : false;
        return ['message' => $records ? 'Tên miền có bản ghi DNS' : 'Không đọc được bản ghi DNS, tiếp tục kiểm tra trang web sau', 'dnsFound' => (bool) $records];
    }

    private function checkDatabaseConnection(array $input): array
    {
        $this->tenantPdo($input)->query('SELECT 1');
        return ['message' => 'Kết nối cơ sở dữ liệu đơn vị thành công'];
    }

    private function verifyDatabaseReady(array $input): array
    {
        $this->preflightDatabaseEmpty($input);
        $this->preflightDatabasePrivileges($input);
        return ['message' => 'Cơ sở dữ liệu đã được cấp sẵn, rỗng và người dùng đủ quyền'];
    }

    private function initializeDatabase(array $input): array
    {
        $pdo = $this->tenantPdo($input);
        $tables = (int) $pdo->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
        if ($tables > 0) {
            throw new RuntimeException('Cơ sở dữ liệu không rỗng, dừng để tránh ghi đè đơn vị hiện có');
        }
        return ['message' => 'Cơ sở dữ liệu rỗng, sẵn sàng nhập dữ liệu'];
    }

    private function createTenantRecord(int $jobId, array $input): array
    {
        $pdo = $this->tenantPdo($input);
        $stmt = $pdo->prepare(
            'UPDATE villages
             SET code=:code, name=:name, unit_name=:unit_name, commune_name=:commune_name, domain=:domain, subdomain=:subdomain, status="ACTIVE"
             WHERE code="default" OR id=(SELECT id FROM (SELECT id FROM villages ORDER BY id ASC LIMIT 1) x)'
        );
        $stmt->execute([
            'code' => $input['code'],
            'name' => $input['name'],
            'unit_name' => $input['unit_name'],
            'commune_name' => $input['commune_name'],
            'domain' => $input['domain'],
            'subdomain' => $input['subdomain'],
        ]);
        $tenantVillageId = (int) $pdo->query('SELECT id FROM villages WHERE code=' . $pdo->quote((string) $input['code']) . ' LIMIT 1')->fetchColumn();

        $registryId = $this->upsertRegistryVillage($input, 'CREATING');
        $this->db()->prepare('UPDATE tenant_install_jobs SET village_id=:village_id WHERE id=:id')->execute(['village_id' => $registryId, 'id' => $jobId]);
        return ['message' => 'Đã ghi bản ghi đơn vị và registry', 'tenantVillageId' => $tenantVillageId, 'registryVillageId' => $registryId];
    }

    private function createAdmin(int $jobId, array $input, array $actor): array
    {
        $pdo = $this->tenantPdo($input);
        $email = (string) $input['admin_email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email quản trị đơn vị không hợp lệ');
        }
        $villageId = (int) $pdo->query('SELECT id FROM villages WHERE code=' . $pdo->quote((string) $input['code']) . ' LIMIT 1')->fetchColumn();
        if ($villageId <= 0) {
            throw new RuntimeException('Không tìm thấy đơn vị trong cơ sở dữ liệu đơn vị');
        }
        $exists = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE village_id=' . $villageId)->fetchColumn();
        if ($exists > 0) {
            return ['message' => 'Đơn vị đã có tài khoản quản trị, bỏ qua'];
        }

        $password = (string) $input['admin_password'];
        $generated = false;
        if ($password === '') {
            $password = bin2hex(random_bytes(6)) . 'Aa1!';
            $generated = true;
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Mật khẩu quản trị đơn vị tối thiểu 8 ký tự');
        }
        $columns = ['village_id', 'email', 'display_name', 'password_hash', 'role', 'status', 'created_by'];
        $params = [
            'village_id' => $villageId,
            'email' => $email,
            'display_name' => $input['admin_name'] ?: $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'created_by' => null,
        ];
        $userColumns = array_map('strtolower', $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN));
        if (in_array('username', $userColumns, true)) {
            $columns[] = 'username';
            $params['username'] = (string) $input['admin_username'];
        }
        $stmt = $pdo->prepare('INSERT INTO users (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')');
        $stmt->execute($params);
        if ($generated) {
            $this->mergeJobResult($jobId, ['generatedAdminEmail' => $email, 'generatedAdminPassword' => $password]);
        }
        return ['message' => 'Đã tạo tài khoản quản trị đơn vị', 'adminEmail' => $email, 'generatedPassword' => $generated];
    }

    private function writeConfig(array $input): array
    {
        $path = $this->envFilePath((string) $input['domain']);
        if (is_file($path)) {
            return ['message' => 'File cấu hình đã tồn tại', 'file' => basename($path)];
        }
        $lines = [
            'APP_NAME=' . $this->envQuote('He thong Quan ly Hanh chinh'),
            'APP_URL=' . $this->envQuote((string) $input['app_url']),
            'APP_KEY=' . $this->envQuote(bin2hex(random_bytes(32))),
            'APP_TIMEZONE=Asia/Ho_Chi_Minh',
            'APP_DEBUG=false',
            'APP_VERSION=v2.0',
            'APP_HOST=' . $this->envQuote((string) $input['domain']),
            'TENANT_DEFAULT_VILLAGE_CODE=' . $this->envQuote((string) $input['code']),
            'TENANT_UNIT_NAME=' . $this->envQuote((string) $input['unit_name']),
            'TENANT_HAMLET_NAME=' . $this->envQuote((string) $input['name']),
            'TENANT_COMMUNE_NAME=' . $this->envQuote((string) $input['commune_name']),
            'TENANT_WEBSITE=' . $this->envQuote((string) $input['app_url']),
            'UPLOAD_PATH=' . $this->envQuote((string) $input['upload_path']),
            'STORAGE_PATH=' . $this->envQuote((string) $input['storage_path']),
            'CACHE_PATH=' . $this->envQuote((string) $input['storage_path'] . '/cache'),
            'LOGS_PATH=' . $this->envQuote((string) $input['storage_path'] . '/logs'),
            'BACKUP_PATH=' . $this->envQuote((string) $input['backup_path']),
            'DB_HOST=' . $this->envQuote((string) $input['database_host']),
            'DB_PORT=' . (int) $input['database_port'],
            'DB_DATABASE=' . $this->envQuote((string) $input['database_name']),
            'DB_USERNAME=' . $this->envQuote((string) $input['database_username']),
            'DB_PASSWORD=' . $this->envQuote((string) $input['database_password']),
            'DB_CHARSET=' . $this->envQuote((string) $input['database_charset']),
            'CONTROL_CENTER_DB_HOST=' . $this->envQuote((string) (getenv('DB_HOST') ?: 'localhost')),
            'CONTROL_CENTER_DB_PORT=' . (string) (getenv('DB_PORT') ?: '3306'),
            'CONTROL_CENTER_DB_DATABASE=' . $this->envQuote((string) (getenv('DB_DATABASE') ?: '')),
            'CONTROL_CENTER_DB_USERNAME=' . $this->envQuote((string) (getenv('DB_USERNAME') ?: '')),
            'CONTROL_CENTER_DB_PASSWORD=' . $this->envQuote((string) (getenv('DB_PASSWORD') ?: '')),
            'CONTROL_CENTER_DB_CHARSET=utf8mb4',
        ];
        if (!@file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX)) {
            throw new RuntimeException('Không ghi được file cấu hình đơn vị: ' . basename($path));
        }
        return ['message' => 'Đã sinh cấu hình đơn vị', 'file' => basename($path)];
    }

    private function createStorage(array $input): array
    {
        foreach ([(string) $input['storage_path'], (string) $input['storage_path'] . '/cache', (string) $input['storage_path'] . '/logs', (string) $input['storage_path'] . '/sessions', (string) $input['upload_path'], (string) $input['backup_path']] as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                throw new RuntimeException('Không tạo được thư mục: ' . $dir);
            }
        }
        return ['message' => 'Đã tạo thư mục lưu trữ/tải lên/sao lưu'];
    }

    private function healthCheck(array $input): array
    {
        $this->tenantPdo($input)->query('SELECT COUNT(*) FROM users');
        return ['message' => 'Kiểm tra sức khỏe cơ sở dữ liệu thành công'];
    }

    private function executeDryRunStep(string $step, array $input): array
    {
        return match ($step) {
            'validate_input' => ['message' => 'Dữ liệu hợp lệ'],
            'check_domain' => $this->checkDomain($input),
            'check_database_connection' => $this->preflightDatabaseConnection($input),
            'check_database_empty' => $this->preflightDatabaseEmpty($input),
            'check_database_privileges' => $this->preflightDatabasePrivileges($input),
            'check_registry' => $this->preflightRegistry(),
            'check_storage_paths' => $this->preflightStoragePaths($input),
            default => throw new RuntimeException('Bước chạy thử không hợp lệ: ' . $step),
        };
    }

    private function addPreflightItem(array &$items, string $key, string $label, callable $check): void
    {
        try {
            $details = $check();
            $items[] = [
                'key' => $key,
                'label' => $label,
                'status' => 'PASS',
                'message' => (string) (($details['message'] ?? null) ?: 'PASS'),
                'fix' => null,
                'details' => $this->redact(is_array($details) ? $details : []),
            ];
        } catch (Throwable $e) {
            $items[] = [
                'key' => $key,
                'label' => $label,
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'fix' => $this->preflightFix($key),
                'details' => null,
            ];
        }
    }

    private function preflightDatabaseConnection(array $input): array
    {
        $this->tenantPdo($input)->query('SELECT 1');
        return ['message' => 'Kết nối cơ sở dữ liệu đơn vị thành công'];
    }

    private function preflightDatabaseEmpty(array $input): array
    {
        $tables = (int) $this->tenantPdo($input)->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
        if ($tables > 0) {
            throw new RuntimeException('Cơ sở dữ liệu không rỗng: có ' . $tables . ' bảng');
        }
        return ['message' => 'Cơ sở dữ liệu rỗng'];
    }

    private function preflightDatabasePrivileges(array $input): array
    {
        $pdo = $this->tenantPdo($input);
        $table = '`_tenant_installer_preflight_' . bin2hex(random_bytes(3)) . '`';
        $created = false;
        try {
            $pdo->exec('CREATE TABLE ' . $table . ' (id INT NOT NULL PRIMARY KEY, value VARCHAR(20) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $created = true;
            $pdo->exec('INSERT INTO ' . $table . ' (id, value) VALUES (1, "ok")');
            $pdo->query('SELECT value FROM ' . $table . ' WHERE id=1')->fetchColumn();
            $pdo->exec('UPDATE ' . $table . ' SET value="done" WHERE id=1');
            $pdo->exec('DELETE FROM ' . $table . ' WHERE id=1');
        } finally {
            if ($created) {
                $pdo->exec('DROP TABLE IF EXISTS ' . $table);
            }
        }
        return ['message' => 'Người dùng cơ sở dữ liệu có quyền CREATE/INSERT/SELECT/UPDATE/DELETE/DROP'];
    }

    private function preflightTenantCodeAvailable(array $input): array
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM villages WHERE code=:code OR domain=:domain OR subdomain=:subdomain');
        $stmt->execute(['code' => $input['code'], 'domain' => $input['domain'], 'subdomain' => $input['subdomain']]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Mã đơn vị/tên miền/tên miền phụ đã tồn tại trong Registry');
        }
        return ['message' => 'Mã đơn vị và khóa Registry chưa tồn tại'];
    }

    private function preflightRegistry(): array
    {
        $this->db()->query('SELECT id, code, status FROM villages LIMIT 1');
        return ['message' => 'Registry đơn vị sẵn sàng'];
    }

    private function preflightStoragePaths(array $input): array
    {
        $this->preflightWritableTarget((string) $input['storage_path']);
        $this->preflightWritableTarget((string) $input['upload_path']);
        $this->preflightWritableTarget((string) $input['backup_path']);
        return ['message' => 'Lưu trữ/tải lên/sao lưu có quyền ghi'];
    }

    private function preflightWritable(string $path, string $message): array
    {
        if (!is_dir($path) || !is_writable($path)) {
            throw new RuntimeException($message . ': ' . $path);
        }
        return ['message' => 'Có quyền ghi: ' . basename($path)];
    }

    private function preflightWritableTarget(string $path): array
    {
        $target = $path;
        while ($target !== '' && !is_dir($target)) {
            $parent = dirname($target);
            if ($parent === $target) {
                break;
            }
            $target = $parent;
        }
        if ($target === '' || !is_dir($target) || !is_writable($target)) {
            throw new RuntimeException('Thư mục cha không có quyền ghi: ' . $path);
        }
        return ['message' => 'Đường dẫn có quyền ghi: ' . $path, 'checkedParent' => $target];
    }

    private function preflightFix(string $key): string
    {
        return match ($key) {
            'input_valid' => 'Sửa thông tin đơn vị trên form',
            'database_connection' => 'Tạo cơ sở dữ liệu/người dùng trong cPanel, gán người dùng vào cơ sở dữ liệu và nhập đúng thông tin kết nối',
            'database_empty' => 'Dùng cơ sở dữ liệu rỗng mới tạo cho đơn vị này',
            'database_privileges' => 'Trong cPanel, gán người dùng vào cơ sở dữ liệu với đầy đủ quyền quản lý bảng và dữ liệu',
            'tenant_code_available' => 'Chọn mã đơn vị/tên miền khác hoặc hoàn tác đơn vị đang lỗi',
            'registry_valid' => 'Kiểm tra cơ sở dữ liệu Community Control Center và bảng villages',
            'source_writable' => 'Cấp quyền ghi cho thư mục mã nguồn để sinh .env đơn vị',
            'storage_writable', 'upload_writable', 'backup_writable' => 'Tạo thư mục cha và cấp quyền ghi cho người dùng web',
            default => 'Kiểm tra cấu hình và thử lại',
        };
    }

    private function markReady(int $jobId): array
    {
        $villageId = $this->jobVillageId($jobId);
        if ($villageId > 0) {
            $this->db()->prepare(
                'UPDATE villages SET status=:status, connection_status="CONNECTED", database_status="CONNECTED", website_status="UNKNOWN", last_checked_at=NOW(), last_database_checked_at=NOW(), last_error=NULL WHERE id=:id'
            )->execute(['id' => $villageId, 'status' => $this->defaultTenantStatus()]);
        }
        return ['message' => 'Đơn vị sẵn sàng'];
    }

    private function defaultTenantStatus(): string
    {
        try {
            $status = strtoupper((string) (new PlatformSettingsRepository())->value('tenant.default_status', 'ACTIVE'));
            return in_array($status, ['ACTIVE', 'PENDING_ACTIVATION'], true) ? $status : 'ACTIVE';
        } catch (Throwable $e) {
            return 'ACTIVE';
        }
    }
    private function executeSqlFile(PDO $pdo, string $path, string $label): array
    {
        if (!is_readable($path)) {
            throw new RuntimeException('Không đọc được ' . $label);
        }
        $count = 0;
        foreach ($this->splitSql((string) file_get_contents($path)) as $statement) {
            $pdo->exec($statement);
            $count++;
        }
        return ['message' => 'Đã import ' . $label, 'statements' => $count];
    }

    private function splitSql(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $statements = [];
        $buffer = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';
            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $buffer .= $char;
                }
                continue;
            }
            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                }
                continue;
            }
            if ($quote === null && $char === '-' && $next === '-' && ($i + 2 >= $length || preg_match('/\s/', $sql[$i + 2]) === 1)) {
                $lineComment = true;
                $i++;
                continue;
            }
            if ($quote === null && $char === '#') {
                $lineComment = true;
                continue;
            }
            if ($quote === null && $char === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }
        return $statements;
    }

    private function upsertRegistryVillage(array $input, string $status): int
    {
        $existing = $this->db()->prepare('SELECT id FROM villages WHERE code=:code OR domain=:domain OR subdomain=:subdomain LIMIT 1');
        $existing->execute(['code' => $input['code'], 'domain' => $input['domain'], 'subdomain' => $input['subdomain']]);
        $id = (int) ($existing->fetchColumn() ?: 0);
        if ($id > 0) {
            $stmt = $this->db()->prepare(
                'UPDATE villages SET name=:name, unit_name=:unit_name, commune_name=:commune_name, domain=:domain, subdomain=:subdomain, database_name=:database_name, database_host=:database_host, database_charset=:database_charset, status=:status, last_error=NULL WHERE id=:id'
            );
            $stmt->execute([
                'id' => $id,
                'name' => $input['name'],
                'unit_name' => $input['unit_name'],
                'commune_name' => $input['commune_name'],
                'domain' => $input['domain'],
                'subdomain' => $input['subdomain'],
                'database_name' => $input['database_name'],
                'database_host' => $input['database_host'],
                'database_charset' => $input['database_charset'],
                'status' => $status,
            ]);
            return $id;
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO villages (code,name,unit_name,commune_name,domain,subdomain,database_name,database_host,database_charset,status,connection_status,database_status,website_status)
             VALUES (:code,:name,:unit_name,:commune_name,:domain,:subdomain,:database_name,:database_host,:database_charset,:status,"UNKNOWN","UNKNOWN","UNKNOWN")'
        );
        $stmt->execute([
            'code' => $input['code'],
            'name' => $input['name'],
            'unit_name' => $input['unit_name'],
            'commune_name' => $input['commune_name'],
            'domain' => $input['domain'],
            'subdomain' => $input['subdomain'],
            'database_name' => $input['database_name'],
            'database_host' => $input['database_host'],
            'database_charset' => $input['database_charset'],
            'status' => $status,
        ]);
        return (int) $this->db()->lastInsertId();
    }

    private function tenantPdo(array $input): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $input['database_host'], $input['database_port'], $input['database_name'], $input['database_charset']);
        return new PDO($dsn, (string) $input['database_username'], (string) $input['database_password'], $this->pdoOptions());
    }

    private function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ];
    }

    private function db(): PDO
    {
        return $this->db ??= Database::pdo();
    }

    private function job(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tenant_install_jobs WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findActiveJobByCode(string $code): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tenant_install_jobs WHERE code=:code AND status IN ("CREATING","WAITING_MANUAL","FAILED") ORDER BY id DESC LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function input(array $job): array
    {
        return json_decode((string) $job['input_json'], true) ?: [];
    }

    private function storedInput(array $input): array
    {
        return $input;
    }

    private function stepStatus(int $jobId, string $step): ?string
    {
        $stmt = $this->db()->prepare('SELECT status FROM tenant_install_job_steps WHERE job_id=:job_id AND step_key=:step');
        $stmt->execute(['job_id' => $jobId, 'step' => $step]);
        $status = $stmt->fetchColumn();
        return $status ? (string) $status : null;
    }

    private function startStep(int $jobId, string $step, int $index, int $totalSteps, array $actor): void
    {
        $percent = (int) floor(($index / max(1, $totalSteps)) * 100);
        $this->db()->prepare('UPDATE tenant_install_jobs SET current_step=:step, progress_percent=:percent, status="CREATING" WHERE id=:id')->execute(['id' => $jobId, 'step' => $step, 'percent' => $percent]);
        $this->db()->prepare(
            'INSERT INTO tenant_install_job_steps (job_id, step_key, status, attempts, started_at)
             VALUES (:job_id,:step,"RUNNING",1,NOW())
             ON DUPLICATE KEY UPDATE status="RUNNING", attempts=attempts+1, started_at=NOW(), message=NULL'
        )->execute(['job_id' => $jobId, 'step' => $step]);
        $this->writeInstallerAudit($jobId, $actor, $step, 'step.running', 'INFO', 'Đang chạy step ' . $step);
    }

    private function finishStep(int $jobId, string $step, string $status, string $message, array $details = [], ?array $actor = null): void
    {
        $this->db()->prepare(
            'UPDATE tenant_install_job_steps SET status=:status, message=:message, details_json=:details, finished_at=NOW() WHERE job_id=:job_id AND step_key=:step'
        )->execute([
            'job_id' => $jobId,
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'details' => json_encode($this->redact($details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $this->writeInstallerAudit($jobId, $actor, $step, 'step.' . strtolower($status), $status === 'FAILED' ? 'ERROR' : ($status === 'WAITING_MANUAL' ? 'WARN' : 'INFO'), $message, $details);
    }

    private function waitingManual(int $jobId, string $step, TenantInstallerManualActionException $e): void
    {
        $this->db()->prepare(
            'UPDATE tenant_install_jobs SET status="WAITING_MANUAL", current_step=:step, manual_action_json=:manual, error_code="MANUAL_ACTION_REQUIRED", error_message=:message WHERE id=:id'
        )->execute([
            'id' => $jobId,
            'step' => $step,
            'manual' => json_encode($e->details(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'message' => $e->getMessage(),
        ]);
    }

    private function failJob(int $jobId, string $step, string $code, string $message): void
    {
        $villageId = $this->jobVillageId($jobId);
        if ($villageId > 0) {
            $this->db()->prepare('UPDATE villages SET status="FAILED", last_error=:error WHERE id=:id')->execute(['id' => $villageId, 'error' => substr($message, 0, 255)]);
        }
        $this->db()->prepare('UPDATE tenant_install_jobs SET status="FAILED", current_step=:step, error_code=:code, error_message=:message WHERE id=:id')->execute([
            'id' => $jobId,
            'step' => $step,
            'code' => $code,
            'message' => $message,
        ]);
    }

    private function jobVillageId(int $jobId): ?int
    {
        $stmt = $this->db()->prepare('SELECT village_id FROM tenant_install_jobs WHERE id=:id');
        $stmt->execute(['id' => $jobId]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        return $id > 0 ? $id : null;
    }

    private function mergeJobResult(int $jobId, array $result): void
    {
        $job = $this->job($jobId);
        $current = $job ? (json_decode((string) ($job['result_json'] ?? ''), true) ?: []) : [];
        $this->db()->prepare('UPDATE tenant_install_jobs SET result_json=:result WHERE id=:id')->execute([
            'id' => $jobId,
            'result' => json_encode($current + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function normalizeJob(array $job): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tenant_install_job_steps WHERE job_id=:job_id ORDER BY id ASC');
        $stmt->execute(['job_id' => $job['id']]);
        $steps = array_map(function (array $row): array {
            return [
                'step' => (string) $row['step_key'],
                'status' => (string) $row['status'],
                'attempts' => (int) $row['attempts'],
                'message' => (string) ($row['message'] ?? ''),
                'details' => json_decode((string) ($row['details_json'] ?? ''), true) ?: null,
                'startedAt' => $row['started_at'] ?? null,
                'finishedAt' => $row['finished_at'] ?? null,
            ];
        }, $stmt->fetchAll() ?: []);
        $auditStmt = $this->db()->prepare('SELECT step_key,event,level,message,details_json,created_at FROM tenant_install_audit_logs WHERE job_id=:job_id ORDER BY id ASC');
        $auditStmt->execute(['job_id' => $job['id']]);
        $logs = array_map(function (array $row): array {
            return [
                'step' => $row['step_key'] !== null ? (string) $row['step_key'] : null,
                'event' => (string) $row['event'],
                'level' => (string) $row['level'],
                'message' => (string) ($row['message'] ?? ''),
                'details' => json_decode((string) ($row['details_json'] ?? ''), true) ?: null,
                'createdAt' => $row['created_at'] ?? null,
            ];
        }, $auditStmt->fetchAll() ?: []);
        return [
            'id' => (int) $job['id'],
            'villageId' => $job['village_id'] !== null ? (int) $job['village_id'] : null,
            'code' => (string) $job['code'],
            'status' => (string) $job['status'],
            'currentStep' => (string) ($job['current_step'] ?? ''),
            'progressPercent' => (int) $job['progress_percent'],
            'manualAction' => json_decode((string) ($job['manual_action_json'] ?? ''), true) ?: null,
            'errorCode' => (string) ($job['error_code'] ?? ''),
            'errorMessage' => (string) ($job['error_message'] ?? ''),
            'result' => json_decode((string) ($job['result_json'] ?? ''), true) ?: null,
            'steps' => $steps,
            'auditLogs' => $logs,
            'createdAt' => $job['created_at'] ?? null,
            'updatedAt' => $job['updated_at'] ?? null,
            'finishedAt' => $job['finished_at'] ?? null,
        ];
    }

    private function envFilePath(string $domain): string
    {
        $host = preg_replace('/[^a-z0-9.-]/', '', strtolower(trim($domain))) ?: 'tenant';
        return BASE_PATH . '/.env.' . $host;
    }

    private function envQuote(string $value): string
    {
        if ($value === '' || preg_match('/\s|#|"|=/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }
        return $value;
    }

    private function redact(array $value): array
    {
        foreach ($value as $key => $item) {
            if (preg_match('/password|secret|token/i', (string) $key)) {
                $value[$key] = '[REDACTED]';
            } elseif (is_array($item)) {
                $value[$key] = $this->redact($item);
            }
        }
        return $value;
    }

    private function markAllPendingRolledBack(int $jobId): void
    {
        $this->db()->prepare('UPDATE tenant_install_job_steps SET status="ROLLED_BACK" WHERE job_id=:job_id AND status IN ("PENDING","FAILED","WAITING_MANUAL","RUNNING")')->execute(['job_id' => $jobId]);
    }

    private function stepWasStarted(int $jobId, string $step): bool
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM tenant_install_job_steps WHERE job_id=:job_id AND step_key=:step AND status IN ("RUNNING","DONE","FAILED","WAITING_MANUAL")');
        $stmt->execute(['job_id' => $jobId, 'step' => $step]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function clearTenantDatabase(array $input): void
    {
        $pdo = $this->tenantPdo($input);
        $tables = $pdo->query('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if ($tables === []) {
            return;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                $quoted = '`' . str_replace('`', '``', (string) $table) . '`';
                $pdo->exec('DROP TABLE IF EXISTS ' . $quoted);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function removeDirectory(string $path): void
    {
        $base = realpath(BASE_PATH);
        $target = realpath($path);
        if ($base === false || $target === false || !str_starts_with($target, $base . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Không rollback được thư mục ngoài project: ' . $path);
        }
        $items = array_diff(scandir($target) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $child = $target . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) {
                $this->removeDirectory($child);
            } else {
                @unlink($child);
            }
        }
        @rmdir($target);
    }

    private function writeInstallerAudit(int $jobId, ?array $actor, ?string $step, string $event, string $level, string $message, array $details = []): void
    {
        try {
            $this->db()->prepare(
                'INSERT INTO tenant_install_audit_logs (job_id, actor_user_id, actor_email, step_key, event, level, message, details_json)
                 VALUES (:job_id,:actor_user_id,:actor_email,:step,:event,:level,:message,:details)'
            )->execute([
                'job_id' => $jobId,
                'actor_user_id' => $actor['id'] ?? null,
                'actor_email' => $actor['email'] ?? null,
                'step' => $step,
                'event' => $event,
                'level' => $level,
                'message' => $message,
                'details' => json_encode($this->redact($details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
            // Installer audit must not block the installer itself.
        }
    }
}

final class TenantInstallerManualActionException extends RuntimeException
{
    public function __construct(string $message, private array $details)
    {
        parent::__construct($message);
    }

    public function details(): array
    {
        return $this->details;
    }
}
