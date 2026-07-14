<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Vehicle;
use Throwable;

final class VehicleController extends BaseController
{
    private Vehicle $vehicles;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->vehicles = new Vehicle();
    }

    public function index(): void
    {
        $this->requirePermission('vehicles', 'read');
        $this->ok($this->vehicles->paginate([
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'vehicle_type' => $this->query('vehicle_type', $this->query('vehicleType', '')),
            'household_id' => $this->query('household_id', $this->query('householdId', '')),
            'owner_name' => $this->query('owner_name', $this->query('ownerName', '')),
            'usage_status' => $this->query('usage_status', $this->query('usageStatus', '')),
            'status' => $this->query('status', ''),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'sort' => $this->query('sort', 'household_code'),
            'direction' => $this->query('direction', 'ASC'),
        ]));
    }

    public function catalogs(): void
    {
        $this->requirePermission('vehicles', 'read');
        $this->ok($this->vehicles->catalogs());
    }

    public function householdSearch(): void
    {
        $this->requirePermission('vehicles', 'read');
        $this->ok(['items' => $this->vehicles->searchHouseholds((string) $this->query('q', $this->query('search', '')))]);
    }

    public function byHousehold(string $householdId): void
    {
        $this->requirePermission('vehicles', 'read');
        $items = $this->vehicles->findByHousehold((int) $householdId);
        $this->ok(['items' => $items, 'total' => count($items)]);
    }

    public function show(string $id): void
    {
        $this->requirePermission('vehicles', 'read');
        $row = $this->vehicles->find((int) $id);
        if (!$row) $this->fail('Không tìm thấy phương tiện', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('vehicles', 'create');
        try {
            $row = $this->vehicles->upsert((array) $this->input(), (int) $user['id']);
            $this->audit($user, 'vehicles', 'create', 'Thêm phương tiện', $row['id'], ['before' => null, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 422);
        }
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('vehicles', 'update');
        try {
            $before = $this->vehicles->find((int) $id);
            if (!$before) $this->fail('Không tìm thấy phương tiện', 404);
            $row = $this->vehicles->upsert((array) $this->input(), (int) $user['id'], (int) $id);
            $this->audit($user, 'vehicles', 'update', 'Chỉnh sửa phương tiện', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 422);
        }
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('vehicles', 'delete');
        try {
            $before = $this->vehicles->find((int) $id);
            if (!$before) $this->fail('Không tìm thấy phương tiện', 404);
            $this->vehicles->softDelete((int) $id, (int) $user['id']);
            $this->audit($user, 'vehicles', 'delete', 'Xóa phương tiện', $id, ['before' => $before, 'after' => null]);
            $this->ok(['id' => (int) $id]);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 422);
        }
    }

    public function dashboard(): void
    {
        $this->requirePermission('vehicles', 'read');
        $filters = [
            'search' => $this->query('search', $this->query('q', '')),
            'vehicle_type' => $this->query('vehicle_type', $this->query('vehicleType', '')),
            'usage_status' => $this->query('usage_status', $this->query('usageStatus', '')),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
        ];
        $this->ok(['metrics' => $this->vehicles->dashboard($filters), 'charts' => $this->vehicles->charts($filters), 'top' => $this->vehicles->topHouseholds($filters)]);
    }
}
