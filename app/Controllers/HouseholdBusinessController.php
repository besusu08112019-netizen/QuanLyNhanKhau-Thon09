<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\HouseholdBusiness;

final class HouseholdBusinessController extends BaseController
{
    private HouseholdBusiness $businesses;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->businesses = new HouseholdBusiness();
    }

    public function index(): void
    {
        $this->requirePermission('household_business', 'read');
        $this->ok($this->businesses->paginate([
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'business_type' => $this->query('business_type', $this->query('businessType', '')),
            'sector' => $this->query('sector', ''),
            'status' => $this->query('status', ''),
            'license' => $this->query('license', ''),
            'tax' => $this->query('tax', ''),
            'located' => $this->query('located', ''),
            'sort' => $this->query('sort', 'household_code'),
            'direction' => $this->query('direction', 'ASC'),
        ]));
    }

    public function show(string $id): void
    {
        $this->requirePermission('household_business', 'read');
        $row = $this->businesses->find((int) $id);
        if (!$row) $this->fail('Không tìm thấy thông tin hộ sản xuất/kinh doanh', 404);
        $row['members'] = $this->businesses->members((int) $row['household_id']);
        $this->ok($row);
    }

    public function byHousehold(string $householdId): void
    {
        $this->requirePermission('household_business', 'read');
        $row = $this->businesses->findByHousehold((int) $householdId);
        $row ? $this->ok($row) : $this->fail('Không tìm thấy hộ gia đình', 404);
    }

    public function store(): void
    {
        $user = $this->requirePermission('household_business', 'create');
        $input = (array) $this->input();
        $this->requireInputFields($input, ['household_id' => 'Hộ gia đình', 'business_type' => 'Loại hình']);
        $row = $this->businesses->upsert($input, (int) $user['id']);
        $action = $this->auditAction($row['business_type']);
        $this->audit($user, 'household_business', 'create', $action, $row['id'], ['before' => null, 'after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('household_business', 'update');
        $before = $this->businesses->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy thông tin hộ sản xuất/kinh doanh', 404);
        $row = $this->businesses->upsert((array) $this->input(), (int) $user['id'], (int) $id);
        $action = $before['status'] !== $row['status'] ? 'Thay đổi trạng thái hộ sản xuất/kinh doanh' : 'Chỉnh sửa hộ sản xuất/kinh doanh';
        $this->audit($user, 'household_business', 'update', $action, $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('household_business', 'delete');
        $before = $this->businesses->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy thông tin hộ sản xuất/kinh doanh', 404);
        $this->businesses->softDelete((int) $id, (int) $user['id']);
        $this->audit($user, 'household_business', 'delete', 'Xóa thông tin hộ sản xuất/kinh doanh', $id, ['before' => $before, 'after' => null]);
        $this->ok(['id' => (int) $id]);
    }

    public function dashboard(): void
    {
        $this->requirePermission('household_business', 'read');
        $this->ok(['metrics' => $this->businesses->dashboard(), 'charts' => $this->businesses->charts()]);
    }

    private function auditAction(string $type): string
    {
        return match ($type) {
            'PRODUCTION' => 'Thêm hộ sản xuất',
            'BUSINESS' => 'Thêm hộ kinh doanh',
            'BOTH' => 'Thêm hộ sản xuất và kinh doanh',
            default => 'Thêm thông tin hộ dân',
        };
    }
}
