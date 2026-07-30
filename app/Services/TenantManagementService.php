<?php

namespace App\Services;

use App\Core\Authorization\ControlCenterAuthorizationInterface;
use App\Repositories\TenantRegistryRepository;
use InvalidArgumentException;
use RuntimeException;

final class TenantManagementService
{
    private const STATUSES = ['CREATING', 'READY', 'ACTIVE', 'MAINTENANCE', 'DISABLED', 'FAILED'];
    private const UNLOCK_STATUSES = ['READY', 'ACTIVE'];

    public function __construct(
        private TenantRegistryRepository $repository,
        private ControlCenterAuthorizationInterface $authorization,
        private ControlCenterAuditService $audit
    ) {
    }

    public function list(array $filters = []): array
    {
        $this->authorization->authorize('tenant.view');
        return $this->repository->paginate($filters);
    }

    public function find(int $id): array
    {
        $this->authorization->authorize('tenant.view');
        return $this->findTenant($id);
    }

    public function create(array $input): array
    {
        $actor = $this->authorization->authorize('tenant.create');
        $this->assertNoSecrets($input);
        $data = $this->validate($input, true);
        $this->assertUnique($data);

        $tenant = $this->repository->create($data, $actor);
        $this->audit->write($actor, 'tenant.created', (int) ($tenant['id'] ?? 0), 'Tạo Tenant', [
            'tenant' => $this->tenantRef($tenant),
            'before' => null,
            'after' => $this->auditSnapshot($tenant),
            'fields' => array_keys($data),
        ]);
        return $tenant;
    }

    public function update(int $id, array $input): array
    {
        $actor = $this->authorization->authorize('tenant.update');
        $this->assertNoSecrets($input);
        $before = $this->findTenant($id);
        $data = $this->validate($input, false);
        if ($data === []) {
            return $before;
        }
        $this->assertUnique($data, $id);

        $tenant = $this->repository->update($id, $data, $actor);
        $this->audit->write($actor, 'tenant.updated', $id, 'Cập nhật Tenant', [
            'tenant' => $this->tenantRef($tenant),
            'before' => $this->auditSnapshot($before),
            'after' => $this->auditSnapshot($tenant),
            'fields' => array_keys($data),
        ]);
        return $tenant;
    }

    public function lock(int $id, array $input): array
    {
        $actor = $this->authorization->authorize('tenant.lock');
        $tenant = $this->findTenant($id);
        if (($tenant['status'] ?? '') === 'DELETED') {
            throw new InvalidArgumentException('Tenant đã bị xóa mềm');
        }
        if (($tenant['status'] ?? '') === 'LOCKED') {
            throw new InvalidArgumentException('Tenant đã bị khóa');
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason, 'UTF-8') > 255) {
            throw new InvalidArgumentException('Lý do khóa Tenant không hợp lệ');
        }

