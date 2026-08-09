<?php

namespace App\Services;

use App\Core\Authorization\ControlCenterAuthorizationInterface;
use App\Repositories\PlatformSettingsRepository;
use InvalidArgumentException;

final class PlatformSettingsService
{
    private const SETTING_DEFINITIONS = [
        'general.platform_name' => ['group' => 'general', 'type' => 'string', 'default' => 'HONG PHONG COMMUNITY PLATFORM'],
        'general.admin_name' => ['group' => 'general', 'type' => 'string', 'default' => 'Community Control Center'],
        'general.parent_unit_name' => ['group' => 'general', 'type' => 'string', 'default' => 'Xã Hồng Phong'],
        'general.province_name' => ['group' => 'general', 'type' => 'string', 'default' => 'Ninh Bình'],
        'general.timezone' => ['group' => 'general', 'type' => 'string', 'default' => 'Asia/Ho_Chi_Minh'],
        'general.locale' => ['group' => 'general', 'type' => 'string', 'default' => 'vi_VN'],
        'general.date_format' => ['group' => 'general', 'type' => 'string', 'default' => 'dd/mm/yyyy'],
        'general.datetime_format' => ['group' => 'general', 'type' => 'string', 'default' => 'dd/mm/yyyy HH:mm'],
        'identity.system_name' => ['group' => 'identity', 'type' => 'string', 'default' => 'HONG PHONG COMMUNITY PLATFORM'],
        'identity.short_name' => ['group' => 'identity', 'type' => 'string', 'default' => 'CCC'],
        'identity.logo_url' => ['group' => 'identity', 'type' => 'string', 'default' => ''],
        'identity.favicon_url' => ['group' => 'identity', 'type' => 'string', 'default' => ''],
        'identity.tenant_logo_url' => ['group' => 'identity', 'type' => 'string', 'default' => ''],
        'identity.login_background_url' => ['group' => 'identity', 'type' => 'string', 'default' => ''],
        'branding.control_center_logo' => ['group' => 'branding', 'type' => 'string', 'default' => ''],
        'branding.favicon' => ['group' => 'branding', 'type' => 'string', 'default' => ''],
        'branding.default_tenant_logo' => ['group' => 'branding', 'type' => 'string', 'default' => ''],
        'branding.default_login_background' => ['group' => 'branding', 'type' => 'string', 'default' => ''],
        'tenant.default_status' => ['group' => 'tenant', 'type' => 'string', 'default' => 'ACTIVE'],
        'tenant.create_database' => ['group' => 'tenant', 'type' => 'boolean', 'default' => true],
        'tenant.run_migrations' => ['group' => 'tenant', 'type' => 'boolean', 'default' => true],
        'tenant.create_admin_account' => ['group' => 'tenant', 'type' => 'boolean', 'default' => true],
        'tenant.apply_platform_settings' => ['group' => 'tenant', 'type' => 'boolean', 'default' => true],
        'tenant.create_uploads_structure' => ['group' => 'tenant', 'type' => 'boolean', 'default' => true],
        'tenant.audit_log_enabled' => ['group' => 'tenant', 'type' => 'boolean', 'default' => true],
        'security.idle_timeout_minutes' => ['group' => 'security', 'type' => 'integer', 'default' => 30],
        'security.session_ttl_hours' => ['group' => 'security', 'type' => 'integer', 'default' => 8],
        'security.max_login_attempts' => ['group' => 'security', 'type' => 'integer', 'default' => 5],
        'security.lockout_minutes' => ['group' => 'security', 'type' => 'integer', 'default' => 15],
        'files.max_file_mb' => ['group' => 'files', 'type' => 'integer', 'default' => 20],
        'files.max_image_mb' => ['group' => 'files', 'type' => 'integer', 'default' => 5],
        'files.allowed_extensions' => ['group' => 'files', 'type' => 'json', 'default' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png']],
        'email.system_email' => ['group' => 'email', 'type' => 'string', 'default' => ''],
        'email.sender_name' => ['group' => 'email', 'type' => 'string', 'default' => 'Community Control Center'],
        'email.smtp_host' => ['group' => 'email', 'type' => 'string', 'default' => ''],
        'email.smtp_port' => ['group' => 'email', 'type' => 'integer', 'default' => 587],
        'email.smtp_encryption' => ['group' => 'email', 'type' => 'string', 'default' => 'tls'],
        'email.smtp_username' => ['group' => 'email', 'type' => 'string', 'default' => ''],
        'email.smtp_password' => ['group' => 'email', 'type' => 'string', 'default' => '', 'secret' => true],
        'maintenance.platform_enabled' => ['group' => 'maintenance', 'type' => 'boolean', 'default' => false],
    ];

    private const PROTECTED_SECURITY = [
        ['key' => 'tenant_guard_per_request', 'label' => 'Kiểm tra trạng thái tenant trên mỗi request', 'enabled' => true],
        ['key' => 'block_locked_tenant', 'label' => 'Chặn tenant khi bị khóa', 'enabled' => true],
        ['key' => 'block_locked_api', 'label' => 'Chặn API khi tenant bị khóa', 'enabled' => true],
        ['key' => 'block_existing_session', 'label' => 'Chặn session cũ khi tenant bị khóa', 'enabled' => true],
        ['key' => 'fail_closed', 'label' => 'Fail-closed khi không xác định được trạng thái tenant', 'enabled' => true],
    ];

    public function __construct(
        private PlatformSettingsRepository $repository,
        private ControlCenterAuthorizationInterface $authorization,
        private ControlCenterAuditService $audit
    ) {
    }

    public function show(): array
    {
        $this->authorization->authorize('control_center.configuration.read');
        $settings = $this->settings();
        $health = $this->repository->health();
        return [
            'settings' => $this->publicSettings($settings),
            'multiTenant' => $this->multiTenantStatus($health),
            'data' => $this->dataStatus($health),
            'system' => $this->systemStatus($health),
            'capabilities' => $this->capabilities($settings),
            'branding' => (new PlatformBrandingService($this->repository))->publicBranding(),
        ];
    }

    public function update(array $input): array
    {
        $actor = $this->authorization->authorize('control_center.configuration.update');
        $items = (array) ($input['settings'] ?? $input);
        $before = $this->settings(true);
        $changed = [];
        foreach ($items as $key => $value) {
            if (!is_string($key) || !isset(self::SETTING_DEFINITIONS[$key])) continue;
            $definition = self::SETTING_DEFINITIONS[$key];
            if (!empty($definition['secret'])) {
                throw new InvalidArgumentException('Secret phải cập nhật qua endpoint riêng');
            }
            $normalized = $this->validate($key, $value, $definition);
            $old = $before[$key]['value'] ?? $definition['default'];
            if ($old === $normalized) continue;
            $this->repository->upsert($key, $normalized, $definition['type'], $definition['group'], false, (int) $actor['id']);
            $changed[] = ['key' => $key, 'old' => $old, 'new' => $normalized, 'group' => $definition['group']];
        }
        foreach ($changed as $change) {
            $this->audit->write($actor, 'platform_settings.updated', null, 'Cập nhật cấu hình nền tảng', $change);
        }
        return $this->show();
    }

    public function updateSecret(array $input): array
    {
        $actor = $this->authorization->authorize('control_center.configuration.security');
        $key = (string) ($input['key'] ?? '');
        if ($key !== 'email.smtp_password') {
            throw new InvalidArgumentException('Secret không hợp lệ');
        }
        $value = (string) ($input['value'] ?? '');
        if ($value === '') {
            throw new InvalidArgumentException('Secret không được để trống');
        }
        $definition = self::SETTING_DEFINITIONS[$key];
        $this->repository->upsert($key, $value, $definition['type'], $definition['group'], true, (int) $actor['id']);
        $this->audit->write($actor, 'platform_settings.secret_updated', null, 'SMTP password updated', ['key' => $key, 'secret_updated' => true]);
        return $this->show();
    }

    public function checkRegistry(): array
    {
        $this->authorization->authorize('control_center.configuration.read');
        return $this->repository->health();
    }

    public function checkBackup(): array
    {
        $this->authorization->authorize('control_center.configuration.read');
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $paths = [$base . '/backups', $base . '/storage/backups'];
        $existing = array_values(array_filter($paths, static fn(string $path): bool => is_dir($path)));
        if (!$existing) {
            return ['ok' => false, 'status' => 'NOT_CONFIGURED', 'message' => 'Chưa cấu hình backup engine hoặc thư mục backup'];
        }
        $count = 0;
        $bytes = 0;
        foreach ($existing as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    $count++;
                    $bytes += filesize($file) ?: 0;
                }
            }
        }
        return ['ok' => true, 'status' => 'OK', 'locations' => count($existing), 'files' => $count, 'bytes' => $bytes];
    }

    public function testEmail(): array
    {
        $this->authorization->authorize('control_center.configuration.update');
        $settings = $this->settings();
        $host = trim((string) ($settings['email.smtp_host']['value'] ?? ''));
        $passwordConfigured = (bool) ($settings['email.smtp_password']['configured'] ?? false);
        if ($host === '' || !$passwordConfigured) {
            throw new InvalidArgumentException('SMTP chưa cấu hình đầy đủ nên không gửi email kiểm tra');
        }
        throw new InvalidArgumentException('Chưa có mailer SMTP runtime, không thực hiện gửi thử giả');
    }

    public function setMaintenance(array $input): array
    {
        $actor = $this->authorization->authorize('control_center.configuration.security');
        $enabled = filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $before = (bool) $this->repository->value('maintenance.platform_enabled', false);
        $this->repository->upsert('maintenance.platform_enabled', $enabled, 'boolean', 'maintenance', false, (int) $actor['id']);
        if ($before !== $enabled) {
            $this->audit->write($actor, 'platform_settings.maintenance_updated', null, 'Cập nhật chế độ bảo trì toàn nền tảng', [
                'key' => 'maintenance.platform_enabled',
                'old' => $before,
                'new' => $enabled,
            ], $enabled ? 'WARN' : 'INFO');
        }
        return $this->show();
    }

    public function uploadAsset(string $type, array $file): array
    {
        $actor = $this->authorization->authorize('control_center.configuration.update');
        $branding = new PlatformBrandingService($this->repository);
        $asset = $branding->storeUploadedAsset($type, $file);
        $key = $branding->settingKey($type);
        $this->repository->upsert($key, $asset['stored'], 'string', 'branding', false, (int) $actor['id']);
        $this->audit->write($actor, 'platform_branding.asset_uploaded', null, 'Cập nhật asset nhận diện nền tảng', [
            'asset_type' => $type,
            'key' => $key,
            'stored' => $asset['stored'],
            'mime' => $asset['mime'],
            'size' => $asset['size'],
        ]);
        return ['asset' => $asset, 'configuration' => $this->show()];
    }

    public function resetAsset(string $type): array
    {
        $actor = $this->authorization->authorize('control_center.configuration.update');
        $branding = new PlatformBrandingService($this->repository);
        $key = $branding->settingKey($type);
        $old = (string) $this->repository->value($key, '');
        $this->repository->upsert($key, '', 'string', 'branding', false, (int) $actor['id']);
        $legacyKeys = [
            'control_center_logo' => 'identity.logo_url',
            'favicon' => 'identity.favicon_url',
            'default_tenant_logo' => 'identity.tenant_logo_url',
            'default_login_background' => 'identity.login_background_url',
        ];
        if (isset($legacyKeys[$type])) {
            $this->repository->upsert($legacyKeys[$type], '', 'string', 'identity', false, (int) $actor['id']);
        }
        $this->audit->write($actor, 'platform_branding.asset_reset', null, 'Khôi phục asset nhận diện nền tảng', [
            'asset_type' => $type,
            'key' => $key,
            'old_configured' => $old !== '',
        ]);
        return $this->show();
    }
    public function settings(bool $includeSecretValues = false): array
    {
        $rows = [];
        foreach ($this->repository->all() as $row) {
            $rows[(string) $row['setting_key']] = $row;
        }
        $settings = [];
        foreach (self::SETTING_DEFINITIONS as $key => $definition) {
            $row = $rows[$key] ?? null;
            $secret = !empty($definition['secret']);
            $value = $row ? $this->repository->castValue($row['setting_value'], (string) $row['setting_type']) : $definition['default'];
            $settings[$key] = [
                'key' => $key,
                'group' => $definition['group'],
                'type' => $definition['type'],
                'value' => $secret && !$includeSecretValues ? null : $value,
                'configured' => $secret ? trim((string) $value) !== '' : true,
                'secret' => $secret,
                'updatedAt' => $row['updated_at'] ?? null,
            ];
        }
        return $settings;
    }

    public function maintenanceEnabled(): bool
    {
        return (bool) $this->repository->value('maintenance.platform_enabled', false);
    }

    private function publicSettings(array $settings): array
    {
        $public = [];
        foreach ($settings as $key => $setting) {
            if (!empty($setting['secret'])) {
                $setting['value'] = null;
                $setting['masked'] = $setting['configured'] ? '********' : '';
            }
            $public[$key] = $setting;
        }
        return $public;
    }

    private function multiTenantStatus(array $health): array
    {
        $rootDomain = $this->rootDomain();
        return [
            'enabled' => true,
            'identification' => 'hostname',
            'rootDomain' => $rootDomain,
            'subdomainRule' => '{tenant}.' . $rootDomain,
            'protectedControls' => self::PROTECTED_SECURITY,
            'components' => [
                ['name' => 'Tenant Guard', 'status' => class_exists(\App\Core\TenantGuard::class) ? 'OK' : 'ERROR'],
                ['name' => 'Tenant Resolver', 'status' => class_exists(\App\Core\PortalContext::class) ? 'OK' : 'ERROR'],
                ['name' => 'Central Registry', 'status' => !empty($health['ok']) ? 'OK' : 'ERROR'],
                ['name' => 'Database Registry', 'status' => !empty($health['villagesTable']) ? 'OK' : 'ERROR'],
            ],
        ];
    }

    private function dataStatus(array $health): array
    {
        return [
            'centralRegistry' => $health,
            'tenantDatabasePolicy' => 'Kiểm tra theo từng tenant trong module Đơn vị/Tenant',
            'backupPolicy' => [
                'retentionCopies' => 7,
                'retentionDays' => 30,
                'database' => true,
                'uploads' => true,
                'configuration' => true,
                'engineConfigured' => is_dir((defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/backups'),
            ],
        ];
    }

    private function systemStatus(array $health): array
    {
        return [
            'version' => getenv('APP_VERSION') ?: date('Ymd'),
            'commit' => getenv('APP_COMMIT') ?: $this->gitCommit(),
            'environment' => getenv('APP_ENV') ?: 'production',
            'phpVersion' => PHP_VERSION,
            'databaseVersion' => $health['databaseVersion'] ?? null,
            'serverTime' => date('c'),
            'timezone' => date_default_timezone_get(),
            'tenantGuard' => class_exists(\App\Core\TenantGuard::class) ? 'OK' : 'ERROR',
            'centralRegistry' => !empty($health['ok']) ? 'OK' : 'ERROR',
        ];
    }

    private function capabilities(array $settings): array
    {
        return [
            'identityUpload' => ['enabled' => true, 'status' => 'OK', 'message' => 'Upload ảnh nhận diện chạy qua backend và kiểm tra MIME'],
            'smtpTest' => ['enabled' => false, 'status' => empty($settings['email.smtp_host']['value']) ? 'NOT_CONFIGURED' : 'PENDING_MAILER', 'message' => 'Chỉ bật khi SMTP runtime được cấu hình'],
            'backupNow' => ['enabled' => false, 'status' => 'READ_ONLY', 'message' => 'Module này chỉ kiểm tra trạng thái, không tạo backup giả'],
        ];
    }

    private function validate(string $key, mixed $value, array $definition): mixed
    {
        if ($definition['type'] === 'boolean') return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        if ($definition['type'] === 'integer') {
            $int = (int) $value;
            if ($int < 0 || $int > 100000) throw new InvalidArgumentException('Giá trị số không hợp lệ: ' . $key);
            return $int;
        }
        if ($definition['type'] === 'json') {
            $items = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
            $allowed = [];
            $blocked = ['php', 'phtml', 'phar', 'cgi', 'exe', 'js', 'sh', 'bat', 'cmd', 'com', 'scr'];
            foreach ($items ?: [] as $item) {
                $ext = strtolower(trim((string) $item, " .\t\n\r\0\x0B"));
                if ($ext === '') continue;
                if (!preg_match('/^[a-z0-9]{2,8}$/', $ext) || in_array($ext, $blocked, true)) {
                    throw new InvalidArgumentException('Loại file không được phép: ' . $ext);
                }
                $allowed[] = $ext;
            }
            return array_values(array_unique($allowed));
        }
        $text = trim((string) $value);
        if (strlen($text) > 500) throw new InvalidArgumentException('Giá trị quá dài: ' . $key);
        if ($key === 'tenant.default_status' && !in_array($text, ['ACTIVE', 'PENDING_ACTIVATION'], true)) {
            throw new InvalidArgumentException('Trạng thái tenant mới không hợp lệ');
        }
        return $text;
    }

    private function rootDomain(): string
    {
        $host = (string) (getenv('PLATFORM_ROOT_DOMAIN') ?: parse_url((string) getenv('APP_URL'), PHP_URL_HOST));
        $host = trim($host) ?: ($_SERVER['HTTP_HOST'] ?? 'hongphongnb.com');
        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);
        $parts = explode('.', $host);
        return count($parts) > 2 ? implode('.', array_slice($parts, -2)) : $host;
    }

    private function gitCommit(): string
    {
        $head = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/.git/HEAD';
        if (!is_file($head)) return '';
        $ref = trim((string) file_get_contents($head));
        if (str_starts_with($ref, 'ref: ')) {
            $path = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/.git/' . substr($ref, 5);
            return is_file($path) ? substr(trim((string) file_get_contents($path)), 0, 12) : '';
        }
        return substr($ref, 0, 12);
    }
}
