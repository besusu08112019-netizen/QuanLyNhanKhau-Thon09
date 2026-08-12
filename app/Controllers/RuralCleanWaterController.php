<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\RuralCleanWater;

final class RuralCleanWaterController extends BaseController
{
    private RuralCleanWater $water;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->water = new RuralCleanWater();
    }

    public function index(): void
    {
        $this->requirePermission('rural_clean_water', 'read');
        $this->ok($this->water->paginate($this->filters()));
    }

    public function catalogs(): void
    {
        $this->requirePermission('rural_clean_water', 'read');
        $this->ok($this->water->catalogs());
    }

    public function dashboard(): void
    {
        $this->requirePermission('rural_clean_water', 'read');
        $this->ok($this->water->dashboard($this->filters()));
    }
    public function stats(): void
    {
        $this->requirePermission('rural_clean_water', 'read');
        $this->ok($this->water->stats($this->filters()));
    }

    public function householdSearch(): void
    {
        $this->requirePermission('rural_clean_water', 'read');
        $this->ok(['items' => $this->water->searchHouseholds((string) $this->query('q', $this->query('search', '')))]);
    }

    public function byHousehold(string $householdId): void
    {
        $this->requirePermission('rural_clean_water', 'read');
        $items = $this->water->byHousehold((int) $householdId);
        $this->ok(['items' => $items, 'total' => count($items)]);
    }

    public function show(string $id): void
    {
        $this->requirePermission('rural_clean_water', 'read');
        $row = $this->water->find((int) $id);
        if (!$row) $this->fail('KhÃ´ng tÃ¬m tháº¥y báº£n ghi nÆ°á»›c sáº¡ch', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('rural_clean_water', 'create');
        $input = (array) $this->input();
        $this->requireInputFields($input, ['household_id' => 'Há»™ gia Ä‘Ã¬nh']);
        $row = $this->water->upsert($input, (int) $user['id']);
        $this->audit($user, 'rural_clean_water', 'create', 'ThÃªm báº£n ghi nÆ°á»›c sáº¡ch nÃ´ng thÃ´n', $row['id'], ['before' => null, 'after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('rural_clean_water', 'update');
        $before = $this->water->find((int) $id);
        if (!$before) $this->fail('KhÃ´ng tÃ¬m tháº¥y báº£n ghi nÆ°á»›c sáº¡ch', 404);
        $row = $this->water->upsert((array) $this->input(), (int) $user['id'], (int) $id);
        $this->audit($user, 'rural_clean_water', 'update', 'Cáº­p nháº­t báº£n ghi nÆ°á»›c sáº¡ch nÃ´ng thÃ´n', $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('rural_clean_water', 'delete');
        $before = $this->water->find((int) $id);
        if (!$before) $this->fail('KhÃ´ng tÃ¬m tháº¥y báº£n ghi nÆ°á»›c sáº¡ch', 404);
        $this->water->softDelete((int) $id, (int) $user['id']);
        $this->audit($user, 'rural_clean_water', 'delete', 'XÃ³a báº£n ghi nÆ°á»›c sáº¡ch nÃ´ng thÃ´n', $id, ['before' => $before, 'after' => null]);
        $this->ok(['id' => (int) $id]);
    }

    private function filters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'connection_type' => $this->query('connection_type', $this->query('connectionType', '')),
            'water_supply_form' => $this->query('water_supply_form', $this->query('waterSupplyForm', '')),
            'clean_water_status' => $this->query('clean_water_status', $this->query('cleanWaterStatus', '')),
            'hygienic_water_status' => $this->query('hygienic_water_status', $this->query('hygienicWaterStatus', '')),
            'is_clean_standard' => $this->query('is_clean_standard', $this->query('isCleanStandard', '')),
            'metric' => $this->query('metric', ''),
            'status' => $this->query('status', ''),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'date_from' => $this->query('date_from', $this->query('dateFrom', '')),
            'date_to' => $this->query('date_to', $this->query('dateTo', '')),
            'sort' => $this->query('sort', 'household_code'),
            'direction' => $this->query('direction', 'ASC'),
        ];
    }
}