        $updated = $this->repository->lock($id, $reason, $actor);
        $this->audit->write($actor, 'tenant.locked', $id, 'Khóa Tenant', [
            'tenant' => $this->tenantRef($updated),
            'before' => $this->auditSnapshot($tenant),
            'after' => $this->auditSnapshot($updated),
            'reason' => $reason,
        ], 'WARN');
        return $updated;
    }

    public function unlock(int $id, array $input): array
    {
        $actor = $this->authorization->authorize('tenant.unlock');
        $tenant = $this->findTenant($id);
        if (($tenant['status'] ?? '') === 'DELETED') {
            throw new InvalidArgumentException('Tenant đã bị xóa mềm');
        }
        if (($tenant['status'] ?? '') !== 'LOCKED') {
            throw new InvalidArgumentException('Tenant chưa bị khóa');
        }
        $targetStatus = strtoupper(trim((string) ($input['targetStatus'] ?? $input['target_status'] ?? 'ACTIVE')));
        if (!in_array($targetStatus, self::UNLOCK_STATUSES, true)) {
            throw new InvalidArgumentException('Trạng thái mở khóa Tenant không hợp lệ');
        }

        $updated = $this->repository->unlock($id, $targetStatus, $actor);
        $this->audit->write($actor, 'tenant.unlocked', $id, 'Mở khóa Tenant', [
            'tenant' => $this->tenantRef($updated),
            'before' => $this->auditSnapshot($tenant),
            'after' => $this->auditSnapshot($updated),
            'target_status' => $targetStatus,
        ]);
        return $updated;
    }

    public function softDelete(int $id, array $input): array
    {
        $actor = $this->authorization->authorize('tenant.delete');
        $tenant = $this->findTenant($id);
        if (($tenant['status'] ?? '') === 'DELETED') {
            throw new InvalidArgumentException('Tenant đã bị xóa mềm');
        }
        $confirmation = trim((string) ($input['confirmation'] ?? ''));
        if (!hash_equals((string) ($tenant['code'] ?? ''), $confirmation)) {
            throw new InvalidArgumentException('Xác nhận mã Tenant không khớp');
        }

        $updated = $this->repository->softDelete($id, $actor);
        $this->audit->write($actor, 'tenant.deleted', $id, 'Xóa mềm Tenant', [
            'tenant' => $this->tenantRef($updated),
            'before' => $this->auditSnapshot($tenant),
            'after' => $this->auditSnapshot($updated),
        ], 'WARN');
        return $updated;
    }

    public function activity(int $id, array $filters = []): array
    {
        $this->authorization->authorize('tenant.activity.view');
        $this->findTenant($id);
        return $this->repository->activity($id, $filters);
    }

    private function findTenant(int $id): array
    {
        $tenant = $this->repository->find($id);
        if (!$tenant) {
            throw new RuntimeException('Không tìm thấy Tenant');
        }
        return $tenant;
    }

    private function validate(array $input, bool $creating): array
    {
        $data = [];

        if ($creating || array_key_exists('code', $input)) {
            $code = strtolower(trim((string) ($input['code'] ?? '')));
            if ($code === '' || !preg_match('/^[a-z0-9_-]{2,50}$/', $code)) {
                throw new InvalidArgumentException('Mã Tenant không hợp lệ');
            }
            $data['code'] = $code;
        }

        if ($creating || array_key_exists('name', $input)) {
            $name = trim((string) ($input['name'] ?? ''));
            if ($name === '' || mb_strlen($name, 'UTF-8') > 190) {
                throw new InvalidArgumentException('Tên Tenant không hợp lệ');
            }
            $data['name'] = $name;
        }

        foreach (['unitName' => 'unit_name', 'unit_name' => 'unit_name', 'communeName' => 'commune_name', 'commune_name' => 'commune_name'] as $inputKey => $field) {
            if (array_key_exists($inputKey, $input)) {
                $data[$field] = $this->nullableText($input[$inputKey], 190, $field);
            }
        }

        foreach (['domain', 'subdomain'] as $field) {
            if ($creating || array_key_exists($field, $input)) {
                $data[$field] = $this->nullableHost($input[$field] ?? null, $field);
            }
        }
        if ($creating && empty($data['domain']) && empty($data['subdomain'])) {
            throw new InvalidArgumentException('Tenant cần có domain hoặc subdomain');
        }

        if ($creating || $this->hasAnyKey($input, ['databaseHost', 'database_host'])) {
            $data['database_host'] = $this->databaseHost($this->firstValue($input, ['databaseHost', 'database_host']));
        }
        if ($creating || $this->hasAnyKey($input, ['databaseName', 'database_name'])) {
            $data['database_name'] = $this->databaseName($this->firstValue($input, ['databaseName', 'database_name']));
        }
        if ($this->hasAnyKey($input, ['databaseCharset', 'database_charset'])) {
            $data['database_charset'] = $this->charset($this->firstValue($input, ['databaseCharset', 'database_charset']));
        }
        if ($creating && !isset($data['database_charset'])) {
            $data['database_charset'] = 'utf8mb4';
        }

        foreach ([
            'app_version' => ['appVersion', 'app_version'],
            'build_version' => ['buildVersion', 'build_version'],
            'schema_version' => ['schemaVersion', 'schema_version'],
        ] as $field => $keys) {
            if ($this->hasAnyKey($input, $keys)) {
                $data[$field] = $this->nullableText($this->firstValue($input, $keys), $field === 'build_version' ? 100 : 50, $field);
            }
        }
        if ($this->hasAnyKey($input, ['managerName', 'manager_name'])) {
            $data['manager_name'] = $this->nullableText($this->firstValue($input, ['managerName', 'manager_name']), 190, 'manager_name');
        }
        if ($this->hasAnyKey($input, ['storageQuotaBytes', 'storage_quota_bytes'])) {
            $data['storage_quota_bytes'] = $this->nullableBytes($this->firstValue($input, ['storageQuotaBytes', 'storage_quota_bytes']));
        }
        if (array_key_exists('notes', $input)) {
            $data['notes'] = $this->nullableText($input['notes'], 2000, 'notes');
        }
        if (array_key_exists('status', $input)) {
            $status = strtoupper(trim((string) $input['status']));
            if (!in_array($status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Trạng thái Tenant không hợp lệ');
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
            throw new InvalidArgumentException('Mã Tenant đã tồn tại');
        }
        if (isset($data['domain']) && $data['domain'] !== null && $this->repository->existsByDomain($data['domain'], $ignoreId)) {
            throw new InvalidArgumentException('Domain đã tồn tại');
        }
        if (isset($data['subdomain']) && $data['subdomain'] !== null && $this->repository->existsBySubdomain($data['subdomain'], $ignoreId)) {
            throw new InvalidArgumentException('Subdomain đã tồn tại');
        }
    }

    private function hasAnyKey(array $input, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return true;
            }
        }
        return false;
    }

    private function firstValue(array $input, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return $input[$key];
            }
        }
        return null;
    }

    private function assertNoSecrets(array $input): void
    {
        foreach ($input as $key => $value) {
            $normalized = strtolower(str_replace(['-', ' '], '_', (string) $key));
            if (preg_match('/(password|passwd|pwd|secret|token|csrf|cookie|authorization)/', $normalized)) {
                throw new InvalidArgumentException('Tenant Management không nhận hoặc lưu thông tin bí mật');
            }
            if (is_array($value)) {
                $this->assertNoSecrets($value);
            }
        }
    }

    private function tenantRef(array $tenant): array
    {
        return [
            'id' => isset($tenant['id']) ? (int) $tenant['id'] : null,
            'code' => $tenant['code'] ?? null,
            'name' => $tenant['name'] ?? null,
        ];
    }

    private function auditSnapshot(array $tenant): array
    {
        $safeFields = [
            'id',
            'code',
            'name',
            'unitName',
            'communeName',
            'domain',
            'subdomain',
            'status',
            'storedStatus',
            'runtimeAllowed',
            'appVersion',
            'buildVersion',
            'schemaVersion',
            'websiteStatus',
            'databaseStatus',
            'connectionStatus',
            'sslStatus',
            'storageUsageBytes',
            'storageQuotaBytes',
            'managerName',
            'notes',
            'deletedAt',
            'lockedAt',
            'lockedBy',
            'lockReason',
            'lastStatusChangedAt',
            'lastStatusChangedBy',
            'createdAt',
            'updatedAt',
        ];

        $snapshot = [];
        foreach ($safeFields as $field) {
            if (array_key_exists($field, $tenant)) {
                $snapshot[$field] = $tenant[$field];
            }
        }
        return $snapshot;
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

    private function databaseHost(mixed $value): string
    {
        $host = trim((string) ($value ?? ''));
        if ($host === '' || mb_strlen($host, 'UTF-8') > 190 || str_contains($host, '/') || str_contains($host, '?')) {
            throw new InvalidArgumentException('Database host không hợp lệ');
        }
        return $host;
    }

    private function databaseName(mixed $value): string
    {
        $name = trim((string) ($value ?? ''));
        if ($name === '' || !preg_match('/^[a-zA-Z0-9_]{1,190}$/', $name)) {
            throw new InvalidArgumentException('Database name không hợp lệ');
        }
        return $name;
    }

    private function charset(mixed $value): string
    {
        $charset = strtolower(trim((string) ($value ?? '')));
        if ($charset === '') {
            return 'utf8mb4';
        }
        if (!preg_match('/^[a-z0-9_]{1,50}$/', $charset)) {
            throw new InvalidArgumentException('Database charset không hợp lệ');
        }
        return $charset;
    }

    private function nullableBytes(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException('Dung lượng không hợp lệ');
        }
        return (int) $value;
    }
}
