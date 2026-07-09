<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Livestock;

final class LivestockController extends BaseController
{
    private Livestock $livestock;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->livestock = new Livestock();
    }

    public function index(): void
    {
        $this->requirePermission('livestock', 'read');
        $this->ok($this->livestock->paginate([
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'animal_type' => $this->query('animal_type', $this->query('animalType', '')),
            'vaccinated' => $this->query('vaccinated', ''),
            'disease_status' => $this->query('disease_status', $this->query('diseaseStatus', '')),
            'status' => $this->query('status', ''),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'barn_area' => $this->query('barn_area', $this->query('barnArea', $this->query('classification', ''))),
            'sort' => $this->query('sort', 'household_code'),
            'direction' => $this->query('direction', 'ASC'),
        ]));
    }

    public function catalogs(): void
    {
        $this->requirePermission('livestock', 'read');
        $this->ok($this->livestock->catalogs());
    }

    public function householdSearch(): void
    {
        $this->requirePermission('livestock', 'read');
        $this->ok(['items' => $this->livestock->searchHouseholds((string) $this->query('q', $this->query('search', '')))]);
    }

    public function byHousehold(string $householdId): void
    {
        $this->requirePermission('livestock', 'read');
        $items = $this->livestock->findByHousehold((int) $householdId);
        $this->ok(['items' => $items, 'total' => count($items)]);
    }

    public function show(string $id): void
    {
        $this->requirePermission('livestock', 'read');
        $row = $this->livestock->find((int) $id);
        if (!$row) $this->fail('Không tìm thấy bản ghi vật nuôi', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('livestock', 'create');
        $input = (array) $this->input();
        $this->requireInputFields($input, ['household_id' => 'Hộ gia đình', 'animal_type' => 'Loại vật nuôi']);
        $row = $this->livestock->upsert($input, (int) $user['id']);
        $this->audit($user, 'livestock', 'create', 'Thêm vật nuôi', $row['id'], ['before' => null, 'after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('livestock', 'update');
        $before = $this->livestock->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy bản ghi vật nuôi', 404);
        $row = $this->livestock->upsert((array) $this->input(), (int) $user['id'], (int) $id);
        $action = $before['status'] !== $row['status'] ? 'Thay đổi trạng thái vật nuôi' : 'Chỉnh sửa vật nuôi';
        $this->audit($user, 'livestock', 'update', $action, $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('livestock', 'delete');
        $before = $this->livestock->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy bản ghi vật nuôi', 404);
        $this->livestock->softDelete((int) $id, (int) $user['id']);
        $this->audit($user, 'livestock', 'delete', 'Xóa vật nuôi', $id, ['before' => $before, 'after' => null]);
        $this->ok(['id' => (int) $id]);
    }

    public function dashboard(): void
    {
        $this->requirePermission('livestock', 'read');
        $filters = [
            'search' => $this->query('search', $this->query('q', '')),
            'animal_type' => $this->query('animal_type', $this->query('animalType', '')),
            'vaccinated' => $this->query('vaccinated', ''),
            'disease_status' => $this->query('disease_status', $this->query('diseaseStatus', '')),
            'status' => $this->query('status', ''),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'barn_area' => $this->query('barn_area', $this->query('barnArea', $this->query('classification', ''))),
        ];
        $this->ok(['metrics' => $this->livestock->dashboard($filters), 'charts' => $this->livestock->charts($filters), 'top' => $this->livestock->topHouseholds($filters)]);
    }
}
