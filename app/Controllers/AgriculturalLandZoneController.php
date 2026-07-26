<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\AgriculturalLandZone;

final class AgriculturalLandZoneController extends BaseController
{
    private AgriculturalLandZone $zones;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->zones = new AgriculturalLandZone();
    }

    public function index(): void
    {
        $this->requirePermission('agricultural_land', 'read');
        $this->ok($this->zones->paginate($this->filters()));
    }

    public function catalogs(): void
    {
        $this->requirePermission('agricultural_land', 'read');
        $this->ok($this->zones->catalogs());
    }

    public function dashboard(): void
    {
        $this->requirePermission('agricultural_land', 'read');
        $this->ok($this->zones->dashboard($this->filters()));
    }

    public function settings(): void
    {
        $this->requirePermission('agricultural_land', 'read');
        $this->ok($this->zones->settings());
    }

    public function updateSettings(): void
    {
        $user = $this->requirePermission('agricultural_land', 'update');
        $settings = $this->zones->updateSettings((array)$this->input());
        $this->audit($user, 'agricultural_land', 'update_settings', 'Cập nhật cấu hình đơn vị quỹ đất nông nghiệp', null, ['after' => $settings]);
        $this->ok($settings);
    }

    public function usageTypes(): void
    {
        $this->requirePermission('agricultural_land', 'read');
        $this->ok(['items' => $this->zones->usageTypes(false)]);
    }

    public function storeUsageType(): void
    {
        $user = $this->requirePermission('agricultural_land', 'update');
        $row = $this->zones->upsertUsageType((array)$this->input(), (int)$user['id']);
        $this->audit($user, 'agricultural_land', 'usage_type_create', 'Thêm loại sử dụng đất', $row['id'], ['after' => $row]);
        $this->ok($row);
    }

    public function updateUsageType(string $id): void
    {
        $user = $this->requirePermission('agricultural_land', 'update');
        $row = $this->zones->upsertUsageType((array)$this->input(), (int)$user['id'], (int)$id);
        $this->audit($user, 'agricultural_land', 'usage_type_update', 'Cập nhật loại sử dụng đất', $row['id'], ['after' => $row]);
        $this->ok($row);
    }

    public function deleteUsageType(string $id): void
    {
        $user = $this->requirePermission('agricultural_land', 'delete');
        $this->zones->deleteUsageType((int)$id, (int)$user['id']);
        $this->audit($user, 'agricultural_land', 'usage_type_delete', 'Ngừng sử dụng loại đất', $id);
        $this->ok(['id' => (int)$id]);
    }

    public function show(string $id): void
    {
        $this->requirePermission('agricultural_land', 'read');
        $row = $this->zones->find((int)$id, (string)$this->query('unit', ''));
        if (!$row) $this->fail('Không tìm thấy khu đất', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('agricultural_land', 'create');
        $row = $this->zones->upsert((array)$this->input(), (int)$user['id']);
        $this->audit($user, 'agricultural_land', 'create', 'Thêm khu đất nông nghiệp', $row['id'], ['after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('agricultural_land', 'update');
        $before = $this->zones->find((int)$id);
        if (!$before) $this->fail('Không tìm thấy khu đất', 404);
        $row = $this->zones->upsert((array)$this->input(), (int)$user['id'], (int)$id);
        $this->audit($user, 'agricultural_land', 'update', 'Cập nhật khu đất nông nghiệp', $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('agricultural_land', 'delete');
        $before = $this->zones->find((int)$id);
        if (!$before) $this->fail('Không tìm thấy khu đất', 404);
        $this->zones->softDelete((int)$id, (int)$user['id']);
        $this->audit($user, 'agricultural_land', 'delete', 'Xóa khu đất nông nghiệp', $id, ['before' => $before]);
        $this->ok(['id' => (int)$id]);
    }

    public function report(): void
    {
        $this->requirePermission('agricultural_land', 'read');
        $this->ok($this->zones->report((string)$this->query('mode', 'all'), $this->filters()));
    }

    private function filters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'status' => $this->query('status', ''),
            'report_year' => $this->query('report_year', $this->query('reportYear', '')),
            'zone_code' => $this->query('zone_code', $this->query('zoneCode', '')),
            'unit' => $this->query('unit', $this->query('displayUnit', '')),
            'sort' => $this->query('sort', 'zone_code'),
            'direction' => $this->query('direction', 'ASC'),
        ];
    }
}
