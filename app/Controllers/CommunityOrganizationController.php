<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\CommunityOrganization;
use RuntimeException;

final class CommunityOrganizationController extends BaseController
{
    private CommunityOrganization $organizations;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->organizations = new CommunityOrganization();
    }

    public function index(): void
    {
        $this->requirePermission('organizations', 'read');
        $this->ok($this->organizations->paginate($this->filters()));
    }

    public function catalogs(): void
    {
        $this->requirePermission('organizations', 'read');
        $this->ok($this->organizations->catalogs());
    }

    public function dashboard(): void
    {
        $this->requirePermission('organizations', 'read');
        $this->ok($this->organizations->dashboard($this->filters()));
    }

    public function citizenSearch(): void
    {
        $this->requirePermission('organizations', 'read');
        $this->ok(['items' => $this->organizations->searchCitizens(
            (string) $this->query('q', $this->query('search', '')),
            (int) $this->query('limit', 12),
            (string) $this->query('organization_code', $this->query('organization', ''))
        )]);
    }

    public function byCitizen(string $citizenId): void
    {
        $this->requirePermission('organizations', 'read');
        $this->ok(['items' => $this->organizations->byCitizen((int) $citizenId)]);
    }

    public function show(string $id): void
    {
        $this->requirePermission('organizations', 'read');
        $row = $this->organizations->find((int) $id);
        if (!$row) $this->fail('Không tìm thấy hồ sơ đoàn thể - chi hội', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('organizations', 'create');
        try {
            $row = $this->organizations->upsert((array) $this->input(), (int) $user['id']);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage(), 422);
        }
        $this->audit($user, 'organizations', 'create', 'Thêm thành viên đoàn thể - chi hội', $row['id'] ?? null, ['after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('organizations', 'update');
        $before = $this->organizations->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy hồ sơ đoàn thể - chi hội', 404);
        try {
            $row = $this->organizations->upsert((array) $this->input(), (int) $user['id'], (int) $id);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage(), 422);
        }
        $this->audit($user, 'organizations', 'update', 'Cập nhật thành viên đoàn thể - chi hội', $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function end(string $id): void
    {
        $user = $this->requirePermission('organizations', 'update');
        $before = $this->organizations->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy hồ sơ đoàn thể - chi hội', 404);
        try {
            $row = $this->organizations->endMembership((int) $id, (array) $this->input(), (int) $user['id']);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage(), 422);
        }
        $this->audit($user, 'organizations', 'end', 'Thôi tham gia đoàn thể - chi hội', $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('organizations', 'delete');
        $before = $this->organizations->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy hồ sơ đoàn thể - chi hội', 404);
        try {
            $this->organizations->softDelete((int) $id, (int) $user['id']);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage(), 422);
        }
        $this->audit($user, 'organizations', 'delete', 'Xóa hồ sơ đoàn thể - chi hội', $id, ['before' => $before, 'preserve_citizen' => true]);
        $this->ok(['id' => (int) $id, 'deleted' => true]);
    }

    public function history(string $id): void
    {
        $this->requirePermission('organizations', 'read');
        $this->ok(['items' => $this->organizations->history((int) $id)]);
    }

    public function report(): void
    {
        $this->requirePermission('organizations', 'export');
        $this->ok($this->organizations->report($this->filters()));
    }

    private function filters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'organization_code' => $this->query('organization_code', $this->query('organizationCode', $this->query('organization', ''))),
            'status' => $this->query('status', ''),
            'gender' => $this->query('gender', ''),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'position_id' => $this->query('position_id', $this->query('positionId', '')),
            'age_from' => $this->query('age_from', $this->query('ageFrom', '')),
            'age_to' => $this->query('age_to', $this->query('ageTo', '')),
            'joined_year' => $this->query('joined_year', $this->query('joinedYear', '')),
            'sort' => $this->query('sort', 'organization'),
            'direction' => $this->query('direction', 'ASC'),
        ];
    }
}
