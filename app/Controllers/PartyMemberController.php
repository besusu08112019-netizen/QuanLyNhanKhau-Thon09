<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\PartyMember;

final class PartyMemberController extends BaseController
{
    private PartyMember $members;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->members = new PartyMember();
    }

    public function index(): void
    {
        $this->requirePermission('party_members', 'read');
        $this->ok($this->members->paginate($this->filters()));
    }

    public function catalogs(): void
    {
        $this->requirePermission('party_members', 'read');
        $this->ok($this->members->catalogs());
    }

    public function citizenSearch(): void
    {
        $this->requirePermission('party_members', 'read');
        $this->ok(['items' => $this->members->searchCitizens((string) $this->query('q', $this->query('search', '')))]);
    }

    public function show(string $id): void
    {
        $this->requirePermission('party_members', 'read');
        $row = $this->members->find((int) $id);
        if (!$row) $this->fail('Không tìm thấy hồ sơ Đảng viên', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('party_members', 'create');
        $input = (array) $this->input();
        $this->requireInputFields($input, ['citizen_id' => 'Nhân khẩu']);
        $row = $this->members->upsert($input, (int) $user['id']);
        $this->audit($user, 'party_members', 'create', 'Thêm hồ sơ Đảng viên', $row['id'] ?? null, ['before' => null, 'after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('party_members', 'update');
        $before = $this->members->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy hồ sơ Đảng viên', 404);
        $row = $this->members->upsert((array) $this->input(), (int) $user['id'], (int) $id);
        $this->audit($user, 'party_members', 'update', 'Cập nhật hồ sơ Đảng viên', $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('party_members', 'delete');
        $before = $this->members->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy hồ sơ Đảng viên', 404);
        $this->members->softDelete((int) $id, (int) $user['id']);
        $this->audit($user, 'party_members', 'delete', 'Xóa hồ sơ Đảng viên', $id, ['before' => $before, 'after' => null]);
        $this->ok(['id' => (int) $id]);
    }

    public function restore(string $id): void
    {
        $user = $this->requirePermission('party_members', 'restore');
        $row = $this->members->restore((int) $id, (int) $user['id']);
        $this->audit($user, 'party_members', 'restore', 'Khôi phục hồ sơ Đảng viên', $id, ['after' => $row]);
        $this->ok($row);
    }

    public function dashboard(): void
    {
        $this->requirePermission('party_members', 'read');
        $filters = $this->filters();
        $this->ok(['metrics' => $this->members->dashboard($filters), 'charts' => $this->members->charts($filters), 'generatedAt' => date('c')]);
    }

    private function filters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'branch_name' => $this->query('branch_name', $this->query('branch', '')),
            'member_type' => $this->query('member_type', $this->query('memberType', '')),
            'activity_status' => $this->query('activity_status', $this->query('activityStatus', $this->query('status', ''))),
            'party_position' => $this->query('party_position', $this->query('position', '')),
            'gender' => $this->query('gender', ''),
            'age_from' => $this->query('age_from', $this->query('ageFrom', '')),
            'age_to' => $this->query('age_to', $this->query('ageTo', '')),
            'sort' => $this->query('sort', 'full_name'),
            'direction' => $this->query('direction', 'ASC'),
        ];
    }
}
