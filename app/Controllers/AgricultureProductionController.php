<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\AgricultureProduction;

final class AgricultureProductionController extends BaseController
{
    private AgricultureProduction $agriculture;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->agriculture = new AgricultureProduction();
    }

    public function index(): void
    {
        $this->requirePermission('agriculture', 'read');
        $this->ok($this->agriculture->paginate([
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'land_type' => $this->query('land_type', $this->query('landType', '')),
            'usage_form' => $this->query('usage_form', $this->query('usageForm', '')),
            'crop' => $this->query('crop', ''),
            'season' => $this->query('season', ''),
            'status' => $this->query('status', ''),
            'sort' => $this->query('sort', 'parcel_code'),
            'direction' => $this->query('direction', 'ASC'),
        ]));
    }

    public function catalogs(): void
    {
        $this->requirePermission('agriculture', 'read');
        $this->ok($this->agriculture->catalogs());
    }

    public function dashboard(): void
    {
        $this->requirePermission('agriculture', 'read');
        $this->ok($this->agriculture->dashboard([
            'search' => $this->query('search', $this->query('q', '')),
            'land_type' => $this->query('land_type', $this->query('landType', '')),
            'usage_form' => $this->query('usage_form', $this->query('usageForm', '')),
            'crop' => $this->query('crop', ''),
            'season' => $this->query('season', ''),
            'status' => $this->query('status', ''),
        ]));
    }


    public function gis(): void
    {
        $this->requirePermission('agriculture', 'read');
        $this->ok(['items' => $this->agriculture->gisFeatures([
            'search' => $this->query('search', $this->query('q', '')),
            'land_type' => $this->query('land_type', $this->query('landType', '')),
            'crop' => $this->query('crop', ''),
            'season' => $this->query('season', ''),
            'status' => $this->query('status', ''),
        ])]);
    }

    public function show(string $id): void
    {
        $this->requirePermission('agriculture', 'read');
        $row = $this->agriculture->find((int)$id);
        if (!$row) $this->fail('Không tìm thấy thửa đất', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('agriculture', 'create');
        $row = $this->agriculture->upsertParcel((array)$this->input(), (int)$user['id']);
        $this->audit($user, 'agriculture', 'create', 'Thêm thửa sản xuất nông nghiệp', $row['id'], ['before' => null, 'after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('agriculture', 'update');
        $before = $this->agriculture->find((int)$id);
        if (!$before) $this->fail('Không tìm thấy thửa đất', 404);
        $row = $this->agriculture->upsertParcel((array)$this->input(), (int)$user['id'], (int)$id);
        $this->audit($user, 'agriculture', 'update', 'Chỉnh sửa thửa sản xuất nông nghiệp', $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('agriculture', 'delete');
        $before = $this->agriculture->find((int)$id);
        if (!$before) $this->fail('Không tìm thấy thửa đất', 404);
        $this->agriculture->softDeleteParcel((int)$id, (int)$user['id']);
        $this->audit($user, 'agriculture', 'delete', 'Xóa thửa sản xuất nông nghiệp', $id, ['before' => $before, 'after' => null]);
        $this->ok(['id' => (int)$id]);
    }

    public function addPlot(string $parcelId): void
    {
        $user = $this->requirePermission('agriculture', 'update');
        $row = $this->agriculture->addPlot((int)$parcelId, (array)$this->input());
        $this->audit($user, 'agriculture', 'add_plot', 'Thêm lô sản xuất', $parcelId, ['after' => $row]);
        $this->ok($row);
    }

    public function addSeason(string $plotId): void
    {
        $user = $this->requirePermission('agriculture', 'update');
        $row = $this->agriculture->addSeason((int)$plotId, (array)$this->input());
        $this->audit($user, 'agriculture', 'add_season', 'Thêm mùa vụ', $plotId, ['after' => $row]);
        $this->ok($row);
    }

    public function addLog(string $seasonId): void
    {
        $user = $this->requirePermission('agriculture', 'update');
        $row = $this->agriculture->addLog((int)$seasonId, (array)$this->input(), (int)$user['id']);
        $this->audit($user, 'agriculture', 'add_log', 'Thêm nhật ký sản xuất', $seasonId, ['after' => $row]);
        $this->ok($row);
    }

    public function addDamage(string $parcelId): void
    {
        $user = $this->requirePermission('agriculture', 'update');
        $row = $this->agriculture->addDamage((int)$parcelId, (array)$this->input());
        $this->audit($user, 'agriculture', 'add_damage', 'Ghi nhận thiệt hại sản xuất', $parcelId, ['after' => $row]);
        $this->ok($row);
    }
}
