<?php

namespace App\Services;

use App\Core\Authorization\ControlCenterAuthorizationInterface;
use App\Repositories\AdministrativeUnitRepository;
use InvalidArgumentException;
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
}
